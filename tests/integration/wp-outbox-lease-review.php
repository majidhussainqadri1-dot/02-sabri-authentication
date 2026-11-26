<?php
/** Isolated CI only. No real members, providers, sessions or notification dispatch. */
defined('ABSPATH') || exit;
$guard=defined('WP_CLI')&&WP_CLI&&wp_get_environment_type()==='staging'
 &&wp_parse_url((string)get_option('siteurl'),PHP_URL_HOST)==='example.test'
 &&getenv('SAUTH_DISPOSABLE_OUTBOX_QA')==='1'
 &&DB_NAME==='wordpress'&&DB_HOST==='127.0.0.1:3306'&&realpath(ABSPATH)==='/tmp/wordpress';
if(!$guard){throw new RuntimeException('Refusing non-disposable outbox fixture');}
global $wpdb;
$table=SAUTH_Activator::table('auth_outbox');
$now=current_time('mysql',true);
$old=gmdate('Y-m-d H:i:s',time()-SAUTH_Event_Outbox::DISPATCH_LEASE-60);
$future=gmdate('Y-m-d H:i:s',time()+3600);
$max=SAUTH_Event_Outbox::MAX_ATTEMPTS;
$ids=[];
function outbox_need($ok,$label){if(!$ok){throw new RuntimeException($label);}echo 'PASS '.$label.PHP_EOL;}
$make=static function($status,$attempts,$updated,$available) use($wpdb,$table,$now,&$ids){
 $row=['event_id'=>wp_generate_uuid4(),'event_name'=>'AccountAuthenticationFailed.v1','schema_version'=>'1.0.0','privacy_class'=>'restricted','actor_user_id'=>0,'subject_user_id'=>0,'trace_id'=>wp_generate_uuid4(),'payload_json'=>'{malformed','status'=>$status,'attempts'=>$attempts,'available_at'=>$available,'created_at'=>$now,'updated_at'=>$updated];
 if(1!==$wpdb->insert($table,$row)){throw new RuntimeException('fixture insert failed');}
 $id=(int)$wpdb->insert_id;$ids[]=$id;return $id;
};
$read=static function($id)use($wpdb,$table){return $wpdb->get_row($wpdb->prepare("SELECT status,attempts,last_error FROM {$table} WHERE id=%d",$id),ARRAY_A);};
try {
 $expired=$make('dispatching',$max,$old,$now);
 $stranded=$make('retry',$max,$old,$now);
 $fresh=$make('dispatching',$max,$now,$now);
 $retry=$make('dispatching',$max-2,$old,$now);
 $deferred=$make('retry',2,$now,$future);
 $current=$make('dispatching',4,$now,$now);
 SAUTH_Event_Outbox::dispatch_due();
 foreach([$expired,$stranded] as $id){$r=$read($id);outbox_need($r['status']==='dead_letter'&&(int)$r['attempts']===$max,'exhausted attempt terminal state');}
 outbox_need($read($fresh)['status']==='dispatching','fresh final lease retained');
 $r=$read($retry);outbox_need($r['status']==='retry'&&(int)$r['attempts']===$max-1,'expired nonfinal claim retried once');
 $dispatch=new ReflectionMethod('SAUTH_Event_Outbox','dispatch_row');$dispatch->setAccessible(true);
 $dispatch->invoke(null,['id'=>$deferred,'attempts'=>2]);
 $r=$read($deferred);outbox_need($r['status']==='retry'&&(int)$r['attempts']===2,'deferred row cannot be claimed early');
 $retryMethod=new ReflectionMethod('SAUTH_Event_Outbox','retry_or_dead_letter');$retryMethod->setAccessible(true);
 $retryMethod->invoke(null,$current,3,'stale_ci_worker');
 $r=$read($current);outbox_need($r['status']==='dispatching'&&(int)$r['attempts']===4,'stale worker cannot finalize current generation');
}finally{foreach($ids as $id){$wpdb->delete($table,['id'=>$id],['%d']);}}
echo "PASS real disposable MariaDB outbox lease and generation checks".PHP_EOL;
