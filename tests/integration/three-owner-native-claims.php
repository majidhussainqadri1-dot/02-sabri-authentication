<?php
/** Disposable loopback QA only. Fixed read-only policy queries; no mutation or input dispatch. */
if ( __DIR__ !== '/tmp/wordpress' || getenv('SAUTH_DISPOSABLE_THREE_OWNER_QA') !== '1'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:8765' ) {
    http_response_code(404); exit;
}
require_once __DIR__.'/wp-load.php';
if ( wp_get_environment_type() !== 'staging' || DB_NAME !== 'wordpress'
    || DB_HOST !== '127.0.0.1:3306' || realpath(ABSPATH) !== '/tmp/wordpress'
    || get_option('siteurl') !== 'http://127.0.0.1:8765'
    || !class_exists('SPF_Authorization') || !class_exists('SAUTH_Session_Manager')
    || !class_exists('SMC_Authorization') ) {
    http_response_code(404); exit;
}
nocache_headers();
header('Content-Type: application/json; charset=utf-8');
$user_id = get_current_user_id();
$actor = $user_id === 0 ? 'guest' : ($user_id === 1 && smc_is_founder(1) ? 'founder' : 'other');
$session_present = $user_id > 0 && wp_get_session_token() !== '';
$cases = array(
    array('register_manifest',array('module_key'=>'file-01','owner_file'=>'01'),'manifest_registration'),
    array('register_contract',array('owner_module'=>'file-01','contract_key'=>'ci-contract'),'contract_registration'),
    array('acknowledge_contract',array('owner_module'=>'file-01','contract_key'=>'ci-contract'),'contract_acknowledgement'),
    array('map_route',array('owner_module'=>'file-01','route_key'=>'ci-route'),'route_mapping'),
    array('repair_owned_mapping',array('module_key'=>'file-01'),'safe_repair'),
    array('set_flag',array('owner_module'=>'file-01','flag_key'=>'ci-flag','environment'=>'staging'),'feature_flag'),
    array('system_check',array('object_id'=>'system-check'),'system_check'),
    array('arbitrary_unregistered_action',array('module_key'=>'file-01'),'arbitrary_unregistered_action')
);
$decisions = array();
foreach ($cases as $case) {
    $decisions[$case[0]] = SPF_Authorization::can($case[0],$case[1],array('purpose'=>$case[2])) === true;
}
echo wp_json_encode(array('actor'=>$actor,'session_present'=>$session_present,'decisions'=>$decisions));
