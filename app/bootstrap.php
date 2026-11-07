<?php
declare(strict_types=1);
ini_set('display_errors','0');
error_reporting(E_ALL);
$configFile=dirname(__DIR__).'/config.php';
if (!is_file($configFile)) { http_response_code(503); exit('Configure config.php and run the CLI installer first.'); }
$config=require $configFile;
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');
if($config['https_only']) header('Strict-Transport-Security: max-age=31536000');
session_name('carproject');
session_start(['cookie_httponly'=>true,'cookie_secure'=>(bool)$config['https_only'],'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
if(isset($_SESSION['last']) && time()-$_SESSION['last']>1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['last']=time();
$_SESSION['csrf']??=bin2hex(random_bytes(32));
function db():PDO { static $db; global $config; return $db??=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
function query(string $sql,array $params=[]):PDOStatement { $s=db()->prepare($sql);$s->execute($params);return $s; }
function out(mixed $value,int $status=200):never { http_response_code($status);header('Content-Type: application/json');echo json_encode($value,JSON_THROW_ON_ERROR);exit; }
class ApiError extends RuntimeException { public function __construct(string $message,public int $status=422){parent::__construct($message);} }
function fail(string $message,int $status=422):never { throw new ApiError($message,$status); }
function user():array { if(empty($_SESSION['uid']))fail('Please sign in.',401);$u=query('SELECT id,name,username,role,active FROM users WHERE id=?',[$_SESSION['uid']])->fetch();if(!$u||!$u['active'])fail('Account unavailable.',401);return $u; }
function allow(array $roles):array { $u=user();if(!in_array($u['role'],$roles,true))fail('Your user type does not permit this action.',403);return $u; }
function txt(array $d,string $k,int $max=160,bool $required=true):string { if(isset($d[$k])&&!is_scalar($d[$k]))fail('Invalid '.$k.'.');$v=trim((string)($d[$k]??''));if(($required&&$v==='')||mb_strlen($v)>$max)fail('Enter a valid '.$k.'.');return $v; }
function money(array $d,string $k):string { if(!isset($d[$k])||!is_scalar($d[$k]))fail('Enter '.$k.'.');$v=(string)$d[$k];if(!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$v))fail('Invalid amount: '.$k);return $v; }
function dateval(array $d,string $key):string { $v=txt($d,$key,10);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$date||$date->format('Y-m-d')!==$v)fail('Enter a valid date.');return $v; }
function master(array $d,string $key,string $kind,bool $required=true):?int { $id=(int)($d[$key]??0);if(!$id&&!$required)return null;if(!query('SELECT id FROM masters WHERE id=? AND kind=?',[$id,$kind])->fetch())fail('Select a valid '.$kind.'.');return $id; }
function audit(string $action,int $id):void { query('INSERT INTO audit_log(user_id,action,entity_id) VALUES(?,?,?)',[$_SESSION['uid'],$action,$id]); }
function transaction(callable $fn):void { db()->beginTransaction();try{$fn();db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;} }
