<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/purchase-editor.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE masters(id INTEGER PRIMARY KEY,kind TEXT);INSERT INTO masters VALUES(1,'vehicle'),(2,'bank'),(3,'client'),(4,'yard'),(5,'portal'),(6,'vehicle');CREATE TABLE purchases(id INTEGER PRIMARY KEY,vehicle_id INTEGER,bank_id INTEGER,client_id INTEGER,yard_id INTEGER,portal_id INTEGER,purchase_date TEXT,amount TEXT,yard_expense TEXT,manager_name TEXT,manager_contact TEXT,created_by INTEGER);INSERT INTO purchases VALUES(10,1,2,3,4,5,'2026-04-05','400000','2000','OLD','',7);CREATE TABLE sales(vehicle_id INTEGER,sale_date TEXT,amount INTEGER);INSERT INTO sales VALUES(1,'2026-05-20',500000);CREATE TABLE expenses(vehicle_id INTEGER,expense_date TEXT);INSERT INTO expenses VALUES(1,'2026-04-20');CREATE TABLE audit_log(action TEXT,entity_id INTEGER);");
$db->exec('CREATE TABLE yard_expenses(id INTEGER PRIMARY KEY,vehicle_id INTEGER,release_date TEXT,cost_date TEXT,legacy_purchase_id INTEGER,deleted_at TEXT)');
function fail(string $m,int $status=422):never{throw new RuntimeException($m,$status);}
function query(string $sql,array $params=[]):PDOStatement{global $db;$s=$db->prepare(str_replace(' FOR UPDATE','',$sql));$s->execute($params);return $s;}
function transaction(callable $fn):void{global $db;$db->beginTransaction();try{$fn();$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}}
function assertWorkspaceDate(string $date):string{if($date<'2026-04-01'||$date>'2027-03-31')fail('Wrong financial year');return $date;}
function postingDate(array $d,string $key):string{$date=$d[$key]??'';$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)fail('Invalid date');return assertWorkspaceDate($date);}
function master(array $d,string $key,string $kind,bool $required=true):?int{$id=(int)($d[$key]??0);if(!$id&&!$required)return null;if(!query('SELECT id FROM masters WHERE id=? AND kind=?',[$id,$kind])->fetchColumn())fail('Invalid master');return $id;}
function money(array $d,string $key):string{$value=(string)($d[$key]??'');if(!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$value))fail('Invalid amount');return $value;}
function txt(array $d,string $key,int $max,bool $required=false):string{$value=(string)($d[$key]??'');if(strlen($value)>$max)fail('Text too long');return strtoupper($value);}
function audit(string $action,int $id):void{query('INSERT INTO audit_log VALUES(?,?)',[$action,$id]);}
function check(bool $ok,string $why):void{if(!$ok)throw new LogicException($why);}
$data=['id'=>10,'vehicle_id'=>1,'bank_id'=>2,'client_id'=>3,'yard_id'=>4,'portal_id'=>5,'purchase_date'=>'2026-04-06','amount'=>'425000.50','yard_expense'=>'3500','manager_name'=>'Updated','manager_contact'=>'1234567890'];
updatePurchaseRecord($data);$row=query('SELECT * FROM purchases WHERE id=10')->fetch();check($row['amount']==='425000.50'&&$row['manager_name']==='UPDATED','Purchase corrected');check((int)$row['created_by']===7,'Creator preserved');check((int)query('SELECT COUNT(*) FROM purchases')->fetchColumn()===1,'No duplicate purchase');check((int)query('SELECT COUNT(*) FROM sales')->fetchColumn()===1&&(int)query('SELECT COUNT(*) FROM expenses')->fetchColumn()===1,'Related rows preserved');
foreach([['purchase_date'=>'2026-04-21'],['purchase_date'=>'2027-04-01'],['vehicle_id'=>6],['amount'=>'-1'],['id'=>999]] as $changes){try{updatePurchaseRecord(array_merge($data,$changes));throw new LogicException('Invalid correction accepted');}catch(RuntimeException $e){}}
check(query('SELECT amount FROM purchases WHERE id=10')->fetchColumn()==='425000.50','Invalid changes rolled back');check((int)query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===1,'Only successful correction audited');
echo "PASS: purchase update preserves links and creator, validates dates/vehicle/amount, and rolls back invalid edits.\n";
