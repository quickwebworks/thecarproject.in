<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
function check(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);}
function rejects(callable $fn):void {try{$fn();}catch(ApiError $e){return;}throw new RuntimeException('Expected rejection');}
check(money(['amount'=>'12345.67'],'amount')==='12345.67','decimal amount');
check(dateval(['date'=>'2024-02-29'],'date')==='2024-02-29','leap date');
rejects(fn()=>money(['amount'=>'-1'],'amount'));
rejects(fn()=>money(['amount'=>'1.999'],'amount'));
rejects(fn()=>money(['amount'=>'1e6'],'amount'));
rejects(fn()=>dateval(['date'=>'2026-02-30'],'date'));
rejects(fn()=>txt(['name'=>''],'name'));
check(password_verify('test password long enough',password_hash('test password long enough',PASSWORD_DEFAULT)),'password hashing');
echo "PASS: decimal amounts, negative/precision rejection, date validation and password hash roundtrip.\n";
