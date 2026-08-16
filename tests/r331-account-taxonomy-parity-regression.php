<?php
$root = dirname( __DIR__ );
$registration = file_get_contents( $root . '/includes/class-sa-registration.php' );
$fail = array();
$matched = preg_match( '/public static function account_types\(\) \{(.*?)\n\t\}/s', $registration, $m );
$block = $matched ? $m[1] : '';
if ( ! $matched ) { $fail[] = 'account_types method block unavailable'; }
$canonical = array( 'member', 'patient', 'student', 'doctor', 'teacher', 'researcher', 'pharmacy', 'clinic', 'publisher' );
foreach ( $canonical as $type ) { if ( false === strpos( $block, "'" . $type . "'" ) ) { $fail[] = 'missing canonical account type: ' . $type; } }
foreach ( array( 'clinic_staff', 'institution_representative' ) as $legacy ) { if ( false !== strpos( $block, "'" . $legacy . "'" ) ) { $fail[] = 'legacy provider-only alias remains in account choices: ' . $legacy; } }
if ( false === strpos( $registration, 'File 00 canonical account taxonomy' ) ) { $fail[] = 'canonical ownership comment missing'; }
if ( $fail ) { fwrite( STDERR, "R331 taxonomy regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R331 account taxonomy parity regression PASS (13 assertions).' . PHP_EOL;
