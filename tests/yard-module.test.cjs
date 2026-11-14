const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
const fields=Object.fromEntries(['vehicle_id','client_id','sale_date','amount','sale_expense','salesperson','bank_id','portal_id','purchase_date','manager_name','manager_contact'].map(k=>[k,{value:''}]));const form={elements:fields,addEventListener(){},querySelector:s=>s==='#form-summary'?{innerHTML:''}:null,querySelectorAll:()=>[]};let body='';
(async()=>{await vm.runInNewContext(source+`(async()=>{
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};demoUser=user;
const first=state.purchases[0],baseline=ledger().find(p=>+p.vehicle_id===+first.vehicle_id).cost;
assert.equal(yardTotal(first.vehicle_id),Number(first.yard_expense));
const input={vehicle_id:first.vehicle_id,yard_id:40,release_date:'2026-11-14',amount:'3000'};
const yard=all('yard')[0];input.yard_id=yard.id;demoRequest('yard_expense',input);const created=state.yard_expenses.find(y=>!y.legacy_purchase_id);assert.equal(ledger().find(p=>+p.vehicle_id===+first.vehicle_id).cost,baseline+3000);
demoRequest('yard_expense',{...input,id:created.id,amount:'4500'});assert.equal(ledger().find(p=>+p.vehicle_id===+first.vehicle_id).cost,baseline+4500);demoRequest('yard_delete',{id:created.id});assert.equal(ledger().find(p=>+p.vehicle_id===+first.vehicle_id).cost,baseline);
const sale=state.sales[0],saleCount=state.sales.length;demoRequest('sale',{...sale,amount:Number(sale.amount)+5000});assert.equal(state.sales.length,saleCount);assert.equal(Number(state.sales.find(s=>+s.id===+sale.id).amount),Number(sale.amount)+5000);
const expense=state.expenses[0],items=state.expense_items.filter(i=>+i.expense_id===+expense.id),cost=ledger().find(p=>+p.vehicle_id===+expense.vehicle_id).cost;demoRequest('expense',{...expense,party_id:30,expense_date:'2026-11-14',items:items.map((i,index)=>({...i,total:Number(i.total)+(index?0:100)}))});assert.equal(ledger().find(p=>+p.vehicle_id===+expense.vehicle_id).cost,cost+100);
page='sales';assert.ok(listings().includes('Edit sale'));page='expenses';assert.ok(listings().includes('Edit expense'));page='yard_expenses';assert.ok(yardPage().includes('Release date'));
modal=(title,desc,html)=>{capture(html);return {layer:{querySelector:()=>form},close(){}}};openForm('purchase');assert.ok(!getBody().includes('name="yard_id"'));assert.ok(!getBody().includes('name="yard_expense"'));openForm('sale',state.sales[0]);assert.equal(form.elements.vehicle_id.disabled,true);assert.equal(form.elements.sale_date.value,state.sales[0].sale_date);
filter='stock';search='';const allStock=inventoryRows();assert.ok(allStock.every(r=>!r.sale));search=allStock[0].vehicle.name;assert.equal(inventoryExportRows().length,2);assert.ok(vehicles().includes('Export Excel / PDF / CSV'));
const rows=inventoryExportRows();rows[1][1]='ਪੰਜਾਬ & <Test>';rows[1][0]='=DANGEROUS()';await writeXlsx(await inventoryXlsx(rows).arrayBuffer());
user.role='viewer';page='sales';assert.ok(!listings().includes('data-edit-sale='));page='expenses';assert.ok(!listings().includes('data-edit-expense='));assert.ok(!yardPage().includes('data-delete-yard='));
console.log('PASS: yard cost CRUD without double-counting, sale/expense edits, purchase fields removed, role controls and filtered inventory exports.');
})()`,{assert,console,Intl,Date,Blob,TextEncoder,FormData,form,capture:s=>body=s,getBody:()=>body,writeXlsx:b=>fs.writeFileSync('/tmp/inventory-test.xlsx',Buffer.from(b)),localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});})().catch(e=>{console.error(e);process.exit(1)});
