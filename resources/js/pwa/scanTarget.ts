export function scanTarget(value:string,kind:'ticket'|'receipt',configuredOrigin:string,localOrigin=window.location.origin):string|null {
 const raw=value.trim();if(/^[a-f0-9]{64}$/.test(raw))return '/field/verify/'+raw+'?kind='+kind;
 try{const url=new URL(raw,localOrigin);const configured=new URL(configuredOrigin,localOrigin);if(!['http:','https:'].includes(url.protocol)||![localOrigin,configured.origin].includes(url.origin)||url.username||url.password||url.hash)return null;
 const match=url.pathname.match(/^\/verify\/(ticket|receipt)\/([a-f0-9]{64})\/?$/);if(match&&!url.search)return '/field/verify/'+match[2]+'?kind='+match[1];
 const field=url.pathname.match(/^\/field\/verify\/([a-f0-9]{64})\/?$/);if(field){const type=url.searchParams.get('kind')??'ticket';if(url.searchParams.size>1||!['ticket','receipt'].includes(type)||[...url.searchParams.keys()].some(k=>k!=='kind'))return null;return '/field/verify/'+field[1]+'?kind='+type;}
 }catch{return null;}return null;
}
