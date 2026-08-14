# R335 — Passkey Legacy Index Reconciliation Correction and Retest Evidence

R335 review and root cause were frozen before product correction.

The corrected migration inspects actual index name/uniqueness/column bindings, recognizes only the proven MariaDB state in which unique key `credential_lookup_hash` is bound exactly to legacy column `credential_hash`, preserves legacy uniqueness while freeing the canonical key name, and fails closed for unexpected conflicts. Runtime advances to 1.2.6; DB remains 1.2.1, passkey schema 1.0.1 and passkey assurance contract 1.0.0.

Complete PHP lint and cumulative no-network source regression, including permanent R335 coverage, passed before this correction commit was created. The cross-file release blocker remains open until the complete exact WordPress/MariaDB integration sequence passes.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
