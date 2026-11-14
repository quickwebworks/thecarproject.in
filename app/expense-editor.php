<?php
declare(strict_types=1);
function updateExpenseRecord(array $data):void {
 $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)fail('Select a valid expense.');
 transaction(function()use($data,$id){
  $original=query('SELECT * FROM expenses WHERE id=?',[$id])->fetch();if(!$original)fail('Expense not found.',404);
  $purchase=query('SELECT * FROM purchases WHERE vehicle_id=? FOR UPDATE',[$original['vehicle_id']])->fetch();if(!$purchase)fail('Purchase not found.',404);
  $old=query('SELECT * FROM expenses WHERE id=? FOR UPDATE',[$id])->fetch();if(!$old)fail('Expense not found.',404);assertWorkspaceDate($old['expense_date']);
  if(master($data,'vehicle_id','vehicle')!==(int)$old['vehicle_id'])fail('The expense vehicle cannot be changed.');
  $date=postingDate($data,'expense_date');if($date<=$purchase['purchase_date'])fail('Date of bill must be later than the vehicle purchase date.');
  $party=master($data,'party_id','party');$bill=txt($data,'bill_no',100);
  $items=$data['items']??null;$existing=query('SELECT * FROM expense_items WHERE expense_id=?',[$id])->fetchAll();
  if(!is_array($items)||count($items)!==count($existing)||!$items)fail('Include every existing bill item.');
  $expected=array_map(fn($r)=>(int)$r['id'],$existing);$seen=[];$values=[];
  foreach($items as $item){
   if(!is_array($item))fail('Invalid bill item.');$itemId=filter_var($item['id']??null,FILTER_VALIDATE_INT);
   if(!$itemId||!in_array($itemId,$expected,true)||isset($seen[$itemId]))fail('Invalid or repeated bill item.');$seen[$itemId]=true;
   $values[]=[$itemId,txt($item,'name',200),money($item,'total')];
  }
  query('UPDATE expenses SET party_id=?,bill_no=?,expense_date=? WHERE id=?',[$party,$bill,$date,$id]);
  foreach($values as [$itemId,$name,$total])query('UPDATE expense_items SET name=?,total=? WHERE id=? AND expense_id=?',[$name,$total,$itemId,$id]);
  audit('Updated expense bill',$id);
 });
}
