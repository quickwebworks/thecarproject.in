<?php
// Copy to companies.php ON THE HOST only. Never commit real passwords.
// The existing config.php connection remains the first company.
// Create each database/user in Hostinger before adding the connection here.
// Leave company keys stable: they also identify private document folders.
return [
 'demo_one'=>[
  'name'=>'DEMO COMPANY ONE','first_year'=>2026,
  'dsn'=>'mysql:host=localhost;port=3306;dbname=YOUR_DEMO_ONE_DATABASE;charset=utf8mb4',
  'username'=>'YOUR_DEMO_ONE_USER','password'=>'YOUR_DEMO_ONE_PASSWORD',
 ],
 'demo_two'=>[
  'name'=>'DEMO COMPANY TWO','first_year'=>2026,
  'dsn'=>'mysql:host=localhost;port=3306;dbname=YOUR_DEMO_TWO_DATABASE;charset=utf8mb4',
  'username'=>'YOUR_DEMO_TWO_USER','password'=>'YOUR_DEMO_TWO_PASSWORD',
 ],
 'demo_three'=>[
  'name'=>'DEMO COMPANY THREE','first_year'=>2026,
  'dsn'=>'mysql:host=localhost;port=3306;dbname=YOUR_DEMO_THREE_DATABASE;charset=utf8mb4',
  'username'=>'YOUR_DEMO_THREE_USER','password'=>'YOUR_DEMO_THREE_PASSWORD',
 ],
];
