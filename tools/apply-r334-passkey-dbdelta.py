#!/usr/bin/env python3
from pathlib import Path
import json
import re

root = Path('.')
old_version = '1.2.4'
new_version = '1.2.5'
old_branch = 'fix/file02-account-taxonomy-parity-1.2.4'
new_branch = 'fix/file02-passkey-dbdelta-migration-1.2.5'
version_re = re.compile(r'(?<![0-9])1\.2\.4(?![0-9])')

# 1. Runtime product correction: make passkey CREATE TABLE dbDelta-compatible.
p = Path('includes/class-sauth-passkeys.php')
s = p.read_text()
old_keys = "\t\t\tPRIMARY KEY  (id), UNIQUE KEY public_id (public_id), UNIQUE KEY credential_lookup_hash (credential_lookup_hash), KEY user_status (user_id,status), KEY revoked_at (revoked_at)\n"
new_keys = "\t\t\tPRIMARY KEY  (id),\n\t\t\tUNIQUE KEY public_id (public_id),\n\t\t\tUNIQUE KEY credential_lookup_hash (credential_lookup_hash),\n\t\t\tKEY user_status (user_id,status),\n\t\t\tKEY revoked_at (revoked_at)\n"
if old_keys in s:
    if s.count(old_keys) != 1:
        raise SystemExit('R334 passkey combined-index preimage count mismatch')
    p.write_text(s.replace(old_keys, new_keys, 1))
elif new_keys not in s:
    raise SystemExit('R334 passkey index state is neither old nor corrected')

# 2. Advance standalone current runtime identity references from 1.2.4 to 1.2.5.
# Historical evidence/changelog records are intentionally excluded.
current_top = [
    'sabri-authentication.php','README.md','STATUS.md','STAGING-ACCEPTANCE.md','CONTRACTS.md',
    'MIGRATION.md','RELEASE-MANIFEST.md','PLAN-TRACEABILITY.md','ARCHITECTURE.md','SBOM.spdx.json',
    'REVIEW-REPORT.md','ROLLBACK.md','BACKUP-RESTORE.md','THREAT-MODEL.md','PRIVACY-RETENTION.md','DATA-DICTIONARY.md'
]
for name in current_top:
    p = Path(name)
    if not p.exists():
        continue
    s = p.read_text()
    s = version_re.sub(new_version, s)
    s = s.replace(old_branch, new_branch)
    p.write_text(s)

# Current test/workflow truth must follow the current candidate. Exact File00 1.2.44 is protected by boundary regex.
for base in (Path('tests'), Path('.github/workflows')):
    for p in base.rglob('*'):
        if not p.is_file() or p.suffix not in {'.php','.yml','.yaml'}:
            continue
        s = p.read_text()
        s2 = version_re.sub(new_version, s).replace(old_branch, new_branch)
        if s2 != s:
            p.write_text(s2)

# 3. WordPress readme: current metadata/new history, retain 1.2.4 history.
p = Path('readme.txt')
s = p.read_text()
if 'Stable tag: 1.2.4' not in s:
    raise SystemExit('R334 readme stable-tag preimage missing')
s = s.replace('Stable tag: 1.2.4', 'Stable tag: 1.2.5', 1)
# Current prose only, before changelog.
parts = s.split('== Changelog ==', 1)
if len(parts) != 2:
    raise SystemExit('R334 readme changelog boundary missing')
parts[0] = version_re.sub(new_version, parts[0]).replace(old_branch, new_branch)
changelog = parts[1]
anchor = '\n\n= 1.2.4 =\n'
entry = """

= 1.2.5 =
* Corrects the real MariaDB/WebAuthn upgrade defect in the passkey table definition by emitting every dbDelta index definition on its own CREATE TABLE line.
* Preserves DB schema identity 1.2.1, passkey schema identity 1.0.1 and passkey assurance contract 1.0.0 because the intended physical schema is unchanged.
* Retains canonical File 00 account-taxonomy parity and the exact File 00 1.2.44 integration boundary. Repository success does not establish staging or live deployment.
"""
if anchor not in changelog:
    raise SystemExit('R334 readme 1.2.4 history anchor missing')
s = parts[0] + '== Changelog ==' + changelog.replace(anchor, entry + anchor, 1)
p.write_text(s)

# 4. Markdown changelog: prepend new current release, retain 1.2.4 provenance.
p = Path('CHANGELOG.md')
s = p.read_text()
anchor = '## 1.2.4 — Canonical Account Taxonomy Parity Candidate'
if anchor not in s:
    raise SystemExit('R334 CHANGELOG 1.2.4 anchor missing')
