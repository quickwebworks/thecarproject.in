<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/workspaces.php';
function fail(string $message,int $status=422):never {throw new RuntimeException($message,$status);}
function scopeCheck(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
function scopeReject(callable $fn):void{try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected rejection');}
scopeCheck(currentFinancialYear()===2026,'Fixed supported year');
scopeReject(fn()=>financialYearRange(2025));scopeReject(fn()=>financialYearRange(2027));
$range=financialYearRange(2026);
scopeCheck($range['start']==='2026-04-01'&&$range['end']==='2027-03-31','April–March range');
$_SESSION=['financial_year'=>2026,'workspace_token'=>'company-a-token'];
scopeCheck(assertWorkspaceDate('2026-04-01')==='2026-04-01','Opening boundary');
scopeCheck(assertWorkspaceDate('2027-03-31')==='2027-03-31','Closing boundary');
scopeReject(fn()=>assertWorkspaceDate('2026-03-31'));
scopeReject(fn()=>assertWorkspaceDate('2027-04-01'));
$_SERVER['HTTP_X_WORKSPACE_TOKEN']='old-company-token';scopeReject(fn()=>requireWorkspaceToken());
$_SERVER['HTTP_X_WORKSPACE_TOKEN']='company-a-token';requireWorkspaceToken();
$a=connectionIdentity(['dsn'=>'mysql:host=localhost;dbname=company_one']);
$b=connectionIdentity(['dsn'=>'mysql:host=localhost;dbname=company_two']);
$c=connectionIdentity(['dsn'=>'mysql:host=localhost;dbname=company_three']);
scopeCheck(count(array_unique([$a,$b,$c]))===3,'Separate database identities');
scopeReject(fn()=>connectionIdentity(['dsn'=>'sqlite:local.db']));
echo "PASS: fiscal year boundaries, date rejection, stale-workspace protection and separate connection identities.\n";
