<?php
require __DIR__.'/../app/company-users.php';
function fail($message,$status=422):never{throw new RuntimeException($message,$status);}
function allow($roles){return ['id'=>1];}
function txt($data,$key,$max){return $data[$key];}
function companySettings(){return $GLOBALS['settings'];}
function db(){return $GLOBALS['source'];}
function check($condition){if(!$condition)throw new RuntimeException('Test failed');}
$source=new PDO('sqlite::memory:');$source->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$sql='CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT,username TEXT UNIQUE,password_hash TEXT,role TEXT); CREATE TABLE audit_log(user_id INTEGER, action TEXT,entity_id INTEGER);';
$source->exec($sql);$source->exec("INSERT INTO users VALUES(1,'Owner','superadmin','unchanged','admin')");
$targetFile=sys_get_temp_dir().'/company-users-test.sqlite';@unlink($targetFile);$target=new PDO('sqlite:'.$targetFile);$target->exec($sql);
$settings=['existing'=>[],'ganpati'=>['dsn'=>'sqlite:'.$targetFile,'username'=>null,'password'=>null]];$_SESSION=['company_key'=>'existing'];
$data=['company_key'=>'ganpati','role'=>'admin','name'=>'Ganpati Admin','username'=>'ganpatiadmin','password'=>'Test-only-Password987!'];
createCompanyUser($data);
check((int)$source->query('SELECT COUNT(*) FROM users')->fetchColumn()===1);
check($source->query('SELECT password_hash FROM users')->fetchColumn()==='unchanged');
$row=$target->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);check($row['username']==='ganpatiadmin'&&password_verify($data['password'],$row['password_hash']));
try{createCompanyUser($data);throw new Exception('Duplicate accepted');}catch(RuntimeException $e){check($e->getCode()===409);}
$_SESSION['company_key']='ganpati';try{userTargetCompany(['company_key'=>'existing'],$settings,'ganpati');throw new Exception('Cross-company accepted');}catch(RuntimeException $e){check($e->getCode()===403);}
try{userTargetCompany([],$settings,'existing');throw new Exception('Missing company accepted');}catch(RuntimeException $e){check($e->getCode()===422);}
check((int)$target->query('SELECT COUNT(*) FROM users')->fetchColumn()===1);@unlink($targetFile);
echo "PASS: selected database only, existing superadmin unchanged, password hashing, duplicate rejection, company authorization, required company\n";
