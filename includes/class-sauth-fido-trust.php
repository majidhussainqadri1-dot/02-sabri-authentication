<?php

defined( 'ABSPATH' ) || exit;

/** Adapter-ready FIDO authenticator trust metadata. */
final class SAUTH_FIDO_Trust {
	const CONTRACT_VERSION = '1.0.0';

	public static function assess( $aaguid, $attestation_format, array $context = array() ) {
		$aaguid = strtolower( preg_replace( '/[^0-9a-f]/i', '', (string) $aaguid ) );
		if ( 32 !== strlen( $aaguid ) ) {
			return array( 'status'=>'unknown','trust_level'=>'unverified','hardware_backed'=>false,'reason'=>'aaguid_invalid' );
		}
		/* attestation=none carries no verifiable device provenance. Never
		 * manufacture hardware-backed trust from an AAGUID alone. */
		if ( 'none' === strtolower( (string) $attestation_format ) ) {
			return array( 'status'=>'privacy_preserving','trust_level'=>'unverified','hardware_backed'=>false,'reason'=>'attestation_none' );
		}
		$metadata = apply_filters( 'sauth_fido_metadata_lookup_v1', null, $aaguid, $context );
		if ( ! is_array( $metadata ) || empty( $metadata['verified'] ) ) {
			return array( 'status'=>'unavailable','trust_level'=>'unverified','hardware_backed'=>false,'reason'=>'metadata_unverified' );
		}
		$status = sanitize_key( (string) ( $metadata['status'] ?? 'unknown' ) );
		$level  = sanitize_key( (string) ( $metadata['trust_level'] ?? 'standard' ) );
		$revoked = ! empty( $metadata['revoked'] ) || in_array( $status, array( 'revoked','compromised','user_verification_bypass' ), true );
		return array(
			'status'          => $revoked ? 'revoked' : $status,
			'trust_level'     => $revoked ? 'blocked' : $level,
			'hardware_backed' => !$revoked && ! empty( $metadata['hardware_backed'] ),
			'reason'          => $revoked ? 'metadata_revoked' : 'metadata_verified',
		);
	}
}
