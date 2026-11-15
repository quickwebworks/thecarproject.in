<?php
declare(strict_types=1);
function ensureDocumentExpectations():void {
 $columns=query("SHOW COLUMNS FROM purchases LIKE 'release_letter_expected'")->fetchAll();
 if(!$columns){
  try{query('ALTER TABLE purchases ADD COLUMN release_letter_expected TINYINT NULL DEFAULT NULL');}
  catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1060)throw $e;}
 }
}