section = """## 1.2.5 — Passkey dbDelta Migration Compatibility Candidate

### Corrected

- Real WordPress 7.0 / MariaDB 11.4 upgrade rehearsal proved that the passkey CREATE TABLE statement placed all index definitions on one line, causing `dbDelta()` to misparse later `UNIQUE KEY` / `KEY` tokens into an invalid primary-key ALTER.
- Each passkey index definition is now emitted on its own SQL line, preserving the exact intended schema while making existing-table reconciliation dbDelta-compatible.
- Permanent R334 regression coverage rejects the former combined-key line and preserves the one-index-per-line invariant.

### Identity

- Runtime: `1.2.5`.
- File 02 DB schema remains `1.2.1`.
- Passkey schema remains `1.0.1`; passkey assurance contract remains `1.0.0`.
- Staging-Accepted, Live-Deployed and Operational remain unclaimed.

"""
p.write_text(s.replace(anchor, section + anchor, 1))

# 5. Release lock current truth; cross-file blocker stays until full exact integration is green.
p = Path('RELEASE-LOCK.json')
data = json.loads(p.read_text())
data['release_version'] = new_version
data['candidate_branch'] = new_branch
data['package_name'] = '02-sabri-authentication-1.2.5-SOURCE-CANDIDATE.zip'
data['manifest_name'] = '02-sabri-authentication-1.2.5-MANIFEST.json'
data['status']['coded'] = 'passkey_dbdelta_candidate_r334_corrected'
data['status']['packaged'] = 'not_claimed_from_review_branch'
data['status']['automated_qa'] = 'exact_head_pending_after_r334'
data['status']['staging_accepted'] = False
data['status']['live_deployed'] = False
data['status']['operational'] = False
data['review_line'] = 'R331-R334-corrective'
data['cross_file_blockers'] = [
    'Exact File 00 1.2.44 taxonomy/provider compatibility must remain blocked until the complete File 00 1.2.44 / File 02 1.2.5 WordPress-MariaDB integration sequence passes after the R334 passkey migration correction.'
]
p.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')

# 6. Permanent R334 regression.
Path('tests/r334-passkey-dbdelta-migration-regression.php').write_text(r'''<?php
$root = dirname( __DIR__ );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( false === strpos( $passkeys, 'PRIMARY KEY  (id), UNIQUE KEY public_id' ), 'combined dbDelta key-definition line remains' );
foreach ( array(
    "\n\t\t\tPRIMARY KEY  (id),\n",
    "\n\t\t\tUNIQUE KEY public_id (public_id),\n",
    "\n\t\t\tUNIQUE KEY credential_lookup_hash (credential_lookup_hash),\n",
    "\n\t\t\tKEY user_status (user_id,status),\n",
    "\n\t\t\tKEY revoked_at (revoked_at)\n",
) as $line ) { $req( false !== strpos( $passkeys, $line ), 'dbDelta-compatible passkey index line missing: ' . trim( $line ) ); }
$req( false !== strpos( $main, 'Version: 1.2.5' ) && false !== strpos( $main, "SAUTH_VERSION', '1.2.5" ), 'runtime identity not 1.2.5' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.2.1" ), 'DB identity changed during R334' );
$req( false !== strpos( $passkeys, "const SCHEMA_VERSION        = '1.0.1'" ), 'passkey schema identity changed during R334' );
$req( false !== strpos( $main, "SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0" ), 'passkey assurance contract changed during R334' );
$req( is_array( $lock ) && '1.2.5' === ( $lock['release_version'] ?? '' ), 'release lock runtime stale' );
$req( 'fix/file02-passkey-dbdelta-migration-1.2.5' === ( $lock['candidate_branch'] ?? '' ), 'release lock branch stale' );
$req( ! empty( $lock['cross_file_blockers'] ), 'cross-file integration blocker cleared before exact retest' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion falsely advanced' );
$req( false !== strpos( $integration, "FILE00_VERSION: '1.2.44'" ) && false !== strpos( $integration, "FILE02_VERSION: '1.2.5'" ), 'paired integration identities stale' );
$req( false !== strpos( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2' ), 'File 00 exact integration pin stale' );
$req( file_exists( $root . '/review-evidence/R334-REVIEW-FROZEN.md' ), 'R334 frozen review evidence missing' );
if ( $fail ) { fwrite( STDERR, "R334 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R334 passkey dbDelta migration regression PASS (18 assertions).' . PHP_EOL;
''')

# 7. Current integration workflow identity/upgrade postconditions follow 1.2.5.
p = Path('.github/workflows/file00-1.2.44-real-integration.yml')
s = p.read_text()
s = version_re.sub(new_version, s).replace(old_branch, new_branch)
p.write_text(s)

# 8. Temporary correction machinery must not survive the corrected tree.
for temp in (Path('.github/workflows/tmp-r334-passkey-dbdelta-apply.yml'), Path('tools/apply-r334-passkey-dbdelta.py')):
    if temp.exists():
        temp.unlink()
