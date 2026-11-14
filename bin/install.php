<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
function stopInstall(string $message):never {fwrite(STDERR,$message."\n");exit(1);}
$options=getopt('', ['name:', 'username:', 'password-stdin', 'company:']);
$stdinMode=array_key_exists('password-stdin',$options);
if($stdinMode){
 if(!isset($options['name'],$options['username'])||!is_string($options['name'])||!is_string($options['username']))stopInstall('With --password-stdin, supply --name and --username.');
 $name=trim($options['name']);$username=trim($options['username']);
}else{
 if(!function_exists('shell_exec'))stopInstall('PHP shell_exec is unavailable. Use --name, --username and --password-stdin with a hidden SSH terminal password prompt. See README.md.');
 echo 'Admin full name: ';$name=trim((string)fgets(STDIN));
 echo 'Admin username: ';$username=trim((string)fgets(STDIN));
}
if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username)||$name===''||strlen($name)>120)stopInstall('Invalid name or username. Username: 3-80 letters, numbers, underscore, dot or hyphen.');
if($stdinMode){
 // The SSH shell hides entry; only password bytes arrive on stdin.
 $line=fgets(STDIN,1024);
 if($line===false)stopInstall('No password received on stdin.');
 $password=rtrim($line,"\r\n");
}else{
 $mode=shell_exec('stty -g');if(!$mode)stopInstall('Run in an interactive terminal or use --password-stdin.');
 echo 'Admin password (12-128 characters; hidden): ';shell_exec('stty -echo');
 try{$password=rtrim((string)fgets(STDIN,1024),"\r\n");}
 finally{shell_exec('stty '.escapeshellarg(trim($mode)));echo "\n";}
}
if(strlen($password)<12||strlen($password)>128)stopInstall('Password must contain between 12 and 128 characters.');
$configPath=dirname(__DIR__).'/config.php';
if(!is_file($configPath))stopInstall('Create config.php from config.example.php and enter your database connection details first.');
require_once dirname(__DIR__).'/app/workspaces.php';
try{[$companyKey,$config]=cliCompanySettings($options);}catch(Throwable $e){stopInstall($e->getMessage());}
echo 'Installing company: '.$config['name']."\n";
$db=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($db->query("SHOW TABLES LIKE 'users'")->fetch())stopInstall('Tables already exist. Installer will not overwrite data.');
$hash=password_hash($password,PASSWORD_DEFAULT);
unset($password,$line);
$db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
$s=$db->prepare('INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,?)');
$s->execute([$name,$username,$hash,'admin']);
echo "Installed. Open your website and sign in with the admin account you just created.\n";
