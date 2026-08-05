<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 session-entry projection and revocation controls.
 *
 * WordPress remains the credential-session implementation. File 02 stores only
 * an HMAC binding and an opaque public identifier, never the raw session token.
 * Revoked registry entries are denied at request resolution even when the core
 * session has not yet expired.
 */
final class SAUTH_Session_Manager {
	const CLEANUP_HOOK      = 'sauth_session_registry_cleanup';
	const HISTORY_RETENTION = 30 * DAY_IN_SECONDS;
	const MAX_LIST          = 50;

	public static function init() {
		add_action( 'admin_post_sauth_revoke_session', array( __CLASS__, 'revoke_one' ) );
		add_action( 'admin_post_sauth_revoke_other_sessions', array( __CLASS__, 'revoke_others' ) );
		add_action( 'admin_post_sauth_revoke_all_sessions', array( __CLASS__, 'revoke_all' ) );
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'register_cookie' ), 30, 6 );
		add_filter( 'determine_current_user', array( __CLASS__, 'deny_revoked_session' ), 99 );
		add_action( 'init', array( __CLASS__, 'touch_current' ), 30 );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="sa-auth-shell"><section class="sa-auth-card"><h1>Account access required</h1><p>Sign in to review active sessions.</p><a class="sa-primary-button" href="' . esc_url( SA_Membership_Adapter::login_url( SA_Security::page_url( 'sessions' ) ) ) . '">Log In</a></section></div>';
		}

		$user_id = get_current_user_id();
		self::ensure_current_registered( $user_id );
		$sessions = self::list_for_user( $user_id );
		$current_hash = self::current_token_hash();
		$active_count = 0;
		foreach ( $sessions as $session ) {
			if ( 'active' === $session['status'] ) {
				$active_count++;
			}
		}

		ob_start();
		?>
		<main class="sa-auth-shell">
			<section class="sa-auth-card sa-auth-wide" aria-labelledby="sauth-sessions-title">
				<span class="sa-kicker">Account security</span>
				<h1 id="sauth-sessions-title">Active Sessions</h1>
				<p class="sa-intro">Review your own sessions and revoke an unfamiliar sign-in. Raw tokens, full IP addresses and precise location are never displayed.</p>
				<?php include SA_DIR . 'templates/partials/notice.php'; ?>
				<div class="sa-session-summary" role="status"><strong>Total active sessions:</strong> <?php echo esc_html( (string) $active_count ); ?></div>
				<div class="sa-session-list">
				<?php if ( empty( $sessions ) ) : ?>
					<p>No session projections are available. The current session remains governed by WordPress and Membership Core.</p>
				<?php endif; ?>
				<?php foreach ( $sessions as $session ) :
					$is_current = '' !== $current_hash && hash_equals( $current_hash, (string) $session['token_hash'] );
				?>
					<article class="sa-session-card" aria-label="<?php echo esc_attr( $is_current ? 'Current session' : 'Account session' ); ?>">
						<h2><?php echo $is_current ? 'Current session' : 'Other session'; ?></h2>
						<dl class="sa-definition">
							<div><dt>Device</dt><dd><?php echo esc_html( $session['device_label'] ); ?></dd></div>
							<div><dt>Last activity</dt><dd><?php echo esc_html( self::display_time( $session['last_seen_at'] ) ); ?></dd></div>
							<div><dt>Network</dt><dd><?php echo esc_html( $session['network_label'] ); ?></dd></div>
							<div><dt>Risk projection</dt><dd><?php echo esc_html( ucfirst( $session['risk_level'] ) ); ?></dd></div>
							<div><dt>Status</dt><dd><?php echo esc_html( ucfirst( $session['status'] ) ); ?></dd></div>
						</dl>
						<?php if ( ! $is_current && 'active' === $session['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="sauth_revoke_session">
								<input type="hidden" name="session_id" value="<?php echo esc_attr( $session['public_id'] ); ?>">
								<?php wp_nonce_field( 'sauth_revoke_session_' . $session['public_id'], 'sauth_nonce' ); ?>
								<button class="sa-secondary-button" type="submit">Revoke This Session</button>
							</form>
						<?php elseif ( $is_current ) : ?>
							<p><strong>Protected current session:</strong> use “Sign out everywhere” to revoke it.</p>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sauth_revoke_other_sessions">
					<?php wp_nonce_field( 'sauth_revoke_other_sessions', 'sauth_nonce' ); ?>
					<button class="sa-secondary-button" type="submit">Revoke All Other Sessions</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sauth_revoke_all_sessions">
					<?php wp_nonce_field( 'sauth_revoke_all_sessions', 'sauth_nonce' ); ?>
					<button class="sa-text-link" type="submit">Sign Out Everywhere</button>
				</form>
			</section>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	public static function register_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$token   = (string) $token;
		if ( ! $user_id || '' === $token ) {
			return;
		}
		$token_hash = self::token_hash( $token );
		$now = current_time( 'mysql', true );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT public_id FROM " . self::table() . " WHERE user_id = %d AND token_hash = %s", $user_id, $token_hash ) );
		$data = array(
			'user_id'       => $user_id,
			'token_hash'    => $token_hash,
			'device_hash'   => SA_Security::client_fingerprint(),
			'device_label'  => self::generalize_user_agent( self::request_user_agent() ),
			'network_label' => self::generalize_ip( self::request_ip() ),
			'risk_level'    => self::current_risk_level( $user_id ),
			'status'        => 'active',
			'last_seen_at'  => $now,
			'expires_at'    => gmdate( 'Y-m-d H:i:s', max( time() + HOUR_IN_SECONDS, absint( $expiration ) ) ),
			'revoked_at'    => null,
			'updated_at'    => $now,
		);
		if ( $existing ) {
			$wpdb->update( self::table(), $data, array( 'public_id' => (string) $existing ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%s' ) );
			return;
		}
		$data['public_id'] = strtolower( wp_generate_uuid4() );
		$data['created_at'] = $now;
		$wpdb->insert( self::table(), $data, array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	/**
	 * Deny a registry-revoked token after WordPress resolved the candidate user.
	 */
	public static function deny_revoked_session( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return $user_id;
		}
		$token = (string) wp_get_session_token();
		if ( '' === $token ) {
			return $user_id;
		}
		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM " . self::table() . " WHERE user_id = %d AND token_hash = %s", $user_id, self::token_hash( $token ) )
		);
		return 'revoked' === $status ? 0 : $user_id;
	}

	public static function touch_current() {
		global $wpdb;
		if ( ! is_user_logged_in() || 1 !== wp_rand( 1, 10 ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$hash = self::current_token_hash();
		if ( '' === $hash ) {
			return;
		}
		$wpdb->update(
			self::table(),
			array( 'last_seen_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'user_id' => $user_id, 'token_hash' => $hash, 'status' => 'active' ),
			array( '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
	}

	public static function revoke_one() {
		global $wpdb;
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		check_admin_referer( 'sauth_revoke_session_' . $session_id, 'sauth_nonce' );
		$user_id = get_current_user_id();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE public_id = %s AND user_id = %d", $session_id, $user_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			self::redirect( 'error', 'The selected session was not found.' );
		}
		$current = self::current_token_hash();
		if ( '' !== $current && hash_equals( $current, (string) $row['token_hash'] ) ) {
			self::redirect( 'error', 'The current session is protected. Use Sign Out Everywhere to revoke it.' );
		}
		if ( 'active' !== (string) $row['status'] ) {
			self::redirect( 'success', 'The selected session was already inactive.' );
		}
		$changed = self::mark_revoked( $user_id, array( (string) $row['public_id'] ), 'user_selected_session' );
		if ( $changed < 1 ) {
			self::redirect( 'error', 'The selected session could not be revoked.' );
		}
		SAUTH_Event_Outbox::emit( 'AuthSessionRevoked.v1', $user_id, $user_id, array( 'scope' => 'single_session', 'reason' => 'user_request', 'session_id' => $session_id ), 'security' );
		SA_Membership_Adapter::audit( 'authentication_session_revoked', $user_id, array( 'scope' => 'single_session' ) );
		self::redirect( 'success', 'The selected session was revoked.' );
	}

	public static function revoke_others() {
		global $wpdb;
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sauth_revoke_other_sessions', 'sauth_nonce' );
		$user_id = get_current_user_id();
		$token   = (string) wp_get_session_token();
		$current = self::current_token_hash();
		if ( '' === $token || '' === $current || ! class_exists( 'WP_Session_Tokens' ) ) {
			self::redirect( 'error', 'The current session could not be verified.' );
		}
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT public_id FROM " . self::table() . " WHERE user_id = %d AND status = 'active' AND token_hash <> %s", $user_id, $current ) );
		self::mark_revoked( $user_id, is_array( $rows ) ? $rows : array(), 'user_revoke_others' );
		WP_Session_Tokens::get_instance( $user_id )->destroy_others( $token );
		SAUTH_Event_Outbox::emit( 'AuthSessionRevoked.v1', $user_id, $user_id, array( 'scope' => 'other_sessions', 'reason' => 'user_request' ), 'security' );
		SA_Membership_Adapter::audit( 'authentication_sessions_revoked', $user_id, array( 'scope' => 'others' ) );
		self::redirect( 'success', 'All other sessions were revoked.' );
	}

	public static function revoke_all() {
		global $wpdb;
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sauth_revoke_all_sessions', 'sauth_nonce' );
		$user_id = get_current_user_id();
		$wpdb->query( $wpdb->prepare( "UPDATE " . self::table() . " SET status = 'revoked', revoked_at = %s, updated_at = %s WHERE user_id = %d AND status = 'active'", current_time( 'mysql', true ), current_time( 'mysql', true ), $user_id ) );
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
		SAUTH_Event_Outbox::emit( 'AuthSessionRevoked.v1', $user_id, $user_id, array( 'scope' => 'all_sessions', 'reason' => 'user_request' ), 'security' );
		SA_Membership_Adapter::audit( 'authentication_sessions_revoked', $user_id, array( 'scope' => 'all' ) );
		wp_clear_auth_cookie();
		wp_safe_redirect( SA_Security::message_url( 'login', 'success', 'You have been signed out on all devices.' ) );
		exit;
	}

	public static function cleanup() {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( "UPDATE " . self::table() . " SET status = 'expired', updated_at = %s WHERE status = 'active' AND expires_at < %s", $now, $now ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . self::table() . " WHERE status IN ('revoked','expired') AND updated_at < %s", gmdate( 'Y-m-d H:i:s', time() - self::HISTORY_RETENTION ) ) );
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private static function list_for_user( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT public_id, token_hash, device_label, network_label, risk_level, status, last_seen_at, expires_at FROM " . self::table() . " WHERE user_id = %d AND (status = 'active' OR updated_at >= %s) ORDER BY status = 'active' DESC, last_seen_at DESC LIMIT %d", absint( $user_id ), gmdate( 'Y-m-d H:i:s', time() - self::HISTORY_RETENTION ), self::MAX_LIST ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	private static function ensure_current_registered( $user_id ) {
		global $wpdb;
		$token = (string) wp_get_session_token();
		if ( '' === $token ) {
			return;
		}
		$hash = self::token_hash( $token );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::table() . " WHERE user_id = %d AND token_hash = %s", absint( $user_id ), $hash ) );
		if ( 0 === (int) $exists ) {
			self::register_cookie( '', time() + DAY_IN_SECONDS, time() + DAY_IN_SECONDS, $user_id, 'logged_in', $token );
		}
	}

	private static function mark_revoked( $user_id, array $public_ids, $reason ) {
		global $wpdb;
		$changed = 0;
		foreach ( array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $public_ids ) ) ) ), 0, self::MAX_LIST ) as $public_id ) {
			$result = $wpdb->update(
				self::table(),
				array( 'status' => 'revoked', 'revoked_at' => current_time( 'mysql', true ), 'revocation_reason' => sanitize_key( (string) $reason ), 'updated_at' => current_time( 'mysql', true ) ),
				array( 'user_id' => absint( $user_id ), 'public_id' => $public_id, 'status' => 'active' ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d', '%s', '%s' )
			);
			if ( 1 === (int) $result ) {
				$changed++;
			}
		}
		return $changed;
	}

	private static function current_risk_level( $user_id ) {
		global $wpdb;
		$score = $wpdb->get_var( $wpdb->prepare( "SELECT risk_score FROM {$wpdb->prefix}sa_auth_devices WHERE user_id = %d AND fingerprint_hash = %s ORDER BY last_seen_at DESC LIMIT 1", absint( $user_id ), SA_Security::client_fingerprint() ) );
		$score = absint( $score );
		return $score >= 80 ? 'high' : ( $score >= 45 ? 'medium' : 'low' );
	}

	private static function current_token_hash() {
		$token = (string) wp_get_session_token();
		return '' === $token ? '' : self::token_hash( $token );
	}

	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function request_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	private static function request_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function generalize_user_agent( $user_agent ) {
		$user_agent = strtolower( sanitize_text_field( (string) $user_agent ) );
		$browser = false !== strpos( $user_agent, 'firefox' ) ? 'Firefox' : ( false !== strpos( $user_agent, 'edg' ) ? 'Edge' : ( false !== strpos( $user_agent, 'chrome' ) ? 'Chrome' : ( false !== strpos( $user_agent, 'safari' ) ? 'Safari' : 'Web browser' ) ) );
		$device  = false !== strpos( $user_agent, 'mobile' ) ? 'mobile device' : 'computer';
		return $browser . ' on a ' . $device;
	}

	private static function generalize_ip( $ip ) {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			return $parts[0] . '.' . $parts[1] . '.x.x';
		}
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			return implode( ':', array_slice( $parts, 0, 2 ) ) . ':…';
		}
		return 'Private';
	}

	private static function display_time( $value ) {
		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? 'Unknown' : wp_date( 'Y-m-d H:i', $timestamp );
	}

	private static function redirect( $type, $message ) {
		wp_safe_redirect( SA_Security::message_url( 'sessions', $type, $message ) );
		exit;
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_auth_sessions';
	}
}
