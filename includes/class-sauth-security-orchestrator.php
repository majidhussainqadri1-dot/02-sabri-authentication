<?php

defined( 'ABSPATH' ) || exit;

/**
 * Account-security orchestration for modern File 02 controls.
 * File 00 remains the canonical identity/membership authority.
 */
final class SAUTH_Security_Orchestrator {
	const ASSURANCE_V2_VERSION = '2.0.0';
	const TIMELINE_RETENTION   = 180 * DAY_IN_SECONDS;
	const RECOVERY_DEFAULT     = DAY_IN_SECONDS;
	const RECOVERY_MIN         = HOUR_IN_SECONDS;
	const RECOVERY_MAX         = 7 * DAY_IN_SECONDS;
	const LOCKDOWN_META        = '_sauth_emergency_lockdown_v1';
	const COMPROMISE_META      = '_sauth_compromise_watch_v1';

	public static function init() {
		add_shortcode( 'sabri_auth_security_center', array( __CLASS__, 'render_security_center' ) );
		add_shortcode( 'sabri_auth_collision_resolution', array( __CLASS__, 'render_collision_resolution' ) );
		add_action( 'wp_ajax_sauth_security_not_me', array( __CLASS__, 'not_me_ajax' ) );
		add_action( 'wp_ajax_sauth_security_lockdown', array( __CLASS__, 'lockdown_ajax' ) );
		add_action( 'wp_ajax_sauth_recovery_change_request', array( __CLASS__, 'recovery_change_ajax' ) );
		add_action( 'wp_ajax_sauth_recovery_change_cancel', array( __CLASS__, 'recovery_cancel_ajax' ) );
		add_action( 'admin_post_nopriv_sauth_resolve_collision', array( __CLASS__, 'resolve_collision_post' ) );
		add_action( 'admin_post_sauth_resolve_collision', array( __CLASS__, 'resolve_collision_post' ) );
		add_filter( 'sauth_authentication_assurance_v2', array( __CLASS__, 'assurance_filter' ), 20, 2 );
		add_action( 'sauth_security_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'sauth_security_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sauth_security_cleanup' );
		}
	}

	public static function timeline_event( $user_id, $event_type, $severity='low', array $details=array() ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$event_type = sanitize_key( (string) $event_type );
		$severity = in_array( $severity, array( 'low','medium','high','critical' ), true ) ? $severity : 'low';
		if ( ! $user_id || '' === $event_type ) { return false; }
		$safe = array();
		foreach ( array_slice( $details, 0, 20, true ) as $k=>$v ) {
			$k = sanitize_key( (string) $k );
			if ( '' === $k || preg_match( '/password|secret|token|cookie|ip|email|phone|address/', $k ) ) { continue; }
			$safe[$k] = is_scalar($v) ? substr(sanitize_text_field((string)$v),0,240) : '';
		}
		$public_id = strtolower( wp_generate_uuid4() );
		$stored = $wpdb->insert(
			SAUTH_Activator::table( 'security_timeline' ),
			array(
				'public_id'=>$public_id,'user_id'=>$user_id,'event_type'=>$event_type,'severity'=>$severity,
				'device_hash'=>SA_Security::client_fingerprint(),'details_json'=>wp_json_encode($safe),
				'created_at'=>current_time('mysql',true),
			),
			array('%s','%d','%s','%s','%s','%s','%s')
		);
		return 1 === (int) $stored ? $public_id : false;
	}

