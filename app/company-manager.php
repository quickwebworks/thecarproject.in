<?php
declare(strict_types=1);
require_once __DIR__.'/demo-data.php';
function allowCompanyManagement():array {
 $u=allow(['admin']);
 if($_SESSION['company_key']!=='existing')fail('Sign in to the original company as admin to manage all companies.',403);
 return $u;
}
function companyPublicDetails():array {
 $rows=[];
 foreach(companySettings() as $key=>$c)$rows[]=['key'=>$key,'name'=>$c['name'],'financial_year'=>'2026–2027','original'=>$key==='existing'];
 return $rows;
}
function saveCompanyConfiguration(array $data,array $actor):void {
 $operation=$data['operation']??'add';$key=(string)($data['key']??'');
 if(!preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$key))fail('Use a company key with 2–40 lowercase letters, digits, underscores or hyphens.');
 $name=txt($data,'name',160);
 $path=dirname(__DIR__).'/storage/company-registry.json';$lock=fopen(dirname($path).'/company-registry.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))fail('Unable to lock the company settings.',500);
 try{
  $registry=readCompanyRegistry();$configured=companySettings();foreach($registry as $rk=>$rc)$configured[$rk]=array_merge($configured[$rk]??[],$rc);
  if($operation==='edit'){
   if(!isset($configured[$key]))fail('Company not found.',404);
   $registry[$key]=array_merge($registry[$key]??[],['name'=>$name]);
  }elseif($operation==='add'){
   if($key==='existing'||isset($configured[$key]))fail('That company key already exists. Use Edit Name.',409);
   $host=txt($data,'host',200);$database=txt($data,'database',100);$username=txt($data,'db_username',100);
   $password=$data['db_password']??null;
   if(!preg_match('/^[a-zA-Z0-9.-]+$/',$host)||!preg_match('/^[a-zA-Z0-9_$-]+$/',$database)||!is_string($password)||$password==='')fail('Enter valid MySQL host, database, username and password.');
   $settings=['name'=>$name,'first_year'=>2026,'dsn'=>'mysql:host='.$host.';port=3306;dbname='.$database.';charset=utf8mb4','username'=>$username,'password'=>$password];
   foreach($configured as $other)if(connectionIdentity($other)===connectionIdentity($settings))fail('Each company needs a separate database. This database is already connected.',409);
   try{
    $target=new PDO($settings['dsn'],$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $tables=$target->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $newDatabase=count($tables)===0;
    $demo=($data['demo']??false)===true||($data['demo']??'')==='1';
    if($demo&&!$newDatabase)fail('Demo samples can only be added to a completely empty database. Existing data was not changed.',409);
    if($newDatabase){
     $target->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
     $admin=query('SELECT name,username,password_hash FROM users WHERE id=?',[$actor['id']])->fetch();
     if(!$admin)fail('Original admin account could not be found.',500);
     $s=$target->prepare("INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,'admin')");
     $s->execute([$admin['name'],$admin['username'],$admin['password_hash']]);
     if($demo)seedDemoRecords($target,$key,(int)$target->lastInsertId());
    }else{
     foreach(['users','masters','purchases','sales','expenses','expense_items','documents','audit_log','login_attempts'] as $table)if(!in_array($table,$tables,true))fail('This is not an empty database or a complete Car Project database. Use a new empty database.',409);
    }
   }catch(PDOException $e){error_log('CarProject company connection/setup error: '.$e->getCode());fail('Could not connect or initialize this database. Check Hostinger database credentials and permissions. No existing business database was overwritten.',422);}
   $registry[$key]=$settings;
  }else fail('Unknown company operation.');
  $temp=tempnam(dirname($path),'company-registry.');
  if($temp===false)fail('Company setup ran, but settings could not be saved. Check storage permissions before retrying.',500);
  try{
   chmod($temp,0600);
   if(file_put_contents($temp,json_encode($registry,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false||!rename($temp,$path))fail('Company setup ran, but settings could not be saved. Check storage permissions.',500);
  }finally{if(is_file($temp))unlink($temp);}
  audit($operation==='edit'?'Renamed company: '.$key:'Connected company: '.$key,0);
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
