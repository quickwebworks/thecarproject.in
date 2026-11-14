<?php
declare(strict_types=1);

function reportDemoPlan():array {
 $models=['MARUTI SWIFT','HYUNDAI I20','HONDA CITY','TATA NEXON','KIA SONET','MAHINDRA XUV300','MARUTI BALENO','HYUNDAI CRETA','TOYOTA GLANZA','RENAULT KWID'];$rows=[];
 for($i=0;$i<50;$i++){
  $date=new DateTimeImmutable(sprintf('2026-%02d-%02d',4+intdiv($i,8),1+($i%8)*3));
  $amount=180000+($i%12)*45000+$i*700;$yard=1500+($i%5)*500;
  $bills=[2500+($i%7)*650,1200+($i%4)*900];$saleExpense=1000+($i%3)*500;
  $profit=$i%7===0?-(7000+$i*100):12000+($i%8)*4000;
  $rows[]=['number'=>'PB10TD'.(5001+$i),'model'=>'REPORT DEMO '.$models[$i%10].' · '.(2019+$i%5),'purchase_date'=>$date->format('Y-m-d'),'amount'=>$amount,'yard'=>$yard,'bills'=>$bills,'bill_dates'=>[$date->modify('+4 days')->format('Y-m-d'),$date->modify('+7 days')->format('Y-m-d')],'sold'=>$i%10<7,'sale_date'=>$date->modify('+15 days')->format('Y-m-d'),'sale_expense'=>$saleExpense,'sale_amount'=>$amount+$yard+array_sum($bills)+$saleExpense+$profit,'profit'=>$profit];
 }
 return $rows;
}
function seedReportDemoRecords(PDO $db,int $admin):void {
 $q=function(string $sql,array $params=[])use($db):PDOStatement{$s=$db->prepare($sql);$s->execute($params);return $s;};
 $db->beginTransaction();
 try{
  $plan=reportDemoPlan();
  foreach($plan as $row)if($q("SELECT id FROM masters WHERE kind='vehicle' AND name=?",[$row['number']])->fetchColumn())throw new RuntimeException('Report demo vehicles already exist in this company. No additional samples were added.');
  $add=function(string $kind,string $name,string $details='')use($q,$db):int{
   $id=$q('SELECT id FROM masters WHERE kind=? AND name=?',[$kind,$name])->fetchColumn();if($id)return (int)$id;
   $q('INSERT INTO masters(kind,name,details) VALUES(?,?,?)',[$kind,$name,$details]);return (int)$db->lastInsertId();
  };
  $bank=$add('bank','REPORT DEMO BANK');$seller=$add('client','REPORT DEMO SELLER');$buyer=$add('client','REPORT DEMO BUYER');$yard=$add('yard','REPORT DEMO YARD');$party=$add('party','REPORT DEMO WORKSHOP');
  foreach($plan as $i=>$row){
   $vehicle=$add('vehicle',$row['number'],$row['model']);
   $q('INSERT INTO purchases(vehicle_id,bank_id,client_id,yard_id,purchase_date,amount,yard_expense,manager_name,manager_contact,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[$vehicle,$bank,$seller,$yard,$row['purchase_date'],$row['amount'],$row['yard'],'REPORT DEMO MANAGER','',$admin]);
   foreach($row['bills'] as $j=>$amount){
    $bill=sprintf('REPORT-DEMO-2026-%03d-%s',$i+1,$j===0?'A':'B');
    $q('INSERT INTO expenses(vehicle_id,party_id,bill_no,expense_date,created_by) VALUES(?,?,?,?,?)',[$vehicle,$party,$bill,$row['bill_dates'][$j],$admin]);$id=(int)$db->lastInsertId();
    $q('INSERT INTO expense_items(expense_id,name,qty,total) VALUES(?,?,1,?)',[$id,$j===0?'REPORT DEMO SERVICE':'REPORT DEMO DETAILING',$amount]);
   }
   if($row['sold'])$q('INSERT INTO sales(vehicle_id,client_id,sale_date,amount,sale_expense,salesperson,created_by) VALUES(?,?,?,?,?,?,?)',[$vehicle,$buyer,$row['sale_date'],$row['sale_amount'],$row['sale_expense'],'REPORT DEMO MANAGER',$admin]);
  }
  $q('INSERT INTO audit_log(user_id,action) VALUES(?,?)',[$admin,'Loaded REPORT DEMO: 50 purchases, 35 sales, 15 unsold, 100 bills; FY 2026–2027']);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
