<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/company-provisioner.php';
function verify(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
for($i=0;$i<100;$i++){
 $password=companyDatabasePassword();
 verify(strlen($password)>=20&&preg_match('/[A-Z]/',$password)&&preg_match('/[a-z]/',$password)&&preg_match('/[0-9]/',$password)&&preg_match('/[^A-Za-z0-9]/',$password),'Generated password must meet hosting rules');
}
$response=json_encode(['errors'=>['password'=>['The submitted SECRET_PASSWORD needs an uppercase letter.'],'website_domain'=>['SECRET_TOKEN invalid value'],'other'=>['UNKNOWN_SECRET']]]);
$summary=hostingerValidationSummary($response);
verify(str_contains($summary,'Database password')&&str_contains($summary,'uppercase letter required')&&str_contains($summary,'Website domain'),'Known validation fields shown');
verify(!str_contains($summary,'SECRET')&&!str_contains($summary,'UNKNOWN'),'Provider values must never be exposed');
verify(hostingerValidationSummary('not JSON')===''&&hostingerValidationSummary('{"errors":null}')==='','Malformed response handled');
echo "PASS: 100 generated passwords meet hosting complexity; validation messages identify fields without exposing provider values.\n";
