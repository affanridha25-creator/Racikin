"use strict";
// derived.js — data turunan: stok, nilai, harga bahan
// ---------- derived ----------
const prod=id=>S.products.find(p=>p.id===id);
const store=id=>S.stores.find(s=>s.id===id);
const mat=id=>S.materials.find(m=>m.id===id);
const notaItems=n=>n.items||[];
let _stockCache=null;
function stock(pid){
  if(!_stockCache){const m={};S.batches.forEach(b=>(b.outputs||[]).forEach(o=>{m[o.productId]=(m[o.productId]||0)+(+o.qty||0);}));S.notas.forEach(n=>notaItems(n).forEach(it=>{m[it.productId]=(m[it.productId]||0)-(+it.qty||0);}));(S.stockAdj||[]).forEach(a=>{m[a.productId]=(m[a.productId]||0)+(+a.qty||0);});_stockCache=m;}
  return _stockCache[pid]||0;
}
// ringkasan per NOTA (gabungan semua item)
const notaSubtotal=n=>notaItems(n).reduce((a,it)=>a+(+it.qty||0)*(+it.harga||0),0);
const notaDisc=n=>Math.max(0,Math.min(+n.discount||0,notaSubtotal(n)));
const notaService=n=>Math.max(0,+n.service||0);   // service charge (Rp), beku per nota
const notaTax=n=>Math.max(0,+n.tax||0);           // pajak/PPN (Rp), beku per nota
const notaBase=n=>notaSubtotal(n)-notaDisc(n);    // penjualan bersih (tanpa service/pajak)
const notaTotal=n=>notaBase(n)+notaService(n)+notaTax(n);
const notaOmzet=n=>notaBase(n)+notaService(n);     // omzet/pendapatan = TANPA pajak (pajak = titipan negara)
const notaSvcRate=n=>+n.svcRate||0;                // tarif service yg dipakai (beku per nota)
const notaTaxRate=n=>+n.taxRate||0;                // tarif pajak yg dipakai
const ratePct=r=>{r=+r||0;return r?" ("+(Number.isInteger(r)?r:(+r.toFixed(2)))+"%)":"";};   // "5"/"11.5", buang .00
const notaProfit=n=>notaItems(n).reduce((a,it)=>a+(+it.qty||0)*((+it.harga||0)-(+it.hpp||0)),0)-notaDisc(n)+notaService(n);  // service = pemasukan; pajak = titipan (tak masuk laba)
const notaQty=n=>notaItems(n).reduce((a,it)=>a+(+it.qty||0),0);
const notaPaid=n=>(n.payments||[]).reduce((a,p)=>a+(+p.amount||0),0);
const notaDue=n=>notaTotal(n)-notaPaid(n);
function notaStatus(n){const t=notaTotal(n),p=notaPaid(n);if(t<=0)return"lunas";if(p<=0)return"belum";if(p>=t)return"lunas";return"sebagian";}
// nomor nota default: NOTA-YYYYMMDD-NNN (urut per hari)
function nextNotaNo(dateStr){
  // angka urut = nomor tertinggi yang sudah ada untuk tanggal itu + 1 (bukan jumlah nota → aman setelah ada yang dihapus)
  const ymd=(dateStr||today()).replace(/-/g,"");
  const pre=`${(BIZ.alias||"nota").toUpperCase()}-${ymd}-`;
  let max=0;S.notas.forEach(x=>{const no=x.notaNo||"";if(no.startsWith(pre)){const k=parseInt(no.slice(pre.length),10);if(k>max)max=k;}});
  return pre+String(max+1).padStart(3,"0");
}
function batchCalc(b){
  const mat=(b.materials||[]).reduce((a,x)=>a+(+x.qty||0)*(+x.price||0),0);
  const ops=(b.ops||[]).reduce((a,o)=>a+(+o.amount||0),0);
  const total=mat+ops;
  const validOut=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0);
  const outIds=new Set(validOut.map(o=>o.productId));
  const bottles=validOut.reduce((a,o)=>a+(+o.qty||0),0);
  // qty & bobot per produk (gabung baris duplikat)
  const qtyByProd={},wByProd={};
  validOut.forEach(o=>{const p=prod(o.productId);const g=(p&&p.gram)||1;qtyByProd[o.productId]=(qtyByProd[o.productId]||0)+(+o.qty||0);wByProd[o.productId]=(wByProd[o.productId]||0)+(+o.qty||0)*g;});
  const wsum=Object.values(wByProd).reduce((a,v)=>a+v,0);
  // bahan: dialokasikan khusus ke satu produk vs dibagi rata (pool)
  let sharedMat=0;const assigned={};
  (b.materials||[]).forEach(x=>{const cost=(+x.qty||0)*(+x.price||0);const t=x.targetProduct;if(t&&outIds.has(t))assigned[t]=(assigned[t]||0)+cost;else sharedMat+=cost;});
  const pool=sharedMat+ops;
  const per={};
  Object.keys(qtyByProd).forEach(pid=>{const q=qtyByProd[pid];const fromPool=wsum>0?pool*wByProd[pid]/wsum/q:(bottles>0?pool/bottles:0);const fromAssigned=q>0?(assigned[pid]||0)/q:0;per[pid]=fromPool+fromAssigned;});
  return{mat,ops,total,bottles,wsum,perProduct:per,avg:bottles>0?total/bottles:0};
}
function srcLabel(s){return {seed:"awal",manual:"manual",batch:"dari batch",beli:"dari pembelian"}[s]||esc(s||"-");}
// ---- stok bahan (opsional) ----
function fmtQty(q){q=+q||0;return (Math.round(q*1000)/1000).toLocaleString("id-ID");}
function matStockInfo(m){const bought=+m.bought||0,used=+m.used||0,min=+m.minStock||0;const tracked=bought>0;const stock=bought-used;return{tracked,bought,used,stock,min,low:tracked&&min>0&&stock<=min};}
function chgBadge(frac,nullText){
  if(frac==null)return nullText;
  if(frac>0)return `<span class="up">▲ ${(frac*100).toFixed(1)}%</span>`;
  if(frac<0)return `<span class="down">▼ ${(Math.abs(frac)*100).toFixed(1)}%</span>`;
  return '<span class="flat">0%</span>';
}
function matStat(m){
  const h=(m.history||[]).slice().sort((a,b)=>(a.date||"").localeCompare(b.date||"")||a.id-b.id);
  const cur=m.price,prev=h.length>1?h[h.length-2].price:null;
  const chg=prev!=null&&prev!==0?(cur-prev)/prev:null;
  return{h,cur,prev,chg,updates:h.length,last:h.length?h[h.length-1].date:null};
}

