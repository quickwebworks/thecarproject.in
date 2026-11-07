<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
$config=require dirname(__DIR__).'/config.php';
$db=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($db->query("SHOW TABLES LIKE 'users'")->fetch())exit("Tables already exist. Installer will not overwrite data.\n");
$name=trim(readline('Admin full name: '));$username=trim(readline('Admin username: '));
if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username)||$name==='')exit("Invalid name or username\n");
if(!function_exists('shell_exec'))exit("Use a terminal with shell_exec enabled for hidden password entry.\n");
$mode=shell_exec('stty -g');if(!$mode)exit("Run from an interactive terminal.\n");
echo 'Admin password (minimum 12 characters; hidden): ';shell_exec('stty -echo');
try{$password=rtrim((string)fgets(STDIN),"\r\n");}finally{shell_exec('stty '.escapeshellarg(trim($mode)));echo "\n";}
if(strlen($password)<12)exit("Password too short\n");
$db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
$s=$db->prepare('INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,?)');
$s->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT),'admin']);
echo "Installed. Set the web document root to the public directory.\n";