	public static function timeline( $user_id, $limit=50 ) {
		global $wpdb;
		$limit = max( 1, min( 100, absint( $limit ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,event_type,severity,details_json,created_at FROM ' . SAUTH_Activator::table('security_timeline') . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d', absint($user_id), $limit ), ARRAY_A );
		if ( ! is_array($rows) ) { return array(); }
		foreach ( $rows as &$row ) { $row['details'] = json_decode((string)$row['details_json'],true); unset($row['details_json']); }
		unset($row);
		return $rows;
	}

	public static function authentication_blocked( $user_id ) {
		$state = get_user_meta( absint($user_id), self::LOCKDOWN_META, true );
		return is_array($state) && ! empty($state['active']);
	}

	public static function compromise_watch( $user_id ) {
		$state = get_user_meta( absint($user_id), self::COMPROMISE_META, true );
		return is_array($state) && absint($state['expires_at'] ?? 0) > time();
	}

	public static function emergency_lockdown( $user_id, $reason='user_request' ) {
		$user_id=absint($user_id);
		if(!$user_id){return false;}
		$state=array('active'=>true,'reason'=>sanitize_key($reason),'started_at'=>time());
		update_user_meta($user_id,self::LOCKDOWN_META,$state);
		update_user_meta($user_id,self::COMPROMISE_META,array('started_at'=>time(),'expires_at'=>time()+7*DAY_IN_SECONDS,'reason'=>sanitize_key($reason)));
		$sessions = SAUTH_Session_Manager::revoke_user_sessions($user_id,'emergency_lockdown');
		if(class_exists('SAUTH_Passkey_Runtime')){SAUTH_Passkey_Runtime::invalidate_user_assurance($user_id);}
		if(class_exists('SAUTH_Shared_Signals')){SAUTH_Shared_Signals::emit('risc','credential_compromise',$user_id,array('reason'=>'user_lockdown'));}
		self::timeline_event($user_id,'emergency_lockdown','critical',array('reason'=>sanitize_key($reason)));
		SA_Membership_Adapter::audit('authentication_emergency_lockdown',$user_id,array('reason'=>sanitize_key($reason)));
		return (bool)$sessions;
	}

	public static function clear_lockdown( $user_id ) {
		$user_id=absint($user_id);
		if(!$user_id || !self::smart_step_up($user_id,'lockdown_release')){return false;}
		delete_user_meta($user_id,self::LOCKDOWN_META);
		self::timeline_event($user_id,'emergency_lockdown_released','high');
		return !self::authentication_blocked($user_id);
	}

	public static function report_not_me( $user_id, $timeline_id ) {
		global $wpdb;
		$user_id=absint($user_id); $timeline_id=sanitize_text_field((string)$timeline_id);
		$owned=$wpdb->get_var($wpdb->prepare('SELECT public_id FROM '.SAUTH_Activator::table('security_timeline').' WHERE public_id=%s AND user_id=%d',$timeline_id,$user_id));
		if(!$owned){return new WP_Error('sauth_timeline_event_not_found','Security event not found.');}
		update_user_meta($user_id,self::COMPROMISE_META,array('started_at'=>time(),'expires_at'=>time()+7*DAY_IN_SECONDS,'reason'=>'unrecognized_event'));
		SAUTH_Session_Manager::revoke_other_sessions($user_id,(string)wp_get_session_token(),'unrecognized_security_event');
		if(class_exists('SAUTH_Shared_Signals')){SAUTH_Shared_Signals::emit('risc','credential_compromise',$user_id,array('reason'=>'unrecognized_event'));}
		self::timeline_event($user_id,'unrecognized_event_reported','high',array('source_event'=>$timeline_id));
		return true;
	}

	public static function risk_v2( $user_id ) {
		global $wpdb;
		$user_id=absint($user_id); if(!$user_id){return array('score'=>100,'reasons'=>array('subject_invalid'));}
		$score=0;$reasons=array();
		if(self::compromise_watch($user_id)){$score+=45;$reasons[]='compromise_watch';}
		$table=SAUTH_Activator::table('auth_devices');
		$devices=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND last_seen_at>=%s",$user_id,gmdate('Y-m-d H:i:s',time()-HOUR_IN_SECONDS)));
		$networks=$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT network_hash) FROM {$table} WHERE user_id=%d AND last_seen_at>=%s",$user_id,gmdate('Y-m-d H:i:s',time()-HOUR_IN_SECONDS)));
		if((int)$devices>=4){$score+=20;$reasons[]='rapid_device_velocity';}
		if((int)$networks>=3){$score+=20;$reasons[]='rapid_network_velocity';}
		if(class_exists('SAUTH_Shared_Signals')){$shared=SAUTH_Shared_Signals::risk_score_for_user($user_id);if($shared){$score+=$shared;$reasons[]='shared_security_signal';}}
		return array('score'=>min(100,$score),'reasons'=>$reasons);
	}

	public static function explain_reasons( array $reasons ) {
		$map=array(
			'compromise_watch'=>'Recent account-security concern',
			'rapid_device_velocity'=>'Several devices were seen in a short period',
			'rapid_network_velocity'=>'Several network contexts were seen in a short period',
			'shared_security_signal'=>'A verified security signal raised risk',
			'new_device'=>'This device is new',
			'new_network'=>'This network context is new',
			'recent_failures'=>'Several recent sign-in attempts failed',
		);
		$out=array(); foreach($reasons as $r){$r=sanitize_key((string)$r);if(isset($map[$r])){$out[]=$map[$r];}}
		return $out;
	}

