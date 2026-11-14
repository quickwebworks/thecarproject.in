<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/report-demo.php';
function verify(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function fixture():PDO{
 $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec("CREATE TABLE masters(id INTEGER PRIMARY KEY,kind TEXT,name TEXT,details TEXT,UNIQUE(kind,name));CREATE TABLE purchases(id INTEGER PRIMARY KEY,vehicle_id INTEGER UNIQUE,bank_id INTEGER,client_id INTEGER,yard_id INTEGER,purchase_date TEXT,amount INTEGER,yard_expense INTEGER,manager_name TEXT,manager_contact TEXT,created_by INTEGER);CREATE TABLE expenses(id INTEGER PRIMARY KEY,vehicle_id INTEGER,party_id INTEGER,bill_no TEXT UNIQUE,expense_date TEXT,created_by INTEGER);CREATE TABLE expense_items(id INTEGER PRIMARY KEY,expense_id INTEGER,name TEXT,qty INTEGER,total INTEGER);CREATE TABLE sales(id INTEGER PRIMARY KEY,vehicle_id INTEGER UNIQUE,client_id INTEGER,sale_date TEXT,amount INTEGER,sale_expense INTEGER,salesperson TEXT,created_by INTEGER);CREATE TABLE audit_log(id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT);");return $db;
}
$db=fixture();$db->exec("INSERT INTO masters(kind,name,details) VALUES('vehicle','REAL-EXISTING','UNCHANGED')");seedReportDemoRecords($db,1);
foreach(['purchases'=>50,'sales'=>35,'expenses'=>100,'expense_items'=>100,'audit_log'=>1] as $table=>$count)verify((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$count,$table.' count');
verify((int)$db->query('SELECT COUNT(*) FROM purchases p LEFT JOIN sales s ON s.vehicle_id=p.vehicle_id WHERE s.id IS NULL')->fetchColumn()===15,'Unsold count');
verify($db->query("SELECT details FROM masters WHERE name='REAL-EXISTING'")->fetchColumn()==='UNCHANGED','Existing record preserved');
verify((int)$db->query('SELECT COUNT(*) FROM expenses e JOIN purchases p ON p.vehicle_id=e.vehicle_id WHERE e.expense_date<p.purchase_date')->fetchColumn()===0,'Bill chronology');
verify((int)$db->query("SELECT COUNT(*) FROM sales s JOIN expenses e ON e.vehicle_id=s.vehicle_id WHERE s.sale_date<e.expense_date")->fetchColumn()===0,'Sale chronology');
$sql='SELECT s.amount-p.amount-p.yard_expense-s.sale_expense-(SELECT SUM(i.total) FROM expenses e JOIN expense_items i ON i.expense_id=e.id WHERE e.vehicle_id=p.vehicle_id) profit FROM sales s JOIN purchases p ON p.vehicle_id=s.vehicle_id';
$profits=$db->query($sql)->fetchAll(PDO::FETCH_COLUMN);verify(array_sum($profits)===717800,'Expected realized profit');$losses=count(array_filter($profits,fn($v)=>$v<0));verify($losses===5,'Five loss-making sales');
try{seedReportDemoRecords($db,1);throw new LogicException('Duplicate seed accepted');}catch(RuntimeException $e){verify(str_contains($e->getMessage(),'already exist'),'Duplicate guard');}
verify((int)$db->query('SELECT COUNT(*) FROM purchases')->fetchColumn()===50,'No duplicates after repeat');
$bad=fixture();$bad->exec("CREATE TRIGGER reject_bill BEFORE INSERT ON expenses WHEN NEW.bill_no='REPORT-DEMO-2026-005-A' BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
try{seedReportDemoRecords($bad,1);throw new LogicException('Failure fixture did not fail');}catch(PDOException $e){}
foreach(['masters','purchases','sales','expenses','expense_items','audit_log'] as $table)verify((int)$bad->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===0,'Atomic rollback: '.$table);
echo 'PASS: 50 purchases, 35 sales (5 losses), 15 unsold, 100 bills; existing data preserved; duplicate prevention and atomic rollback. Net profit: '.array_sum($profits)."\n";
