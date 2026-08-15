<?php

defined( 'ABSPATH' ) || exit;

/**
 * Signed, one-time, privacy-minimized email-verification lifecycle.
 *
 * File 02 owns the challenge and delivery surface. File 00 remains the owner of
 * the canonical email-verification state and is updated only through the
 * versioned account-orchestration contract.
 */
final class SAUTH_Email_Verification {
	const VERIFY_ACTION    = 'sauth_verify_email';
	const RESEND_ACTION    = 'sauth_resend_email_verification';
	const CLEANUP_HOOK     = 'sauth_email_verification_cleanup';
	const RESEND_JOB_HOOK  = 'sauth_email_verification_resend_job';
	const TOKEN_TTL        = 1800;
	const RESEND_DELAY     = 300;
	const MAX_ATTEMPTS     = 8;
	const MAX_TOKEN_BYTES  = 256;
	const RESEND_JOB_TTL   = 900;

	public static function init() {
		add_action( 'admin_post_nopriv_' . self::VERIFY_ACTION, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_' . self::VERIFY_ACTION, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_' . self::RESEND_ACTION, array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_' . self::RESEND_ACTION, array( __CLASS__, 'handle_resend' ) );
		add_action( self::RESEND_JOB_HOOK, array( __CLASS__, 'run_resend_job' ), 10, 1 );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/** Create or rotate a verification challenge and deliver it. */
	public static function issue( $user_id, $email, $force = false ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$email   = sanitize_email( (string) $email );
		$canonical_user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $canonical_user instanceof WP_User
			|| strlen( $email ) > 320
			|| ! is_email( $email )
			|| ! hash_equals( strtolower( (string) $canonical_user->user_email ), strtolower( $email ) ) ) {
			return new WP_Error( 'sauth_email_subject_invalid', 'Email verification could not be prepared.' );
		}
		if ( SAUTH_Operations::safe_mode() ) {
			return new WP_Error( 'sauth_email_safe_mode', 'Email verification is temporarily paused by Safe Mode.' );
		}
		if ( ! SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {
			return new WP_Error( 'sauth_email_privacy_erasure_active', 'Email verification is paused while File 02 privacy erasure is active.' );
		}
		$email = sanitize_email( (string) $canonical_user->user_email );
		if ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
			return new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );
		}
		if ( ! SAUTH_Provider_Health::allow_request( 'email' ) ) {
			return new WP_Error( 'sauth_email_delivery_circuit_open', 'Email delivery is temporarily paused. Retry later.' );
		}
		$table = self::table();
		if ( '' === $table ) {
			return new WP_Error( 'sauth_email_storage_unavailable', 'Email verification storage is unavailable.' );
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) {
			return new WP_Error( 'sauth_email_storage_unavailable', 'Email verification storage is unavailable.' );
		}
		if ( is_array( $row )
			&& 'verified' === (string) $row['status']
			&& hash_equals( (string) ( $row['email_hash'] ?? '' ), self::email_hash( $email ) ) ) {
			return array( 'result' => 'verified', 'reason_code' => 'already_verified' );
		}
		if ( is_array( $row ) && in_array( (string) ( $row['status'] ?? '' ), array( 'issuing', 'verifying' ), true ) ) {
			return new WP_Error( 'sauth_email_issue_in_progress', 'Email verification is already being processed. Wait briefly before retrying.' );
		}
		if ( ! $force && is_array( $row ) && 'pending' === (string) ( $row['status'] ?? '' ) && ! empty( $row['sent_at'] ) ) {
			$sent_at = strtotime( (string) $row['sent_at'] );
			if ( false !== $sent_at && $sent_at + self::RESEND_DELAY > time() ) {
				return new WP_Error( 'sauth_email_resend_throttled', 'Please wait before requesting another verification email.' );
			}
		}
		$token = SA_Security::random_token( 32 );
		if ( strlen( $token ) < 64 || strlen( $token ) > self::MAX_TOKEN_BYTES ) {
			return new WP_Error( 'sauth_email_token_unavailable', 'Email verification could not be prepared.' );
		}
		$token_hash = self::token_hash( $token );
		$email_hash = self::email_hash( $email );
		$now        = current_time( 'mysql', true );
		$expires    = gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_TTL );
		/* Reserve one exact challenge generation before delivery. REPLACE is not
		 * used: it can delete a concurrently verified row and silently recreate it. */
		if ( is_array( $row ) ) {
			$stored = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET email_hash=%s,token_hash=%s,status='issuing',attempts=0,sent_at=%s,expires_at=%s,verified_at=NULL,updated_at=%s WHERE user_id=%d AND status=%s AND token_hash=%s AND updated_at=%s",
					$email_hash,
					$token_hash,
					$now,
					$expires,
					$now,
					$user_id,
					(string) $row['status'],
					(string) $row['token_hash'],
					(string) $row['updated_at']
				)
			);
		} else {
			$stored = $wpdb->insert(
				$table,
				array(
					'user_id' => $user_id,
					'email_hash' => $email_hash,
					'token_hash' => $token_hash,
					'status' => 'issuing',
					'attempts' => 0,
					'sent_at' => $now,
					'expires_at' => $expires,
					'verified_at' => null,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d','%s','%s','%s','%d','%s','%s','%s','%s','%s' )
			);
		}
		$reserved = $wpdb->get_row( $wpdb->prepare( "SELECT email_hash,token_hash,status,sent_at,expires_at FROM {$table} WHERE user_id=%d", $user_id ), ARRAY_A );
		if ( 1 !== (int) $stored
			|| ! is_array( $reserved )
			|| '' !== (string) $wpdb->last_error
			|| 'issuing' !== (string) ( $reserved['status'] ?? '' )
			|| ! hash_equals( $email_hash, (string) ( $reserved['email_hash'] ?? '' ) )
			|| ! hash_equals( $token_hash, (string) ( $reserved['token_hash'] ?? '' ) )
			|| ! hash_equals( $now, (string) ( $reserved['sent_at'] ?? '' ) )
			|| ! hash_equals( $expires, (string) ( $reserved['expires_at'] ?? '' ) ) ) {
			$token = '';
			return new WP_Error( 'sauth_email_challenge_claim_failed', 'Email verification is already being changed by another request. Retry shortly.' );
		}
		$link = add_query_arg( array( 'uid' => $user_id, 'token' => $token ), SA_Security::page_url( 'email_verify', home_url( '/' ) ) );
		$subject = 'Verify your Sabri account email';
		$message = "Verify your email address for the Sabri Social Homeopathy Platform.\n\n";
		$message .= "Open this secure link and confirm verification:\n{$link}\n\n";
		$message .= "This link expires in 30 minutes and can be used once. If you did not request this account, ignore this email.";
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		try {
			$delivered = apply_filters( 'sauth_email_verification_delivery', null, $email, $subject, $message, $headers, $user_id );
			if ( null === $delivered ) {
				$delivered = wp_mail( $email, $subject, $message, $headers );
			}
		} catch ( Throwable $error ) {
			$delivered = false;
		}
		$token = '';
		if ( true !== $delivered ) {
			$failed_write = $wpdb->update(
				$table,
				array( 'status' => 'delivery_failed', 'updated_at' => current_time( 'mysql', true ) ),
				array( 'user_id' => $user_id, 'token_hash' => $token_hash, 'status' => 'issuing' ),
				array( '%s', '%s' ),
				array( '%d', '%s', '%s' )
			);
			$failed_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE user_id=%d AND token_hash=%s", $user_id, $token_hash ) );
			SA_Membership_Adapter::audit( 'email_verification_delivery_failed', $user_id );
			if ( 1 !== (int) $failed_write || 'delivery_failed' !== $failed_status || '' !== (string) $wpdb->last_error ) {
				return new WP_Error( 'sauth_email_delivery_state_failed', 'Email delivery and local challenge state could not be reconciled safely. Retry later.' );
			}
			return new WP_Error( 'sauth_email_delivery_failed', 'The account was created, but the verification email could not be delivered. Retry from the verification page.' );
		}
		$published = $wpdb->update(
			$table,
			array( 'status' => 'pending', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'user_id' => $user_id, 'token_hash' => $token_hash, 'status' => 'issuing' ),
			array( '%s','%s' ),
			array( '%d','%s','%s' )
		);
		$published_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE user_id=%d AND token_hash=%s", $user_id, $token_hash ) );
		if ( 1 !== (int) $published || 'pending' !== $published_status || '' !== (string) $wpdb->last_error ) {
			$contained = $wpdb->update( $table, array( 'status' => 'delivery_failed', 'updated_at' => current_time( 'mysql', true ) ), array( 'user_id' => $user_id, 'token_hash' => $token_hash, 'status' => 'issuing' ), array( '%s','%s' ), array( '%d','%s','%s' ) );
			$contained_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE user_id=%d AND token_hash=%s", $user_id, $token_hash ) );
			if ( 1 !== (int) $contained || 'delivery_failed' !== $contained_status || '' !== (string) $wpdb->last_error ) { SAUTH_Operations::enter_safe_mode(); }
			SA_Membership_Adapter::audit( 'email_verification_publish_failed', $user_id );
			return new WP_Error( 'sauth_email_challenge_publish_failed', 'The verification email was sent, but its one-time challenge could not be activated safely. Request a new link.' );
		}
		SA_Membership_Adapter::audit( 'email_verification_sent', $user_id );
		return array( 'result' => 'sent', 'reason_code' => 'verification_email_sent', 'expires_at' => gmdate( 'c', time() + self::TOKEN_TTL ) );
	}

	public static function render() {
		$uid   = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( strlen( $token ) > self::MAX_TOKEN_BYTES ) { $token = ''; }
		ob_start();
		?>
		<main class="sa-auth-shell">
			<section class="sa-auth-card" aria-labelledby="sauth-email-title">
				<div class="sa-brand-mark">SH</div><span class="sa-kicker">Email security</span>
				<h1 id="sauth-email-title">Verify Your Email</h1>
				<p class="sa-intro">A one-time verification link confirms email ownership. File 00 remains the canonical owner of the verified identity state.</p>
				<?php include SA_DIR . 'templates/partials/notice.php'; ?>
				<?php if ( $uid && $token ) : ?>
					<form class="sa-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::VERIFY_ACTION ); ?>">
						<input type="hidden" name="uid" value="<?php echo esc_attr( (string) $uid ); ?>">
						<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
						<?php wp_nonce_field( 'sauth_verify_email_' . $uid, 'sauth_nonce' ); ?>
						<button class="sa-primary-button" type="submit">Confirm Email Verification</button>
					</form>
				<?php else : ?>
					<p>Open the secure link sent to your email address. A new link can be requested below without revealing whether an account exists.</p>
				<?php endif; ?>
				<div class="sa-divider">Need another link?</div>
				<form class="sa-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RESEND_ACTION ); ?>">
					<?php wp_nonce_field( 'sauth_resend_email_verification', 'sauth_nonce' ); ?>
					<label for="sauth-verification-email">Account email</label>
					<input id="sauth-verification-email" type="email" name="email" autocomplete="email" maxlength="320" required>
					<button class="sa-secondary-button" type="submit">Send a New Verification Link</button>
				</form>
			</section>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_verify() {
		$user_id = isset( $_POST['uid'] ) ? absint( $_POST['uid'] ) : 0;
		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		check_admin_referer( 'sauth_verify_email_' . $user_id, 'sauth_nonce' );
		$result = self::verify( $user_id, $token );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( SA_Security::message_url( 'email_verify', 'error', $result->get_error_message() ) );
			exit;
		}
		wp_safe_redirect( SA_Security::message_url( 'login', 'success', 'Your email address has been verified. You may now sign in.' ) );
		exit;
	}

	/** Anonymous resend queues an opaque job so public response timing is not mail-provider bound. */
	public static function handle_resend() {
		check_admin_referer( 'sauth_resend_email_verification', 'sauth_nonce' );
		$raw_email = isset( $_POST['email'] ) ? trim( (string) wp_unslash( $_POST['email'] ) ) : '';
		$email = strlen( $raw_email ) <= 320 ? sanitize_email( $raw_email ) : '';
		$key = self::email_hash( $email );
		$blocked = SA_Security::rate_limited( 'email_verification_resend_ip', 8, HOUR_IN_SECONDS )
			|| SA_Security::rate_limited( 'email_verification_resend_account', 3, HOUR_IN_SECONDS, $key );
		if ( ! $blocked && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			$user_id = $user instanceof WP_User ? (int) $user->ID : 0;
			$job_user_id = 0;
			$job_epoch   = '';
			if ( $user_id && SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {
				$job_epoch = SAUTH_Privacy_Jobs::snapshot( $user_id );
				if ( '' !== $job_epoch ) { $job_user_id = $user_id; }
			}
			$job_token = SA_Security::random_token( 16 );
			if ( '' !== $job_token ) {
				$job_key = self::resend_job_key( $job_token );
				$job = array( 'user_id' => $job_user_id, 'privacy_epoch' => $job_epoch, 'created_at' => time(), 'retry_count' => 0 );
				set_transient( $job_key, $job, self::RESEND_JOB_TTL );
				$stored = get_transient( $job_key ) === $job;
				$indexed = ! $job_user_id || SAUTH_Privacy_Jobs::register_job( $job_user_id, $job_key );
				$scheduled = false;
				if ( $stored && $indexed && function_exists( 'wp_schedule_single_event' ) ) {
					$scheduled = false !== wp_schedule_single_event( time() + 1, self::RESEND_JOB_HOOK, array( $job_token ) );
				}
				if ( ! $scheduled ) {
					delete_transient( $job_key );
					if ( $job_user_id ) { SAUTH_Privacy_Jobs::forget_job( $job_user_id, $job_key ); }
				}
			}
		}
		wp_safe_redirect( SA_Security::message_url( 'email_verify', 'success', 'If an eligible account exists, a verification email will be sent. Please also check spam or junk folders.' ) );
		exit;
	}

	public static function run_resend_job( $job_token ) {
		$job_token = sanitize_text_field( (string) $job_token );
		if ( '' === $job_token || strlen( $job_token ) > 128 ) { return; }
		$key = self::resend_job_key( $job_token );
		$job = get_transient( $key );
		if ( ! is_array( $job ) ) { return; }
		$user_id = absint( $job['user_id'] ?? 0 );
		$epoch   = (string) ( $job['privacy_epoch'] ?? '' );
		$created = absint( $job['created_at'] ?? 0 );
		if ( ! $created || $created < time() - self::RESEND_JOB_TTL ) { self::delete_resend_job( $user_id, $key ); return; }
		if ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { self::delete_resend_job( $user_id, $key ); return; }
		if ( SAUTH_Operations::safe_mode() || ! SAUTH_Account_Contract::provider_available() ) { self::retry_resend_job( $job_token, $key, $job, 60 ); return; }
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) { self::delete_resend_job( $user_id, $key ); return; }
		$result = self::issue( $user_id, (string) $user->user_email, false );
		if ( is_wp_error( $result ) ) {
			$retryable = array( 'sauth_email_safe_mode', 'sauth_email_provider_unavailable', 'sauth_email_delivery_circuit_open', 'sauth_email_storage_unavailable', 'sauth_email_issue_in_progress', 'sauth_email_challenge_claim_failed', 'sauth_email_delivery_state_failed', 'sauth_email_delivery_failed', 'sauth_email_challenge_publish_failed', 'sauth_email_resend_throttled' );
			if ( in_array( $result->get_error_code(), $retryable, true ) ) { self::retry_resend_job( $job_token, $key, $job, 'sauth_email_resend_throttled' === $result->get_error_code() ? self::RESEND_DELAY : 60 ); return; }
		}
		self::delete_resend_job( $user_id, $key );
	}

	private static function retry_resend_job( $job_token, $key, array $job, $minimum_delay ) {
		$user_id = absint( $job['user_id'] ?? 0 );
		$created = absint( $job['created_at'] ?? 0 );
		$retries = absint( $job['retry_count'] ?? 0 );
		$delay = max( absint( $minimum_delay ), min( 300, 60 * ( 1 << min( $retries, 2 ) ) ) );
		if ( $retries >= 3 || ! $created || $created + self::RESEND_JOB_TTL <= time() + $delay || ! function_exists( 'wp_schedule_single_event' ) ) { self::delete_resend_job( $user_id, $key ); return false; }
		$job['retry_count'] = $retries + 1;
		$ttl = max( 1, $created + self::RESEND_JOB_TTL - time() );
		$stored = set_transient( $key, $job, $ttl );
		if ( false === $stored || get_transient( $key ) !== $job ) { self::delete_resend_job( $user_id, $key ); return false; }
		if ( false === wp_schedule_single_event( time() + $delay, self::RESEND_JOB_HOOK, array( $job_token ) ) ) { self::delete_resend_job( $user_id, $key ); return false; }
		return true;
	}

	private static function delete_resend_job( $user_id, $key ) {
		delete_transient( $key );
		if ( $user_id ) { SAUTH_Privacy_Jobs::forget_job( $user_id, $key ); }
	}

	/** Atomically consume one valid email-verification token. */
	public static function verify( $user_id, $token ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$token   = trim( (string) $token );
		if ( ! $user_id || strlen( $token ) < 64 || strlen( $token ) > self::MAX_TOKEN_BYTES ) {
			return new WP_Error( 'sauth_email_token_invalid', 'This verification link is invalid or expired.' );
		}
		if ( SAUTH_Operations::safe_mode() ) {
			return new WP_Error( 'sauth_email_safe_mode', 'Email verification is temporarily paused by Safe Mode.' );
		}
		if ( ! SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {
			return new WP_Error( 'sauth_email_privacy_erasure_active', 'Email verification is paused while File 02 privacy erasure is active.' );
		}
		if ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
			return new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );
		}
		if ( SA_Security::rate_limited( 'email_verification_attempt', self::MAX_ATTEMPTS, HOUR_IN_SECONDS, (string) $user_id ) ) {
			return new WP_Error( 'sauth_email_attempts_limited', 'Too many verification attempts. Request a new link.' );
		}
		$table = self::table();
		if ( '' === $table ) { return new WP_Error( 'sauth_email_storage_unavailable', 'Email verification storage is unavailable.' ); }
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		if ( ! is_array( $row ) || '' !== (string) $wpdb->last_error ) { return new WP_Error( 'sauth_email_challenge_missing', 'This verification link is invalid or expired.' ); }
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) { return new WP_Error( 'sauth_email_subject_changed', 'The account email changed. Request a new verification link.' ); }
		if ( 'verified' === (string) $row['status'] ) {
			return hash_equals( (string) $row['email_hash'], self::email_hash( (string) $user->user_email ) )
				? array( 'result' => 'verified', 'reason_code' => 'already_verified' )
				: new WP_Error( 'sauth_email_subject_changed', 'The account email changed. Request a new verification link.' );
		}
		if ( in_array( (string) $row['status'], array( 'issuing', 'verifying' ), true ) ) {
			return new WP_Error( 'sauth_email_verification_in_progress', 'This verification link is already being processed. Wait briefly and retry.' );
		}
		$expires = strtotime( (string) ( $row['expires_at'] ?? '' ) );
		if ( false === $expires || $expires < time() ) {
			$wpdb->update( $table, array( 'status' => 'expired', 'updated_at' => current_time( 'mysql', true ) ), array( 'user_id' => $user_id, 'status' => (string) $row['status'], 'token_hash' => (string) $row['token_hash'] ), array( '%s', '%s' ), array( '%d', '%s', '%s' ) );
			return new WP_Error( 'sauth_email_challenge_expired', 'This verification link has expired. Request a new link.' );
		}
		if ( absint( $row['attempts'] ) >= self::MAX_ATTEMPTS ) { return new WP_Error( 'sauth_email_attempts_exhausted', 'This verification link can no longer be used. Request a new link.' ); }
		$token_hash = self::token_hash( $token );
		$token = '';
		if ( ! hash_equals( (string) $row['token_hash'], $token_hash ) ) {
			return new WP_Error( 'sauth_email_token_mismatch', 'This verification link is invalid or expired.' );
		}
		if ( ! hash_equals( (string) $row['email_hash'], self::email_hash( (string) $user->user_email ) ) ) {
			return new WP_Error( 'sauth_email_subject_changed', 'The account email changed. Request a new verification link.' );
		}
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'verifying', attempts = attempts + 1, updated_at = %s WHERE user_id = %d AND token_hash = %s AND status = 'pending' AND attempts < %d AND expires_at >= %s",
				current_time( 'mysql', true ), $user_id, $token_hash, self::MAX_ATTEMPTS, current_time( 'mysql', true )
			)
		);
		if ( 1 !== (int) $claimed ) {
			$current_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE user_id = %d", $user_id ) );
			if ( 'verified' === $current_status ) { return array( 'result' => 'verified', 'reason_code' => 'already_verified' ); }
			return new WP_Error( 'sauth_email_token_claim_failed', 'This verification link is invalid, expired or already being processed.' );
		}
		$claim_row = $wpdb->get_row( $wpdb->prepare( "SELECT status,token_hash,attempts FROM {$table} WHERE user_id=%d", $user_id ), ARRAY_A );
		if ( ! is_array( $claim_row )
			|| '' !== (string) $wpdb->last_error
			|| 'verifying' !== (string) ( $claim_row['status'] ?? '' )
			|| ! hash_equals( $token_hash, (string) ( $claim_row['token_hash'] ?? '' ) )
			|| absint( $claim_row['attempts'] ?? 0 ) !== absint( $row['attempts'] ?? 0 ) + 1 ) {
			return new WP_Error( 'sauth_email_token_claim_failed', 'This verification link could not be claimed safely. Retry later.' );
		}
		try {
			$provider = SAUTH_Account_Contract::mark_email_verified(
				$user_id,
				$user->user_email,
				array( 'purpose' => 'email_verification', 'idempotency_key' => 'email-verify-' . $user_id . '-' . substr( $token_hash, 0, 16 ) )
			);
		} catch ( Throwable $error ) {
			$provider = array( 'result' => 'unknown' );
		}
		if ( 'allow' !== ( $provider['result'] ?? '' ) ) {
			$reopened = $wpdb->update(
				$table,
				array( 'status' => 'pending', 'updated_at' => current_time( 'mysql', true ) ),
				array( 'user_id' => $user_id, 'status' => 'verifying', 'token_hash' => $token_hash ),
				array( '%s', '%s' ), array( '%d', '%s', '%s' )
			);
			$reopened_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE user_id=%d AND token_hash=%s", $user_id, $token_hash ) );
			if ( 1 !== (int) $reopened || 'pending' !== $reopened_status || '' !== (string) $wpdb->last_error ) {
				SA_Membership_Adapter::audit( 'email_verification_reopen_failed', $user_id );
				return new WP_Error( 'sauth_email_challenge_reopen_failed', 'Email verification could not restore its retry state safely. Wait before retrying.' );
			}
			return new WP_Error( 'sauth_email_provider_rejected', 'Email verification is temporarily unavailable. The link has not been consumed; retry later.' );
		}
		$updated = $wpdb->update(
			$table,
			array( 'status' => 'verified', 'verified_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ), 'token_hash' => str_repeat( '0', 64 ) ),
			array( 'user_id' => $user_id, 'status' => 'verifying', 'token_hash' => $token_hash ),
			array( '%s', '%s', '%s', '%s' ), array( '%d', '%s', '%s' )
		);
		$completed = $wpdb->get_row( $wpdb->prepare( "SELECT email_hash,token_hash,status,verified_at FROM {$table} WHERE user_id=%d", $user_id ), ARRAY_A );
		if ( 1 !== (int) $updated
			|| ! is_array( $completed )
			|| '' !== (string) $wpdb->last_error
			|| 'verified' !== (string) ( $completed['status'] ?? '' )
			|| ! hash_equals( self::email_hash( (string) $user->user_email ), (string) ( $completed['email_hash'] ?? '' ) )
			|| ! hash_equals( str_repeat( '0', 64 ), (string) ( $completed['token_hash'] ?? '' ) )
			|| '' === (string) ( $completed['verified_at'] ?? '' ) ) {
			SA_Membership_Adapter::audit( 'email_verification_completion_store_failed', $user_id );
			return new WP_Error( 'sauth_email_completion_store_failed', 'Email verification was accepted but local completion evidence could not be stored. Retry or contact support with the time of this attempt.' );
		}
		SA_Security::clear_rate_limit( 'email_verification_attempt', (string) $user_id );
		SAUTH_Event_Outbox::emit( 'EmailVerified.v1', $user_id, $user_id, array( 'method' => 'signed_email_link' ), 'restricted' );
		SA_Membership_Adapter::audit( 'email_verified', $user_id );
		return array( 'result' => 'verified', 'reason_code' => 'email_verified' );
	}

	public static function cleanup() {
		global $wpdb;
		$table = self::table();
		/* A worker crash after the atomic claim must not permanently strand a valid
		 * token. Reopen only stale, unexpired verifying rows; provider marking is
		 * idempotent, so a repeated completion remains safe. */
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status='pending', updated_at=%s WHERE status='verifying' AND updated_at < %s AND expires_at >= %s",
				current_time( 'mysql', true ), gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS ), current_time( 'mysql', true )
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status='delivery_failed', updated_at=%s WHERE status='issuing' AND updated_at < %s",
				current_time( 'mysql', true ), gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS )
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE (status IN ('expired','delivery_failed') AND updated_at < %s) OR (status = 'verified' AND verified_at < %s)",
				gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ),
				gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS )
			)
		);
	}

	public static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function email_hash( $email ) {
		return hash_hmac( 'sha256', strtolower( trim( (string) $email ) ), wp_salt( 'secure_auth' ) );
	}

	private static function resend_job_key( $token ) {
		return 'sauth_email_verification_resend_job_' . hash( 'sha256', (string) $token );
	}

	private static function table() {
		return class_exists( 'SAUTH_Activator' ) ? (string) SAUTH_Activator::table( 'email_verifications' ) : '';
	}
}
