# File 02 Authentication Incident Runbook — 1.0.0

## Severity examples

- **Critical:** account takeover at scale, authentication bypass, raw secret/token exposure, private identity leakage.
- **High:** widespread sign-in/recovery failure, unauthorized session persistence, provider callback compromise.
- **Medium:** bounded provider degradation, delayed events, isolated route/session defect without exposure.

## Immediate actions

1. Name incident commander, security operator, communications owner and rollback owner.
2. Enable Safe Mode or disable the affected provider/action; preserve public reading.
3. Preserve exact source/package hashes, UTC timeline, redacted logs, trace IDs and System Check.
4. Revoke affected sessions/challenges and rotate secrets only when evidence supports it.
5. Do not delete evidence, expose private account data or instruct users to send passwords/OTP/recovery codes.

## Investigation

- Identify affected versions, routes, providers, subjects and time window.
- Check File 00 membership/suspension/step-up truth and File 02 outbox/provider circuits.
- Test replay, enumeration, IDOR, redirect, CSRF, concurrency and cache leakage hypotheses.
- Distinguish provider outage from local application failure.
- Minimize data access and record every privileged investigation action.

## Recovery

Apply reversible containment, then a reviewed correction. Run affected unit/integration/security suites, deterministic package parity and staging regression. Restore from backup only when integrity requires it. Do not reopen high-risk actions until the incident commander and Founder-designated approver accept the evidence.

## Notification and review

Notify users/regulators/providers only through approved legal/privacy procedures. Communications must be accurate about scope and uncertainty. Complete post-incident root cause, timeline, data impact, corrective actions, residual risk, owner and due dates. Permanent product changes require change-control ratification.
