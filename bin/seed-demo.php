<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/workspaces.php';
$options=getopt('', ['company:']);
try{
 [$key,$config]=cliCompanySettings($options);
 if(!in_array($key,['demo_one','demo_two','demo_three'],true))throw new RuntimeException('Use --company=demo_one, demo_two or demo_three. Existing business data will not be seeded.');
 $db=new PDO($config['dsn'],$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $q=function(string $sql,array $params=[])use($db):PDOStatement{$s=$db->prepare($sql);$s->execute($params);return $s;};
 $db->beginTransaction();
 foreach(['purchases','sales','expenses','documents'] as $table)if((int)$q('SELECT COUNT(*) FROM '.$table)->fetchColumn()>0)throw new RuntimeException('Company already has business records. No demo data was added.');
 if((int)$q("SELECT COUNT(*) FROM masters WHERE kind NOT IN ('document','portal')")->fetchColumn()>0)throw new RuntimeException('Company already has business masters. No demo data was added.');
 $admin=$q("SELECT id FROM users WHERE role='admin' AND active=1 ORDER BY id LIMIT 1")->fetchColumn();
 if(!$admin)throw new RuntimeException('Run bin/install.php for this company first.');
 $add=function(string $kind,string $name,string $details='')use($q,$db):int{$q('INSERT INTO masters(kind,name,details) VALUES(?,?,?)',[$kind,$name,$details]);return (int)$db->lastInsertId();};
 $index=array_search($key,['demo_one','demo_two','demo_three'],true);$prefix=['PB10','DL01','HR26'][$index];$factor=[1,1.2,0.8][$index];
 $bank=$add('bank','DEMO BANK');$seller=$add('client','DEMO SELLER');$buyer=$add('client','DEMO BUYER');$party=$add('party','DEMO AUTO SERVICE');$yard=$add('yard','DEMO VEHICLE YARD');
 $v1=$add('vehicle',$prefix.'DM1001','DEMO BALENO · 2022 · PETROL');$v2=$add('vehicle',$prefix.'DM1002','DEMO CRETA · 2021 · DIESEL');
 $year=currentFinancialYear();$prior=($year-1).'-12-15';$date=$year.'-04-20';
 foreach([[$v1,$prior,400000*$factor],[$v2,$date,600000*$factor]] as [$v,$purchaseDate,$amount])$q('INSERT INTO purchases(vehicle_id,bank_id,client_id,yard_id,purchase_date,amount,yard_expense,manager_name,manager_contact,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$v,$bank,$seller,$yard,$purchaseDate,$amount,2000,'DEMO MANAGER','',$admin]);
 foreach([[$v1,'DEMO-BILL-001','ENGINE SERVICE',12000],[$v2,'DEMO-BILL-002','DETAILING',15000]] as [$v,$bill,$item,$amount]){
  $q('INSERT INTO expenses(vehicle_id,party_id,bill_no,expense_date,created_by) VALUES(?,?,?,?,?)',[$v,$party,$bill,$year.'-04-25',$admin]);$eid=(int)$db->lastInsertId();
  $q('INSERT INTO expense_items(expense_id,name,qty,total) VALUES(?,?,1,?)',[$eid,$item,$amount]);
 }
 $q('INSERT INTO sales(vehicle_id,client_id,sale_date,amount,sale_expense,salesperson,created_by) VALUES(?,?,?,?,?,?,?)',[$v2,$buyer,$year.'-05-25',700000*$factor,3000,'DEMO MANAGER',$admin]);
 $q('INSERT INTO audit_log(user_id,action) VALUES(?,?)',[$admin,'Added fictional demo company records']);
 $db->commit();echo 'Demo records added to '.$config['name'].'. Select FY '.$year.'–'.($year+1)." to see a sale and a carried-forward vehicle.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,$e instanceof PDOException?"Database setup failed. Check this company connection and installer.\n":$e->getMessage()."\n");exit(1);}
