const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
let captured='';const form={elements:{name:{focus(){}}},querySelector(){return null}};
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};demoUser=user;
modal=(title,description,body)=>{capture(body);return {layer:{querySelector:()=>form},close(){}}};
companyForm();
for(const name of ['name','financial_year','address','contact_person','mobile','email'])assert.ok(getCaptured().includes('name="'+name+'"'));
for(const name of ['db_password','db_username','database','host','key'])assert.ok(!getCaptured().includes('name="'+name+'"'));
demoRequest('company_save',{operation:'add',name:'TEST COMPANY',financial_year:2026,address:'LUDHIANA',contact_person:'QA',mobile:'1234567890',email:'qa@example.com'});
applySession(demoSession());const added=companies.find(c=>c.name==='TEST COMPANY');assert.ok(added);assert.equal(companyProfiles[added.key].address,'LUDHIANA');
demoRequest('company_save',{operation:'edit',key:added.key,name:'UPDATED COMPANY',address:'DELHI',contact_person:'UPDATED',mobile:'9876543210',email:'new@example.com'});
applySession(demoSession());assert.equal(companyProfiles[added.key].address,'DELHI');companyForm(companies.find(c=>c.key===added.key));assert.ok(getCaptured().includes('DELHI'));
assert.equal(rupees(220500),'₹2,20,500.00');
const sold=viewLedger().filter(x=>x.sale&&inYear(x.sale.sale_date));const cost=sold.reduce((sum,x)=>sum+x.cost+(+x.sale.sale_expense),0);assert.ok(reports().includes(rupees(cost)));
console.log('PASS: business-only company form; generated key; add/edit profile persistence; full sold vehicle cost amount.');
`,{assert,console,Intl,Date,FormData,form,capture:s=>captured=s,getCaptured:()=>captured,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
