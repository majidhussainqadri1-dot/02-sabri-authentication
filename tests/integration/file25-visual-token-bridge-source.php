<?php
/**
 * F02-NFR-005 / CEN-01: File25 exact-HASH design-system integration.
 * Static CSS proof only, not a screenshot, WCAG or dark-mode browser claim.
 */
$root=dirname(__DIR__,2);
$f25=file_get_contents($root.'/../file25/assets/css/design-system.css');
$f02=file_get_contents($root.'/assets/css/authentication.css');
function cssproof($ok,$why){if(!$ok){fwrite(STDERR,'RED: '.$why."\n");exit(1);}}
foreach([
 '--sabri-visual-primary: #087A4E;',
 '--sabri-visual-primary-strong: #065C3B;',
 '--sabri-visual-primary-soft: #E7F5EE;',
 'body.sabri-visual-system'
] as $token)cssproof(strpos($f25,$token)!==false,'File25 exact CSS lacks token '.$token);
foreach([
 'body.sabri-visual-system .sa-auth-shell',
 '--sa-primary:var(--sabri-visual-primary',
 '--sa-primary-dark:var(--sabri-visual-primary-strong',
 '--sa-soft:var(--sabri-visual-primary-soft',
 'background:var(--sabri-visual-surface',
 'outline-color:var(--sabri-visual-focus',
 '@media (forced-colors: active)'
] as $token)cssproof(strpos($f02,$token)!==false,'File02 lacks scoped token bridge '.$token);
cssproof(strpos($f02,'--sa-primary:#087A4E')!==false,'File02 fallback green broken');
echo "PASS pinned File25 exact tokens, scoped File02 bridge, visual focus and forced-color support (source).\n";
