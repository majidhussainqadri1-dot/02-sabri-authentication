#!/usr/bin/env python3
from pathlib import Path
import json

ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R319 ledger 1: add_query_arg owns encoding of login return destinations.
p='includes/class-sa-membership-adapter.php'; s=read(p)
s=one(s,"add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), $url )","add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), $url )",'membership login redirect encoding')
# R319 ledger 2: compatibility helper must consume hardened epoch-aware assurance.
old="""\t/** Historical helper now reflects current File 02 passkey assurance only. */
\tpublic static function two_factor_enabled( $user_id ) {
\t\t$user_id = absint( $user_id );
\t\tif ( ! $user_id || ! class_exists( 'SAUTH_Passkeys' ) || ! is_callable( array( 'SAUTH_Passkeys', 'file00_assurance' ) ) ) {
\t\t\treturn false;
\t\t}
\t\ttry {
\t\t\t$assurance = SAUTH_Passkeys::file00_assurance( array(), $user_id );
\t\t\treturn is_array( $assurance ) && 'file02' === ( $assurance['owner'] ?? '' ) && ! empty( $assurance['passkey_asserted'] );
\t\t} catch ( Throwable $error ) {
\t\t\treturn false;
\t\t}
\t}
"""
new="""\t/** Historical helper now reflects current hardened File 02 passkey assurance only. */
\tpublic static function two_factor_enabled( $user_id ) {
\t\t$user_id = absint( $user_id );
\t\tif ( ! $user_id || ! class_exists( 'SAUTH_Passkey_Runtime' ) || ! is_callable( array( 'SAUTH_Passkey_Runtime', 'current_assurance' ) ) ) {
\t\t\treturn false;
\t\t}
\t\ttry {
\t\t\t$assurance = SAUTH_Passkey_Runtime::current_assurance( $user_id );
\t\t\treturn is_array( $assurance ) && 'file02' === ( $assurance['owner'] ?? '' ) && ! empty( $assurance['passkey_asserted'] ) && (int) ( $assurance['level'] ?? 0 ) >= 3;
\t\t} catch ( Throwable $error ) {
\t\t\treturn false;
\t\t}
\t}
"""
s=one(s,old,new,'membership hardened assurance helper')
write(p,s)

# R319 ledger 3: release lock must identify the current review line without claiming staging/live/package completion.
p='RELEASE-LOCK.json'; data=json.loads(read(p))
data['candidate_branch']='review/file02-r311-r320-2026-08-14'
data['review_line']='R311-R320'
data['cross_file_blockers']=['File 00 canonical account taxonomy and smc.authentication-account 1.1.0 provider vocabulary require owner-side harmonization before release; File 02 does not perform a lossy remap.']
write(p,json.dumps(data,indent=2,ensure_ascii=False)+'\n')

# R319 ledger 4: status/README/manifest were stale to R301-R310.
p='README.md'; s=read(p)
s=s.replace('R309 freezes its current `main` at `c4ab298b3ba2b870d507d32b36b1b4afd2771621`','R319 re-verifies its current `main` at `c4ab298b3ba2b870d507d32b36b1b4afd2771621`')
s=s.replace('| Source coding | **Review candidate**; R301–R310 closure and exact-head CI are required |','| Source coding | **Review candidate**; R311–R320 corrective review is the current source line |')
s=s.replace('| Packaged | Exact-head deterministic CI gate |','| Packaged | Not claimed from this review branch; separate deterministic release gate |')
s=s.replace('| Automated QA | Exact-head CI gate, including real WordPress/File 00 integration |','| Automated QA | Review exact-head lint/regression gate only; real WordPress/File 00 integration remains separate |')
if 'R311–R320 corrective review' not in s: raise SystemExit('README review-line update failed')
write(p,s)

p='STATUS.md'; s=read(p)
s=s.replace('`review/file02-r301-r310-2026-08-14`','`review/file02-r311-r320-2026-08-14`')
s=s.replace('Repository `main` at R301 freeze','Repository `main` re-verified during R319')
s=s.replace('R301 began a fresh review line from that corrected source.','R301–R310 completed the prior corrective line; R311–R318 completed further sequential review/fix/retest rounds, and R319–R320 are the current closing review line.')
s=s.replace('subject to the current R301–R310 cycle','subject to the current R311–R320 cycle')
block='- Cross-file release blocker: File 00 canonical account taxonomy and its `smc.authentication-account 1.1.0` provider vocabulary still require owner-side harmonization; File 02 intentionally does not invent a lossy account-type remap.\n'
marker='## External owner and environment gates\n\n'
if marker not in s: raise SystemExit('STATUS external-gates marker missing')
s=s.replace(marker,marker+block,1)
write(p,s)

p='RELEASE-MANIFEST.md'; s=read(p)
s=s.replace('`review/file02-r301-r310-2026-08-14`','`review/file02-r311-r320-2026-08-14`')
s=s.replace('Current repository `main` at R301 freeze','Current repository `main` re-verified during R319')
s=s.replace('R309 integration freeze: File 00','R319 dependency re-verification: File 00')
add='- Cross-file release blocker: File 00 must harmonize its canonical account taxonomy with the `smc.authentication-account 1.1.0` provider vocabulary; File 02 deliberately performs no lossy remap.\n'
needle='- Advanced Trust projection: File 02 passkey assurance `1.0.0`, owner `file02`\n'
if needle not in s: raise SystemExit('manifest dependency anchor missing')
s=s.replace(needle,needle+add,1)
write(p,s)

