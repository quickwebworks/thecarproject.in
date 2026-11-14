const fs=require('fs'),vm=require('vm'),assert=require('assert');
let clock='2026-11-14T12:00:00Z';class TestDate extends Date{constructor(...args){super(...(args.length?args:[clock]))}}
const source=fs.readFileSync(__dirname+'/../public/assets/app.js','utf8').replace(/init\(\);\s*$/,'');
vm.runInNewContext(source+`
state=seed();applySession(demoSession());user={id:1,name:'QA',role:'admin'};
chartMode='6m';let months=chartMonths();assert.equal(months.length,6);assert.equal(months[0].key,'2026-06');assert.equal(months[5].key,'2026-11');
state.purchases.push({id:900,vehicle_id:999,purchase_date:'2026-10-05',amount:500000,yard_expense:0});
const html=chart();assert.ok(html.includes('<rect'));assert.ok(html.includes('height="160"'));assert.ok(html.includes(rupees(chartMonths().find(x=>x.key==='2026-10').purchase)));assert.ok(!html.includes('style='));
chartMode='12m';months=chartMonths();assert.equal(months.length,12);assert.equal(months[0].key,'2026-04');assert.equal(months[11].key,'2027-03');
const meter=inventoryStockBar(15,36);assert.ok(meter.includes('width="29.411764705882355"'));assert.ok(meter.includes('width="70.58823529411765"'));assert.ok(!meter.includes('style='));assert.ok(!inventoryStockBar(0,0).includes('NaN'));
state={...state,purchases:[],sales:[]};assert.ok(chart().includes('No purchases or sales'));
console.log('PASS: recent six-month window, full fiscal-year window, nonzero SVG bars, full rupee labels, 15/36 stock segments and empty states without inline styles.');
`,{assert,console,Intl,Date:TestDate,FormData,localStorage:{getItem:()=>null,setItem:()=>{}},setTimeout,clearTimeout});
