<?php
/** F02-CEN-01 / NFR-002: exercise native page classification, not only strings. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Post {public $ID=9; public $post_content='';}
final class SAUTH_Canonical_Routes {
 const QUERY_VAR='sauth_canonical_route', SESSIONS='account_sessions', SAFETY='account_safety', SECURITY='account_security', RESOLVE='resolve_account', RELATED_ORIGIN='related_origin_webauthn';
}
$GLOBALS['route']='';$GLOBALS['post']=null;
function get_query_var($k){return $GLOBALS['route'];}
function is_singular($type){return $GLOBALS['post'] instanceof WP_Post;}
function get_queried_object(){return $GLOBALS['post'];}
function get_post_meta($id,$key,$single){return '';}
function has_shortcode($content,$name){return strpos($content,'['.$name.']')!==false;}
function must($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
require_once __DIR__.'/../includes/class-sa-access-control.php';
$access=new SA_Access_Control();
foreach(['account_sessions','account_safety','account_security','resolve_account'] as $route){
 $GLOBALS['route']=$route;
 must(SA_Access_Control::is_file02_page(),'canonical page '.$route);
 $robots=$access->robots(['index'=>true,'follow'=>true]);
 must(!empty($robots['noindex'])&&!empty($robots['nofollow'])&&!isset($robots['index'],$robots['follow']),'private robots '.$route);
}
$GLOBALS['route']='';$GLOBALS['post']=new WP_Post();
foreach(['sabri_auth_security_center','sabri_auth_collision_resolution'] as $shortcode){
 $GLOBALS['post']->post_content='['.$shortcode.']';
 must(SA_Access_Control::is_file02_page(),'embedded private shortcode '.$shortcode);
 must(!empty($access->robots([])['noindex']),'embedded private robots '.$shortcode);
}
$GLOBALS['post']->post_content='ordinary public page';
must(!SA_Access_Control::is_file02_page(),'ordinary page unaffected');
must($access->robots(['index'=>true])===['index'=>true],'ordinary robots unchanged');
echo "PASS 14 private route classification/robots checks (isolated WP mock).".PHP_EOL;
