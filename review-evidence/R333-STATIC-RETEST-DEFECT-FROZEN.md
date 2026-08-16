# R333 — Static Retest Defect Frozen Before Correction

Exact static CI head: `c38e2ff9df55ed31e048b23e9b82d0dd832fffac`.

Permanent static run `31848495918` reached the cumulative regression set with PHP lint and all tests through R308 passing, then R309 failed because `tests/r309-release-truth-regression.php` still reads the intentionally deleted historical workflow path `.github/workflows/file00-1.2.43-real-integration.yml`.

The failure is a stale permanent regression expectation created by the intentional R333 replacement of the current integration gate with `.github/workflows/file00-1.2.44-real-integration.yml`; it is not a newly demonstrated authentication runtime defect.

No correction to this static retest defect was started before this evidence was frozen.

## Required correction

- Audit all current permanent tests/workflows for references to the old current-integration path and obsolete File 00 exact pin.
- Update only current-truth assertions to `.github/workflows/file00-1.2.44-real-integration.yml`, File 00 `1.2.44`, and the exact corrected File 00 candidate; preserve explicitly historical R309/R329 evidence where it records past provenance rather than current truth.
- Require taxonomy-parity assertions and the supported passkey/logical-identity migration rehearsals in the current integration gate.
- Rerun PHP 7.4 and PHP 8.3 cumulative regression and exact-head release constitution from the corrected head.
