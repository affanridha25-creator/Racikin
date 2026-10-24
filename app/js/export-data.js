"use strict";
// export-data.js — export / backup data
// ================= EXPORT DATA =================
// Backup/restore JSON & reset DIHILANGKAN dari UI pelanggan (berisiko merusak data;
// pemulihan/restore ditangani developer dari sisi server). Yang tersisa: export CSV read-only.
function rBackup(){
  const fnb=bizType()==="fnb";
  const n=(a)=>(a||[]).length;
  const nDist=n(S.notas),nPay=S.notas.reduce((a,x)=>a+n(x.payments),0),nMat=n(S.materials),nBat=n(S.batches);
  const card=(ic,t,d,cnt2,type,tone)=>`<div class="ex-card ${tone||""}"><div class="ex-ic">${ic}</div><div class="ex-tx"><div class="ex-t">${t}</div><div class="ex-d">${d}</div><div class="ex-n">${cnt2}</div></div><button class="ex-dl" onclick="exportCSV('${type}')"><span>⬇️</span> Unduh CSV</button></div>`;
  const cards=(fnb?"":card("🏭","Batch Produksi","Riwayat produksi: tanggal, produk jadi, biaya bahan &amp; operasional, serta HPP rata-rata per item.",nBat+" batch","batches","t1"))
    +card(fnb?"🧾":"🚚",fnb?"Penjualan":"Distribusi",fnb?"Semua nota penjualan beserta item, harga, HPP, laba, dan status pembayaran.":"Semua nota kirim/titip ke toko: item, harga, HPP, laba per baris, dan status nota.",nDist+" nota","distributions","t2")
    +card("💳","Pembayaran","Catatan pembayaran dari tiap toko per nota — tanggal bayar, jumlah, dan catatan.",nPay+" pembayaran","payments","t3")
    +card("🧂","Harga Bahan","Riwayat harga setiap bahan baku beserta sumber dan tanggalnya.",nMat+" bahan","materials","t4");
  document.getElementById("v-backup").innerHTML=`<div class="ex"><div class="ex-hero"><div class="dh-bg" aria-hidden="true"><i></i><i></i><i></i></div><div class="ex-hm"><div class="ex-hi">📦 Export Data</div><h2 class="ex-h">Datamu, milikmu.</h2><div class="ex-hs">Unduh data usahamu sebagai file Excel/CSV kapan saja — siap dibuka di Excel, Google Sheets, atau diserahkan ke akuntan.</div></div><div class="ex-hb" aria-hidden="true">📊</div></div>
  <div class="ex-grid">${cards}</div>
  ${fnb?"":`<div class="ex-sec"><div class="ex-ic sm">🔄</div><div class="ex-tx"><div class="ex-t">Sinkron Harga Bahan</div><div class="ex-d">Selaraskan harga master &amp; riwayat bahan dari harga yang dipakai di batch (harga batch tanggal terbaru jadi harga terkini).</div></div><button class="ex-dl ghost" onclick="resyncPrices()">🔄 Sinkron Ulang</button></div>`}
  <div class="ex-sec safe"><div class="ex-ic sm">🛡️</div><div class="ex-tx"><div class="ex-t">Cadangan &amp; Pemulihan Data</div><div class="ex-d">Cadangan database dikelola otomatis dari sisi server demi keamanan datamu. Butuh pemulihan data? Hubungi admin Racikin via WhatsApp bisnis.</div></div></div></div>`;
  if(typeof dashAnimate==="function")dashAnimate(document.querySelector("#v-backup .ex"));
}
function csvCell(v){v=v==null?"":String(v);return /[",\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v;}
function exportCSV(type){let rows=[];
  if(type==="batches"){rows.push(["Tanggal","Catatan","Produk Jadi","Item","Bahan","Ops","Total Modal","HPP rata-rata/item"]);S.batches.forEach(b=>{const c=batchCalc(b);rows.push([b.date,b.note,(b.outputs||[]).map(o=>`${(prod(o.productId)||{}).name} x${o.qty}`).join("; "),c.bottles,c.mat,c.ops,c.total,Math.round(c.avg)]);});}
  else if(type==="distributions"){rows.push(["Tanggal","No. Nota","Toko","Produk","Jenis","Qty","Harga","Total","HPP","Laba","Status Nota"]);S.notas.forEach(n=>notaItems(n).forEach(it=>rows.push([n.date,n.notaNo,(store(n.storeId)||{}).name,(prod(it.productId)||{}).name,(it.kind||"jual"),it.qty,it.harga,(+it.qty||0)*(+it.harga||0),it.hpp,(+it.qty||0)*((+it.harga||0)-(+it.hpp||0)),notaStatus(n)])));}
  else if(type==="materials"){rows.push(["Bahan","Satuan","Tanggal","Harga","Sumber"]);S.materials.forEach(m=>(m.history||[]).forEach(h=>rows.push([m.name,m.unit,h.date,h.price,h.source])));}
  else{rows.push(["Tanggal Bayar","No. Nota","Toko","Jumlah","Catatan"]);S.notas.forEach(n=>(n.payments||[]).forEach(p=>rows.push([p.date,n.notaNo,(store(n.storeId)||{}).name,p.amount,p.note])));}
  dl(type+"-"+today()+".csv","﻿"+rows.map(r=>r.map(csvCell).join(",")).join("\n"),"text/csv");}
function dl(name,content,mime){const b=new Blob([content],{type:mime}),u=URL.createObjectURL(b),a=document.createElement("a");a.href=u;a.download=name;a.click();URL.revokeObjectURL(u);}
async function resyncPrices(){const r=await api("resyncPrices");await reload();toast(`Harga ${r.materials||0} bahan disinkron ✓`);rBackup();}

