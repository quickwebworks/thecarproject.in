<?php
require __DIR__.'/../app/company-users.php';
function fail($message,$status=422):never{throw new RuntimeException($message,$status);}
function allow($roles){if($GLOBALS['role']!=='admin')fail('Forbidden',403);return ['id'=>1];}
function query($sql,$params=[]){$s=$GLOBALS['db']->prepare($sql);$s->execute($params);return $s;}
function transaction($fn){$GLOBALS['db']->beginTransaction();try{$fn();$GLOBALS['db']->commit();}catch(Throwable $e){$GLOBALS['db']->rollBack();throw $e;}}
function audit($action,$id){query('INSERT INTO audit_log(action,entity_id) VALUES(?,?)',[$action,$id]);}
function check($value){if(!$value)throw new Exception('Test failed');}
function rejects($fn,$code){try{$fn();throw new Exception('Unexpected success');}catch(RuntimeException $e){check($e->getCode()===$code);}}
$role='admin';$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,password_hash TEXT,role TEXT,active INTEGER);CREATE TABLE login_attempts(identifier TEXT);CREATE TABLE audit_log(action TEXT,entity_id INTEGER);');
foreach(['admin','manager','accountant','viewer'] as $i=>$r)query('INSERT INTO users VALUES(?,?,?,?,1)',[$i+1,$r,'original',$r]);
$pw='New-password-987654!';$d=['password'=>$pw,'confirm_password'=>$pw];
query('INSERT INTO login_attempts VALUES(?)',[hash('sha256','viewer')]);query('INSERT INTO login_attempts VALUES(?)',['other']);
foreach(range(1,4) as $id){resetCompanyUserPassword($d+['id'=>$id]);$row=query('SELECT * FROM users WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);check(password_verify($pw,$row['password_hash']));check($row['active']===1);}
check((int)query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===4);check((int)query('SELECT COUNT(*) FROM login_attempts')->fetchColumn()===1);
rejects(fn()=>resetCompanyUserPassword($d+['id'=>999]),404);
rejects(fn()=>resetCompanyUserPassword(['id'=>1,'password'=>$pw,'confirm_password'=>'wrong']),422);
rejects(fn()=>resetCompanyUserPassword(['id'=>1,'password'=>'short','confirm_password'=>'short']),422);
$role='viewer';rejects(fn()=>resetCompanyUserPassword($d+['id'=>1]),403);
echo "PASS: all four roles, hashed passwords, self-reset, missing IDs, mismatches, short passwords, admin-only access, targeted lockout clearing and audit entries\n";
