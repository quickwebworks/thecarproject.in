<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/workspaces.php';
if (!in_array('--clear-all-company-data', $argv, true)) {
 fwrite(STDERR, "This clears ALL business records in ALL configured companies, preserves users and company details, and removes demo companies from selection.\nRun with --clear-all-company-data to execute.\n"); exit(1);
}
umask(0077);
$root=dirname(__DIR__);
$lock=fopen($root.'/storage/company-registry.lock','c');
if(!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Cannot lock company settings.');
$backup=$root.'/storage/production-reset-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
if(!mkdir($backup,0700))throw new RuntimeException('Cannot create backup.');
$connections=[];
$order=['expense_items','documents','sales','yard_expenses','expenses','purchases','masters','audit_log','login_attempts'];
try {
 $companies=companySettings();
 foreach(['companies.php','storage/company-registry.json'] as $file)
  if(is_file($root.'/'.$file)&&!copy($root.'/'.$file,$backup.'/'.basename($file)))throw new RuntimeException('Cannot back up company settings.');
 // Complete every database backup and validate tables before deleting any rows.
 foreach($companies as $key=>$c){
  $pdo=new PDO($c['dsn'],$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  foreach($tables as $table)if(!in_array($table,array_merge($order,['users']),true))throw new RuntimeException('Unexpected table: '.$table.'. No cleanup started.');
  $dump=[];
  foreach($tables as $table)$dump[$table]=$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll();
  $json=json_encode($dump,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
  if(file_put_contents($backup.'/'.$key.'.json',$json)===false)throw new RuntimeException('Backup failed.');
  chmod($backup.'/'.$key.'.json',0600);
  $connections[$key]=[$pdo,$tables];
 }
 foreach($connections as $key=>[$pdo,$tables]){
  $pdo->beginTransaction();
  try{
   foreach($order as $table)if(in_array($table,$tables,true))$pdo->exec('DELETE FROM `'.$table.'`');
   foreach($order as $table)if(in_array($table,$tables,true)&&(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==0)throw new RuntimeException('Nonempty table: '.$table);
   $pdo->commit();
   echo 'Cleared business records: '.$companies[$key]['name']."\n";
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 // Archive attachments rather than leave obsolete documents in the active folder.
 if(is_dir($root.'/storage/documents')&&!rename($root.'/storage/documents',$backup.'/documents'))throw new RuntimeException('Rows cleared, but document archiving failed.');
 $registry=readCompanyRegistry();
 $extra=is_file($root.'/companies.php')?require $root.'/companies.php':[];
 foreach($companies as $key=>$c){
  if($key!=='existing'&&(str_starts_with($key,'demo_')||preg_match('/^DEMO COMPANY(?:\\s|$)/i',$c['name']))){
   unset($registry[$key],$extra[$key]); echo 'Removed demo company from selection: '.$c['name']."\n";
  }
 }
 $write=function(string $path,string $content):void{
  $temp=$path.'.reset-tmp';
  if(file_put_contents($temp,$content)===false||!chmod($temp,0600)||!rename($temp,$path))throw new RuntimeException('Cannot save company configuration.');
 };
 $write($root.'/storage/company-registry.json',json_encode((object)$registry,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
 if(is_file($root.'/companies.php'))$write($root.'/companies.php',"<?php\nreturn ".var_export($extra,true).";\n");
 echo "Cleanup completed. Users, passwords, company details and financial years preserved.\nPrivate backup: ".$backup."\nSign out and sign in again before entering real records.\n";
}catch(Throwable $e){
 fwrite(STDERR,"Cleanup stopped: ".$e->getMessage()."\nSome companies may already be cleared. Private backup: ".$backup."\n");exit(1);
}finally{flock($lock,LOCK_UN);fclose($lock);}
