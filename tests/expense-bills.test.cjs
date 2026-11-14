const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
const dateInput={value:'2026-06-12',setCustomValidity(m){this.message=m},setAttribute(){}},dateError={textContent:''},display={},hint={},row={querySelector:s=>s==='[data-bill-date]'?dateInput:dateError};
const form={elements:{vehicle_id:{value:1}},querySelector:s=>s==='#expense-purchase-date'?display:hint,querySelectorAll:()=>[row]};
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};
const purchase=state.purchases.find(p=>+p.vehicle_id===1);purchase.purchase_date='2026-06-12';
const base={vehicle_id:1,bills:[{party_id:30,bill_no:'NEW-001',bill_date:'2026-11-14',name:'engine oil',total:'100.50'},{party_id:31,bill_no:'NEW-002',bill_date:'2026-11-13',name:'detailing',total:'200'}]};
const before=state.expenses.length;demoRequest('expense',normalizeEntry('expense',base));assert.equal(state.expenses.length,before+2);assert.equal(+state.expenses.find(e=>e.bill_no==='NEW-001').party_id,30);assert.equal(+state.expenses.find(e=>e.bill_no==='NEW-002').party_id,31);
assert.throws(()=>validateExpenseBillRows([{...base.bills[0],bill_date:'2026-06-12'}],purchase.purchase_date),/later than/);assert.throws(()=>validateExpenseBillRows([{...base.bills[0],bill_date:'2026-06-11'}],purchase.purchase_date),/later than/);assert.throws(()=>validateExpenseBillRows([{...base.bills[0],party_id:''}],purchase.purchase_date),/party name/);
const count=state.expenses.length;assert.throws(()=>demoRequest('expense',base),/already exists/);assert.equal(state.expenses.length,count);
updateExpensePurchaseDate(form);assert.equal(display.value,'2026-06-12');assert.equal(dateInput.min,'2026-06-13');assert.ok(dateInput.message.includes('later than'));assert.equal(dateError.textContent,dateInput.message);dateInput.value='2026-06-13';updateExpensePurchaseDate(form);assert.equal(dateInput.message,'');
console.log('PASS: row-specific parties, atomic duplicate rejection, purchase-date display, next-day minimum and inline date messages.');
`,{assert,console,Intl,Date,FormData,form,dateInput,dateError,display,hint,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
