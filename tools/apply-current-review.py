#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R314: interactive Google flow start must not consume the half-open provider probe.
p='includes/class-sauth-provider-health.php'; s=read(p)
old="""\t/** Non-mutating projection for buttons/status surfaces. */
\tpublic static function available_for_ui( $provider ) {
\t\t$status = (string) self::state( $provider )['status'];
\t\treturn ! in_array( $status, array( 'open', 'half_open' ), true );
\t}
"""
new="""\t/**
\t * Non-mutating projection for an interactive provider flow. A cooled-down
\t * circuit is visible again in half-open state, but only the actual outbound
\t * HTTP request may claim the single probe lease via allow_request().
\t */
\tpublic static function available_for_ui( $provider ) {
\t\t$status = (string) self::state( $provider )['status'];
\t\treturn 'open' !== $status;
\t}
"""
s=one(s,old,new,'provider UI half-open liveness')
write(p,s)

p='includes/class-sa-google-oauth.php'; s=read(p)
old="""\t\tif ( SAUTH_Operations::safe_mode() || ! self::configured() || ! SAUTH_Provider_Health::allow_request( 'google' ) ) {
"""
new="""\t\tif ( SAUTH_Operations::safe_mode() || ! self::configured() || ! SAUTH_Provider_Health::available_for_ui( 'google' ) ) {
"""
s=one(s,old,new,'Google login/link start non-mutating health check')
write(p,s)

p='includes/class-sauth-google-registration.php'; s=read(p)
old="""\t\t\t&& SAUTH_Account_Contract::provider_available()
\t\t\t&& SAUTH_Provider_Health::allow_request( 'google' );
"""
new="""\t\t\t&& SAUTH_Account_Contract::provider_available()
\t\t\t&& SAUTH_Provider_Health::available_for_ui( 'google' );
"""
s=one(s,old,new,'Google registration start non-mutating health check')
write(p,s)

write('tests/r314-google-provider-health-regression.php',r'''<?php
$root=dirname(__DIR__); $h=file_get_contents($root.'/includes/class-sauth-provider-health.php'); $g=file_get_contents($root.'/includes/class-sa-google-oauth.php'); $r=file_get_contents($root.'/includes/class-sauth-google-registration.php'); $guard=file_get_contents($root.'/includes/class-sauth-provider-http-guard.php'); $fail=array();
$checks=array(
 array($h,"return 'open' !== $status",'half-open Google circuit cannot become visible for a recovery attempt'),
 array($g,"available_for_ui( 'google' )",'Google login/link start still consumes the half-open probe'),
 array($r,"available_for_ui( 'google' )",'Google registration start still consumes the half-open probe'),
 array($guard,"allow_request( $provider )",'actual provider HTTP boundary does not own probe acquisition'),
 array($guard,"record_success( $provider",'Google provider success is not recorded centrally'),
 array($guard,"record_failure( $provider",'Google provider failure is not recorded centrally')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($g,"allow_request( 'google' )"))$fail[]='Google OAuth start retains a direct mutating health gate';
if(false!==strpos($r,"&& SAUTH_Provider_Health::allow_request( 'google' )"))$fail[]='Google registration availability retains a direct mutating health gate';
if($fail){fwrite(STDERR,"R314 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R314 Google provider-health regression PASS ('.(count($checks)+2)." assertions).\n";
''')
print('R314 frozen ledger corrections applied')
