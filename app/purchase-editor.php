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
  $bank=master($data,'bank_id','bank',false);$client=master($data,'client_id','client',false);$yard=master($data,'yard_id','yard',false);$portal=master($data,'portal_id','portal',false);
  query('UPDATE purchases SET bank_id=?,client_id=?,yard_id=?,portal_id=?,purchase_date=?,amount=?,yard_expense=?,manager_name=?,manager_contact=? WHERE id=?',[$bank,$client,$yard,$portal,$date,money($data,'amount'),money($data,'yard_expense'),txt($data,'manager_name',120,false),txt($data,'manager_contact',60,false),$id]);
  audit('Updated purchase',$id);
 });
}
