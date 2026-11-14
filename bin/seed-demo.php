<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/workspaces.php';
require dirname(__DIR__).'/app/demo-data.php';
$options=getopt('', ['company:']);
try{
 [$key,$config]=cliCompanySettings($options);
 if(!in_array($key,['demo_one','demo_two','demo_three'],true))throw new RuntimeException('Use --company=demo_one, demo_two or demo_three. Existing business data will not be seeded.');
 $db=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $admin=$db->query("SELECT id FROM users WHERE role='admin' AND active=1 ORDER BY id LIMIT 1")->fetchColumn();
 if(!$admin)throw new RuntimeException('Install a company admin account first.');
 seedDemoRecords($db,$key,(int)$admin);
 echo 'Demo records added to '.$config['name']." for FY 2026–2027.\n";
}catch(Throwable $e){fwrite(STDERR,$e instanceof PDOException?"Check the company database connection and installation.\n":$e->getMessage()."\n");exit(1);}
