#!/usr/bin/env python3
from pathlib import Path
import json
import re

old_version = '1.2.5'
new_version = '1.2.6'
old_branch = 'fix/file02-passkey-dbdelta-migration-1.2.5'
new_branch = 'fix/file02-passkey-index-reconciliation-1.2.6'
version_re = re.compile(r'(?<![0-9])1\.2\.5(?![0-9])')

def current_sync(text):
    return version_re.sub(new_version, text.replace(old_branch, new_branch))

p = Path('includes/class-sauth-passkeys.php')
s = p.read_text()
old = """\t\t$has_legacy_hash = in_array( 'credential_hash', $columns, true );
\t\t$has_legacy_cipher = in_array( 'credential_cipher', $columns, true );
\t\tif ( $has_legacy_hash && ! in_array( 'credential_lookup_hash', $columns, true ) ) {
"""
new = """\t\t$has_legacy_hash = in_array( 'credential_hash', $columns, true );
\t\t$has_legacy_cipher = in_array( 'credential_cipher', $columns, true );
\t\tif ( $has_legacy_hash && ! self::reconcile_legacy_credential_hash_index( $table ) ) { return false; }
\t\tif ( $has_legacy_hash && ! in_array( 'credential_lookup_hash', $columns, true ) ) {
"""
if old not in s: raise SystemExit('R335 prepare_legacy preimage missing')
s = s.replace(old, new, 1)
anchor = "\tprivate static function ensure_manager_page() {\n"
helper = r'''	/**
	 * MariaDB keeps an index name when CHANGE renames its indexed column. A
	 * legacy credential_lookup_hash index can therefore remain uniquely bound
	 * to credential_hash and collide with dbDelta's canonical index creation.
	 * Preserve legacy uniqueness while freeing the canonical key name.
	 */
	private static function reconcile_legacy_credential_hash_index( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { return false; }
		$indexes = array();
		foreach ( $rows as $row ) {
			$name = (string) ( $row['Key_name'] ?? '' );
			$seq  = absint( $row['Seq_in_index'] ?? 0 );
			if ( '' === $name || $seq < 1 ) { continue; }
			if ( ! isset( $indexes[ $name ] ) ) { $indexes[ $name ] = array( 'non_unique' => (int) ( $row['Non_unique'] ?? 1 ), 'columns' => array() ); }
			$indexes[ $name ]['columns'][ $seq ] = (string) ( $row['Column_name'] ?? '' );
		}
		foreach ( $indexes as &$index ) { ksort( $index['columns'] ); $index['columns'] = array_values( $index['columns'] ); }
		unset( $index );
		if ( ! isset( $indexes['credential_lookup_hash'] ) ) { return true; }
		$current = $indexes['credential_lookup_hash'];
		if ( 0 === (int) $current['non_unique'] && array( 'credential_lookup_hash' ) === $current['columns'] ) { return true; }
		if ( 0 !== (int) $current['non_unique'] || array( 'credential_hash' ) !== $current['columns'] ) { return false; }
		$legacy_unique_exists = false;
		foreach ( $indexes as $name => $index ) {
			if ( 'credential_lookup_hash' !== $name && 0 === (int) $index['non_unique'] && array( 'credential_hash' ) === $index['columns'] ) { $legacy_unique_exists = true; break; }
		}
		$legacy_name = 'credential_hash_legacy';
		if ( isset( $indexes[ $legacy_name ] ) && ( 0 !== (int) $indexes[ $legacy_name ]['non_unique'] || array( 'credential_hash' ) !== $indexes[ $legacy_name ]['columns'] ) ) { return false; }
		if ( $legacy_unique_exists ) {
			$sql = "ALTER TABLE `{$table}` DROP INDEX `credential_lookup_hash`"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "ALTER TABLE `{$table}` DROP INDEX `credential_lookup_hash`, ADD UNIQUE KEY `{$legacy_name}` (`credential_hash`)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( false === $wpdb->query( $sql ) ) { return false; }
		$verify = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $verify ) || '' !== (string) $wpdb->last_error ) { return false; }
		$canonical_name_free = true;
		$legacy_unique = false;
		foreach ( $verify as $row ) {
			if ( 'credential_lookup_hash' === (string) ( $row['Key_name'] ?? '' ) ) { $canonical_name_free = false; }
			if ( 'credential_hash' === (string) ( $row['Column_name'] ?? '' ) && 0 === (int) ( $row['Non_unique'] ?? 1 ) ) { $legacy_unique = true; }
		}
		return $canonical_name_free && $legacy_unique;
	}

'''
if anchor not in s: raise SystemExit('R335 helper insertion anchor missing')
s = s.replace(anchor, helper + anchor, 1)
p.write_text(s)