# R319 ledger 5: migration guide contradicted fail-closed activation and omitted R315 legacy passkey-column reconciliation.
p='MIGRATION.md'; s=read(p)
old='- If passkey table creation fails, password/Google authentication can remain available where their own dependencies are healthy, but passkey operations remain disabled and the System Check reports the missing schema.'
new='- Passkey storage, canonical manager-page and cleanup-schedule postconditions are mandatory activation/guarded-repair postconditions. If they fail, File 02 fails closed, does not publish a successful version/schema marker, enters/retains containment as applicable, and must not claim password/Google authentication availability from that incomplete migration.'
s=one(s,old,new,'migration fail-closed passkey policy')
needle='- Existing WordPress salts can be rotated without changing the stable credential-ID lookup hash; encrypted exclusion/presentation copies fail closed if key material changes unexpectedly.\n'
addition='- Legacy File 02 passkey rows using `credential_hash` / `credential_cipher` are reconciled non-destructively into canonical `credential_lookup_hash` / `credential_id_ciphertext` columns before schema completion is accepted; incomplete copies fail the migration postcondition.\n'
if needle not in s: raise SystemExit('migration passkey reconciliation anchor missing')
s=s.replace(needle,needle+addition,1)
write(p,s)

p='DATA-DICTIONARY.md'; s=read(p)
old='Version 1.2.0 adds `wp_sauth_passkeys` without rewriting File 00 data. Legacy tables are not deleted automatically and remain rollback evidence until a separately approved purge.'
new='Version 1.2.0 adds `wp_sauth_passkeys` without rewriting File 00 data. Current review hardening also reconciles legacy File 02 passkey columns `credential_hash` / `credential_cipher` into canonical `credential_lookup_hash` / `credential_id_ciphertext` before passkey schema readiness can succeed. Legacy tables and source columns are not destructively purged automatically and remain rollback evidence until a separately approved purge.'
s=one(s,old,new,'data dictionary passkey migration truth')
write(p,s)

p='CONTRACTS.md'; s=read(p)
old='Consumer: File 00 Advanced Trust. Producer: `SAUTH_Passkeys::file00_assurance()`.'
new='Consumer: File 00 Advanced Trust. Public compatibility/filter projection: `SAUTH_Passkeys::file00_assurance()`. Current File 02 assurance consumers use the hardened epoch-aware `SAUTH_Passkey_Runtime::current_assurance()` projection so credential changes invalidate stale receipts.'
s=one(s,old,new,'contract hardened passkey projection distinction')
write(p,s)

write('tests/r319-release-contract-truth-regression.php',r'''<?php
$root=dirname(__DIR__); $adapter=file_get_contents($root.'/includes/class-sa-membership-adapter.php'); $lock=file_get_contents($root.'/RELEASE-LOCK.json'); $status=file_get_contents($root.'/STATUS.md'); $readme=file_get_contents($root.'/README.md'); $manifest=file_get_contents($root.'/RELEASE-MANIFEST.md'); $migration=file_get_contents($root.'/MIGRATION.md'); $dict=file_get_contents($root.'/DATA-DICTIONARY.md'); $contracts=file_get_contents($root.'/CONTRACTS.md'); $fail=array();
$checks=array(
 array($adapter,'SAUTH_Passkey_Runtime::current_assurance','membership compatibility helper bypasses hardened passkey assurance'),
 array($adapter,"add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect )",'membership login URL still pre-encodes redirect destination'),
 array($lock,'review/file02-r311-r320-2026-08-14','release lock names stale review line'),
 array($lock,'cross_file_blockers','release lock hides cross-file account-taxonomy blocker'),
 array($status,'R311–R320','status document names stale review line'),
 array($readme,'R311–R320 corrective review','README names stale review line'),
 array($manifest,'review/file02-r311-r320-2026-08-14','release manifest names stale review line'),
 array($migration,'mandatory activation/guarded-repair postconditions','migration guide still claims auth can remain available after required passkey migration failure'),
 array($migration,'credential_lookup_hash','migration guide omits canonical passkey-column reconciliation'),
 array($dict,'credential_id_ciphertext','data dictionary omits canonical passkey-column reconciliation'),
 array($contracts,'SAUTH_Passkey_Runtime::current_assurance','contract register does not distinguish hardened current assurance runtime')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($adapter,'rawurlencode( SA_Security::safe_redirect( $redirect )'))$fail[]='membership login redirect remains pre-encoded';
if(false!==strpos($adapter,'SAUTH_Passkeys::file00_assurance'))$fail[]='membership compatibility helper still calls legacy assurance directly';
if(false!==strpos($migration,'password/Google authentication can remain available'))$fail[]='migration guide retains fail-open passkey-failure claim';
if($fail){fwrite(STDERR,"R319 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R319 release/contract truth regression PASS ('.(count($checks)+3)." assertions).\n";
''')
print('R319 frozen ledger corrections applied')
