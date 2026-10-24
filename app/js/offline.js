"use strict";
// offline.js — mode offline kasir (cache + antrean + sync)
// ================= MODE OFFLINE (kasir tetap jalan tanpa internet) =================
// Data terakhir di-cache (IndexedDB); transaksi saat offline diantre & disinkron saat online.
// posSale server bersifat idempoten (id nota dikirim) → aman dari dobel walau sync diulang.
let OFFLINE=false;
const IDB={_db:null,
  open(){return this._db?Promise.resolve(this._db):new Promise((res,rej)=>{const r=indexedDB.open("racikin",1);r.onupgradeneeded=()=>r.result.createObjectStore("kv");r.onsuccess=()=>{this._db=r.result;res(this._db);};r.onerror=()=>rej(r.error);});},
  async get(k){try{const db=await this.open();return await new Promise((res)=>{const t=db.transaction("kv").objectStore("kv").get(k);t.onsuccess=()=>res(t.result);t.onerror=()=>res(null);});}catch(e){return null;}},
  async set(k,v){try{const db=await this.open();return await new Promise((res)=>{const t=db.transaction("kv","readwrite").objectStore("kv").put(v,k);t.onsuccess=()=>res(1);t.onerror=()=>res(0);});}catch(e){return 0;}},
};
const _alias=()=>((BIZ&&BIZ.alias)||"") ;
const cid=p=>(p||"n")+Array.from(crypto.getRandomValues(new Uint8Array(6))).map(b=>b.toString(16).padStart(2,"0")).join("");
function isNetErr(e){return !navigator.onLine || (e&&(e.name==="TypeError"||/fetch|network|jaringan/i.test(e.message||"")));}
async function queueGet(){return (await IDB.get("queue_"+_alias()))||[];}
async function queueSet(q){return IDB.set("queue_"+_alias(),q);}
async function queueCount(){return (await queueGet()).length;}
// ubah item antre → objek nota (dg pembayaran) supaya tampil & terhitung di S
function queueItemToNota(it){return {...it.nota, createdAt:it.nota.date+" "+(it.clock||"00:00"), payments:[{id:"q"+it.nota.id,amount:it.total,date:it.nota.date,note:"POS "+(it.method||"Tunai")}], _pending:true};}
async function applyQueueToS(){const q=await queueGet();if(!q.length)return;const have=new Set((S.notas||[]).map(n=>n.id));q.forEach(it=>{if(!have.has(it.nota.id))S.notas.unshift(queueItemToNota(it));});}

async function reload(){
  try{
    S=await api("bootstrap");
    OFFLINE=false;
    if(S.biz){BIZ={...S.biz,...(S.me||{})};await IDB.set("cache_S_"+S.biz.alias,S);await IDB.set("cache_BIZ_"+S.biz.alias,BIZ);await IDB.set("cache_lastAlias",S.biz.alias);}
    await applyQueueToS();            // transaksi antre (belum sync) tetap tampil
  }catch(e){
    if(!isNetErr(e))throw e;          // error non-jaringan (mis. sesi habis) → biarkan normal
    const a=_alias()||await IDB.get("cache_lastAlias")||"";
    const c=await IDB.get("cache_S_"+a);
    if(!c)throw e;                    // tak ada cache → tak bisa offline
    S=c;OFFLINE=true;
    const cb=await IDB.get("cache_BIZ_"+a);if(cb)BIZ=cb;
    await applyQueueToS();
  }
  _stockCache=null;if(S.biz)BIZ={...S.biz,...(S.me||{}),...BIZ,alias:(S.biz&&S.biz.alias)||BIZ.alias};updateBizUI();applyPerms();updateOfflineUI();
}
// saat authStatus gagal karena offline tapi ada cache → tetap masuk pakai data terakhir
async function offlineAuthFallback(){
  if(navigator.onLine)return {loggedIn:false};
  const a=await IDB.get("cache_lastAlias");if(!a)return {loggedIn:false};
  const cb=await IDB.get("cache_BIZ_"+a);if(!cb)return {loggedIn:false};
  OFFLINE=true;return {loggedIn:true,offline:true,...cb};
}
// indikator kecil offline / antrean (chip mengambang, ketuk = sinkron)
async function updateOfflineUI(){
  let el=document.getElementById("offlineChip");
  const n=await queueCount();
  if(!OFFLINE && n===0){if(el)el.remove();return;}
  if(!el){el=document.createElement("div");el.id="offlineChip";el.onclick=()=>syncQueue(true);
    el.style.cssText="position:fixed;left:12px;bottom:calc(74px + var(--sab));z-index:70;background:#2A1712;color:#fff;border-radius:20px;padding:8px 14px;font-size:12.5px;font-weight:700;box-shadow:0 8px 22px rgba(0,0,0,.3);cursor:pointer;display:flex;gap:8px;align-items:center";
    document.body.appendChild(el);}
  el.innerHTML=(OFFLINE?"⚡ Offline":"🔄 Online")+(n>0?` · ${n} antre`+(OFFLINE?"":" — ketuk sinkron"):"");
  el.style.background=OFFLINE?"#B5300D":"#1E7A44";
}
// sinkron antrean ke server (idempoten). manual=true → beri notifikasi.
let _syncing=false;
async function syncQueue(manual){
  if(_syncing)return; if(!navigator.onLine){if(manual)toast("Masih offline — belum bisa sinkron.");return;}
  _syncing=true;
  try{
    let q=await queueGet();if(!q.length){if(manual)toast("Tak ada antrean.");return;}
    const rest=[];let ok=0;
    for(const it of q){
      try{const r=await api("posSale",{nota:it.nota},{silent:true});ok++;}
      catch(e){ if(isNetErr(e))rest.push(it); else rest.push({...it,_err:(e&&e.message)||"gagal",_stuck:true}); }
    }
    await queueSet(rest);
    if(ok>0){ await reload(); toast(`Sinkron: ${ok} transaksi tersimpan ✓`); if(curView==="pos")rPOS(); if(curView==="rekap")rRekap(); }
    const stuck=rest.filter(x=>x._stuck).length;
    if(stuck>0)toast(`${stuck} transaksi offline perlu diperiksa (mis. stok).`);
  }finally{_syncing=false;updateOfflineUI();}
}
window.addEventListener("online",()=>{OFFLINE=false;updateOfflineUI();syncQueue();});
window.addEventListener("offline",()=>{OFFLINE=true;updateOfflineUI();});
