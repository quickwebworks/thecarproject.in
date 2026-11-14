<?php
declare(strict_types=1);
require_once __DIR__.'/demo-data.php';
require_once __DIR__.'/company-provisioner.php';
function allowCompanyManagement():array {
 $u=allow(['admin']);
 if($_SESSION['company_key']!=='existing')fail('Sign in to the original company as admin to manage all companies.',403);
 return $u;
}
function companyPublicDetails():array {
 $rows=[];
 foreach(companySettings() as $key=>$c)$rows[]=['key'=>$key,'name'=>$c['name'],'address'=>$c['address']??'','contact_person'=>$c['contact_person']??'','mobile'=>$c['mobile']??'','email'=>$c['email']??'','financial_year'=>'2026–2027','original'=>$key==='existing'];
 return $rows;
}
function saveCompanyConfiguration(array $data,array $actor):void {
 $operation=$data['operation']??'add';$name=txt($data,'name',160);
 $key=$operation==='add'?'c_'.substr(hash('sha256',$name),0,24):(string)($data['key']??'');
 if($operation==='add'&&in_array($data['key']??'', ['demo_one','demo_two','demo_three'],true))$key=$data['key'];
 $metadata=['name'=>$name,'address'=>txt($data,'address',500,false),'contact_person'=>txt($data,'contact_person',120,false),'mobile'=>txt($data,'mobile',60,false),'email'=>txt($data,'email',254,false)];
 if($metadata['email']!==''&&!filter_var($metadata['email'],FILTER_VALIDATE_EMAIL))fail('Enter a valid email address.');
 if(isset($data['financial_year'])&&(string)$data['financial_year']!=='2026')fail('Select financial year 2026–2027.');
 if(!preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$key))fail('Use a company key with 2–40 lowercase letters, digits, underscores or hyphens.');
 $path=dirname(__DIR__).'/storage/company-registry.json';$lock=fopen(dirname($path).'/company-registry.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))fail('Unable to lock the company settings.',500);
 try{
  $registry=readCompanyRegistry();$configured=companySettings();foreach($registry as $rk=>$rc)$configured[$rk]=array_merge($configured[$rk]??[],$rc);
  foreach($configured as $otherKey=>$other)if($otherKey!==$key&&mb_strtoupper($other['name'],'UTF-8')===$name)fail('A company with this name already exists.',409);
  if($operation==='edit'){
   if(!isset($configured[$key]))fail('Company not found.',404);
   $registry[$key]=array_merge($registry[$key]??[],$metadata);
  }elseif($operation==='add'){
   if($key==='existing'||isset($configured[$key]))fail('That company key already exists. Use Edit Name.',409);
   [$jobPath,$job,$settings]=prepareAutomaticCompanyDatabase($key,$metadata+['first_year'=>2026]);
   $username=$settings['username'];$password=$settings['password'];
   foreach($configured as $other)if(connectionIdentity($other)===connectionIdentity($settings))fail('Each company needs a separate database. This database is already connected.',409);
   try{
    $target=new PDO($settings['dsn'],$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $tables=$target->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $newDatabase=count($tables)===0;
    $demo=($data['demo']??false)===true||($data['demo']??'')==='1';
    if($demo&&!$newDatabase&&$job['stage']!=='initialized')fail('Demo samples can only be added to a completely empty database. Existing data was not changed.',409);
    if($job['stage']==='initialized'){
     // A previous request finished setup but could not persist the registry.
    }elseif($newDatabase){
     $target->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
     $job['stage']='schema_ready';writePrivateCompanyJson($jobPath,$job);
     $admin=query('SELECT name,username,password_hash FROM users WHERE id=?',[$actor['id']])->fetch();
     if(!$admin)fail('Original admin account could not be found.',500);
     $s=$target->prepare("INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,'admin')");
     $s->execute([$admin['name'],$admin['username'],$admin['password_hash']]);
     if($demo)seedDemoRecords($target,$key,(int)$target->lastInsertId());
    }else fail('The generated company database has a partial setup. Ask the server administrator to inspect its private setup record; no existing data was overwritten.',409);
    $job['stage']='initialized';writePrivateCompanyJson($jobPath,$job);
   }catch(PDOException $e){error_log('CarProject company setup SQL error: '.$e->getCode());fail('The company database was created but initialization could not finish. Check hosting permissions and the private setup record. Existing company data was not changed.',503);}
   $registry[$key]=$settings;
  }else fail('Unknown company operation.');
  writePrivateCompanyJson($path,$registry);
  if(isset($jobPath)&&is_file($jobPath))unlink($jobPath);
  audit($operation==='edit'?'Updated company: '.$key:'Connected company: '.$key,0);
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}

