"use strict";
// keuangan.js — keuangan: filter bulan, laba rugi, kas
// ========== KEUANGAN: filter bulan, laba rugi, kas ==========
const curMonth=()=>{const d=new Date();return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0");};
let FILTER_MONTH=curMonth(), KEU_TAB="lr";   // default: bulan berjalan
const cashOut=()=>S.cashOut||[];
const MONF=["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
const monthLabelFull=k=>k==="all"?"Semua Bulan":`${MONF[(+String(k).slice(5,7))-1]} ${String(k).slice(0,4)}`;
const inMonth=d=>FILTER_MONTH==="all"||((d||"").slice(0,7)===FILTER_MONTH);
function monthKeys(){const set=new Set([curMonth()]);S.notas.forEach(n=>{if(n.date)set.add(n.date.slice(0,7));(n.payments||[]).forEach(p=>{if(p.date)set.add(p.date.slice(0,7));});});cashOut().forEach(c=>{if(c.date)set.add(c.date.slice(0,7));});S.batches.forEach(b=>{if(b.date)set.add(b.date.slice(0,7));});return[...set].sort().reverse();}
function setMonth(v){FILTER_MONTH=v;renderCur();}
function monthBar(){const ks=monthKeys();if(FILTER_MONTH!=="all"&&!ks.includes(FILTER_MONTH))FILTER_MONTH="all";return `<select class="monthsel" onchange="setMonth(this.value)"><option value="all" ${FILTER_MONTH==="all"?"selected":""}>📅 Semua Bulan</option>${ks.map(k=>`<option value="${k}" ${FILTER_MONTH===k?"selected":""}>${monthLabelFull(k)}</option>`).join("")}</select>`;}
function keuCalc(mk){
  mk=mk||FILTER_MONTH;const inM=d=>mk==="all"||((d||"").slice(0,7)===mk);
  const notas=S.notas.filter(n=>inM(n.date));
  const pendapatan=notas.reduce((a,n)=>a+notaBase(n)+notaService(n),0);   // omzet tanpa pajak (service = pemasukan usaha)
  const pajak=notas.reduce((a,n)=>a+notaTax(n),0);                        // pajak terkumpul (titipan negara, bukan laba)
  const isJual=it=>(it.kind||"jual")==="jual";
  const hpp=notas.reduce((a,n)=>a+notaItems(n).filter(isJual).reduce((x,it)=>x+(+it.qty||0)*(+it.hpp||0),0),0);
  const promo=notas.reduce((a,n)=>a+notaItems(n).filter(it=>!isJual(it)).reduce((x,it)=>x+(+it.qty||0)*(+it.hpp||0),0),0);
  const labaKotor=pendapatan-hpp;
  const co=cashOut().filter(c=>inM(c.date));
  const byCat=k=>co.filter(c=>c.category===k).reduce((a,c)=>a+(+c.amount||0),0);
  const biayaOp=byCat("operasional")+byCat("lain");
  const labaBersih=labaKotor-promo-biayaOp;
  const kasMasukP=S.notas.reduce((a,n)=>a+(n.payments||[]).filter(p=>inM(p.date)).reduce((x,p)=>x+(+p.amount||0),0),0);
  const kasKeluarP=co.reduce((a,c)=>a+(+c.amount||0),0);
  const totalMasuk=S.notas.reduce((a,n)=>a+notaPaid(n),0);
  const totalKeluar=cashOut().reduce((a,c)=>a+(+c.amount||0),0);
  return {notas,pendapatan,pajak,hpp,promo,labaKotor,biayaOp,labaBersih,byCat,co,kasMasukP,kasKeluarP,saldo:totalMasuk-totalKeluar};
}
const CASH_CATS=[["prive","Kas Diambil Pemilik"],["operasional","Biaya Operasional"],["modal","Beli Bahan / Modal"],["lain","Lainnya"]];
const cashCatLabel=k=>({prive:"Kas Diambil Pemilik",operasional:"Biaya Operasional",modal:"Beli Bahan / Modal",lain:"Lainnya"}[k]||k);
const cashCatIcon=k=>({prive:"👤",operasional:"⚙️",modal:"📦",lain:"•"}[k]||"•");
// ---- Keuangan: ringkasan vs bulan lalu, tren 6 bulan, laba per produk, rincian kas per kategori ----
let KAS_CAT="";
const monthShift=(mk,d)=>{let y=+mk.slice(0,4),m=+mk.slice(5,7)-1+d;y+=Math.floor(m/12);m=((m%12)+12)%12;return `${y}-${String(m+1).padStart(2,"0")}`;};
const keuDelta=(cur,prev)=>{if(!prev||cur===prev)return"";const d=(cur-prev)/Math.abs(prev)*100;return `<span class="mini" style="color:${d>0?"var(--green)":"var(--red)"};font-weight:700"> ${d>0?"▲":"▼"}${Math.abs(d).toFixed(0)}%</span>`;};
function keuSummaryHtml(k){
  const mk=FILTER_MONTH;const pv=mk==="all"?null:keuCalc(monthShift(mk,-1));
  const mg=k.pendapatan>0?k.labaBersih/k.pendapatan*100:0,mgk=k.pendapatan>0?k.labaKotor/k.pendapatan*100:0;
  const c=(l,v,d,cl)=>`<div class="card ${cl||""}"><div class="lbl">${l}</div><div class="val">${v}${d||""}</div></div>`;
  return `<div class="cards">${c("Omzet",rp(k.pendapatan),pv?keuDelta(k.pendapatan,pv.pendapatan):"")}${c("Laba Bersih",rp(k.labaBersih),pv?keuDelta(k.labaBersih,pv.labaBersih):"",k.labaBersih>=0?"green":"")}${c("Margin Laba Kotor",mgk.toFixed(0)+"%")}${c("Margin Laba Bersih",mg.toFixed(0)+"%")}</div>${pv?`<p class="mini" style="margin:-10px 4px 14px">▲▼ dibanding ${monthLabelFull(monthShift(mk,-1))}.</p>`:""}`;
}
function keuTrendHtml(){
  const base=FILTER_MONTH==="all"?curMonth():FILTER_MONTH;
  const ms=[5,4,3,2,1,0].map(d=>monthShift(base,-d));
  const ds=ms.map(m=>({m,k:keuCalc(m)}));
  const mx=Math.max(1,...ds.map(d=>Math.max(d.k.pendapatan,Math.abs(d.k.labaBersih))));
  const col=d=>{const h1=Math.round(d.k.pendapatan/mx*100),h2=Math.round(Math.abs(d.k.labaBersih)/mx*100);
    return `<div style="flex:1;text-align:center;min-width:0"><div style="height:110px;display:flex;align-items:flex-end;justify-content:center;gap:4px"><div title="Omzet ${rp(d.k.pendapatan)}" style="width:42%;max-width:26px;height:${h1}%;min-height:2px;background:var(--orange-l);border-radius:5px 5px 0 0"></div><div title="Laba bersih ${rp(d.k.labaBersih)}" style="width:42%;max-width:26px;height:${h2}%;min-height:2px;background:${d.k.labaBersih>=0?"var(--green)":"var(--red)"};border-radius:5px 5px 0 0"></div></div><div class="mini" style="margin-top:4px">${monthLabel(d.m)}</div><div class="mini" style="font-size:10.5px;color:${d.k.labaBersih>=0?"var(--green)":"var(--red)"}">${rp(d.k.labaBersih)}</div></div>`;};
  return `<div class="panel"><h3>Tren 6 Bulan <span class="mini" style="font-weight:400">omzet <span style="color:var(--orange)">■</span> · laba bersih <span style="color:var(--green)">■</span></span></h3><div style="display:flex;gap:6px">${ds.map(col).join("")}</div></div>`;
}
function keuProdukHtml(k){
  const m={};k.notas.forEach(n=>notaItems(n).forEach(it=>{if((it.kind||"jual")!=="jual")return;const o=m[it.productId]||(m[it.productId]={q:0,om:0,lb:0});const q=+it.qty||0;o.q+=q;o.om+=q*(+it.harga||0);o.lb+=q*((+it.harga||0)-(+it.hpp||0));}));
  const rows=Object.entries(m).sort((a,b)=>b[1].lb-a[1].lb);
  if(!rows.length)return"";
  return `<div class="panel"><h3>Laba per Produk <span class="mini" style="font-weight:400">(sebelum diskon &amp; biaya lain)</span></h3><div style="overflow-x:auto"><table><thead><tr><th>Produk</th><th class="num">Terjual</th><th class="num">Omzet</th><th class="num">Laba</th><th class="num">Margin</th></tr></thead><tbody>${rows.map(([pid,o])=>`<tr><td>${esc((prod(pid)||{}).name||"?")}</td><td class="num">${o.q}</td><td class="num">${rp(o.om)}</td><td class="num" style="color:${o.lb>=0?"var(--green)":"var(--red)"}"><b>${rp(o.lb)}</b></td><td class="num">${o.om>0?(o.lb/o.om*100).toFixed(0):0}%</td></tr>`).join("")}</tbody></table></div></div>`;
}
function keuKasCatHtml(k){
  const tot=k.kasKeluarP;if(!(tot>0))return"";
  return `<div class="panel"><h3>Kas Keluar per Kategori <span class="mini" style="font-weight:400">${monthLabelFull(FILTER_MONTH)}</span></h3>${CASH_CATS.map(([v,t])=>{const a=k.byCat(v);if(!a)return"";const pc=a/tot*100;return `<div style="margin:8px 0"><div style="display:flex;justify-content:space-between;font-size:13px"><span>${cashCatIcon(v)} ${t}</span><span><b>${rp(a)}</b> <span class="mini">${pc.toFixed(0)}%</span></span></div><div style="height:7px;background:var(--bg);border-radius:6px;margin-top:4px"><div style="width:${pc}%;height:100%;background:var(--orange);border-radius:6px"></div></div></div>`;}).join("")}</div>`;
}
function exportKas(){
  const co=cashOut().filter(c=>FILTER_MONTH==="all"||(c.date||"").slice(0,7)===FILTER_MONTH).sort((a,b)=>(a.date||"").localeCompare(b.date||""));
  if(!co.length){toast("Tak ada kas keluar pada periode ini.");return;}
  const rows=[["Tanggal","Kategori","Jumlah","Catatan"]];co.forEach(c=>rows.push([c.date,cashCatLabel(c.category),+c.amount||0,c.note||""]));
  rows.push(["TOTAL","",co.reduce((a,c)=>a+(+c.amount||0),0),""]);
  downloadCSV("kas-keluar-"+(FILTER_MONTH==="all"?"semua":FILTER_MONTH)+".csv",rows);
}
function rKeuangan(){
  const k=keuCalc();
  const tabs=`<div class="ltabs" style="max-width:320px;margin-bottom:14px"><button class="${KEU_TAB==='lr'?'on':''}" onclick="KEU_TAB='lr';rKeuangan()">Laba Rugi</button><button class="${KEU_TAB==='kas'?'on':''}" onclick="KEU_TAB='kas';rKeuangan()">Kas</button></div>`;
  let body;
  if(KEU_TAB==='lr'){
    body=keuSummaryHtml(k)+`<div class="panel"><h3>Laba Rugi <span class="mini" style="font-weight:400">${monthLabelFull(FILTER_MONTH)}</span> <button class="btn sm" onclick="printLR()">🖨 Cetak</button> <button class="btn sm gray" onclick="exportLR()">⬇️ CSV</button></h3>
      <div class="lrrow"><span>Pendapatan (Omzet)</span><b>${rp(k.pendapatan)}</b></div>
      <div class="lrrow"><span>Harga Pokok Penjualan (HPP)</span><span style="color:var(--red)">(${rp(k.hpp)})</span></div>
      <div class="lrrow tot"><span>Laba Kotor</span><b>${rp(k.labaKotor)}</b></div>
      ${k.promo>0?`<div class="lrrow"><span>Biaya Barang Gratis (bonus/endorse/tester)</span><span style="color:var(--red)">(${rp(k.promo)})</span></div>`:""}
      <div class="lrrow"><span>Biaya Operasional Lain <span style="font-weight:400;color:var(--muted)">(non-produksi)</span></span><span style="color:var(--red)">(${rp(k.biayaOp)})</span></div>
      <div class="lrrow tot big"><span>Laba Bersih</span><b style="color:${k.labaBersih>=0?'var(--green)':'var(--red)'}">${rp(k.labaBersih)}</b></div>
      ${k.pajak>0?`<div class="lrrow" style="border-top:1px dashed var(--line)"><span>Pajak Terkumpul (PPN) <span style="font-weight:400;color:var(--muted)">— titipan negara, di luar laba</span></span><b style="color:var(--amber)">${rp(k.pajak)}</b></div>`:""}</div>
      <p class="mini" style="padding:0 4px">HPP sudah termasuk bahan + operasional produksi. Kas diambil pemilik (prive) tidak masuk laba rugi — lihat tab <b>Kas</b>.${k.pajak>0?" Pajak yang dipungut bukan laba — sisihkan untuk disetor.":""}</p>`+keuTrendHtml()+keuProdukHtml(k);
  } else {
    const rowsF=k.co.filter(c=>!KAS_CAT||c.category===KAS_CAT).sort((a,b)=>(b.date||"").localeCompare(a.date||"")).map(c=>`<div class="crow" onclick="editCash('${c.id}')"><div class="ci">${cashCatIcon(c.category)}</div><div class="cmain"><div class="ctitle">${cashCatLabel(c.category)}</div><div class="csub">${fmtDate(c.date)}${c.note?" · "+esc(c.note):""}</div></div><div class="cright"><div class="camt" style="color:var(--red)">−${rp(c.amount)}</div></div><button class="crow-del" onclick="event.stopPropagation();delCash('${c.id}')">✕</button></div>`);
    body=`<div class="cards"><div class="card accent"><div class="lbl">Saldo Kas (total)</div><div class="val">${rp(k.saldo)}</div></div></div>
    <div class="tiles">
      <div class="tile"><div class="tl2">⬇️ Kas Masuk</div><div class="tv" style="color:var(--green)">${rp(k.kasMasukP)}</div><div class="tc">${monthLabelFull(FILTER_MONTH)}</div></div>
      <div class="tile"><div class="tl2">⬆️ Kas Keluar</div><div class="tv" style="color:var(--red)">${rp(k.kasKeluarP)}</div><div class="tc">prive ${rp(k.byCat('prive'))}</div></div>
    </div>
    <div class="flexbtns"><button class="btn" onclick="editCash()">+ Kas Keluar / Prive</button><button class="btn gray" onclick="exportKas()">⬇️ CSV</button></div>
    ${keuKasCatHtml(k)}
    <div class="panel"><h3>Rincian Kas Keluar</h3><div class="flexbtns" style="margin:0 0 10px;flex-wrap:wrap">${[["","Semua"],...CASH_CATS].map(([v,t])=>`<button class="btn sm ${KAS_CAT===v?"":"gray"}" onclick="KAS_CAT='${v}';rKeuangan()">${t}</button>`).join("")}</div>${rowsF.length===0?'<div class="empty">Belum ada kas keluar.</div>':`<div class="clist">${rowsF.join("")}</div>`}</div>`;
  }
  document.getElementById("v-keuangan").innerHTML=`<h2 class="title">Keuangan</h2><div class="desc">Laba rugi & arus kas usaha.</div>${monthBar()}${tabs}${body}`;
}
function editCash(id){
  const c=id?JSON.parse(JSON.stringify(cashOut().find(x=>x.id===id))):{id:null,date:today(),category:"prive",amount:"",note:""};
  window._c=c;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${c.id?"Edit":"Tambah"} Kas Keluar</h3>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal</label><input type="date" value="${c.date}" oninput="_c.date=this.value"></div><div><label class="f">Kategori</label><select oninput="_c.category=this.value">${CASH_CATS.map(([v,t])=>`<option value="${v}" ${c.category===v?"selected":""}>${t}</option>`).join("")}</select></div></div>
  <div style="margin-bottom:12px"><label class="f">Jumlah (Rp)</label><input inputmode="numeric" value="${c.amount?grp(c.amount):""}" oninput="this.value=grp(this.value);_c.amount=digits(this.value)" placeholder="0"></div>
  <label class="f">Catatan</label><input value="${esc(c.note||"")}" oninput="_c.note=this.value" placeholder="mis. ambil untuk keperluan pribadi">
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveCash()">Simpan</button></div>`);
}
async function saveCash(){const c=window._c;if(!(+c.amount>0)){toast("Isi jumlah.");return;}await api("saveCashOut",{cash:c});await reload();closeModal();toast("Kas keluar dicatat ✓");rKeuangan();}
async function delCash(id){if(confirm("Hapus catatan kas keluar ini?")){await api("deleteCashOut",{id});await reload();rKeuangan();}}
