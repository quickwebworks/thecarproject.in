const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};
const rows=viewLedger().filter(r=>!r.sale),sold=viewLedger().filter(r=>r.sale),html=unsoldDashboard(rows);
assert.ok(rows.length>0&&sold.length>0);for(const row of rows){assert.ok(html.includes(row.vehicle.name));assert.ok(html.includes(rupees(row.cost)))}for(const row of sold)assert.ok(!html.includes(row.vehicle.name));
assert.ok(html.includes(rupees(rows.reduce((s,r)=>s+r.cost,0))));assert.ok(dashboard().includes('Unsold vehicles ('+rows.length+')'));assert.ok(unsoldDashboard([]).includes('No unsold vehicles'));
console.log('PASS: dashboard unsold list excludes sold vehicles, includes stock costs and total investment, and handles empty stock.');
`,{assert,console,Intl,Date,FormData,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
