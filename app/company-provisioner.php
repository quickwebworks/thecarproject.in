<?php
declare(strict_types=1);

function writePrivateCompanyJson(string $path,array $value):void {
 $temp=tempnam(dirname($path),'company-');
 if($temp===false)fail('Unable to save private company settings.',500);
 try{
  if(!chmod($temp,0600)||file_put_contents($temp,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false||!rename($temp,$path))fail('Unable to save private company settings. Check storage permissions.',500);
 }finally{if(is_file($temp))unlink($temp);}
}
function companyProvisioningConfig():array {
 $path=dirname(__DIR__).'/storage/company-provisioning.php';
 $config=is_file($path)?require $path:[];
 if(!is_array($config))fail('Invalid private company provisioning configuration.',503);
 $config+=['api_token'=>getenv('HOSTINGER_API_TOKEN')?:'','account'=>getenv('HOSTINGER_ACCOUNT')?:'','domain'=>getenv('HOSTINGER_DOMAIN')?:'thecarproject.in','host'=>'localhost'];
 if(!is_string($config['api_token'])||trim($config['api_token'])===''||!preg_match('/^u[0-9]+$/',(string)$config['account']))fail('Automatic company setup needs a one-time Hostinger connection. Configure storage/company-provisioning.php as described in docs/COMPANIES.md. Do not enter MySQL credentials in this form.',503);
 if(!is_string($config['host'])||!preg_match('/^[a-zA-Z0-9.-]+$/',$config['host'])||!is_string($config['domain'])||!preg_match('/^[a-zA-Z0-9.-]+$/',$config['domain']))fail('Check the private hosting account configuration.',503);
 return $config;
}
function companyDatabasePassword():string {
 // Hostinger requires uppercase, lowercase and a digit; include a symbol as well.
 return 'Aa7!'.bin2hex(random_bytes(16));
}
function newCompanyDatabaseSettings(array $config):array {
 // Keep the complete name short, including Hostinger's account prefix.
 $suffixLength=min(5,16-strlen($config['account'])-1);
 if($suffixLength<4)fail('The hosting account prefix is too long for automatic short database names.',503);
 $alphabet='0123456789abcdefghijklmnopqrstuvwxyz';$suffix='c';
 for($i=1;$i<$suffixLength;$i++)$suffix.=$alphabet[random_int(0,strlen($alphabet)-1)];
 $database=$config['account'].'_'.$suffix;
 return ['database'=>$database,'dsn'=>'mysql:host='.$config['host'].';port=3306;dbname='.$database.';charset=utf8mb4','username'=>$database,'password'=>companyDatabasePassword()];
}
function companySetupForRetry(array $job,array $config):array {
 // Only explicitly rejected requests can receive a new name. Never rename
 // provisioned/initialized databases or uncertain, in-flight requests.
 if($job['stage']==='rejected')$job['settings']=newCompanyDatabaseSettings($config);
 return $job;
}
function hostingerCompanyDatabasePayload(array $config,array $settings):array {
 $prefix=$config['account'].'_';
 if(!str_starts_with($settings['database'],$prefix)||!str_starts_with($settings['username'],$prefix))fail('The database setup record does not match the hosting account.',503);
 return ['name'=>substr($settings['database'],strlen($prefix)),'user'=>substr($settings['username'],strlen($prefix)),'password'=>$settings['password'],'website_domain'=>$config['domain']];
}
function hostingerValidationSummary(string $response,array $secrets=[]):string {
 $body=json_decode($response,true);if(!is_array($body))return ' Hostinger returned no readable JSON error details.';
 $redact=function(string $text)use($secrets):string{
  foreach($secrets as $secret){
   if(!is_string($secret)||$secret==='')continue;
   $variants=[$secret,rawurlencode($secret),urlencode($secret),htmlspecialchars($secret,ENT_QUOTES,'UTF-8')];
   $text=str_replace($variants,'[REDACTED]',$text);
  }
  $text=preg_replace('/Bearer\\s+[^\\s,;]+/i','Bearer [REDACTED]',$text);
  $text=preg_replace('/\\b(password|api[_ -]?token|authorization)\\s*[=:]\\s*[^\\s,;]+/i','$1=[REDACTED]',$text);
  $text=trim(preg_replace('/[\\x00-\\x1F\\x7F]+/',' ',strip_tags($text)));
  return mb_substr($text,0,240,'UTF-8');
 };
 $details=[];$collect=function(mixed $value,int $depth=0)use(&$collect,&$details,$redact):void{
  if(count($details)>=4||$depth>4)return;
  if(is_string($value)){$text=$redact($value);if($text!=='')$details[]=$text;}
  elseif(is_array($value))foreach($value as $item)$collect($item,$depth+1);
 };
 // Only error messages, never echoed request/input/header objects.
 foreach(['message','detail'] as $key)if(isset($body[$key])&&is_string($body[$key]))$collect($body[$key]);
 if(isset($body['error'])&&is_string($body['error']))$collect($body['error']);
 elseif(isset($body['error'])&&is_array($body['error']))foreach(['message','detail','errors'] as $key)if(isset($body['error'][$key]))$collect($body['error'][$key]);
 if(isset($body['errors'])&&is_array($body['errors']))$collect($body['errors']);
 $message=$details?' Hostinger: '.implode(' ',array_unique($details)):' Hostinger returned no specific validation message.';
 $id=$body['correlation_id']??null;if(is_string($id)&&preg_match('/^[a-f0-9-]{16,64}$/i',$id))$message.=' Support reference: '.$id.'.';
 return $message;
}
function hostingerCreateCompanyDatabase(array $config,array $settings):void {
 if(!function_exists('curl_init'))fail('Enable the PHP cURL extension for automatic company setup.',503);
 $curl=curl_init('https://developers.hostinger.com/api/hosting/v1/accounts/'.rawurlencode($config['account']).'/databases');
 curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$config['api_token'],'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode(hostingerCompanyDatabasePayload($config,$settings),JSON_THROW_ON_ERROR)]);
 $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
 if($response===false||$status<200||$status>=300){
  // Never include provider responses or credentials in browser errors or logs.
  error_log('CarProject automatic database provisioning HTTP status: '.$status);
  $detail=$status===422?hostingerValidationSummary(is_string($response)?$response:'',[$config['api_token'],$settings['password']]):'';
  throw new RuntimeException('Hostinger did not confirm database creation (HTTP '.$status.').'.$detail.' Check hosting configuration and database quota. The private setup record was retained.', $status);
 }
}
function prepareAutomaticCompanyDatabase(string $key,array $metadata):array {
 $config=companyProvisioningConfig();
 if(!function_exists('curl_init'))fail('Enable the PHP cURL extension for automatic company setup.',503);
 $path=dirname(__DIR__).'/storage/company-setup-'.$key.'.json';
 if(is_file($path)){
  $job=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
  if(!is_array($job)||!isset($job['settings'],$job['stage'])||!in_array($job['stage'],['new','rejected','requesting','provisioned','schema_ready','initialized'],true))fail('Company setup record needs administrator attention.',503);
  if($job['stage']==='requesting')fail('An earlier database creation was not confirmed. The administrator must verify the private company setup record in Hostinger before retrying. Existing databases were not changed.',409);
 }else{
  $settings=newCompanyDatabaseSettings($config);
  $job=['stage'=>'new','settings'=>$settings];
 }
 if(in_array($job['stage'],['new','rejected'],true)){
  $job=companySetupForRetry($job,$config);
  $job['stage']='requesting';
  // Save generated credentials before the external request, including uncertain failures.
  writePrivateCompanyJson($path,$job);
  try{hostingerCreateCompanyDatabase($config,$job['settings']);}
  catch(RuntimeException $e){
   if(in_array($e->getCode(),[400,401,403,404,422,429],true)){$job['stage']='rejected';writePrivateCompanyJson($path,$job);}
   fail($e->getMessage().' If the request timed out, ask the administrator to verify it before retrying.',503);
  }
  $job['stage']='provisioned';writePrivateCompanyJson($path,$job);
 }
 return [$path,$job,array_merge($job['settings'],$metadata)];
}
