<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 session-entry presentation and safe revocation controls.
 *
 * WordPress remains the credential-session implementation. This service never
 * exposes raw tokens and never treats a session as authorization for a native
 * domain action.
 */
final class SAUTH_Session_Manager {
	public static function init() {
		add_action( 'admin_post_sauth_revoke_other_sessions', array( __CLASS__, 'revoke_others' ) );
		add_action( 'admin_post_sauth_revoke_all_sessions', array( __CLASS__, 'revoke_all' ) );
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="sa-auth-shell"><section class="sa-auth-card"><h1>Account access required</h1><p>Sign in to review active sessions.</p><a class="sa-primary-button" href="' . esc_url( SA_Membership_Adapter::login_url( SA_Security::page_url( 'sessions' ) ) ) . '">Log In</a></section></div>';
		}

		$user_id = get_current_user_id();
		$current = self::current_projection( $user_id );
		$count   = self::session_count( $user_id );
		$others  = max( 0, $count - 1 );

		ob_start();
		?>
		<main class="sa-auth-shell">
			<section class="sa-auth-card" aria-labelledby="sauth-sessions-title">
				<span class="sa-kicker">Account security</span>
				<h1 id="sauth-sessions-title">Active Sessions</h1>
				<p class="sa-intro">Review the current session and revoke other sign-ins. Raw session tokens, full IP addresses and precise location are never displayed.</p>
				<div class="sa-session-summary" role="status">
					<p><strong>Total active sessions:</strong> <?php echo esc_html( (string) $count ); ?></p>
					<p><strong>Other sessions:</strong> <?php echo esc_html( (string) $others ); ?></p>
				</div>
				<article class="sa-session-card" aria-label="Current session">
					<h2>Current session</h2>
					<p><strong>Device:</strong> <?php echo esc_html( $current['device'] ); ?></p>
					<p><strong>Last activity:</strong> <?php echo esc_html( $current['last_activity'] ); ?></p>
					<p><strong>Network:</strong> <?php echo esc_html( $current['network'] ); ?></p>
				</article>
				<?php if ( $others > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sauth_revoke_other_sessions">
						<?php wp_nonce_field( 'sauth_revoke_other_sessions', 'sauth_nonce' ); ?>
						<button class="sa-secondary-button" type="submit">Revoke all other sessions</button>
					</form>
				<?php else : ?>
					<p>No other active sessions were found.</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sauth_revoke_all_sessions">
					<?php wp_nonce_field( 'sauth_revoke_all_sessions', 'sauth_nonce' ); ?>
					<button class="sa-text-link" type="submit">Sign out everywhere</button>
				</form>
			</section>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	public static function revoke_others() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sauth_revoke_other_sessions', 'sauth_nonce' );
		$user_id = get_current_user_id();
		$token   = wp_get_session_token();
		if ( '' === (string) $token || ! class_exists( 'WP_Session_Tokens' ) ) {
			wp_safe_redirect( SA_Security::message_url( 'sessions', 'error', 'The current session could not be verified.' ) );
			exit;
		}

		WP_Session_Tokens::get_instance( $user_id )->destroy_others( $token );
		SAUTH_Event_Outbox::emit(
			'AuthSessionRevoked.v1',
			$user_id,
			$user_id,
			array( 'scope' => 'other_sessions', 'reason' => 'user_request' ),
			'security'
		);
		wp_safe_redirect( SA_Security::message_url( 'sessions', 'success', 'All other sessions were revoked.' ) );
		exit;
	}

	public static function revoke_all() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sauth_revoke_all_sessions', 'sauth_nonce' );
		$user_id = get_current_user_id();
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
		SAUTH_Event_Outbox::emit(
			'AuthSessionRevoked.v1',
			$user_id,
			$user_id,
			array( 'scope' => 'all_sessions', 'reason' => 'user_request' ),
			'security'
		);
		wp_clear_auth_cookie();
		wp_safe_redirect( SA_Security::message_url( 'login', 'success', 'You have been signed out on all devices.' ) );
		exit;
	}

	private static function session_count( $user_id ) {
		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return 1;
		}
		$sessions = WP_Session_Tokens::get_instance( absint( $user_id ) )->get_all();
		return is_array( $sessions ) ? count( $sessions ) : 1;
	}

	private static function current_projection( $user_id ) {
		$projection = array(
			'device'        => 'Current browser',
			'last_activity' => current_time( 'mysql' ),
			'network'       => 'Private',
		);
		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return $projection;
		}

		$token = wp_get_session_token();
		$data  = '' !== (string) $token ? WP_Session_Tokens::get_instance( absint( $user_id ) )->get( $token ) : false;
		if ( ! is_array( $data ) ) {
			return $projection;
		}
		if ( ! empty( $data['ua'] ) ) {
			$projection['device'] = self::generalize_user_agent( (string) $data['ua'] );
		}
		if ( ! empty( $data['login'] ) ) {
			$projection['last_activity'] = wp_date( 'Y-m-d H:i', (int) $data['login'] );
		}
		if ( ! empty( $data['ip'] ) ) {
			$projection['network'] = self::generalize_ip( (string) $data['ip'] );
		}
		return $projection;
	}

	private static function generalize_user_agent( $user_agent ) {
		$user_agent = strtolower( sanitize_text_field( $user_agent ) );
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
}
