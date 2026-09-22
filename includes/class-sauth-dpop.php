<?php

defined( 'ABSPATH' ) || exit;

/** Adapter-ready OAuth DPoP proof validation. Signature verification is delegated to an approved crypto adapter and fails closed when absent. */
final class SAUTH_DPoP {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_AGE = 300;

	public static function validate( $jwt, $method, $url, $access_token = '' ) {
		$jwt = trim( (string) $jwt );
		$parts = explode( '.', $jwt );
		if ( 3 !== count( $parts ) || strlen( $jwt ) > 32768 ) { return new WP_Error( 'sauth_dpop_malformed', 'DPoP proof is malformed.' ); }
		$header = self::json_part( $parts[0] );
		$claims = self::json_part( $parts[1] );
		if ( ! is_array( $header ) || ! is_array( $claims ) ) { return new WP_Error( 'sauth_dpop_malformed', 'DPoP proof is malformed.' ); }
		$typ = strtolower( (string) ( $header['typ'] ?? '' ) );
		$alg = (string) ( $header['alg'] ?? '' );
		$jwk = isset( $header['jwk'] ) && is_array( $header['jwk'] ) ? $header['jwk'] : array();
		if ( 'dpop+jwt' !== $typ || ! in_array( $alg, array( 'ES256','RS256' ), true ) || empty( $jwk ) || isset( $jwk['d'] ) ) {
			return new WP_Error( 'sauth_dpop_header_invalid', 'DPoP header is invalid.' );
		}
		$htm = strtoupper( (string) ( $claims['htm'] ?? '' ) );
		$htu = self::canonical_htu( (string) ( $claims['htu'] ?? '' ) );
		$expected_htu = self::canonical_htu( (string) $url );
		$iat = absint( $claims['iat'] ?? 0 );
		$jti = (string) ( $claims['jti'] ?? '' );
		if ( $htm !== strtoupper( (string) $method ) || '' === $htu || ! hash_equals( $expected_htu, $htu ) || ! $iat || abs( time() - $iat ) > self::MAX_AGE || ! preg_match( '/^[A-Za-z0-9._~-]{16,200}$/', $jti ) ) {
			return new WP_Error( 'sauth_dpop_claims_invalid', 'DPoP claims are invalid or stale.' );
		}
		if ( '' !== (string) $access_token ) {
			$ath = (string) ( $claims['ath'] ?? '' );
			$expected = self::b64url( hash( 'sha256', (string) $access_token, true ) );
			if ( '' === $ath || ! hash_equals( $expected, $ath ) ) { return new WP_Error( 'sauth_dpop_ath_invalid', 'DPoP access-token hash mismatch.' ); }
		}
		$thumbprint = self::jwk_thumbprint( $jwk );
		if ( '' === $thumbprint ) { return new WP_Error( 'sauth_dpop_jwk_invalid', 'DPoP public JWK is invalid.' ); }
		$replay_key = 'sauth_dpop_' . hash( 'sha256', $thumbprint . '|' . $jti );
		if ( ! add_option( $replay_key, time(), '', false ) ) { return new WP_Error( 'sauth_dpop_replay', 'DPoP proof replayed.' ); }
		$verified = true === apply_filters( 'sauth_dpop_verify_signature_v1', false, $parts[0] . '.' . $parts[1], $parts[2], $jwk, $alg );
		if ( ! $verified ) { delete_option( $replay_key ); return new WP_Error( 'sauth_dpop_signature_unverified', 'DPoP signature verifier unavailable or rejected the proof.' ); }
		return array( 'result'=>'allow','jkt'=>$thumbprint,'jti'=>$jti,'iat'=>$iat,'alg'=>$alg );
	}

	public static function jwk_thumbprint( array $jwk ) {
		$kty = (string) ( $jwk['kty'] ?? '' );
		if ( 'EC' === $kty && 'P-256' === (string) ( $jwk['crv'] ?? '' ) && ! empty( $jwk['x'] ) && ! empty( $jwk['y'] ) ) {
			$data = array( 'crv'=>'P-256','kty'=>'EC','x'=>(string)$jwk['x'],'y'=>(string)$jwk['y'] );
		} elseif ( 'RSA' === $kty && ! empty( $jwk['e'] ) && ! empty( $jwk['n'] ) ) {
			$data = array( 'e'=>(string)$jwk['e'],'kty'=>'RSA','n'=>(string)$jwk['n'] );
		} else { return ''; }
		return self::b64url( hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_SLASHES ), true ) );
	}

	private static function canonical_htu( $url ) {
		$p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['scheme'] ) || empty( $p['host'] ) ) { return ''; }
		$scheme = strtolower( (string) $p['scheme'] );
		if ( ! in_array( $scheme, array( 'https','http' ), true ) ) { return ''; }
		$port = isset( $p['port'] ) ? ':' . absint( $p['port'] ) : '';
		$path = isset( $p['path'] ) && '' !== $p['path'] ? $p['path'] : '/';
		return $scheme . '://' . strtolower( (string) $p['host'] ) . $port . $path;
	}

	private static function json_part( $part ) {
		$raw = self::b64decode( $part );
		$data = false === $raw ? null : json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}
	private static function b64decode( $v ) { $v=strtr((string)$v,'-_','+/'); $v.=str_repeat('=',(4-strlen($v)%4)%4); return base64_decode($v,true); }
	private static function b64url( $v ) { return rtrim( strtr( base64_encode( (string) $v ), '+/', '-_' ), '=' ); }
}
