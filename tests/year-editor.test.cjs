const fs=require('fs'),vm=require('vm'),assert=require('assert');
let captured='';const button={},status={},form={elements:{name:{value:'UNSAVED NAME',focus(){}},address:{value:'UNSAVED ADDRESS'},financial_year:{innerHTML:''}},querySelector:s=>s==='#add-company-year'?button:s==='#company-year-status'?status:null};
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};demoUser=user;
modal=(title,description,body)=>{capture(body);return {layer:{querySelector:()=>form},close(){throw Error('Editor must stay open')}}};
companyForm(companies.find(c=>c.key==='existing'));assert.ok(getCaptured().includes('Add financial year 2027–2028'));assert.equal(typeof button.onclick,'function');
addFinancialYear=(company,onAdded)=>{demoRequest('company_save',{operation:'add_year',key:company.key,year:2027});applySession(demoSession());onAdded()};
button.onclick();assert.equal(form.elements.name.value,'UNSAVED NAME');assert.equal(form.elements.address.value,'UNSAVED ADDRESS');assert.ok(form.elements.financial_year.innerHTML.includes('2027–2028'));assert.ok(button.textContent.includes('2028–2029'));
console.log('PASS: Edit Company offers next year and preserves unsaved details after adding it.');
`,{assert,console,Intl,Date,FormData,form,button,capture:s=>captured=s,getCaptured:()=>captured,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
