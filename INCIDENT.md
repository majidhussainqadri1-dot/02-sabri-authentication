# File 02 Authentication Incident Runbook — 1.2.1

## Severity examples

- **Critical:** account takeover at scale, authentication bypass, raw secret/token exposure, private identity leakage.
- **High:** widespread sign-in/recovery failure, unauthorized session persistence, provider callback compromise.
- **Medium:** bounded provider degradation, delayed events, isolated route/session defect without exposure.

## Immediate actions

1. Freeze live reality before repository diagnosis: active File 02/plugin version, exact deployed package/files and checksum where possible, WordPress/PHP/runtime dependencies, File 02 database/schema version, relevant tables/columns/rows, `wp_options` migration/version/Safe-Mode state, active configuration and the contemporaneous runtime error/log. Live, staging and GitHub are separate realities.
2. Name incident commander, security operator, communications owner and rollback owner.
3. Enable Safe Mode or disable the affected provider/action; preserve public reading.
4. Preserve exact source/package hashes, UTC timeline, redacted logs, trace IDs and System Check.
5. Revoke affected sessions/challenges and rotate secrets only when evidence supports it.
6. Do not delete evidence, expose private account data or instruct users to send passwords/OTP/recovery codes.

## Investigation

- Follow the mandatory order: live symptom → live evidence → exact deployed version → DB/schema state → deployment parity → root cause → repository code. If GitHub and deployed code differ, stop ordinary debugging and perform a Deployment-Parity Audit.
- Identify affected versions, routes, providers, subjects and time window.
- Check File 00 membership/suspension/step-up truth and File 02 outbox/provider circuits.
- Test replay, enumeration, IDOR, redirect, CSRF, concurrency and cache leakage hypotheses.
- Distinguish provider outage from local application failure.
- Minimize data access and record every privileged investigation action.

## Recovery

Apply reversible containment, then a reviewed correction. Run affected unit/integration/security suites, deterministic package parity and staging regression. Restore from backup only when integrity requires it. Do not reopen high-risk actions until the incident commander and Founder-designated approver accept the evidence.

## Notification and review

Notify users/regulators/providers only through approved legal/privacy procedures. Communications must be accurate about scope and uncertainty. Complete post-incident root cause, timeline, data impact, corrective actions, residual risk, owner and due dates. Every live incident report must separately state Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status. Never call a defect resolved without deploy + live re-test + parity confirmation. If deployed source is unavailable, record exactly: “Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔” Permanent product changes require change-control ratification.
