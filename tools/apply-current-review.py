#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R316 ledger 1: authentication assurance must consume the hardened epoch-aware passkey runtime.
p='includes/class-sa-authentication-assurance.php'; s=read(p)
old="""\tpublic static function provider_available() {
\t\treturn class_exists( 'SA_Membership_Adapter' )
\t\t\t&& SA_Membership_Adapter::available()
\t\t\t&& class_exists( 'SAUTH_Passkeys' )
\t\t\t&& is_callable( array( 'SAUTH_Passkeys', 'file00_assurance' ) );
\t}
"""
new="""\tpublic static function provider_available() {
\t\treturn class_exists( 'SA_Membership_Adapter' )
\t\t\t&& SA_Membership_Adapter::available()
\t\t\t&& class_exists( 'SAUTH_Passkey_Runtime' )
\t\t\t&& is_callable( array( 'SAUTH_Passkey_Runtime', 'current_assurance' ) );
\t}
"""
s=one(s,old,new,'hardened passkey provider availability')
old="""\tprivate static function current_passkey_assurance( $user_id ) {
\t\tif ( ! self::provider_available() ) {
\t\t\treturn array();
\t\t}
\t\ttry {
\t\t\t$result = SAUTH_Passkeys::file00_assurance( array(), absint( $user_id ) );
\t\t\treturn is_array( $result ) ? $result : array();
\t\t} catch ( Throwable $error ) {
\t\t\treturn array();
\t\t}
\t}
"""
new="""\tprivate static function current_passkey_assurance( $user_id ) {
\t\tif ( ! self::provider_available() ) {
\t\t\treturn array();
\t\t}
\t\ttry {
\t\t\t$result = SAUTH_Passkey_Runtime::current_assurance( absint( $user_id ) );
\t\t\treturn is_array( $result ) ? $result : array();
\t\t} catch ( Throwable $error ) {
\t\t\treturn array();
\t\t}
\t}
"""
s=one(s,old,new,'epoch-aware passkey assurance consumer')
write(p,s)

# R316 ledger 2: session revocation postcondition must not treat a failed COUNT read as zero.
p='includes/class-sauth-session-manager.php'; s=read(p)
old="""\t\t$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . \" WHERE user_id=%d AND status='active'\", $user_id ) );
\t\treturn false !== $db_result && 0 === $remaining;
"""
new="""\t\t$remaining_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . \" WHERE user_id=%d AND status='active'\", $user_id ) );
\t\tif ( null === $remaining_raw || '' !== (string) $wpdb->last_error ) { return false; }
\t\treturn false !== $db_result && 0 === (int) $remaining_raw;
"""
s=one(s,old,new,'revoke-all material postcondition')
write(p,s)

write('tests/r316-session-assurance-hardening-regression.php',r'''<?php
$root=dirname(__DIR__); $s=file_get_contents($root.'/includes/class-sauth-session-manager.php'); $a=file_get_contents($root.'/includes/class-sa-authentication-assurance.php'); $p=file_get_contents($root.'/includes/class-sa-professional-reauthentication.php'); $ac=file_get_contents($root.'/includes/class-sa-access-control.php'); $sm=file_get_contents($root.'/includes/class-sauth-safe-mode-challenge-gate.php'); $fail=array();
$checks=array(
 array($a,"SAUTH_Passkey_Runtime', 'current_assurance",'CF-01 assurance still consumes legacy non-epoch-aware passkey projection'),
 array($a,'SAUTH_Passkey_Runtime::current_assurance','CF-01 assurance bypasses hardened passkey runtime'),
 array($s,'null === $remaining_raw','session revoke-all can accept a failed postcondition read as zero'),
 array($s,"session_registry_read_failed",'session authentication projection does not fail closed on registry DB error'),
 array($p,'password_binding','professional reauthentication is not password-state bound'),
 array($ac,'block_unmanaged_password_authentication','unmanaged password authentication remains exposed'),
 array($sm,'challenge_invalidated_by_safe_mode','pre-containment passkey challenges can survive Safe Mode')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($a,'SAUTH_Passkeys::file00_assurance'))$fail[]='active CF-01 assurance still calls legacy passkey assurance directly';
if($fail){fwrite(STDERR,"R316 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R316 session/assurance hardening regression PASS ('.(count($checks)+1)." assertions).\n";
''')
print('R316 frozen ledger corrections applied')
