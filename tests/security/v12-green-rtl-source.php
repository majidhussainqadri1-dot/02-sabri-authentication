<?php
/* v12 F02-CEN-01 and NFR-005/010: source invariants, not browser acceptance. */
$root=dirname(__DIR__,2);
$css=file_get_contents($root.'/assets/css/authentication.css');
foreach(['--sa-primary:#087A4E','--sa-primary-dark:#065C3B','--sa-soft:#E7F5EE'] as $token){if(strpos($css,$token)===false){fwrite(STDERR, "FAIL canonical brand token: ".$token."\n");exit(1);}}
foreach(['login.php','signup.php'] as $file){
 $html=file_get_contents($root.'/templates/'.$file);
 if(strpos($html,"is_rtl() ? 'rtl' : 'ltr'")===false){fwrite(STDERR, "FAIL RTL shell ".$file."\n");exit(1);}
}
echo "PASS v12 canonical green token and RTL shell direction (static).\n";
