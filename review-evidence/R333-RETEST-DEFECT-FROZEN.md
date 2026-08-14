# R333 — Retest Defect Frozen Before Correction

The first exact File 00 1.2.44 / File 02 1.2.4 MariaDB integration retest was run at File 02 head `1139076f1d8be2c21c4a13f9301cf972b4c849f7`, GitHub Actions run `31848369253`.

The exact paired-source checkout and immutable-version checks passed. The run then failed before WordPress installation because the inherited workflow used the nonexistent WP-CLI URL `https://raw.githubusercontent.com/wp-cli/builds-pages/gh-pages/phar/wp-cli.phar`, which returned HTTP 404.

This is an integration-test infrastructure defect, not evidence of a File 00/File 02 runtime incompatibility. The official WP-CLI installation source uses the `wp-cli/builds` repository path.

No correction to this retest defect was started before this evidence was frozen.

## Required correction

Replace only the obsolete `wp-cli/builds-pages` download path with the official `wp-cli/builds` path, then rerun the entire R333 WordPress/MariaDB integration from the beginning. Do not clear the cross-file blocker unless the full integration passes.
