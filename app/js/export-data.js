"use strict";
// export-data.js — export / backup data
// ================= EXPORT DATA =================
// Backup/restore JSON & reset DIHILANGKAN dari UI pelanggan (berisiko merusak data;
// pemulihan/restore ditangani developer dari sisi server). Yang tersisa: export CSV read-only.
function rBackup(){
  const fnb=bizType()==="fnb";
  document.getElementById("v-backup").innerHTML=`<h2 class="title">Export Data</h2><div class="desc">Unduh data usahamu sebagai file Excel/CSV. Cadangan database dikelola dari sisi server — hubungi admin Racikin bila butuh pemulihan data.</div>
  <div class="panel"><h3>Export Excel/CSV</h3><div class="flexbtns">${fnb?"":`<button class="btn dark" onclick="exportCSV('batches')">Batch Produksi</button>`}<button class="btn dark" onclick="exportCSV('distributions')">${fnb?"Penjualan":"Distribusi"}</button><button class="btn dark" onclick="exportCSV('payments')">Pembayaran</button><button class="btn dark" onclick="exportCSV('materials')">Harga Bahan</button></div></div>
  ${fnb?"":`<div class="panel"><h3>Harga Bahan</h3><p class="mini" style="margin-bottom:14px">Selaraskan harga master &amp; riwayat bahan dari harga yang dipakai di batch (harga batch tanggal terbaru jadi harga terkini).</p><button class="btn ghost" onclick="resyncPrices()">🔄 Sinkron Ulang Harga Bahan</button></div>`}`;
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

