<?php
declare(strict_types=1);
function yardExpensesSchema():string {return "CREATE TABLE IF NOT EXISTS yard_expenses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 vehicle_id BIGINT UNSIGNED NOT NULL, yard_id BIGINT UNSIGNED NULL,
 release_date DATE NULL, cost_date DATE NOT NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 legacy_purchase_id BIGINT UNSIGNED NULL UNIQUE,
 created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at TIMESTAMP NULL DEFAULT NULL,
 FOREIGN KEY(vehicle_id) REFERENCES masters(id), FOREIGN KEY(yard_id) REFERENCES masters(id),
 FOREIGN KEY(legacy_purchase_id) REFERENCES purchases(id), FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX(vehicle_id), INDEX(cost_date), CHECK(amount>=0)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";}
function ensureYardExpenses():void {
 static $ready=[];$key=$_SESSION['company_key'];if(isset($ready[$key]))return;
 if(!(int)query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='yard_expenses'")->fetchColumn())db()->exec(yardExpensesSchema());
 // Keep the source purchase fields intact. The unique legacy ID makes this
 // incremental migration repeatable, including after a legacy row is deleted.
 query('INSERT IGNORE INTO yard_expenses(vehicle_id,yard_id,release_date,cost_date,amount,legacy_purchase_id,created_by) SELECT p.vehicle_id,p.yard_id,NULL,p.purchase_date,p.yard_expense,p.id,p.created_by FROM purchases p WHERE (p.yard_expense>0 OR p.yard_id IS NOT NULL) AND NOT EXISTS(SELECT 1 FROM yard_expenses y WHERE y.legacy_purchase_id=p.id)');
 $ready[$key]=true;
}
function saveYardExpense(array $data,array $actor,bool $delete=false):void {
 $id=isset($data['id'])?filter_var($data['id'],FILTER_VALIDATE_INT):null;
 if(($delete||isset($data['id']))&&(!$id||$id<1))fail('Select a valid yard expense.');
 transaction(function()use($data,$actor,$delete,$id){
  $old=$id?query('SELECT * FROM yard_expenses WHERE id=? AND deleted_at IS NULL',[$id])->fetch():null;
  if($id&&!$old)fail('Yard expense not found.',404);
  $vehicle=$old?(int)$old['vehicle_id']:master($data,'vehicle_id','vehicle');
  $purchase=query('SELECT * FROM purchases WHERE vehicle_id=? FOR UPDATE',[$vehicle])->fetch();if(!$purchase)fail('Select a purchased vehicle.');
  if($old){$old=query('SELECT * FROM yard_expenses WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id])->fetch();if(!$old)fail('Yard expense not found.',404);assertWorkspaceDate($old['cost_date']);}
  if($delete){query('UPDATE yard_expenses SET deleted_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);audit('Deleted yard expense',$id);return;}
  if($old&&master($data,'vehicle_id','vehicle')!==$vehicle)fail('The yard expense vehicle cannot be changed.');
  $yard=master($data,'yard_id','yard');$date=postingDate($data,'release_date');if($date<$purchase['purchase_date'])fail('Yard release date cannot precede the vehicle purchase date.');$amount=money($data,'amount');
  if($id){query('UPDATE yard_expenses SET yard_id=?,release_date=?,cost_date=?,amount=? WHERE id=?',[$yard,$date,$date,$amount,$id]);audit('Updated yard expense',$id);}
  else{query('INSERT INTO yard_expenses(vehicle_id,yard_id,release_date,cost_date,amount,created_by) VALUES(?,?,?,?,?,?)',[$vehicle,$yard,$date,$date,$amount,$actor['id']]);audit('Recorded yard expense',(int)db()->lastInsertId());}
 });
}
