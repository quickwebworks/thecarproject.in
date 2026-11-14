const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};
assert.equal(rupees(334000),'₹3,34,000.00');assert.equal(rupees(560000),'₹5,60,000.00');assert.equal(rupees(16640150),'₹1,66,40,150.00');assert.equal(typeof compact,'undefined');
for(const html of [dashboard(),reports(),chart()])assert.ok(!/₹[0-9,.]+L\\b/.test(html));
const html=companyPage();assert.ok(html.includes('data-report-demo="existing"'));assert.ok(!html.includes('data-report-demo="second_company"'));assert.ok(!html.includes('data-report-demo="third_company"'));assert.equal((html.match(/data-report-demo=/g)||[]).length,1);
console.log('PASS: full INR formatting in cards/reports/charts; sample loader offered only for original company.');
`,{assert,console,Intl,Date,FormData,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
