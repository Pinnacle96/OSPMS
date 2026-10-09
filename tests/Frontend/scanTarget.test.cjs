const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const ts=require('typescript');
const path=require('node:path');
const moduleValue={exports:{}};
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname,'../../resources/js/pwa/scanTarget.ts'),'utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText,{exports:moduleValue.exports,URL});
const {scanTarget}=moduleValue.exports;
const code='abc123'.repeat(10)+'abcd',local='http://localhost:8000',configured='https://parks.example';
const scan=(value,kind='ticket')=>scanTarget(value,kind,configured,local);
test('bare verification tokens retain the explicitly selected kind',()=>{
 assert.equal(scan(' '+code+' '),'/field/verify/'+code+'?kind=ticket');
 assert.equal(scan(code,'receipt'),'/field/verify/'+code+'?kind=receipt');
});
test('ticket and receipt QR URLs map to local authenticated field routes',()=>{
 for(const origin of [local,configured,''])for(const kind of ['ticket','receipt'])assert.equal(scan(origin+'/verify/'+kind+'/'+code),'/field/verify/'+code+'?kind='+kind);
 assert.equal(scan(configured+'/verify/receipt/'+code+'/'),'/field/verify/'+code+'?kind=receipt');
});
test('field URLs accept only a single supported kind selector',()=>{
 assert.equal(scan('/field/verify/'+code),'/field/verify/'+code+'?kind=ticket');
 assert.equal(scan('/field/verify/'+code+'?kind=receipt'),'/field/verify/'+code+'?kind=receipt');
 for(const query of ['?kind=receipt&kind=ticket','?kind=driver','?redirect=https://attacker.example','?kind=ticket&extra=1'])assert.equal(scan('/field/verify/'+code+query),null);
});
test('external origins credentials and non-HTTP schemes are rejected',()=>{
 for(const url of ['https://attacker.example/verify/ticket/'+code,'//attacker.example/verify/ticket/'+code,'https://parks.example.attacker.example/verify/ticket/'+code,'https://user:password@parks.example/verify/ticket/'+code,'javascript:alert(1)','data:text/plain,'+code,'file:///verify/ticket/'+code])assert.equal(scan(url),null,url);
});
test('unknown routes and ambiguous public links are rejected',()=>{
 for(const url of ['/payments/'+code,'/verify/driver/'+code,'/verify/ticket/'+code+'?kind=receipt','/verify/ticket/'+code+'#fragment','/verify/ticket/'+code+'/extra'])assert.equal(scan(url),null,url);
});
test('malformed tokens and invalid URL input cannot navigate',()=>{
 for(const value of ['',code.toUpperCase(),code.slice(1),code+'0','z'.repeat(64),'http://[invalid'])assert.equal(scan(value),null,value);
});