current_top = ['sabri-authentication.php','README.md','STATUS.md','STAGING-ACCEPTANCE.md','CONTRACTS.md','MIGRATION.md','RELEASE-MANIFEST.md','PLAN-TRACEABILITY.md','ARCHITECTURE.md','SBOM.spdx.json','REVIEW-REPORT.md','ROLLBACK.md','BACKUP-RESTORE.md','THREAT-MODEL.md','PRIVACY-RETENTION.md','DATA-DICTIONARY.md']
for name in current_top:
    p=Path(name)
    if p.exists(): p.write_text(current_sync(p.read_text()))
for base in (Path('tests'),Path('.github/workflows')):
    for p in base.rglob('*'):
        if p.is_file() and p.suffix in {'.php','.yml','.yaml'}: p.write_text(current_sync(p.read_text()))

p=Path('readme.txt'); s=p.read_text()
if 'Stable tag: 1.2.5' not in s: raise SystemExit('R335 readme stable-tag preimage missing')
s=s.replace('Stable tag: 1.2.5','Stable tag: 1.2.6',1); parts=s.split('== Changelog ==',1)
if len(parts)!=2: raise SystemExit('R335 readme changelog boundary missing')
parts[0]=current_sync(parts[0]); anchor='\n\n= 1.2.5 =\n'
entry="""

= 1.2.6 =
* Corrects the proven MariaDB legacy passkey index-name collision: a unique key named credential_lookup_hash can remain bound to renamed legacy column credential_hash.
* The migration now recognizes only that exact stale binding, preserves legacy uniqueness under a legacy key name, frees the canonical key name, and lets dbDelta create and verify the canonical unique credential_lookup_hash index.
* Preserves DB schema 1.2.1, passkey schema 1.0.1 and passkey assurance contract 1.0.0; staging/live/operational status remains unclaimed.
"""
if anchor not in parts[1]: raise SystemExit('R335 readme 1.2.5 history anchor missing')
p.write_text(parts[0]+'== Changelog =='+parts[1].replace(anchor,entry+anchor,1))

p=Path('CHANGELOG.md'); s=p.read_text(); anchor='## 1.2.5 — Passkey dbDelta Migration Compatibility Candidate'
if anchor not in s: raise SystemExit('R335 CHANGELOG 1.2.5 anchor missing')
section="""## 1.2.6 — Legacy Passkey Index Reconciliation Candidate

### Corrected

- MariaDB 11.4 proof established that renaming `credential_lookup_hash` to legacy `credential_hash` preserves the unique key name `credential_lookup_hash` while rebinding that key to the legacy column.
- Before dbDelta, File 02 now detects only that exact misbound unique-index state, fails closed on unexpected bindings, preserves legacy uniqueness under a legacy key name when necessary, and frees the canonical key name for the canonical column/index.
- The correction is idempotent and data-preserving; the intended physical schema is unchanged.

### Identity

- Runtime: `1.2.6`.
- File 02 DB schema: `1.2.1` unchanged.
- Passkey schema: `1.0.1` unchanged.
- Passkey assurance contract: `1.0.0` unchanged.
- Staging-Accepted, Live-Deployed and Operational remain unclaimed.

"""
p.write_text(s.replace(anchor,section+anchor,1))

