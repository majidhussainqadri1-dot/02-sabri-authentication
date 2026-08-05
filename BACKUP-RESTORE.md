# File 02 Backup and Restore Protocol — 1.0.0

## Backup set

Before migration, provider change or production deployment preserve:

- WordPress database, including all File 02 tables and relevant options/user metadata;
- exact File 02 and compatible File 00 packages, source heads and SHA-256 manifests;
- WordPress configuration and the approved encryption-key material through the secure infrastructure process;
- provider configuration identifiers, excluding raw secrets from ordinary documentation;
- route/page mappings and System Check output.

## Security rules

Backups are encrypted, access-controlled, retention-bounded and stored separately from the live host. Authentication tokens and provider secrets must not be copied into ordinary logs or support bundles. Backup access requires named least-privilege operators and audit.

## Restore proof

A backup is not accepted until restored into an isolated, access-controlled staging environment. Verify:

1. database integrity and File 02 tables;
2. decryption of the Google Client Secret using the restored key configuration;
3. managed routes and noindex/no-store headers;
4. File 00 contract availability;
5. login, recovery, email-verification, risk challenge and session revocation journeys;
6. outbox retry/dead-letter state;
7. privacy export/erasure and scheduled cleanup;
8. no live-provider message is accidentally sent from the restore environment.

## Recovery objectives

Release operators must record target RPO/RTO, actual backup time, restore start/end, errors and approver. Any failure to decrypt required configuration or reconcile account/session state blocks release.

## Post-restore

Rotate environment-specific secrets where exposure is suspected, clear staging cookies/tokens, rebuild caches, run System Check and retain a signed restore receipt linked to the exact release candidate.
