<?php
declare(strict_types=1);
function seedDemoRecords(PDO $db,string $key,int $admin):void {
 $q=function(string $sql,array $params=[])use($db):PDOStatement{$s=$db->prepare($sql);$s->execute($params);return $s;};
 $db->beginTransaction();
 try{
  foreach(['purchases','sales','expenses','documents'] as $table)if((int)$q('SELECT COUNT(*) FROM '.$table)->fetchColumn()>0)throw new RuntimeException('Demo company already has business records.');
  if((int)$q("SELECT COUNT(*) FROM masters WHERE kind NOT IN ('document','portal')")->fetchColumn()>0)throw new RuntimeException('Demo company already has business masters.');
  $add=function(string $kind,string $name,string $details='')use($q,$db):int{$q('INSERT INTO masters(kind,name,details) VALUES(?,?,?)',[$kind,$name,$details]);return (int)$db->lastInsertId();};
  $index=['demo_one'=>0,'demo_two'=>1,'demo_three'=>2][$key]??0;$prefix=['PB10','DL01','HR26'][$index];$factor=[1,1.2,0.8][$index];
  $bank=$add('bank','DEMO BANK');$seller=$add('client','DEMO SELLER');$buyer=$add('client','DEMO BUYER');$party=$add('party','DEMO AUTO SERVICE');$yard=$add('yard','DEMO VEHICLE YARD');
  $v1=$add('vehicle',$prefix.'DM1001','DEMO BALENO · 2022 · PETROL');$v2=$add('vehicle',$prefix.'DM1002','DEMO CRETA · 2021 · DIESEL');
  foreach([[$v1,'2026-04-05',400000*$factor],[$v2,'2026-04-20',600000*$factor]] as [$v,$date,$amount])$q('INSERT INTO purchases(vehicle_id,bank_id,client_id,yard_id,purchase_date,amount,yard_expense,manager_name,manager_contact,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$v,$bank,$seller,$yard,$date,$amount,2000,'DEMO MANAGER','',$admin]);
  foreach([[$v1,'DEMO-BILL-001','ENGINE SERVICE',12000],[$v2,'DEMO-BILL-002','DETAILING',15000]] as [$v,$bill,$name,$amount]){
   $q('INSERT INTO expenses(vehicle_id,party_id,bill_no,expense_date,created_by) VALUES(?,?,?,?,?)',[$v,$party,$bill,'2026-04-25',$admin]);$eid=(int)$db->lastInsertId();
   $q('INSERT INTO expense_items(expense_id,name,qty,total) VALUES(?,?,1,?)',[$eid,$name,$amount]);
  }
  $q('INSERT INTO sales(vehicle_id,client_id,sale_date,amount,sale_expense,salesperson,created_by) VALUES(?,?,?,?,?,?,?)',[$v2,$buyer,'2026-05-25',700000*$factor,3000,'DEMO MANAGER',$admin]);
  $q('INSERT INTO audit_log(user_id,action) VALUES(?,?)',[$admin,'Added fictional demo records for FY 2026–2027']);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
