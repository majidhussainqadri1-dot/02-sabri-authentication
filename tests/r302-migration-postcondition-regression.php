<?php
$root = dirname( __DIR__ );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$fail = array();
$checks = array(
    'activation migration failure is contained' => "if ( ! self::repair() )",
    'upgrade migration failure enables Safe Mode' => "update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );",
    'repair proves storage before markers' => "! self::storage_ready()",
    'canonical version marker is read back' => "SAUTH_VERSION !== (string) get_option( 'sauth_version', '' )",
    'legacy copy failure is observed' => 'if ( false === $result )',
    'legacy migration marker waits for success' => 'if ( $ok )',
    'table postcondition exists' => "SHOW TABLES LIKE %s",
    'page postcondition exists' => '! self::exact_shortcode_page( $page, $spec[\'shortcode\'] )',
    'minimum File 00 version copy is current' => "Membership Core 1.2.43 or later",
);
foreach ( $checks as $label => $needle ) {
    if ( false === strpos( $activator, $needle ) ) { $fail[] = $label; }
}
if ( $fail ) { fwrite( STDERR, "R302 migration regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo "R302 migration postcondition regression PASS (" . count( $checks ) . " assertions).\n";
