<?php
declare(strict_types=1);

function currentFinancialYear():int { return 2026; }
function financialYearRange(int $year):array {
 if($year<2026||$year>2100)throw new RuntimeException('Invalid financial year.');
 return ['year'=>$year,'label'=>$year.'–'.($year+1),'start'=>$year.'-04-01','end'=>($year+1).'-03-31'];
}
function companyFinancialYears(array $company):array {
 $years=$company['financial_years']??[2026];
 if(!is_array($years))throw new RuntimeException('Invalid financial year settings.');
 $result=[2026];
 foreach($years as $year){
  if(!is_int($year)||$year<2026||$year>2100)throw new RuntimeException('Financial years must start from 2026 and be explicitly added.');
  $result[]=$year;
 }
 $result=array_values(array_unique($result));sort($result,SORT_NUMERIC);return $result;
}

function connectionIdentity(array $settings):string {
 $dsn=(string)($settings['dsn']??'');
 if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Company connections must use MySQL.');
 $parts=[];foreach(explode(';',substr($dsn,6)) as $part){$pair=explode('=',$part,2);if(count($pair)===2)$parts[strtolower(trim($pair[0]))]=trim($pair[1]);}
 if(empty($parts['dbname']))throw new RuntimeException('A company database name is required.');
 return strtolower(($parts['host']??'localhost').':'.($parts['port']??'3306').'/'.$parts['dbname']);
}
function companySettings(?array $base=null):array {
 static $cached=null;if($base===null&&$cached!==null)return $cached;
 $fromFile=$base===null;
 if($base===null){$path=dirname(__DIR__).'/config.php';if(!is_file($path))throw new RuntimeException('Configure config.php and run the CLI installer first.');$base=require $path;}
 $companies=['existing'=>array_merge($base,['name'=>$base['company_name']??'THE CAR PROJECT','first_year'=>(int)(2026)])];
 $path=dirname(__DIR__).'/companies.php';
 if($fromFile&&is_file($path)){
  $extra=require $path;if(!is_array($extra))throw new RuntimeException('companies.php must return a company configuration array.');
  foreach($extra as $key=>$settings){
   if(!is_string($key)||!preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$key)||$key==='existing'||!is_array($settings))throw new RuntimeException('Invalid or reserved company key in companies.php.');
   $companies[$key]=$settings;
  }
 }
 if($fromFile){
  $registry=readCompanyRegistry();
  foreach($registry as $key=>$settings){if(isset($companies[$key]))$companies[$key]=array_merge($companies[$key],$settings);else $companies[$key]=$settings;}
 }
 $seen=[];
 foreach($companies as $key=>&$settings){
  foreach(['dsn','username','password','name'] as $field)if(!isset($settings[$field])||!is_string($settings[$field]))throw new RuntimeException('Missing company connection setting: '.$key.' / '.$field);
  $settings['name']=trim($settings['name']);if($settings['name']===''||strlen($settings['name'])>160)throw new RuntimeException('Invalid company name.');
  $settings['first_year']=2026;$settings['financial_years']=companyFinancialYears($settings);
  $identity=connectionIdentity($settings);
  if(isset($seen[$identity]))throw new RuntimeException('Companies must use separate database names: '.$key.' duplicates '.$seen[$identity]);
  $seen[$identity]=$key;
 }
 unset($settings);if($fromFile)$cached=$companies;return $companies;
}
function cliCompanySettings(array $options):array {
 $key=$options['company']??'existing';
 if(!is_string($key))throw new RuntimeException('Use one --company key.');
 $companies=companySettings();
 if(!isset($companies[$key]))throw new RuntimeException('Unknown company key. Check companies.php.');
 return [$key,$companies[$key]];
}
function ensureWorkspace():void {
 $companies=companySettings();
 $key=(string)($_SESSION['company_key']??'existing');
 if(!isset($companies[$key])){unset($_SESSION['uid'],$_SESSION['auth_company']);$key='existing';}
 $_SESSION['company_key']=$key;
 $year=(int)($_SESSION['financial_year']??currentFinancialYear());
 if(!in_array($year,companyFinancialYears($companies[$key]),true)){$year=2026;$_SESSION['workspace_token']=bin2hex(random_bytes(24));}
 $_SESSION['financial_year']=$year;
 $_SESSION['workspace_token']??=bin2hex(random_bytes(24));
}
function workspaceInfo():array {
 ensureWorkspace();$key=$_SESSION['company_key'];$companies=companySettings();
 return ['company_key'=>$key,'company_name'=>$companies[$key]['name'],'financial_year'=>financialYearRange((int)$_SESSION['financial_year'])];
}
function workspaceChoices():array {
 $result=[];foreach(companySettings() as $key=>$company){$years=array_map('financialYearRange',companyFinancialYears($company));$result[]=['key'=>$key,'name'=>$company['name'],'years'=>$years];}
 return $result;
}
function workspaceSessionPayload():array {
 $profiles=[];
 if(!empty($_SESSION['uid'])&&($_SESSION['company_key']??'')==='existing'&&user()['role']==='admin'){
  foreach(companySettings() as $key=>$c)$profiles[$key]=array_intersect_key($c,array_flip(['address','contact_person','mobile','email']));
 }
 return ['company_profiles'=>$profiles,'user'=>empty($_SESSION['uid'])?null:user(),'csrf'=>$_SESSION['csrf'],'workspace_token'=>$_SESSION['workspace_token'],'workspace'=>workspaceInfo(),'companies'=>workspaceChoices()];
}
function requireWorkspaceToken():void {
 $token=(string)($_SERVER['HTTP_X_WORKSPACE_TOKEN']??($_GET['workspace_token']??''));
 if(!hash_equals($_SESSION['workspace_token'],$token))fail('Company or financial year changed in another tab. Reload this page before continuing.',409);
}
function selectWorkspace(array $data):array {
 $key=$data['company_key']??'';$year=filter_var($data['financial_year']??null,FILTER_VALIDATE_INT);
 $companies=companySettings();
 if(!is_string($key)||!isset($companies[$key])||!in_array($year,companyFinancialYears($companies[$key]),true))fail('Select a valid company and financial year.');
 if($key!==$_SESSION['company_key'])unset($_SESSION['uid'],$_SESSION['auth_company']);
 $_SESSION['company_key']=$key;$_SESSION['financial_year']=$year;
 $_SESSION['workspace_token']=bin2hex(random_bytes(24));$_SESSION['csrf']=bin2hex(random_bytes(32));
 session_regenerate_id(true);return workspaceSessionPayload();
}
function assertWorkspaceDate(string $date):string {
 $range=financialYearRange((int)$_SESSION['financial_year']);
 if($date<$range['start']||$date>$range['end'])fail('Entry date must fall within financial year '.$range['label'].' ('.$range['start'].' to '.$range['end'].').');
 return $date;
}
function postingDate(array $data,string $key):string {return assertWorkspaceDate(dateval($data,$key));}
function documentDirectory():string {
 $key=$_SESSION['company_key'];$path=dirname(__DIR__).'/storage/documents'.($key==='existing'?'':'/'.$key);
 if(!is_dir($path)&&!mkdir($path,0700,true)&&!is_dir($path))fail('Unable to create the private company document folder.',500);
 return $path;
}

function readCompanyRegistry():array {
 $path=dirname(__DIR__).'/storage/company-registry.json';
 if(!is_file($path))return [];
 $data=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if(!is_array($data))throw new RuntimeException('Invalid company registry.');return $data;
}


