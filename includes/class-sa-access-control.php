<?php

defined( 'ABSPATH' ) || exit;

final class SA_Access_Control {
	public function hooks() {
		add_filter( 'option_comment_registration', '__return_true' );
		add_filter( 'login_url', array( $this, 'login_url' ), 10, 3 );
		add_filter( 'register_url', array( $this, 'register_url' ) );
		add_filter( 'logout_url', array( $this, 'logout_url' ), 10, 2 );
		add_action( 'pre_comment_on_post', array( $this, 'require_login_for_comment' ) );
	}

	public function login_url( $login_url, $redirect, $force_reauth ) {
		if ( is_admin() ) { return $login_url; }
		$pages = (array) get_option( 'sa_page_map', array() );
		if ( empty( $pages['login'] ) ) { return $login_url; }
		$url = get_permalink( absint( $pages['login'] ) );
		if ( $redirect ) { $url = add_query_arg( 'redirect_to', $redirect, $url ); }
		if ( $force_reauth ) { $url = add_query_arg( 'reauth', '1', $url ); }
		return $url;
	}

	public function register_url( $url ) {
		$pages = (array) get_option( 'sa_page_map', array() );
		return empty( $pages['signup'] ) ? $url : get_permalink( absint( $pages['signup'] ) );
	}

	public function logout_url( $url, $redirect ) {
		$action = wp_nonce_url( admin_url( 'admin-post.php?action=sa_logout' ), 'sa_logout' );
		return $redirect ? add_query_arg( 'redirect_to', $redirect, $action ) : $action;
	}

	public function require_login_for_comment() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}
	}
}
