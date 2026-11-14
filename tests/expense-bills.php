<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/expense-bills.php';
function expenseCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function expenseRejects(callable $fn):void {try{$fn();}catch(ApiError $e){return;}throw new RuntimeException('Expected invalid bill rejection');}
$rows=prepareExpenseBills(['bills'=>[
 ['bill_no'=>'row-001','bill_date'=>'2026-11-14','name'=>'engine service','total'=>'100.50'],
 ['bill_no'=>'row-002','bill_date'=>'2026-11-13','name'=>'detailing','total'=>'200'],
]],'2026-06-12');
expenseCheck(count($rows)===2,'One expense per row');
expenseCheck($rows[0]['bill_no']==='ROW-001','Uppercase bill number');
expenseCheck($rows[0]['items'][0]['qty']==='1','No quantity multiplier');
expenseCheck($rows[0]['items'][0]['total']==='100.50','Decimal preserved');
expenseRejects(fn()=>prepareExpenseBills(['bills'=>[
 ['bill_no'=>'same','bill_date'=>'2026-11-14','name'=>'A','total'=>'10'],
 ['bill_no'=>'SAME','bill_date'=>'2026-11-14','name'=>'B','total'=>'20']
]],'2026-06-12'));
expenseRejects(fn()=>prepareExpenseBills(['bills'=>[['bill_no'=>'DATE','bill_date'=>'2026-02-30','name'=>'A','total'=>'1']]],'2026-01-01'));
expenseRejects(fn()=>prepareExpenseBills(['bills'=>[['bill_no'=>'OLD','bill_date'=>'2026-05-01','name'=>'A','total'=>'1']]],'2026-06-12'));
expenseRejects(fn()=>prepareExpenseBills(['bills'=>[['bill_no'=>'NEG','bill_date'=>'2026-11-14','name'=>'A','total'=>'-1']]],'2026-06-12'));
$legacy=prepareExpenseBills(['bill_no'=>'LEGACY','expense_date'=>'2026-11-14','items'=>[['name'=>'Four tyres','qty'=>'4','total'=>'20000']]],'2026-06-12');
expenseCheck($legacy[0]['items'][0]['total']==='20000','Legacy full line total preserved');
echo "PASS: server bill rows, uniqueness, date/amount validation and legacy compatibility.\n";
