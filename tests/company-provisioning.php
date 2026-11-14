<?php
declare(strict_types=1);
$root=sys_get_temp_dir().'/tcp-provision-'.bin2hex(random_bytes(5));mkdir($root);mkdir($root.'/app');mkdir($root.'/storage');
foreach(['company-provisioner.php','company-manager.php','demo-data.php'] as $file)copy(__DIR__.'/../app/'.$file,$root.'/app/'.$file);
class TestError extends RuntimeException{}
function fail(string $message,int $status=422):never{throw new TestError($message,$status);}
function txt(array $d,string $k,int $max=160,bool $required=true):string{$v=trim((string)($d[$k]??''));if($k==='name')$v=strtoupper($v);if(($required&&$v==='')||strlen($v)>$max)fail('Invalid '.$k);return $v;}
function companySettings():array{return ['existing'=>['name'=>'ORIGINAL','dsn'=>'mysql:host=localhost;dbname=original','username'=>'old_user','password'=>'old_password']];}
function readCompanyRegistry():array{global $root;return is_file($root.'/storage/company-registry.json')?json_decode(file_get_contents($root.'/storage/company-registry.json'),true):[];}
function audit(string $message,int $id):void{}
function allow(array $roles):array{return ['id'=>1];}
function check(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);}
require $root.'/app/company-manager.php';
try{
 saveCompanyConfiguration(['operation'=>'edit','key'=>'existing','name'=>'Updated','address'=>'Ludhiana','contact_person'=>'QA','mobile'=>'1234567890','email'=>'qa@example.com','financial_year'=>2026],['id'=>1]);
 $saved=readCompanyRegistry()['existing'];check($saved['name']==='UPDATED'&&$saved['address']==='Ludhiana','Profile update');check(!isset($saved['dsn']),'Edit does not replace connection');
 try{saveCompanyConfiguration(['operation'=>'add','name'=>'NEW COMPANY','financial_year'=>2026],['id'=>1]);throw new RuntimeException('Missing config accepted');}catch(TestError $e){check($e->getCode()===503&&str_contains($e->getMessage(),'one-time Hostinger'),'Missing config explanation');}
 check(count(glob($root.'/storage/company-setup-*.json'))===0,'No provisioning before config');
 try{saveCompanyConfiguration(['operation'=>'edit','key'=>'existing','name'=>'Updated','email'=>'invalid'],['id'=>1]);throw new RuntimeException('Invalid email accepted');}catch(TestError $e){}
 try{saveCompanyConfiguration(['operation'=>'add','name'=>'ANOTHER','financial_year'=>2001],['id'=>1]);throw new RuntimeException('Old year accepted');}catch(TestError $e){}
 writePrivateCompanyJson($root.'/storage/private.json',['secret'=>'test']);check((fileperms($root.'/storage/private.json')&0777)===0600,'Private file permissions');
 echo "PASS: profile editing preserves connections; missing config makes no provisioning request; invalid email/year rejected; credentials saved with 0600 permissions.\n";
}finally{foreach(glob($root.'/storage/*') as $file)unlink($file);foreach(glob($root.'/app/*') as $file)unlink($file);rmdir($root.'/storage');rmdir($root.'/app');rmdir($root);}