	public static function assurance_v2( $user_id ) {
		$user_id=absint($user_id);
		if(!$user_id || get_current_user_id()!==$user_id || self::authentication_blocked($user_id)){return array('result'=>'deny','contract_version'=>self::ASSURANCE_V2_VERSION);}
		$base=class_exists('SAUTH_Passkey_Runtime')?SAUTH_Passkey_Runtime::current_assurance($user_id):array();
		$method=(string)($base['method']??'');
		$verified=absint($base['verified_at']??0);
		if('webauthn_passkey'!==$method || empty($base['passkey_asserted']) || !$verified || $verified<time()-300){return array('result'=>'deny','contract_version'=>self::ASSURANCE_V2_VERSION);}
		$risk=self::risk_v2($user_id);
		return array(
			'result'=>'allow','contract'=>'sauth.authentication-assurance','contract_version'=>self::ASSURANCE_V2_VERSION,
			'owner'=>'file02','method'=>$method,'freshness_seconds'=>max(0,time()-$verified),
			'risk_score'=>absint($risk['score']),'user_verification'=>true,'phishing_resistant'=>true,
			'hardware_backed'=>!empty($base['hardware_backed']),'verified_at'=>$verified,'expires_at'=>min($verified+300,time()+300),
			'session_binding'=>hash_hmac('sha256',(string)wp_get_session_token(),wp_salt('auth')),
			'fingerprint_binding'=>SA_Security::client_fingerprint(),
		);
	}


	public static function assurance_filter( $baseline, $user_id ) {
		$receipt = self::assurance_v2( absint( $user_id ) );
		return 'allow' === ( $receipt['result'] ?? '' ) ? $receipt : ( is_array( $baseline ) ? $baseline : array() );
	}

	public static function smart_step_up( $user_id, $action, array $policy=array() ) {
		$receipt=self::assurance_v2($user_id);
		if('allow'!==($receipt['result']??'')){return false;}
		$minimum=isset($policy['max_age'])?max(30,min(300,absint($policy['max_age']))):300;
		if(absint($receipt['freshness_seconds']??9999)>$minimum){return false;}
		if(!empty($policy['phishing_resistant']) && empty($receipt['phishing_resistant'])){return false;}
		return true === apply_filters('sauth_smart_step_up_allow_v1',true,absint($user_id),sanitize_key((string)$action),$receipt,$policy);
	}

	public static function allow_method_removal( $user_id, $method ) {
		$user_id=absint($user_id);$method=sanitize_key((string)$method);
		if(!$user_id || !self::smart_step_up($user_id,'remove_'.$method,array('phishing_resistant'=>true,'max_age'=>300))){return false;}
		return true;
	}


	/**
	 * Create an opaque one-time collision case. The submitted identifier remains
	 * encrypted inside File 02 and the public route receives only random values.
	 */
	public static function create_collision_case( $identifier, $reason='possible_existing_account' ) {
		global $wpdb;
		$identifier = trim( strtolower( (string) $identifier ) );
		if ( '' === $identifier || strlen( $identifier ) > 320 ) { return new WP_Error( 'sauth_collision_identifier_invalid', 'Account resolution could not be started.' ); }
		$public_id = strtolower( wp_generate_uuid4() );
		$token = SA_Security::random_token( 32 );
		$token_cipher = SA_Security::encrypt( $token );
		$payload_cipher = SA_Security::encrypt( wp_json_encode( array( 'identifier'=>$identifier, 'reason'=>sanitize_key( $reason ) ) ) );
		if ( '' === $token || '' === $token_cipher || '' === $payload_cipher ) { return new WP_Error( 'sauth_collision_protection_failed', 'Account resolution could not be protected.' ); }
		$now = current_time( 'mysql', true );
		$stored = $wpdb->insert(
			SAUTH_Activator::table( 'recovery_changes' ),
			array(
				'public_id'=>$public_id, 'user_id'=>0, 'change_kind'=>'collision_resolution',
				'payload_ciphertext'=>$payload_cipher, 'owner_token_ciphertext'=>$token_cipher,
				'fingerprint_hash'=>SA_Security::client_fingerprint(), 'status'=>'pending',
				'apply_after'=>$now, 'created_at'=>$now, 'updated_at'=>$now,
			),
			array( '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' )
		);
		if ( 1 !== (int) $stored ) { return new WP_Error( 'sauth_collision_store_failed', 'Account resolution could not be started.' ); }
		return array(
			'case'=>$public_id,
			'proof'=>$token,
			'url'=>add_query_arg( array( 'case'=>$public_id, 'proof'=>$token ), home_url( '/resolve-account/' ) ),
		);
	}

