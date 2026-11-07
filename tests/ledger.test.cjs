const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
vm.runInNewContext(source+`
state=seed(); user={id:1,name:'QA',role:'admin'};
assert.equal(ledger().find(x=>x.vehicle_id===1).profit,48000);
assert.equal(ledger().find(x=>x.vehicle_id===2).profit,null);
assert.equal(ledger().filter(x=>x.sale).reduce((a,x)=>a+x.profit,0),156500);
// A four-item quantity has a full line total, so 20,000 is counted once.
state.expenses.push({id:900,vehicle_id:1,party_id:30,bill_no:'TEST',expense_date:'2026-11-07'});
state.expense_items.push({id:900,expense_id:900,name:'Tyres',qty:4,total:20000});
assert.equal(ledger().find(x=>x.vehicle_id===1).profit,28000);
assert.throws(()=>demoRequest('expense',{vehicle_id:1,party_id:30,bill_no:'TEST',expense_date:'2026-11-07',items:[]}),/unique/);
assert.throws(()=>demoRequest('sale',{vehicle_id:1,client_id:21,sale_date:'2026-11-07',amount:1,sale_expense:0}),/already sold/);
assert.throws(()=>demoRequest('sale',{vehicle_id:2,client_id:21,sale_date:'2020-01-01',amount:1,sale_expense:0}),/precede/);
user.role='viewer';assert.equal(can('purchase'),false);assert.equal(can('user'),false);
user.role='accountant';assert.equal(can('expense'),true);assert.equal(can('sale'),false);
console.log('PASS: ledger profit, unsold status, single-count expenses, bill uniqueness, duplicate sales, date validation and role visibility.');
`,{assert,console,Intl,Date,localStorage:{setItem(){}},setTimeout,clearTimeout});
