const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
const elements=Object.fromEntries(['vehicle_id','bank_id','client_id','yard_id','portal_id','purchase_date','amount','yard_expense','manager_name','manager_contact'].map(k=>[k,{value:''}]));
const summary={innerHTML:''},form={elements,addEventListener(){},querySelector:s=>s==='#form-summary'?summary:null,querySelectorAll:()=>[]};let title='';
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};
modal=(name,description,body)=>{setTitle(name);return {layer:{querySelector:()=>form},close(){}}};
const purchase=state.purchases.find(p=>inYear(p.purchase_date)&&state.sales.some(s=>+s.vehicle_id===+p.vehicle_id));
openForm('purchase',purchase);assert.equal(getTitle(),'Edit purchase');assert.equal(form.elements.amount.value,purchase.amount);assert.equal(form.elements.vehicle_id.disabled,true);
const count=state.purchases.length,before=ledger().find(p=>+p.vehicle_id===+purchase.vehicle_id).profit;
demoRequest('purchase',{...purchase,amount:Number(purchase.amount)+10000});assert.equal(state.purchases.length,count);assert.equal(ledger().find(p=>+p.vehicle_id===+purchase.vehicle_id).profit,before-10000);
page='purchases';assert.ok(listings().includes('Edit purchase'));user.role='viewer';assert.ok(!listings().includes('data-edit-purchase='));user.role='admin';
const stock=viewLedger().filter(p=>!p.sale),vehicle=stock[0].vehicle_id;assert.ok(unsoldDashboard(stock).includes('data-sell-vehicle="'+vehicle+'"'));openForm('sale',null,vehicle);assert.equal(form.elements.vehicle_id.value,vehicle);assert.equal(getTitle(),'Record a sale');user.role='viewer';assert.ok(!unsoldDashboard(stock).includes('data-sell-vehicle='));
console.log('PASS: purchase form prefills and profit recalculates; role-based Edit buttons; unsold vehicle opens preselected sale.');
`,{assert,console,Intl,Date,FormData,form,setTitle:s=>title=s,getTitle:()=>title,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
