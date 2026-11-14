<?php
declare(strict_types=1);
function updateSaleRecord(array $data):void {
 $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)fail('Select a valid sale.');
 transaction(function()use($data,$id){
  $original=query('SELECT * FROM sales WHERE id=?',[$id])->fetch();if(!$original)fail('Sale not found.',404);
  $purchase=query('SELECT * FROM purchases WHERE vehicle_id=? FOR UPDATE',[$original['vehicle_id']])->fetch();if(!$purchase)fail('Purchase not found.',404);
  $old=query('SELECT * FROM sales WHERE id=? FOR UPDATE',[$id])->fetch();if(!$old)fail('Sale not found.',404);assertWorkspaceDate($old['sale_date']);
  if(master($data,'vehicle_id','vehicle')!==(int)$old['vehicle_id'])fail('The sale vehicle cannot be changed.');
  $date=postingDate($data,'sale_date');if($date<$purchase['purchase_date'])fail('Sale date cannot precede purchase date.');$client=master($data,'client_id','client');
  query('UPDATE sales SET client_id=?,sale_date=?,amount=?,sale_expense=?,salesperson=? WHERE id=?',[$client,$date,money($data,'amount'),money($data,'sale_expense'),txt($data,'salesperson',120,false),$id]);audit('Updated sale',$id);
 });
}
