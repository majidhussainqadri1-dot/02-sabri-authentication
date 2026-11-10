<?php
/**
 * File02 v12 48-ID release register: completeness and no false acceptance.
 * Source gating only; this test does not execute 48 end-to-end flows.
 */
declare(strict_types=1);
$p=__DIR__.'/../../review-evidence/F02-V12-2026-11-10-48-ACCEPTANCE-REGISTER.json';
$data=json_decode((string)file_get_contents($p),true);
function check($valid,$message){if(!$valid){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}}
check(is_array($data)&&($data['summary']['requirements']??0)===48,'missing 48 plan row count');
$ids=array();$group=['FR'=>0,'NFR'=>0,'CEN'=>0,'X24'=>0];
foreach(($data['requirements']??[]) as $row){
 check(is_array($row)&&!empty($row['id'])&&isset($group[$row['class']??'']),'invalid row');
 $id=$row['id'];check(!isset($ids[$id]),'duplicate '.$id);
 $ids[$id]=true;$group[$row['class']]++;
 check(($row['acceptance']??'')==='not_verified','unproven acceptance marked complete '.$id);
 check(in_array($row['source']??'', ['implemented_candidate','partial_acceptance_controls','cross_file_contract_candidate','adapter_or_partial_candidate'],true),'invalid source evidence '.$id);
}
check(count($ids)===48&&$group===['FR'=>12,'NFR'=>10,'CEN'=>2,'X24'=>24],'wrong class counts');
for($i=1;$i<=12;$i++)check(isset($ids['F02-FR-'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]),'missing FR '.$i);
for($i=1;$i<=10;$i++)check(isset($ids['F02-NFR-'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]),'missing NFR '.$i);
for($i=1;$i<=2;$i++)check(isset($ids['F02-CEN-'.str_pad((string)$i,2,'0',STR_PAD_LEFT)]),'missing CEN '.$i);
for($i=1;$i<=24;$i++)check(isset($ids['F02-X24-'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]),'missing X24 '.$i);
check(($data['summary']['independently_verified_production_acceptances']??-1)===0,'false release acceptance');
echo "PASS 48/48 explicit IDs and no invented production acceptance (source register).\n";
