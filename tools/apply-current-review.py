#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

p='includes/class-sauth-email-verification.php'; s=read(p)
old="""\t\tif ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
\t\t\treturn new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );
\t\t}
\t\t$table = self::table();
"""
new="""\t\tif ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
\t\t\treturn new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );
\t\t}
\t\tif ( ! SAUTH_Provider_Health::allow_request( 'email' ) ) {
\t\t\treturn new WP_Error( 'sauth_email_delivery_circuit_open', 'Email delivery is temporarily paused. Retry later.' );
\t\t}
\t\t$table = self::table();
"""
s=one(s,old,new,'email verification delivery circuit')
old="""\t\tif ( ! $force && is_array( $row ) && ! empty( $row['sent_at'] ) ) {
\t\t\t$sent_at = strtotime( (string) $row['sent_at'] );
"""
new="""\t\tif ( ! $force && is_array( $row ) && 'pending' === (string) ( $row['status'] ?? '' ) && ! empty( $row['sent_at'] ) ) {
\t\t\t$sent_at = strtotime( (string) $row['sent_at'] );
"""
s=one(s,old,new,'delivery failed retry throttle')
write(p,s)

p='includes/class-sa-registration.php'; s=read(p)
old="""\t\tif ( SAUTH_Operations::safe_mode() || ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) { return; }
\t\tif ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }
"""
new="""\t\tif ( SAUTH_Operations::safe_mode() || ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) { return; }
\t\tif ( ! SAUTH_Provider_Health::allow_request( 'email' ) ) { return; }
\t\tif ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }
"""
s=one(s,old,new,'password recovery email circuit')
write(p,s)

write('tests/r313-registration-recovery-regression.php',r'''<?php
$root=dirname(__DIR__); $e=file_get_contents($root.'/includes/class-sauth-email-verification.php'); $r=file_get_contents($root.'/includes/class-sa-registration.php'); $fail=array();
$checks=array(
 array($e,"allow_request( 'email' )",'verification delivery ignores email provider circuit'),
 array($e,'sauth_email_delivery_circuit_open','verification delivery circuit has no explicit failure'),
 array($e,'\'pending\' === (string) ( $row[\'status\'] ?? \'\' )','delivery_failed verification cannot be retried immediately'),
 array($r,"allow_request( 'email' )",'password recovery worker ignores email provider circuit')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if($fail){fwrite(STDERR,"R313 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R313 registration/recovery regression PASS ('.count($checks)." assertions).\n";
''')
print('R313 frozen ledger corrections applied')
