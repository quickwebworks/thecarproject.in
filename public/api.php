<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
try {
 $action=(string)($_GET['action']??'state');$method=$_SERVER['REQUEST_METHOD'];
 if($action==='session'&&$method==='GET')out(['user'=>empty($_SESSION['uid'])?null:user(),'csrf'=>$_SESSION['csrf']]);
 if($method==='POST'){
  if(!hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))fail('Security token expired. Reload the page.',403);
  $d=str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR):$_POST;
  if(!is_array($d))fail('Invalid request.');
  if($action==='login'){
   $username=txt($d,'username',80);$pw=(string)($d['password']??'');
   $ident=hash('sha256',strtolower($username));$ipident=hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??''));
   if((int)query('SELECT COUNT(*) FROM login_attempts WHERE identifier IN (?,?) AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$ident,$ipident])->fetchColumn()>=10)fail('Too many attempts. Try again in 15 minutes.',429);
   $u=query('SELECT * FROM users WHERE username=?',[$username])->fetch();
   $hash=$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
   $valid=password_verify($pw,$hash);
   if(!$u||!$valid||!$u['active']){query('INSERT INTO login_attempts(identifier) VALUES(?),(?)',[$ident,$ipident]);fail('Incorrect username or password.',401);}
   session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));audit('Signed in',(int)$u['id']);out(['user'=>user(),'csrf'=>$_SESSION['csrf']]);
  }
  $u=user();
  if($action==='logout'){$_SESSION=[];session_destroy();out(['ok'=>true]);}
  if($action==='master'){
   allow(['admin','manager','accountant']);$kind=txt($d,'kind',20);if(!in_array($kind,['vehicle','bank','client','party','document','yard','portal'],true))fail('Invalid master type.');
   $name=txt($d,'name');if($kind==='vehicle'){$name=strtoupper(preg_replace('/\s+/','',$name));if(!preg_match('/^[A-Z0-9-]{4,20}$/',$name))fail('Enter a valid vehicle number.');}
   $newId=0;transaction(function()use($d,$kind,$name,&$newId){query('INSERT INTO masters(kind,name,contact,address,city,details) VALUES(?,?,?,?,?,?)',[$kind,$name,txt($d,'contact',60,false),txt($d,'address',500,false),txt($d,'city',100,false),txt($d,'details',500,false)]);$newId=(int)db()->lastInsertId();audit('Created '.$kind,$newId);});
   out(['id'=>$newId,'ok'=>true]);
  }
  if($action==='purchase'){
   allow(['admin','manager']);$v=master($d,'vehicle_id','vehicle');$b=master($d,'bank_id','bank',false);$c=master($d,'client_id','client',false);$y=master($d,'yard_id','yard',false);$p=master($d,'portal_id','portal',false);
   transaction(function()use($d,$v,$b,$c,$y,$p,$u){
    query('SELECT id FROM masters WHERE id=? FOR UPDATE',[$v]);
    query('INSERT INTO purchases(vehicle_id,bank_id,client_id,yard_id,portal_id,purchase_date,amount,yard_expense,manager_name,manager_contact,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$v,$b,$c,$y,$p,dateval($d,'purchase_date'),money($d,'amount'),money($d,'yard_expense'),txt($d,'manager_name',120,false),txt($d,'manager_contact',60,false),$u['id']]);audit('Recorded purchase',(int)db()->lastInsertId());});out(['ok'=>true]);
  }
  if($action==='sale'){
   allow(['admin','manager']);$v=master($d,'vehicle_id','vehicle');$c=master($d,'client_id','client');
   transaction(function()use($v,$c,$d,$u){$p=query('SELECT * FROM purchases WHERE vehicle_id=? FOR UPDATE',[$v])->fetch();if(!$p)fail('Purchase this vehicle before selling it.');$date=dateval($d,'sale_date');if($date<$p['purchase_date'])fail('Sale date cannot precede purchase date.');
    query('INSERT INTO sales(vehicle_id,client_id,sale_date,amount,sale_expense,salesperson,created_by) VALUES(?,?,?,?,?,?,?)',[$v,$c,$date,money($d,'amount'),money($d,'sale_expense'),txt($d,'salesperson',120,false),$u['id']]);audit('Recorded sale',(int)db()->lastInsertId());});out(['ok'=>true]);
  }
  if($action==='expense'){
   allow(['admin','manager','accountant']);$v=master($d,'vehicle_id','vehicle');$p=master($d,'party_id','party');
   require_once dirname(__DIR__).'/app/expense-bills.php';
   transaction(function()use($v,$p,$d,$u){
    $purchase=query('SELECT * FROM purchases WHERE vehicle_id=? FOR UPDATE',[$v])->fetch();
    if(!$purchase)fail('Select a purchased vehicle.');
    $records=prepareExpenseBills($d,$purchase['purchase_date']);
    // Validate the entire batch before inserting any rows.
    foreach($records as $record){
     if(query('SELECT id FROM expenses WHERE bill_no=?',[$record['bill_no']])->fetch())fail('Bill number '.$record['bill_no'].' already exists.');
    }
    foreach($records as $record){
     query('INSERT INTO expenses(vehicle_id,party_id,bill_no,expense_date,created_by) VALUES(?,?,?,?,?)',[$v,$p,$record['bill_no'],$record['expense_date'],$u['id']]);
     $id=(int)db()->lastInsertId();
     foreach($record['items'] as $item)query('INSERT INTO expense_items(expense_id,name,qty,total) VALUES(?,?,?,?)',[$id,$item['name'],$item['qty'],$item['total']]);
     audit('Recorded expense',$id);
    }
   });out(['ok'=>true]);
  }
  if($action==='user'){
   allow(['admin']);$role=txt($d,'role',20);if(!in_array($role,['admin','manager','accountant','viewer'],true))fail('Invalid user type.');$pw=(string)($d['password']??'');if(strlen($pw)<12||strlen($pw)>128)fail('Use a password between 12 and 128 characters.');$username=txt($d,'username',80);if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username))fail('Invalid username.');
   transaction(function()use($d,$pw,$role,$username){query('INSERT INTO users(name,username,password_hash,role) VALUES(?,?,?,?)',[txt($d,'name',120),$username,password_hash($pw,PASSWORD_DEFAULT),$role]);audit('Created user',(int)db()->lastInsertId());});out(['ok'=>true]);
  }
  if($action==='toggle_user'){
   allow(['admin']);$id=(int)($d['id']??0);if($id===$u['id'])fail('You cannot disable your own account.');transaction(function()use($id){$target=query('SELECT id FROM users WHERE id=? FOR UPDATE',[$id])->fetch();if(!$target)fail('User not found.');query('UPDATE users SET active=1-active WHERE id=?',[$id]);audit('Changed user status',$id);});out(['ok'=>true]);
  }
  if($action==='document'){
   allow(['admin','manager','accountant']);$v=master($d,'vehicle_id','vehicle');$type=master($d,'document_id','document');$f=$_FILES['file']??null;
   if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>10*1024*1024)fail('Upload a PDF, JPEG or PNG, maximum 10 MB.');
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null;if(!$ext)fail('Only PDF, JPEG and PNG files are allowed.');
   $filename=bin2hex(random_bytes(24)).'.'.$ext;$path=dirname(__DIR__).'/storage/documents/'.$filename;
   $expiry=empty($d['expiry_date'])?null:dateval($d,'expiry_date');if(!move_uploaded_file($f['tmp_name'],$path))fail('Unable to store file.');chmod($path,0600);
   try{transaction(function()use($v,$type,$f,$filename,$mime,$expiry,$u){query('INSERT INTO documents(vehicle_id,document_id,original_name,stored_name,mime,expiry_date,created_by) VALUES(?,?,?,?,?,?,?)',[$v,$type,mb_substr(basename($f['name']),0,255),$filename,$mime,$expiry,$u['id']]);audit('Uploaded document',(int)db()->lastInsertId());});}catch(Throwable $e){unlink($path);throw $e;}out(['ok'=>true]);
  }
  fail('Unknown action.',404);
 }
 $u=user();
 if($action==='download'){
  $f=query('SELECT * FROM documents WHERE id=?',[(int)($_GET['id']??0)])->fetch();if(!$f)fail('Document not found.',404);$path=dirname(__DIR__).'/storage/documents/'.$f['stored_name'];if(!is_file($path))fail('File unavailable.',404);
  header('Content-Type: '.$f['mime']);header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($f['original_name']));header('Content-Length: '.filesize($path));readfile($path);exit;
 }
 if($action==='state'){
  $state=[];foreach(['masters','purchases','sales','expenses','expense_items','documents'] as $table){$cols=$table==='documents'?'id,vehicle_id,document_id,original_name,expiry_date,created_at':'*';$state[$table]=query("SELECT $cols FROM $table ORDER BY id DESC")->fetchAll();}
  $state['users']=$u['role']==='admin'?query('SELECT id,name,username,role,active FROM users ORDER BY id DESC')->fetchAll():[];
  $state['audit']=query('SELECT a.*,u.name AS user_name FROM audit_log a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 100')->fetchAll();out($state);
 }
 fail('Not found.',404);
}catch(ApiError $e){out(['error'=>$e->getMessage()],$e->status);}catch(PDOException $e){error_log('CarProject database error: '.$e->getCode());fail($e->getCode()==='23000'?'This record already exists or conflicts with a linked record.':'Database operation failed. Check server logs.',409);}catch(JsonException $e){fail('Invalid JSON.');}catch(Throwable $e){error_log('CarProject error: '.$e->getMessage());fail('Unable to complete request. Check server logs.',500);}
