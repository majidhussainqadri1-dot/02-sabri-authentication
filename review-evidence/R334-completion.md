# R334 — Passkey dbDelta Migration Correction and Retest Evidence

R334 was frozen before product correction in `review-evidence/R334-REVIEW-FROZEN.md`.

The corrected source puts every passkey index definition on its own CREATE TABLE line for WordPress dbDelta compatibility. Runtime identity advances to 1.2.5 while DB remains 1.2.1, passkey schema remains 1.0.1 and passkey assurance contract remains 1.0.0.

Complete PHP lint and the cumulative no-network source regression set, including the permanent R334 passkey-dbDelta regression, passed before this correction commit was created.

The cross-file File 00 1.2.44 release blocker remains open until the complete exact WordPress/MariaDB integration chain passes on the corrected 1.2.5 source candidate.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
