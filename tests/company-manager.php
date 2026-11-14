<?php
declare(strict_types=1);
$root=sys_get_temp_dir().'/tcp-company-test-'.bin2hex(random_bytes(6));
mkdir($root,0700);mkdir($root.'/app',0700);mkdir($root.'/storage',0700);
foreach(['workspaces.php','company-manager.php','demo-data.php'] as $file)copy(dirname(__DIR__).'/app/'.$file,$root.'/app/'.$file);
file_put_contents($root.'/config.php',"<?php return ['dsn'=>'mysql:host=localhost;dbname=fixture_only','username'=>'fixture','password'=>'fixture-only','first_year'=>2001];");
function fail(string $message,int $status=422):never{throw new RuntimeException($message,$status);}
function allow(array $roles):array{if(!in_array($GLOBALS['role'],$roles,true))fail('Denied',403);return ['id'=>1,'role'=>$GLOBALS['role']];}
function txt(array $data,string $key,int $max=160):string{$v=trim((string)($data[$key]??''));if($v===''||strlen($v)>$max)fail('Invalid field');return $key==='name'?strtoupper($v):$v;}
function audit(string $action,int $id):void{$GLOBALS['audits'][]=$action;}
function checkCompany(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function rejectsCompany(callable $fn):void{try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected rejection');}
try{
 require $root.'/app/workspaces.php';require $root.'/app/company-manager.php';
 $GLOBALS['role']='admin';$_SESSION=['company_key'=>'existing'];$actor=allowCompanyManagement();
 $choices=workspaceChoices();checkCompany(count($choices[0]['years'])===1&&$choices[0]['years'][0]['year']===2026,'Old year configuration must not re-enable old years');
 $public=companyPublicDetails();checkCompany(!isset($public[0]['dsn'],$public[0]['password'],$public[0]['username']),'Credentials must not be returned');
 saveCompanyConfiguration(['operation'=>'edit','key'=>'existing','name'=>"Renamed O'Brien Motors"],$actor);
 $saved=readCompanyRegistry();checkCompany($saved['existing']['name']==="RENAMED O'BRIEN MOTORS",'Name saved safely');checkCompany(count($saved['existing'])===1,'Rename must not change database connection');
 rejectsCompany(fn()=>saveCompanyConfiguration(['operation'=>'add','key'=>'existing','name'=>'DUPLICATE'],$actor));
 rejectsCompany(fn()=>saveCompanyConfiguration(['operation'=>'edit','key'=>'unknown_company','name'=>'UNKNOWN'],$actor));
 $_SESSION['company_key']='demo_one';rejectsCompany(fn()=>allowCompanyManagement());
 $_SESSION['company_key']='existing';$GLOBALS['role']='viewer';rejectsCompany(fn()=>allowCompanyManagement());
 echo "PASS: original-admin-only company management, safe name edits, single year, duplicate key rejection and no credentials in listings.\n";
}finally{
 foreach(glob($root.'/storage/*')?:[] as $file)unlink($file);
 foreach(glob($root.'/app/*')?:[] as $file)unlink($file);
 unlink($root.'/config.php');rmdir($root.'/storage');rmdir($root.'/app');rmdir($root);
}
