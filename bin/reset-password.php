<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function resetError(string $message):never {fwrite(STDERR,$message."\n");exit(1);}
$options=getopt('', ['username:', 'password-stdin', 'company:']);
$username=$options['username']??'';
if(!is_string($username)||!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username)||!array_key_exists('password-stdin',$options)){
 resetError('Usage: php bin/reset-password.php --username=superadmin --password-stdin (pipe the new password on stdin)');
}
$line=fgets(STDIN,1024);
if($line===false)resetError('No password received on stdin.');
$password=rtrim($line,"\r\n");
if(strlen($password)<12||strlen($password)>72)resetError('Use a password between 12 and 72 bytes.');
$configPath=dirname(__DIR__).'/config.php';
if(!is_file($configPath))resetError('config.php was not found. Run this command from the deployed project.');
require_once dirname(__DIR__).'/app/workspaces.php';
try{[$companyKey,$config]=cliCompanySettings($options);}catch(Throwable $e){resetError($e->getMessage());}
echo 'Selected company: '.$config['name']."\n";
$hash=password_hash($password,PASSWORD_DEFAULT);
unset($password,$line);
$db=null;
try{
 $db=new PDO($config['dsn'],$config['username'],$config['password'],[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
 ]);
 $db->beginTransaction();
 $s=$db->prepare('SELECT id,active FROM users WHERE username=? FOR UPDATE');$s->execute([$username]);$u=$s->fetch();
 if(!$u)throw new RuntimeException('User not found. No account was changed.');
 if(!(int)$u['active'])throw new RuntimeException('Account is disabled. Password was not changed.');
 $s=$db->prepare('UPDATE users SET password_hash=? WHERE id=?');$s->execute([$hash,$u['id']]);
 $s=$db->prepare('INSERT INTO audit_log(user_id,action,entity_id) VALUES(?,?,?)');$s->execute([$u['id'],'Password reset via SSH',$u['id']]);
 $db->commit();
 echo "Password reset successfully for ".$username.". Sign in using your new password.\n";
}catch(Throwable $e){
 if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
 if($e instanceof PDOException){error_log('CarProject password reset database failure: '.$e->getCode());resetError('Database operation failed. Check config.php and private server logs.');}
 resetError($e->getMessage());
}
