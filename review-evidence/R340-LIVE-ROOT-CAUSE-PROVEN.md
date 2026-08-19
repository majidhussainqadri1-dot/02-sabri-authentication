# R340 — Live Passkey Assurance Cycle Root Cause Proven

## Evidence status

This record freezes the live evidence that justified the bounded File 02 1.3.3 repository correction. It is not a live-resolution claim.

## Repository baseline

- Repository: `majidhussainqadri1-dot/02-sabri-authentication`
- Baseline main HEAD: `58cc464ff4f62d7e83ff3ba0b9cb25ecd3140a13`
- Baseline runtime: `1.3.2`
- File 02 DB: `1.3.0`
- Passkey schema: `1.0.1`
- Passkey assurance contract: `1.0.0`

## Live symptom

After a successful passkey sign-in, the authenticated Google Account Security page rejected Google linking with the message that a passkey must first be verified in the current session.

## Live evidence

Read-only runtime diagnostics established that:

1. the passkey credential was active;
2. the current WordPress login/session existed;
3. a session-bound File 02 passkey assurance receipt was created successfully;
4. that receipt subsequently disappeared before Google Link consumed it; and
5. delete tracing identified `SAUTH_Passkey_Runtime::current_assurance()` in `includes/class-sauth-passkey-runtime.php` as the owning deletion path.

The live call trace crossed File 00 membership/capability evaluation and re-entered File 02 authentication assurance.

## Exact deployed key-source parity

The following live-deployed File 02 key files matched the GitHub 1.3.2 baseline by Git blob identity:

- `includes/class-sauth-passkeys.php` → `a65a86d7925314361e0641d6a7aca3f1f1e962db`
- `includes/class-sauth-session-manager.php` → `d812242b5041f0fd36b9f1db9529cbc6b7e38c64`
- `includes/class-sa-security.php` → `0a432e381895e2aafc6e5b6e50f442557b56a839`
- `sabri-authentication.php` → `2208168b0ea3c09afd808238feced5c845309774`

The deployed `class-sauth-passkey-runtime.php` source observed during the incident contained the same critical `current_assurance()` membership assertion/deletion block as repository HEAD `58cc464...`.

## Proven circular dependency

The failing architecture was:

```text
File 02 SAUTH_Passkey_Runtime::current_assurance()
  → File 00 membership assertion
    → File 00 membership/capability evaluation
      → File 00 authentication_assurance()
        → File 02 assurance filter
          → File 02 current_assurance() re-entry
```

The outer File 02 method had already validated the session-bound receipt. It then asked File 00 for authorization state. File 00 itself consumes File 02 authentication assurance during protected capability evaluation. That creates a re-entrant cycle. During the nested cycle the membership result cannot complete as the outer method expects, and File 02 deletes the otherwise valid assurance receipt.

## Contract contradiction

The File 02 authentication-assurance contract states that authentication assurance is evidence and does not authorize the consumer's native object/action. File 00 and native action owners retain authorization responsibility. Therefore `current_assurance()` must not call back into File 00 authorization to decide whether a cryptographically/session-valid File 02 authentication receipt exists.

## Excluded causes

The incident evidence excluded these as the root cause of the false Google fresh-passkey rejection:

- LiteSpeed/Memcached transient persistence failure;
- WordPress salt instability between requests;
- unexpected WordPress session-token rotation;
- client-fingerprint mismatch;
- normal five-minute assurance expiry;
- database/passkey schema migration failure; and
- stale key deployed source for the verified File 02 files.

## Bounded permanent correction

File 02 1.3.3 changes only the projection boundary:

- `SAUTH_Passkey_Runtime::current_assurance()` continues to require the current logged-in user;
- it continues to require the current WordPress session token;
- it continues to load the exact session-bound receipt;
- it continues to require `valid_session_receipt()` including TTL, fingerprint, assurance epoch and session binding;
- it no longer calls `SA_Membership_Adapter::membership_assertion()`; and
- it no longer deletes a valid File 02 receipt merely because an external authorization provider cannot complete during the assurance projection.

Authorization is not removed. Google linking independently checks current approved membership through `SA_Membership_Adapter::can_use_google()` before it checks fresh File 02 passkey assurance. File 00 continues to enforce its own membership/capability/revalidation rules.

## Permanent regression

`tests/r340-passkey-assurance-cycle-regression.php` guards that:

- `current_assurance()` remains session-receipt-bound;
- it does not re-enter File 00 membership authorization;
- it does not delete a valid receipt from the pure projection path;
- Google Link retains both membership authorization and fresh-passkey gates in the intended order; and
- the authentication-versus-authorization contract remains documented.

## Resolution boundary

**Root Cause: PROVEN.**

**Repository correction: IMPLEMENTED IN 1.3.3 CANDIDATE.**

**Live resolution: NOT CLAIMED.**

Required remaining sequence:

`test → exact-head CI → deterministic package → controlled deploy → exact deployed parity → fresh passkey → File 00 capability evaluation → Google Link start → Google callback → live verification → cleanup diagnostics/debug state → final parity confirmation`.
