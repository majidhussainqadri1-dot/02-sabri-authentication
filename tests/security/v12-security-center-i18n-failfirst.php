<?php
/**
 * File02 F02-NFR-010 / X24-007/012: security-center and collision wizard
 * must use localized UI phrases, scoped direction and escaped text.
 * Static gate only; not full screen-reader or browser acceptance.
 */
declare(strict_types=1);
$root=dirname(__DIR__,2);
$source=file_get_contents($root.'/includes/class-sauth-security-orchestrator.php');
$start=strpos($source,'public static function render_security_center()');
$end=strpos($source,'public static function cleanup()');
if($start===false||$end===false){fwrite(STDERR,"RED: security pages absent\n");exit(1);}
$view=substr($source,$start,$end-$start);
$required=[
 'Account Security',
 'Review recent authentication activity. Exact IP addresses and secrets are not displayed.',
 'Secure My Account',
 'This was not me',
 'Resolve Existing Account',
 'Verify and Continue Safely'
];
foreach($required as $phrase){
 $expected="esc_html__( '".$phrase."', 'sabri-authentication' )";
 if(strpos($view,$expected)===false){fwrite(STDERR,"RED: untranslated account security string ".$phrase."\n");exit(1);}
}
if(substr_count($view,"is_rtl() ? 'rtl' : 'ltr'")<2){fwrite(STDERR,"RED: RTL direction not explicit on both security views\n");exit(1);}
foreach(['ur','ar'] as $loc){
 $catalog=require $root.'/languages/sabri-authentication-'.$loc.'.l10n.php';
 foreach($required as $phrase)if(empty($catalog['messages'][$phrase])){fwrite(STDERR,"RED: locale ".$loc." lacks ".$phrase."\n");exit(1);}
}
echo "PASS File02 Security Center + Collision Wizard Urdu/Arabic, RTL, escaped labels (static).\n";
