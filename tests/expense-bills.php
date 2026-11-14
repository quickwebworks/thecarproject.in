<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/expense-bills.php';
function fail(string $m,int $status=422):never{throw new RuntimeException($m,$status);}
function txt(array $d,string $k,int $max):string{$v=strtoupper(trim((string)($d[$k]??'')));if($v===''||strlen($v)>$max)fail('Invalid text');return $v;}
function money(array $d,string $k):string{$v=(string)($d[$k]??'');if(!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$v))fail('Invalid amount');return $v;}
function dateval(array $d,string $k):string{$v=(string)($d[$k]??'');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$date||$date->format('Y-m-d')!==$v)fail('Invalid date');return $v;}
function check(bool $ok,string $m):void{if(!$ok)throw new LogicException($m);}
function rejects(callable $fn):void{try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected rejection');}
$bill=['party_id'=>30,'bill_no'=>'ROW-1','bill_date'=>'2026-06-13','name'=>'SERVICE','total'=>'100.50'];
$other=array_merge($bill,['party_id'=>31,'bill_no'=>'ROW-2','total'=>'200']);
$rows=prepareExpenseBills(['bills'=>[$bill,$other]],'2026-06-12');check(count($rows)===2&&$rows[0]['party_id']===30&&$rows[1]['party_id']===31,'Separate row parties');check($rows[0]['items'][0]['total']==='100.50'&&$rows[0]['items'][0]['qty']==='1','Line total preserved');
foreach([['bill_date'=>'2026-06-12'],['bill_date'=>'2026-06-11'],['bill_date'=>'2026-02-30'],['total'=>'-1'],['party_id'=>null],['party_id'=>0]] as $change)rejects(fn()=>prepareExpenseBills(['bills'=>[array_merge($bill,$change)]],'2026-06-12'));
rejects(fn()=>prepareExpenseBills(['bills'=>[$bill,$bill]],'2026-06-12'));
$legacy=prepareExpenseBills(['party_id'=>30,'bill_no'=>'LEGACY','expense_date'=>'2026-06-13','items'=>[['name'=>'Tyres','qty'=>'4','total'=>'20000']]],'2026-06-12');check($legacy[0]['party_id']===30&&$legacy[0]['items'][0]['total']==='20000','Legacy API preserved');
echo "PASS: per-row parties, positive IDs, strict later bill dates, duplicates, line totals and legacy requests.\n";