	public static function resolve_collision_case( $public_id, $owner_token ) {
		global $wpdb;
		$public_id = strtolower( sanitize_text_field( (string) $public_id ) );
		$owner_token = (string) $owner_token;
		if ( ! preg_match( '/^[0-9a-f-]{36}$/', $public_id ) || strlen( $owner_token ) > 512 ) {
			return new WP_Error( 'sauth_collision_case_invalid', 'Account-resolution case is invalid.' );
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SAUTH_Activator::table( 'recovery_changes' ) . " WHERE public_id=%s AND change_kind='collision_resolution'", $public_id ), ARRAY_A );
		if ( ! is_array( $row ) || 'pending' !== (string) ( $row['status'] ?? '' ) ) { return new WP_Error( 'sauth_collision_case_unavailable', 'Account-resolution case is unavailable.' ); }
		if ( ! hash_equals( (string) ( $row['fingerprint_hash'] ?? '' ), SA_Security::client_fingerprint() ) ) {
			return new WP_Error( 'sauth_collision_fingerprint_mismatch', 'Account-resolution context changed.' );
		}
		$expected = SA_Security::decrypt( (string) $row['owner_token_ciphertext'] );
		if ( false === $expected || ! hash_equals( (string) $expected, $owner_token ) ) { return new WP_Error( 'sauth_collision_proof_invalid', 'Account-resolution proof is invalid.' ); }
		$payload_json = SA_Security::decrypt( (string) $row['payload_ciphertext'] );
		$payload = json_decode( (string) $payload_json, true );
		if ( ! is_array( $payload ) ) { return new WP_Error( 'sauth_collision_payload_invalid', 'Account-resolution case is unreadable.' ); }
		/* File 00/canonical identity owner must make the final decision. File 02
		 * never performs an email-only merge. */
		$result = apply_filters( 'sauth_resolve_collision_case_v1', null, $payload, array( 'case'=>$public_id, 'fingerprint'=>SA_Security::client_fingerprint() ) );
		if ( ! is_array( $result ) || 'allow' !== (string) ( $result['result'] ?? '' ) || empty( $result['user_id'] ) ) {
			return new WP_Error( 'sauth_collision_owner_verification_required', 'Canonical identity verification is required before an account can be resolved.' );
		}
		$changed = $wpdb->update(
			SAUTH_Activator::table( 'recovery_changes' ),
			array( 'user_id'=>absint( $result['user_id'] ), 'status'=>'applied', 'updated_at'=>current_time( 'mysql', true ) ),
			array( 'public_id'=>$public_id, 'status'=>'pending' ),
			array( '%d','%s','%s' ), array( '%s','%s' )
		);
		if ( 1 !== (int) $changed ) { return new WP_Error( 'sauth_collision_state_race', 'Account-resolution state changed. Retry safely.' ); }
		self::timeline_event( absint( $result['user_id'] ), 'account_collision_resolved', 'high', array( 'method'=>'canonical_owner_verification' ) );
		return array( 'result'=>'allow', 'user_id'=>absint( $result['user_id'] ) );
	}

	public static function resolve_collision_post() {
		check_admin_referer( 'sauth_resolve_collision', 'sauth_nonce' );
		$case = isset( $_POST['case'] ) ? sanitize_text_field( wp_unslash( $_POST['case'] ) ) : '';
		$proof = isset( $_POST['proof'] ) ? (string) wp_unslash( $_POST['proof'] ) : '';
		$result = self::resolve_collision_case( $case, $proof );
		$proof = ''; unset( $_POST['proof'] );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'status', 'verification_required', home_url( '/resolve-account/' ) ) );
			exit;
		}
		wp_safe_redirect( SA_Membership_Adapter::login_url( home_url( '/' ) ) );
		exit;
	}

	public static function schedule_recovery_change( $user_id, $kind, array $payload, $delay=self::RECOVERY_DEFAULT ) {
		global $wpdb;
		$user_id=absint($user_id);$kind=sanitize_key((string)$kind);
		$allowed=array('email_change','phone_change','provider_unlink','passkey_remove','recovery_contact','collision_resolution');
		if(!$user_id || !in_array($kind,$allowed,true) || !self::smart_step_up($user_id,'recovery_change_'.$kind)){return new WP_Error('sauth_recovery_step_up_required','Fresh strong authentication is required.');}
		$delay=max(self::RECOVERY_MIN,min(self::RECOVERY_MAX,absint($delay)));
		$token=SA_Security::random_token(32);$cipher=SA_Security::encrypt($token);$payload_cipher=SA_Security::encrypt(wp_json_encode($payload));
		if(''===$token||''===$cipher||''===$payload_cipher){return new WP_Error('sauth_recovery_token_failed','Recovery change could not be protected.');}
		$public_id=strtolower(wp_generate_uuid4());$now=time();
		$stored=$wpdb->insert(SAUTH_Activator::table('recovery_changes'),array(
			'public_id'=>$public_id,'user_id'=>$user_id,'change_kind'=>$kind,'payload_ciphertext'=>$payload_cipher,
			'owner_token_ciphertext'=>$cipher,'fingerprint_hash'=>SA_Security::client_fingerprint(),'status'=>'pending',
			'apply_after'=>gmdate('Y-m-d H:i:s',$now+$delay),'created_at'=>gmdate('Y-m-d H:i:s',$now),'updated_at'=>gmdate('Y-m-d H:i:s',$now)
		),array('%s','%d','%s','%s','%s','%s','%s','%s','%s','%s'));
		if(1!==(int)$stored){return new WP_Error('sauth_recovery_store_failed','Recovery change could not be stored.');}
		self::timeline_event($user_id,'recovery_change_pending','high',array('kind'=>$kind,'apply_after'=>$now+$delay));
		return array('public_id'=>$public_id,'owner_token'=>$token,'apply_after'=>$now+$delay);
	}

	public static function cancel_recovery_change( $user_id, $public_id, $owner_token ) {
		global $wpdb;
		$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SAUTH_Activator::table('recovery_changes').' WHERE public_id=%s AND user_id=%d',$public_id,absint($user_id)),ARRAY_A);
		if(!is_array($row)||'pending'!==(string)$row['status']){return false;}
		$expected=SA_Security::decrypt((string)$row['owner_token_ciphertext']);
		if(false===$expected||!hash_equals((string)$expected,(string)$owner_token)){return false;}
		$updated=$wpdb->update(SAUTH_Activator::table('recovery_changes'),array('status'=>'cancelled','updated_at'=>current_time('mysql',true)),array('public_id'=>$public_id,'user_id'=>absint($user_id),'status'=>'pending'),array('%s','%s'),array('%s','%d','%s'));
		return 1===(int)$updated;
	}

	public static function apply_recovery_change( $public_id, $owner_token ) {
		global $wpdb;
		$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SAUTH_Activator::table('recovery_changes').' WHERE public_id=%s',$public_id),ARRAY_A);
		if(!is_array($row)||'pending'!==(string)$row['status']||strtotime((string)$row['apply_after'])>time()){return new WP_Error('sauth_recovery_not_ready','Recovery change is not ready.');}
		$expected=SA_Security::decrypt((string)$row['owner_token_ciphertext']);
		if(false===$expected||!hash_equals((string)$expected,(string)$owner_token)){return new WP_Error('sauth_recovery_token_invalid','Recovery change token is invalid.');}
		if ( 'collision_resolution' === (string) $row['change_kind'] && ! hash_equals( (string) ( $row['fingerprint_hash'] ?? '' ), SA_Security::client_fingerprint() ) ) { return new WP_Error('sauth_recovery_fingerprint_mismatch','Recovery context changed.'); }
		$payload_json=SA_Security::decrypt((string)$row['payload_ciphertext']);$payload=json_decode((string)$payload_json,true);
		$result=apply_filters('sauth_apply_recovery_change_v1',null,(string)$row['change_kind'],absint($row['user_id']),is_array($payload)?$payload:array());
		if(true!==$result){return new WP_Error('sauth_recovery_handler_unavailable','Canonical owner did not approve or handle the recovery change.');}
		$updated=$wpdb->update(SAUTH_Activator::table('recovery_changes'),array('status'=>'applied','updated_at'=>current_time('mysql',true)),array('public_id'=>$public_id,'status'=>'pending'),array('%s','%s'),array('%s','%s'));
		return 1===(int)$updated;
	}

	public static function not_me_ajax() {
		if(!is_user_logged_in()){wp_send_json_error(array('code'=>'authentication_required'),401);} check_ajax_referer('sauth_security_center','nonce');
		$id=isset($_POST['event_id'])?sanitize_text_field(wp_unslash($_POST['event_id'])):'';
		$result=self::report_not_me(get_current_user_id(),$id);
		is_wp_error($result)?wp_send_json_error(array('code'=>$result->get_error_code()),400):wp_send_json_success(array('contained'=>true));
	}
	public static function lockdown_ajax() {
		if(!is_user_logged_in()){wp_send_json_error(array('code'=>'authentication_required'),401);} check_ajax_referer('sauth_security_center','nonce');
		wp_send_json_success(array('locked'=>self::emergency_lockdown(get_current_user_id(),'user_request')));
	}
	public static function recovery_change_ajax() { wp_send_json_error(array('code'=>'use_canonical_owner_flow'),409); }
	public static function recovery_cancel_ajax() { wp_send_json_error(array('code'=>'use_canonical_owner_flow'),409); }

	public static function render_security_center() {
		if(!is_user_logged_in()){return '<div class="sa-auth-shell"><p>Sign in to review account security.</p></div>';}
		$rows=self::timeline(get_current_user_id(),30); ob_start(); ?>
		<main class="sa-auth-shell"><section class="sa-auth-card" aria-labelledby="sauth-security-title">
		<h1 id="sauth-security-title">Account Security</h1>
		<p>Review recent authentication activity. Exact IP addresses and secrets are not displayed.</p>
		<button type="button" class="sa-secondary-button" data-sauth-lockdown>Secure My Account</button>
		<ul class="sauth-security-timeline">
		<?php foreach($rows as $row): ?><li><strong><?php echo esc_html(ucwords(str_replace('_',' ',(string)$row['event_type']))); ?></strong> — <?php echo esc_html((string)$row['created_at']); ?> <button type="button" data-sauth-not-me="<?php echo esc_attr((string)$row['public_id']); ?>">This was not me</button></li><?php endforeach; ?>
		</ul></section></main><?php return (string)ob_get_clean();
	}
	public static function render_collision_resolution(){
		$case = isset( $_GET['case'] ) ? sanitize_text_field( wp_unslash( $_GET['case'] ) ) : '';
		$proof = isset( $_GET['proof'] ) ? sanitize_text_field( wp_unslash( $_GET['proof'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		ob_start(); ?>
		<section class="sa-auth-shell"><div class="sa-auth-card"><h1>Resolve Existing Account</h1>
		<p>Accounts are never merged silently or from email alone. This flow uses an opaque, one-time, fingerprint-bound case and requires verification by the canonical File 00 identity owner.</p>
		<?php if ( 'verification_required' === $status ) : ?><div class="sa-notice sa-notice-error" role="status">Additional canonical identity verification is required. No account was merged.</div><?php endif; ?>
		<?php if ( $case && $proof ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sauth_resolve_collision"><input type="hidden" name="case" value="<?php echo esc_attr( $case ); ?>"><input type="hidden" name="proof" value="<?php echo esc_attr( $proof ); ?>">
		<?php wp_nonce_field( 'sauth_resolve_collision', 'sauth_nonce' ); ?><button class="sa-primary-button" type="submit">Verify and Continue Safely</button></form>
		<?php else : ?><p>Start from the registration, sign-in, or account-recovery flow so a protected resolution case can be created without exposing whether an identifier exists.</p><?php endif; ?>
		</div></section><?php return (string)ob_get_clean();
	}

	public static function cleanup() {
		global $wpdb;
		$wpdb->query($wpdb->prepare('DELETE FROM '.SAUTH_Activator::table('security_timeline').' WHERE created_at<%s',gmdate('Y-m-d H:i:s',time()-self::TIMELINE_RETENTION)));
		$wpdb->query($wpdb->prepare("DELETE FROM ".SAUTH_Activator::table('recovery_changes')." WHERE status IN ('applied','cancelled','expired') AND updated_at<%s",gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS)));
	}
}
