#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R318 ledger 1: passive UI rendering must not consume Google circuit-breaker probes.
p='includes/class-sa-plugin.php'; s=read(p)
old="SAUTH_Provider_Health::allow_request( 'google' )"
count=s.count(old)
if count!=2: raise SystemExit(f'passive Google health gates: expected 2, found {count}')
s=s.replace(old,"SAUTH_Provider_Health::available_for_ui( 'google' )")
write(p,s)

# R318 ledger 2: add_query_arg owns query encoding; pre-encoding the canonical return URL double-encodes it.
p='includes/class-sauth-canonical-routes.php'; s=read(p)
old="rawurlencode( home_url( '/account/sessions/' ) )"
new="home_url( '/account/sessions/' )"
s=one(s,old,new,'canonical sessions return URL encoding')
write(p,s)

# R318 ledger 3: evidence-honest Google unavailability copy.
p='templates/google-account.php'; s=read(p)
old="Google sign-in is not enabled by the platform administrator."
new="Google sign-in and account linking are currently unavailable. This can occur while the provider is disabled, temporarily unhealthy, or authentication Safe Mode is active."
s=one(s,old,new,'Google unavailable copy')
write(p,s)

# R318 ledger 4: all visible text actions must meet the 44px target gate, not only buttons/checkboxes.
p='assets/css/authentication.css'; s=read(p)
old=".sa-text-link{display:block;margin-top:14px;color:var(--sa-primary-dark);text-align:center}"
new=".sa-form-row a,.sa-bottom-text a,.sa-text-link{display:inline-flex;align-items:center;min-height:44px;padding:8px 4px}.sa-text-link{display:flex;justify-content:center;margin-top:14px;color:var(--sa-primary-dark);text-align:center}"
s=one(s,old,new,'text action touch targets')
write(p,s)

write('tests/r318-route-ui-hardening-regression.php',r'''<?php
$root=dirname(__DIR__); $plugin=file_get_contents($root.'/includes/class-sa-plugin.php'); $routes=file_get_contents($root.'/includes/class-sauth-canonical-routes.php'); $google=file_get_contents($root.'/templates/google-account.php'); $css=file_get_contents($root.'/assets/css/authentication.css'); $fail=array();
$checks=array(
 array($plugin,"available_for_ui( 'google' )",'passive Google UI does not use non-mutating health projection'),
 array($routes,"home_url( '/account/sessions/' )",'canonical sessions return URL is missing'),
 array($google,'temporarily unhealthy','Google unavailable copy asserts only administrator-disabled state'),
 array($css,'.sa-form-row a,.sa-bottom-text a,.sa-text-link','text actions are not included in the touch-target gate'),
 array($css,'min-height:44px;padding:8px 4px','text action 44px target is absent')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($plugin,"SAUTH_Provider_Health::allow_request( 'google' )"))$fail[]='passive File 02 UI still consumes a Google provider probe';
if(false!==strpos($routes,"rawurlencode( home_url( '/account/sessions/' ) )"))$fail[]='canonical sessions return URL is pre-encoded before add_query_arg';
if($fail){fwrite(STDERR,"R318 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R318 route/UI hardening regression PASS ('.(count($checks)+2)." assertions).\n";
''')
print('R318 frozen ledger corrections applied')
