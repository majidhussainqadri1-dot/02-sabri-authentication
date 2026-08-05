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
	const VERIFY_ACTION = 'sauth_verify_email';
	const RESEND_ACTION = 'sauth_resend_email_verification';
	const CLEANUP_HOOK  = 'sauth_email_verification_cleanup';
	const TOKEN_TTL     = 1800;
	const RESEND_DELAY  = 300;
	const MAX_ATTEMPTS  = 8;

	public static function init() {
		add_action( 'admin_post_nopriv_' . self::VERIFY_ACTION, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_' . self::VERIFY_ACTION, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_' . self::RESEND_ACTION, array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_' . self::RESEND_ACTION, array( __CLASS__, 'handle_resend' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );

		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Create or rotate a verification challenge and deliver it.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function issue( $user_id, $email, $force = false ) {
		global $wpdb;

		$user_id       = absint( $user_id );
		$email         = sanitize_email( (string) $email );
		$canonical_user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $canonical_user instanceof WP_User
			|| ! is_email( $email )
			|| ! hash_equals( strtolower( (string) $canonical_user->user_email ), strtolower( $email ) ) ) {
			return new WP_Error( 'sauth_email_subject_invalid', 'Email verification could not be prepared.' );
		}
		$email = sanitize_email( (string) $canonical_user->user_email );
		if ( ! SAUTH_Account_Contract::provider_available() ) {
			return new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );
		}

		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		if ( is_array( $row ) && 'verified' === (string) $row['status'] ) {
			return array( 'result' => 'verified', 'reason_code' => 'already_verified' );
		}
		if ( ! $force && is_array( $row ) && ! empty( $row['sent_at'] ) ) {
			$sent_at = strtotime( (string) $row['sent_at'] );
			if ( false !== $sent_at && $sent_at + self::RESEND_DELAY > time() ) {
				return new WP_Error( 'sauth_email_resend_throttled', 'Please wait before requesting another verification email.' );
			}
		}

		$token = SA_Security::random_token( 32 );
		if ( strlen( $token ) < 64 ) {
			return new WP_Error( 'sauth_email_token_unavailable', 'Email verification could not be prepared.' );
		}
		$token_hash = self::token_hash( $token );
		$email_hash = self::email_hash( $email );
		$now        = current_time( 'mysql', true );
		$expires    = gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_TTL );

		$stored = $wpdb->replace(
			$table,
			array(
				'user_id'     => $user_id,
				'email_hash'   => $email_hash,
				'token_hash'   => $token_hash,
				'status'       => 'pending',
				'attempts'     => 0,
				'sent_at'      => $now,
				'expires_at'   => $expires,
				'verified_at'  => null,
				'created_at'   => is_array( $row ) && ! empty( $row['created_at'] ) ? $row['created_at'] : $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $stored ) {
			return new WP_Error( 'sauth_email_challenge_store_failed', 'Email verification could not be prepared.' );
		}

		$link = add_query_arg(
			array(
				'uid'   => $user_id,
				'token' => $token,
			),
			SA_Security::page_url( 'email_verify', home_url( '/' ) )
		);
		$subject = 'Verify your Sabri account email';
		$message = "Verify your email address for the Sabri Social Homeopathy Platform.\n\n";
		$message .= "Open this secure link and confirm verification:\n{$link}\n\n";
		$message .= "This link expires in 30 minutes and can be used once. If you did not request this account, ignore this email.";
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$delivered = apply_filters( 'sauth_email_verification_delivery', null, $email, $subject, $message, $headers, $user_id );
		if ( null === $delivered ) {
			$delivered = wp_mail( $email, $subject, $message, $headers );
		}
		if ( true !== $delivered ) {
			$wpdb->update(
				$table,
				array( 'status' => 'delivery_failed', 'updated_at' => current_time( 'mysql', true ) ),
				array( 'user_id' => $user_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			SA_Membership_Adapter::audit( 'email_verification_delivery_failed', $user_id );
			return new WP_Error( 'sauth_email_delivery_failed', 'The account was created, but the verification email could not be delivered. Retry from the verification page.' );
		}

		SA_Membership_Adapter::audit( 'email_verification_sent', $user_id );
		return array( 'result' => 'sent', 'reason_code' => 'verification_email_sent', 'expires_at' => gmdate( 'c', time() + self::TOKEN_TTL ) );
	}

	public static function render() {
		$uid   = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
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
					<input id="sauth-verification-email" type="email" name="email" autocomplete="email" required>
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

	public static function handle_resend() {
		check_admin_referer( 'sauth_resend_email_verification', 'sauth_nonce' );
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$key     = self::email_hash( $email );
		$blocked = SA_Security::rate_limited( 'email_verification_resend_ip', 8, HOUR_IN_SECONDS )
			|| SA_Security::rate_limited( 'email_verification_resend_account', 3, HOUR_IN_SECONDS, $key );
		if ( ! $blocked && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user instanceof WP_User ) {
				self::issue( $user->ID, (string) $user->user_email, false );
			}
		}
		wp_safe_redirect( SA_Security::message_url( 'email_verify', 'success', 'If an eligible account exists, a verification email will be sent. Please also check spam or junk folders.' ) );
		exit;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function verify( $user_id, $token ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$token   = trim( (string) $token );
		if ( ! $user_id || strlen( $token ) < 64 ) {
			return new WP_Error( 'sauth_email_token_invalid', 'This verification link is invalid or expired.' );
		}
		if ( SA_Security::rate_limited( 'email_verification_attempt', self::MAX_ATTEMPTS, HOUR_IN_SECONDS, (string) $user_id ) ) {
			return new WP_Error( 'sauth_email_attempts_limited', 'Too many verification attempts. Request a new link.' );
		}

		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'sauth_email_challenge_missing', 'This verification link is invalid or expired.' );
		}
		if ( 'verified' === (string) $row['status'] ) {
			return array( 'result' => 'verified', 'reason_code' => 'already_verified' );
		}
		if ( empty( $row['expires_at'] ) || strtotime( (string) $row['expires_at'] ) < time() ) {
			$wpdb->update( $table, array( 'status' => 'expired', 'updated_at' => current_time( 'mysql', true ) ), array( 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
			return new WP_Error( 'sauth_email_challenge_expired', 'This verification link has expired. Request a new link.' );
		}
		if ( absint( $row['attempts'] ) >= self::MAX_ATTEMPTS ) {
			return new WP_Error( 'sauth_email_attempts_exhausted', 'This verification link can no longer be used. Request a new link.' );
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET attempts = attempts + 1, updated_at = %s WHERE user_id = %d", current_time( 'mysql', true ), $user_id ) );
		if ( ! hash_equals( (string) $row['token_hash'], self::token_hash( $token ) ) ) {
			return new WP_Error( 'sauth_email_token_mismatch', 'This verification link is invalid or expired.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! hash_equals( (string) $row['email_hash'], self::email_hash( (string) $user->user_email ) ) ) {
			return new WP_Error( 'sauth_email_subject_changed', 'The account email changed. Request a new verification link.' );
		}

		$provider = SAUTH_Account_Contract::mark_email_verified(
			$user_id,
			$user->user_email,
			array( 'purpose' => 'email_verification', 'idempotency_key' => 'email-verify-' . $user_id . '-' . substr( (string) $row['token_hash'], 0, 16 ) )
		);
		if ( 'allow' !== ( $provider['result'] ?? '' ) ) {
			return new WP_Error( 'sauth_email_provider_rejected', 'Email verification is temporarily unavailable. The link has not been consumed; retry later.' );
		}

		$updated = $wpdb->update(
			$table,
			array( 'status' => 'verified', 'verified_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ), 'token_hash' => str_repeat( '0', 64 ) ),
			array( 'user_id' => $user_id, 'status' => 'pending' ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( 1 !== (int) $updated ) {
			$current_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE user_id = %d", $user_id ) );
			if ( 'verified' === $current_status ) {
				return array( 'result' => 'verified', 'reason_code' => 'already_verified' );
			}
			return new WP_Error( 'sauth_email_completion_store_failed', 'Email verification was accepted but local completion evidence could not be stored. Contact support with the time of this attempt.' );
		}

		SA_Security::clear_rate_limit( 'email_verification_attempt', (string) $user_id );
		SAUTH_Event_Outbox::emit( 'EmailVerified.v1', $user_id, $user_id, array( 'method' => 'signed_email_link' ), 'restricted' );
		SA_Membership_Adapter::audit( 'email_verified', $user_id );
		return array( 'result' => 'verified', 'reason_code' => 'email_verified' );
	}

	public static function cleanup() {
		global $wpdb;
		$table = self::table();
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

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_email_verifications';
	}
}
