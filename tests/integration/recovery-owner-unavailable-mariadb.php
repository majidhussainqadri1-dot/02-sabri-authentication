<?php
/** WordPress/MariaDB disposable test: absent owner must not consume recovery case. */
if(!defined('ABSPATH')||!class_exists('SAUTH_Security_Orchestrator'))exit(2);
global $wpdb;
$hook='sauth_apply_recovery_change_v1';
if(false!==has_filter($hook)){fwrite(STDERR,"FAIL owner unexpectedly registered\n");exit(1);}
$t=SAUTH_Activator::table('recovery_changes');
$id=strtolower(wp_generate_uuid4());$secret=SA_Security::random_token(32);
$now=current_time('mysql',true);
try {
 $ok=$wpdb->insert($t,[
  'public_id'=>$id,'user_id'=>1,'change_kind'=>'email_change',
  'payload_ciphertext'=>SA_Security::encrypt('{}'),
  'owner_token_ciphertext'=>SA_Security::encrypt($secret),
  'fingerprint_hash'=>SA_Security::client_fingerprint(),'status'=>'pending',
  'apply_after'=>gmdate('Y-m-d H:i:s',time()-60),
  'created_at'=>$now,'updated_at'=>$now
 ],['%s','%d','%s','%s','%s','%s','%s','%s','%s','%s']);
 if(1!==(int)$ok)throw new RuntimeException('fixture storage');
 $r=SAUTH_Security_Orchestrator::apply_recovery_change($id,$secret);
 $status=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$t} WHERE public_id=%s",$id));
 if(!is_wp_error($r)||$r->get_error_code()!=='sauth_recovery_owner_unavailable'||$status!=='pending')throw new RuntimeException('missing owner consumed pending');
 $calls=0;$f=static function($default,$kind,$uid,$payload)use(&$calls){++$calls;return false;};
 add_filter($hook,$f,10,4);
 $r=SAUTH_Security_Orchestrator::apply_recovery_change($id,$secret);
 $status=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$t} WHERE public_id=%s",$id));
 if(!is_wp_error($r)||$status!=='processing'||$calls!==1)throw new RuntimeException('owner failure not retained');
 $r=SAUTH_Security_Orchestrator::apply_recovery_change($id,$secret);
 if(!is_wp_error($r)||$calls!==1)throw new RuntimeException('owner callback replayed');
 remove_filter($hook,$f,10);
 echo "PASS missing owner, uncertain owner and replay containment (real MariaDB).\n";
} catch(Throwable $e){fwrite(STDERR,"FAIL ".$e->getMessage()."\n");exit(1);}
finally{$wpdb->delete($t,['public_id'=>$id],['%s']);}
