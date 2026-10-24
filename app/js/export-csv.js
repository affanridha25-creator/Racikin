"use strict";
// export-csv.js — export CSV penjualan & laba rugi
// ---- Export CSV (buka di Excel; pemisah ; + BOM utk locale Indonesia) ----
function downloadCSV(filename,rows){
  const q=v=>{
    if(typeof v==="number")return String(v);
    v=(v==null?"":String(v));
    // cegah formula/CSV injection: sel teks diawali = + - @ tab CR → beri prefix ' (kecuali angka murni)
    if(/^[=+\-@\t\r]/.test(v)&&!/^-?\d+(\.\d+)?$/.test(v))v="'"+v;
    return /[";\n\r]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v;};
  const csv=rows.map(r=>r.map(q).join(";")).join("\r\n");
  const blob=new Blob(["﻿"+csv],{type:"text/csv;charset=utf-8"});
  const url=URL.createObjectURL(blob);const a=document.createElement("a");a.href=url;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
  toast("File CSV diunduh ✓");
}
function exportPenjualan(scope){   // scope: 'distribusi' (non-POS) | 'pos' | undefined(semua)
  let list=S.notas.filter(n=>inMonth(n.date));
  if(scope==="distribusi")list=list.filter(n=>!n.sessionId);
  else if(scope==="pos")list=list.filter(n=>n.sessionId);
  list=list.sort((a,b)=>(a.date||"").localeCompare(b.date||""));
  if(list.length===0){toast("Tak ada data pada periode ini.");return;}
  const rows=[["Tanggal","No Nota","Toko/Pelanggan","Item","Subtotal","Diskon","Total","Terbayar","Sisa","Status","Kasir","Metode"]];
  list.forEach(n=>rows.push([n.date,n.notaNo||"",(store(n.storeId)||{}).name||"",notaItems(n).reduce((a,i)=>a+(+i.qty||0),0),notaSubtotal(n),notaDisc(n),notaTotal(n),notaPaid(n),notaDue(n),notaStatus(n),kasirName(n.createdBy),n.payMethod||""]));
  const t=list.reduce((a,n)=>({s:a.s+notaSubtotal(n),d:a.d+notaDisc(n),t:a.t+notaTotal(n),p:a.p+notaPaid(n),u:a.u+notaDue(n)}),{s:0,d:0,t:0,p:0,u:0});
  rows.push(["TOTAL","","","",t.s,t.d,t.t,t.p,t.u,"","",""]);
  const pre=scope==="pos"?"penjualan-kasir":(scope==="distribusi"?"distribusi":"penjualan");
  downloadCSV(`${pre}-${FILTER_MONTH==="all"?"semua":FILTER_MONTH}.csv`,rows);
}
function exportLR(){
  const k=keuCalc();
  const rows=[["Laporan Laba Rugi",monthLabelFull(FILTER_MONTH)],[],
    ["Keterangan","Jumlah (Rp)"],
    ["Pendapatan (Omzet)",k.pendapatan],
    ["Harga Pokok Penjualan (HPP)",-k.hpp],
    ["Laba Kotor",k.labaKotor],
    ["Biaya Barang Gratis (bonus/endorse/tester)",-k.promo],
    ["Biaya Operasional Lain",-k.biayaOp],
    ["Laba Bersih",k.labaBersih],
    ...(k.pajak>0?[["Pajak Terkumpul (PPN, titipan negara)",k.pajak]]:[]),[],
    ["Saldo Kas (total)",k.saldo]];
  if((k.co||[]).length){rows.push([],["Rincian Kas Keluar (periode)"],["Tanggal","Kategori","Jumlah","Catatan"]);k.co.forEach(c=>rows.push([c.date,cashCatLabel(c.category),c.amount,c.note||""]));}
  downloadCSV(`laba-rugi-${FILTER_MONTH==="all"?"semua":FILTER_MONTH}.csv`,rows);
}
function printLR(){
  const k=keuCalc();
  document.getElementById("printArea").innerHTML=`<div class="preport">
    ${printHead("LAPORAN LABA RUGI","Periode: "+monthLabelFull(FILTER_MONTH)+" • Dicetak "+fmtDate(today()))}
    <div class="psec">Laba Rugi</div>
    <table><tbody>
      <tr><td>Pendapatan (Omzet)</td><td class="num">${rp(k.pendapatan)}</td></tr>
      <tr><td>Harga Pokok Penjualan (HPP)</td><td class="num">(${rp(k.hpp)})</td></tr>
      <tr class="tot"><td>Laba Kotor</td><td class="num">${rp(k.labaKotor)}</td></tr>
      ${k.promo>0?`<tr><td>Biaya Barang Gratis (bonus/endorse/tester)</td><td class="num">(${rp(k.promo)})</td></tr>`:""}
      <tr><td>Biaya Operasional Lain (non-produksi)</td><td class="num">(${rp(k.biayaOp)})</td></tr>
      <tr class="tot"><td><b>Laba Bersih</b></td><td class="num"><b>${rp(k.labaBersih)}</b></td></tr>
    </tbody></table>
    <div class="psec">Arus Kas — ${monthLabelFull(FILTER_MONTH)}</div>
    <table><tbody>
      <tr><td>Kas Masuk (pembayaran diterima)</td><td class="num">${rp(k.kasMasukP)}</td></tr>
      <tr><td>Kas Keluar</td><td class="num">(${rp(k.kasKeluarP)})</td></tr>
      <tr><td>&nbsp;&nbsp;• termasuk Kas Diambil Pemilik</td><td class="num">(${rp(k.byCat('prive'))})</td></tr>
      <tr class="tot"><td><b>Saldo Kas (total keseluruhan)</b></td><td class="num"><b>${rp(k.saldo)}</b></td></tr>
    </tbody></table>
    <div class="psub" style="text-align:left;margin-top:16px">Racikin • ${esc(BIZ.name||"")}</div>
  </div>`;
  window.print();
}
function toast(m){const t=document.getElementById("toast");t.textContent=m;t.classList.add("show");clearTimeout(window._tt);window._tt=setTimeout(()=>t.classList.remove("show"),2200);}

