<?php
/** F02-X24-019: registered provider returning null MUST NOT mean safe.
 * Unconfigured local-only behavior remains permitted, by explicit policy.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
$GLOBALS['providerRegistered']=true;
function has_filter($hook){return $GLOBALS['providerRegistered'] ? 10 : false;}
function apply_filters($hook,$default,$prefix){return null;}
require __DIR__.'/../../includes/class-sauth-password-safety.php';
$password='f02-UniqueCorrectHorse-ProviderNull-2026';
$r=SAUTH_Password_Safety::check($password);
if(!is_wp_error($r)||$r->get_error_code()!=='sauth_password_breach_lookup_unavailable')exit("RED: configured breach provider null silently accepted password\n");
$GLOBALS['providerRegistered']=false;
if(true!==SAUTH_Password_Safety::check($password))exit("FAIL: no provider should preserve explicit local-only policy\n");
echo "PASS registered null-denying breach provider; unconfigured local-only policy (mock).\n";
