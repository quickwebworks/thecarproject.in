<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/company-provisioner.php';
function verify(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
for($i=0;$i<100;$i++){
 $password=companyDatabasePassword();
 verify(strlen($password)>=20&&preg_match('/[A-Z]/',$password)&&preg_match('/[a-z]/',$password)&&preg_match('/[0-9]/',$password)&&preg_match('/[^A-Za-z0-9]/',$password),'Generated password must meet hosting rules');
}
$token='token-secret-123';$password='Password7!secret';
$response=json_encode(['message'=>'Database quota exceeded for this hosting plan.','errors'=>['password'=>['Rejected '.$password],'hosting'=>['API value '.$token]],'input'=>['password'=>'UNKNOWN_REQUEST_SECRET'],'correlation_id'=>'12345678-1234-1234-1234-123456789abc']);
$summary=hostingerValidationSummary($response,[$token,$password]);
verify(str_contains($summary,'Database quota exceeded'),'Top level provider explanation included');
verify(!str_contains($summary,$token)&&!str_contains($summary,$password)&&!str_contains($summary,'UNKNOWN_REQUEST_SECRET'),'Credentials and echoed input must never be exposed');
verify(str_contains($summary,'Support reference:'),'Support reference included');
$nested=hostingerValidationSummary(json_encode(['error'=>['message'=>'Website is not assigned to this account.','errors'=>['name'=>['Maximum name length exceeded.']]]]),[$token,$password]);
verify(str_contains($nested,'Website is not assigned')&&str_contains($nested,'Maximum name length'),'Nested provider errors included');
$encoded=hostingerValidationSummary(json_encode(['message'=>'<b>'.rawurlencode($password).'</b> Authorization=secret-value']),[$token,$password]);
verify(!str_contains($encoded,'secret-value')&&!str_contains($encoded,rawurlencode($password))&&!str_contains($encoded,'<b>'),'Encoded values and authorization redacted');
verify(str_contains(hostingerValidationSummary('not JSON'),'no readable JSON'),'Non JSON handled');
echo "PASS: compliant passwords; top-level/nested error details; support references; raw and encoded secret redaction; echoed input excluded.\n";