p=Path('RELEASE-LOCK.json'); data=json.loads(p.read_text())
data['release_version']=new_version; data['candidate_branch']=new_branch; data['package_name']='02-sabri-authentication-1.2.6-SOURCE-CANDIDATE.zip'; data['manifest_name']='02-sabri-authentication-1.2.6-MANIFEST.json'; data['status']['coded']='passkey_index_reconciliation_candidate_r335_corrected'; data['status']['packaged']='not_claimed_from_review_branch'; data['status']['automated_qa']='exact_head_pending_after_r335'; data['status']['staging_accepted']=False; data['status']['live_deployed']=False; data['status']['operational']=False; data['review_line']='R331-R335-corrective'; data['cross_file_blockers']=['Exact File 00 1.2.44 / File 02 1.2.6 WordPress-MariaDB integration must pass the full deferred-bootstrap, taxonomy, legacy passkey-index/column upgrade and logical-identity migration sequence before cross-file release closure.']; p.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')

Path('tests/r335-passkey-index-reconciliation-regression.php').write_text(r'''<?php
$root = dirname( __DIR__ );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( false !== strpos( $passkeys, 'reconcile_legacy_credential_hash_index( $table )' ), 'legacy index reconciliation is not invoked before canonical column migration' );
$req( false !== strpos( $passkeys, "array( 'credential_hash' ) !== \$current['columns']" ), 'unexpected canonical-name index binding does not fail closed' );
$req( false !== strpos( $passkeys, 'credential_hash_legacy' ), 'legacy uniqueness preservation key is missing' );
$req( false !== strpos( $passkeys, 'DROP INDEX `credential_lookup_hash`, ADD UNIQUE KEY' ), 'stale canonical key name is not atomically replaced when legacy uniqueness needs preservation' );
$req( false !== strpos( $passkeys, '$canonical_name_free && $legacy_unique' ), 'postcondition does not verify freed canonical name and preserved legacy uniqueness' );
$req( false !== strpos( $main, 'Version: 1.2.6' ) && false !== strpos( $main, "SAUTH_VERSION', '1.2.6" ), 'runtime identity not 1.2.6' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.2.1" ), 'DB identity changed during R335' );
$req( false !== strpos( $passkeys, "const SCHEMA_VERSION        = '1.0.1'" ), 'passkey schema identity changed during R335' );
$req( false !== strpos( $main, "SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0" ), 'passkey assurance contract changed during R335' );
$req( is_array( $lock ) && '1.2.6' === ( $lock['release_version'] ?? '' ), 'release lock runtime stale' );
$req( 'fix/file02-passkey-index-reconciliation-1.2.6' === ( $lock['candidate_branch'] ?? '' ), 'release lock branch stale' );
$req( ! empty( $lock['cross_file_blockers'] ), 'cross-file blocker cleared before exact R335 integration retest' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion falsely advanced' );
$req( false !== strpos( $integration, "FILE00_VERSION: '1.2.44'" ) && false !== strpos( $integration, "FILE02_VERSION: '1.2.6'" ), 'paired integration identities stale' );
$req( false !== strpos( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2' ), 'File 00 exact integration pin stale' );
$req( file_exists( $root . '/review-evidence/R335-REVIEW-FROZEN.md' ) && file_exists( $root . '/review-evidence/R335-ROOT-CAUSE-PROVEN.md' ), 'R335 frozen/root-cause evidence missing' );
$req( ! file_exists( $root . '/.github/workflows/r335-passkey-index-diagnostic.yml' ), 'temporary R335 diagnostic workflow remains' );
$req( ! file_exists( $root . '/tools/apply-r335-passkey-index-reconciliation.py' ), 'temporary R335 applicator remains' );
if ( $fail ) { fwrite( STDERR, "R335 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R335 passkey index reconciliation regression PASS (18 assertions).' . PHP_EOL;
''')

p=Path('.github/workflows/file00-1.2.44-real-integration.yml'); p.write_text(current_sync(p.read_text()))
for temp in (Path('.github/workflows/r335-passkey-index-diagnostic.yml'),Path('.github/workflows/tmp-r335-passkey-index-reconciliation.yml'),Path('tools/apply-r335-passkey-index-reconciliation.py')):
    if temp.exists(): temp.unlink()
