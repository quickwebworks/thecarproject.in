<?php
declare(strict_types=1);
function updatePurchaseRecord(array $data):void {
 $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)fail('Select a valid purchase.');
 transaction(function()use($data,$id){
  $old=query('SELECT * FROM purchases WHERE id=? FOR UPDATE',[$id])->fetch();if(!$old)fail('Purchase not found.',404);
  assertWorkspaceDate($old['purchase_date']);
  $vehicle=master($data,'vehicle_id','vehicle');
  if($vehicle!==(int)$old['vehicle_id'])fail('The purchase vehicle cannot be changed. Edit its registration in Masters instead.');
  $date=postingDate($data,'purchase_date');
  $saleDate=query('SELECT sale_date FROM sales WHERE vehicle_id=?',[$vehicle])->fetchColumn();
  $billDate=query('SELECT MIN(expense_date) FROM expenses WHERE vehicle_id=?',[$vehicle])->fetchColumn();
  if(($saleDate&&$date>$saleDate)||($billDate&&$date>$billDate))fail('Purchase date cannot be after an existing sale or expense bill.');
  $bank=master($data,'bank_id','bank',false);$client=master($data,'client_id','client',false);$portal=master($data,'portal_id','portal',false);
  $releaseDate=query('SELECT MIN(release_date) FROM yard_expenses WHERE vehicle_id=? AND deleted_at IS NULL',[$vehicle])->fetchColumn();if($releaseDate&&$date>$releaseDate)fail('Purchase date cannot be after an existing yard release date.');
  query('UPDATE purchases SET bank_id=?,client_id=?,portal_id=?,purchase_date=?,amount=?,manager_name=?,manager_contact=? WHERE id=?',[$bank,$client,$portal,$date,money($data,'amount'),txt($data,'manager_name',120,false),txt($data,'manager_contact',60,false),$id]);
  query('UPDATE yard_expenses SET cost_date=? WHERE legacy_purchase_id=? AND release_date IS NULL',[$date,$id]);
  audit('Updated purchase',$id);
 });
}

