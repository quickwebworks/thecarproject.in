const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
vm.runInNewContext(source+`
state=seed();user={id:1,name:'QA',role:'admin'};
const base={vehicle_id:1,party_id:30,bills:[
 {bill_no:'new-001',bill_date:'2026-11-14',name:'engine oil',total:'100.50'},
 {bill_no:'new-002',bill_date:'2026-11-13',name:'detailing',total:'200'}
]};
demoRequest('expense',normalizeEntry('expense',base));
assert.equal(state.expenses.filter(x=>x.bill_no.startsWith('NEW-')).length,2);
assert.equal(ledger().find(x=>x.vehicle_id===1).profit,47699.5);
assert.equal(state.expense_items.find(x=>x.name==='ENGINE OIL').qty,1);
let count=state.expenses.length,items=state.expense_items.length;
assert.throws(()=>demoRequest('expense',{vehicle_id:1,party_id:30,bills:[
 {bill_no:'UNUSED',bill_date:'2026-11-14',name:'Valid',total:'10'},
 {bill_no:'new-001',bill_date:'2026-11-14',name:'Duplicate',total:'10'}
]}),/already exists/);
assert.equal(state.expenses.length,count);assert.equal(state.expense_items.length,items);
assert.throws(()=>validateExpenseBillRows([
 {bill_no:'same',bill_date:'2026-11-14',name:'A',total:'10'},
 {bill_no:'SAME',bill_date:'2026-11-14',name:'B',total:'20'}
],'2026-06-12'),/repeated/);
assert.throws(()=>validateExpenseBillRows([{bill_no:'DATE',bill_date:'2026-06-01',name:'A',total:'10'}],'2026-06-12'),/precede/);
assert.throws(()=>validateExpenseBillRows([{bill_no:'NEG',bill_date:'2026-11-14',name:'A',total:'-10'}]),/amount/);
console.log('PASS: separate row bills, profit totals, quantity removed, duplicates rejected atomically, and bill dates validated.');
`,{assert,console,Intl,Date,FormData,localStorage:{setItem(){}},setTimeout,clearTimeout});
