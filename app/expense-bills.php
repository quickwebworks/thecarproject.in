<?php
declare(strict_types=1);

/**
 * Convert row-based bills (or an older multi-item request) to validated
 * expense records. Every new bill row has exactly one item and quantity 1.
 */
function prepareExpenseBills(array $data,string $purchaseDate):array {
 if(array_key_exists('bills',$data)){
  if(!is_array($data['bills'])||count($data['bills'])<1||count($data['bills'])>100)fail('Add between 1 and 100 bill rows.');
  $records=[];
  foreach($data['bills'] as $row){
   if(!is_array($row))fail('Invalid bill row.');
   $party=filter_var($row['party_id']??($data['party_id']??null),FILTER_VALIDATE_INT);if(!$party||$party<1)fail('Select a party name for every bill row.');
   $records[]=['party_id'=>$party,'bill_no'=>txt($row,'bill_no',100),'expense_date'=>dateval($row,'bill_date'),
    'items'=>[['name'=>txt($row,'name',200),'qty'=>'1','total'=>money($row,'total')]]];
  }
 }else{
  // Preserve compatibility with existing clients and historical multi-item bills.
  $items=$data['items']??null;
  if(!is_array($items)||count($items)<1||count($items)>100)fail('Add between 1 and 100 bill items.');
  $validated=[];
  foreach($items as $item){
   if(!is_array($item))fail('Invalid bill item.');
   $qty=money($item,'qty');
   if((float)$qty<=0||(float)$qty>99999999)fail('Quantity must be positive and within range.');
   $validated[]=['name'=>txt($item,'name',200),'qty'=>$qty,'total'=>money($item,'total')];
  }
  $party=filter_var($data['party_id']??null,FILTER_VALIDATE_INT);if(!$party||$party<1)fail('Select a party name.');
  $records=[['party_id'=>$party,'bill_no'=>txt($data,'bill_no',100),'expense_date'=>dateval($data,'expense_date'),'items'=>$validated]];
 }
 $seen=[];
 foreach($records as $record){
  $key=mb_strtoupper($record['bill_no'],'UTF-8');
  if(isset($seen[$key]))fail('Every row needs a unique bill number. Repeated: '.$record['bill_no']);
  $seen[$key]=true;
  if($record['expense_date']<=$purchaseDate)fail('Date of bill must be later than the vehicle purchase date ('.$purchaseDate.').');
 }
 return $records;
}

