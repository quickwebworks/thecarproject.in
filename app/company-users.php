<?php
declare(strict_types=1);
function userTargetCompany(array $data,array $companies,string $current):string {
 $key=$data['company_key']??null;
 if(!is_string($key)||!isset($companies[$key]))fail('Select a valid company.');
 if($key!==$current&&$current!=='existing')fail('You can create users only for your current company.',403);
 return $key;
}
function createCompanyUser(array $data):void {
 $actor=allow(['admin']);$companies=companySettings();
 $key=userTargetCompany($data,$companies,$_SESSION['company_key']);
 $role=txt($data,'role',20);
 if(!in_array($role,['admin','manager','accountant','viewer'],true))fail('Invalid user type.');
 $pw=$data['password']??null;
 if(!is_string($pw)||strlen($pw)<12||strlen($pw)>72)fail('Use a password between 12 and 72 characters.');
 $username=txt($data,'username',80);$name=txt($data,'name',120);
 if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username))fail('Invalid username.');
 $settings=$companies[$key];
 $target=$key===$_SESSION['company_key']?db():new PDO($settings['dsn'],$settings['username'],$settings['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $target->beginTransaction();
 try{
  $check=$target->prepare('SELECT id FROM users WHERE LOWER(username)=LOWER(?)');$check->execute([$username]);
  if($check->fetchColumn())fail('That username already exists in the selected company.',409);
  $insert=$target->prepare('INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,?)');
  $insert->execute([$name,$username,password_hash($pw,PASSWORD_DEFAULT),$role]);$id=(int)$target->lastInsertId();
  // The target company's own account ID is used; source IDs are unrelated.
  $log=$target->prepare('INSERT INTO audit_log(user_id,action,entity_id) VALUES(?,?,?)');
  $log->execute([$key===$_SESSION['company_key']?$actor['id']:$id,'Created company user',$id]);
  $target->commit();
 }catch(Throwable $e){
  if($target->inTransaction())$target->rollBack();
  if($e instanceof PDOException&&(int)($e->errorInfo[1]??0)===1062)fail('That username already exists in the selected company.',409);
  throw $e;
 }
}
