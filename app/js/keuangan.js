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
// ---- Keuangan v2: hero laba bersih, air terjun laba rugi, tren 6 bulan, laba per produk, tab Kas ----
let KAS_CAT="";
const KN_COL={prive:"#D53E0F",operasional:"#FA8743",modal:"#7E0306",lain:"#C9B3A6"};
const monthShift=(mk,d)=>{let y=+mk.slice(0,4),m=+mk.slice(5,7)-1+d;y+=Math.floor(m/12);m=((m%12)+12)%12;return `${y}-${String(m+1).padStart(2,"0")}`;};
const keuDelta=(cur,prev)=>{if(!prev||cur===prev)return"";const d=(cur-prev)/Math.abs(prev)*100;return `<span class="mini" style="color:${d>0?"var(--green)":"var(--red)"};font-weight:700"> ${d>0?"▲":"▼"}${Math.abs(d).toFixed(0)}%</span>`;};
const knPct=(v,t)=>t>0?Math.max(0,Math.min(100,v/t*100)):0;
// pil perubahan vs bulan lalu (di dalam hero)
function knDeltaPill(cur,prev,lbl){
  if(FILTER_MONTH==="all")return `<span class="kn-pill">📅 Seluruh periode</span>`;
  if(!prev)return `<span class="kn-pill">${cur?"✨ Belum ada pembanding bulan lalu":"Belum ada data"}</span>`;
  if(cur===prev)return `<span class="kn-pill">≈ Sama dengan ${lbl}</span>`;
  const d=(cur-prev)/Math.abs(prev)*100;
  return `<span class="kn-pill ${d>0?"up":"dn"}">${d>0?"▲":"▼"} ${Math.abs(d).toFixed(0)}% vs ${lbl}</span>`;
}
// insight murah: hari penjualan terbaik & pengeluaran terbesar
function knInsights(k){
  const out=[];
  const byDay={};k.notas.forEach(n=>{if(n.date)byDay[n.date]=(byDay[n.date]||0)+notaBase(n)+notaService(n);});
  const days=Object.entries(byDay).sort((a,b)=>b[1]-a[1]);
  if(days.length&&days[0][1]>0)out.push(["🏆","Hari penjualan terbaik",`${fmtDate(days[0][0])} — ${rp(days[0][1])}`]);
  const cats=CASH_CATS.map(([v,t])=>[v,t,k.byCat(v)]).filter(c=>c[2]>0).sort((a,b)=>b[2]-a[2]);
  if(cats.length)out.push([cashCatIcon(cats[0][0]),"Pengeluaran terbesar",`${cats[0][1]} — ${rp(cats[0][2])}`]);
  if(k.pendapatan>0){const mg=k.labaBersih/k.pendapatan*100;out.push([mg>=20?"😄":mg>=0?"🙂":"⚠️","Kesehatan margin",mg>=20?"Sehat — margin bersih di atas 20%":mg>=0?"Tipis — cek HPP & biaya operasional":"Rugi bulan ini — biaya melebihi pendapatan"]);}
  if(!out.length)return"";
  return `<div class="kn-ins">${out.map(([i,t,v])=>`<div class="kn-in-c"><span class="kn-in-i">${i}</span><div><div class="kn-in-t">${t}</div><div class="kn-in-v">${v}</div></div></div>`).join("")}</div>`;
}
// hero (bulan + pemilih bulan + angka utama) — tab "lr" & "kas"
function knHero(k,tab){
  const mk=FILTER_MONTH,pm=mk==="all"?null:monthShift(mk,-1),pv=pm?keuCalc(pm):null,pl=pm?monthLabelFull(pm):"";
  const top=`<div class="kn-top"><div><div class="kn-hi">💼 Keuangan</div><div class="kn-sub">${monthLabelFull(mk)}</div></div><div class="kn-month">${monthBar()}</div></div>`;
  const bg=`<div class="dh-bg" aria-hidden="true"><i></i><i></i><i></i></div>`;
  if(tab==="lr"){
    const mg=k.pendapatan>0?k.labaBersih/k.pendapatan*100:0,off=(1-Math.max(0,Math.min(1,mg/100))).toFixed(3);
    return `<div class="kn-hero">${bg}<div class="kn-hmain">${top}
      <div class="kn-lbl">Laba bersih</div><div class="kn-val ${k.labaBersih<0?"neg":""}">${cnt(k.labaBersih)}</div>
      <div class="kn-pills">${knDeltaPill(k.labaBersih,pv&&pv.labaBersih,pl)}<span class="kn-pill">💰 Omzet ${rp(k.pendapatan)}</span></div></div>
      <div class="kn-hside"><div class="kn-ringrow"><div class="kn-ring"><svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="8"/><circle class="rg" cx="40" cy="40" r="32" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round" pathLength="1" style="--off:${off}" transform="rotate(-90 40 40)"/></svg><b>${cnt(Math.max(0,mg),"pct")}</b></div><div><div class="kn-rl">Margin laba bersih</div><div class="kn-rs">${k.pendapatan>0?(mg>=20?"Sehat, pertahankan! 🎉":mg>=0?"Masih bisa ditingkatkan":"Perlu perhatian — rugi"):"Belum ada penjualan"}</div></div></div></div></div>`;
  }
  return `<div class="kn-hero kas">${bg}<div class="kn-hmain">${top}
    <div class="kn-lbl">Saldo kas</div><div class="kn-val ${k.saldo<0?"neg":""}">${cnt(k.saldo)}</div>
    <div class="kn-pills"><span class="kn-pill up">⬇️ Masuk ${rp(k.kasMasukP)}</span><span class="kn-pill dn">⬆️ Keluar ${rp(k.kasKeluarP)}</span></div></div>
    <div class="kn-hside"><button class="kn-add" onclick="editCash()"><span>＋</span> Catat Kas Keluar</button></div></div>`;
}
function keuSummaryHtml(k){
  const mk=FILTER_MONTH;const pv=mk==="all"?null:keuCalc(monthShift(mk,-1));
  const mg=k.pendapatan>0?k.labaBersih/k.pendapatan*100:0,mgk=k.pendapatan>0?k.labaKotor/k.pendapatan*100:0;
  const card=(ic,l,v,sub,bar,cl)=>`<div class="kn-card ${cl||""}"><div class="kn-ci">${ic}</div><div class="kn-cl">${l}</div><div class="kn-cv">${v}</div>${bar!=null?`<div class="kn-bar"><span style="width:${Math.max(0,Math.min(100,bar))}%"></span></div>`:""}<div class="kn-cs">${sub||""}</div></div>`;
  return `<div class="kn-cards">${card("📈","Margin laba kotor",cnt(mgk,"pct"),"Omzet − HPP",mgk,"o")}${card("🎯","Margin laba bersih",cnt(mg,"pct"),"Setelah semua biaya",mg,mg>=0?"g":"r")}${card("💰","Omzet",cnt(k.pendapatan),pv?keuDelta(k.pendapatan,pv.pendapatan)+" <span class=\"mini\">vs bulan lalu</span>":"Tanpa pajak")}${card("🧾","Laba kotor",cnt(k.labaKotor),pv?keuDelta(k.labaKotor,pv.labaKotor)+" <span class=\"mini\">vs bulan lalu</span>":"Sebelum biaya operasional")}</div>`;
}
// laporan laba rugi bentuk "air terjun"
function knWaterfall(k){
  const P=k.pendapatan,sc=Math.max(P,k.hpp+k.promo+k.biayaOp,Math.abs(k.labaBersih),1);
  let cur=P;
  const row=(ic,l,v,kind,note)=>{
    let left=0,w=v;
    if(kind==="cost"){left=Math.max(0,cur-v);w=cur-left;cur-=v;}
    else if(kind==="sub"){w=Math.max(0,v);}
    else if(kind==="end"){w=Math.abs(v);}
    const pc=P>0?(v/P*100).toFixed(0)+"%":"–";
    const neg=kind==="cost",col=kind==="end"?(v>=0?"g":"r"):kind==="cost"?"c":kind==="sub"?"s":"t";
    return `<div class="kn-wf ${kind}"><div class="kn-wl"><span class="kn-wi">${ic}</span><span class="kn-wn">${l}${note?`<small>${note}</small>`:""}</span></div><div class="kn-wt"><div class="kn-wb ${col}" style="left:${left/sc*100}%;width:${Math.max(.6,w/sc*100)}%"></div></div><div class="kn-wv"><b class="${col}">${neg?"−":""}${rp(Math.abs(v))}</b><span>${pc}</span></div></div>`;
  };
  let h=row("💰","Omzet",P,"top","Penjualan tanpa pajak");
  h+=row("🧪","HPP",k.hpp,"cost","Bahan + operasional produksi");
  h+=row("🧮","Laba kotor",k.labaKotor,"sub");cur=k.labaKotor;
  if(k.promo>0)h+=row("🎁","Barang gratis",k.promo,"cost","Bonus / endorse / tester");
  h+=row("⚙️","Biaya operasional",k.biayaOp,"cost","Non-produksi");
  h+=row(k.labaBersih>=0?"✅":"⚠️","Laba bersih",k.labaBersih,"end");
  return `<div class="panel kn-panel"><h3>Laba Rugi <span class="kn-h3r"><button class="kn-btn" onclick="printLR()">🖨 Cetak</button><button class="kn-btn ghost" onclick="exportLR()">⬇️ CSV</button></span></h3><div class="kn-wfs">${h}</div></div>`;
}
function knPajak(k){
  if(!(k.pajak>0))return"";
  return `<div class="kn-tax"><div class="kn-tx-i">🏛️</div><div><div class="kn-tx-t">Pajak terkumpul (PPN)</div><div class="kn-tx-s">Titipan negara, di luar laba — sisihkan untuk disetor.</div></div><b class="kn-tx-v">${rp(k.pajak)}</b></div>`;
}
function keuTrendHtml(){
  const base=FILTER_MONTH==="all"?curMonth():FILTER_MONTH;
  const ms=[5,4,3,2,1,0].map(d=>monthShift(base,-d));
  const ds=ms.map(m=>({m,k:keuCalc(m)}));
  if(ds.every(d=>!d.k.pendapatan&&!d.k.labaBersih))return `<div class="panel kn-panel"><h3>Tren 6 Bulan</h3><div class="empty">Belum ada data penjualan untuk digrafikkan.</div></div>`;
  const W=360,H=140,pt=12,pb=6,cw=W/6;
  const hi=Math.max(1,...ds.map(d=>Math.max(d.k.pendapatan,d.k.labaBersih))),lo=Math.min(0,...ds.map(d=>d.k.labaBersih)),rng=(hi-lo)||1;
  const Y=v=>pt+(H-pt-pb)*(1-(v-lo)/rng),y0=Y(0),cx=i=>cw*(i+.5);
  const bars=ds.map((d,i)=>{const y=Y(Math.max(0,d.k.pendapatan)),h=Math.max(2,y0-y);return `<rect class="kn-bar2${d.m===base?" cur":""}" x="${(cx(i)-13).toFixed(1)}" y="${(y0-h).toFixed(1)}" width="26" height="${h.toFixed(1)}" rx="8" style="transform-origin:${cx(i).toFixed(1)}px ${y0.toFixed(1)}px;animation-delay:${i*70}ms"><title>Omzet ${rp(d.k.pendapatan)}</title></rect>`;}).join("");
  const pts=ds.map((d,i)=>[cx(i),Y(d.k.labaBersih)]);
  const path=pts.map((p,i)=>(i?"L":"M")+p[0].toFixed(1)+" "+p[1].toFixed(1)).join(" ");
  const dots=pts.map((p,i)=>`<circle class="kn-dot" cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="4.5" style="animation-delay:${900+i*70}ms"><title>Laba bersih ${rp(ds[i].k.labaBersih)}</title></circle>`).join("");
  const labs=ds.map(d=>`<div class="${d.m===base?"cur":""}"><span>${monthLabel(d.m)}</span><b style="color:${d.k.labaBersih>=0?"var(--green)":"var(--red)"}">${rp(d.k.labaBersih)}</b></div>`).join("");
  return `<div class="panel kn-panel"><h3>Tren 6 Bulan <span class="kn-leg"><i class="o"></i>Omzet <i class="g"></i>Laba bersih</span></h3>
    <svg class="kn-trend" viewBox="0 0 ${W} ${H}" role="img" aria-label="Tren omzet dan laba bersih 6 bulan"><defs><linearGradient id="knbg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FA8743"/><stop offset="1" stop-color="#FCAF67" stop-opacity=".55"/></linearGradient></defs><line x1="0" x2="${W}" y1="${y0.toFixed(1)}" y2="${y0.toFixed(1)}" stroke="#eadfd8" stroke-width="1" stroke-dasharray="3 4"/>${bars}<path class="kn-line" d="${path}" pathLength="1" fill="none" stroke="#1E8449" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>${dots}</svg>
    <div class="kn-tl">${labs}</div></div>`;
}
function keuProdukHtml(k){
  const m={};k.notas.forEach(n=>notaItems(n).forEach(it=>{if((it.kind||"jual")!=="jual")return;const o=m[it.productId]||(m[it.productId]={q:0,om:0,lb:0});const q=+it.qty||0;o.q+=q;o.om+=q*(+it.harga||0);o.lb+=q*((+it.harga||0)-(+it.hpp||0));}));
  const rows=Object.entries(m).sort((a,b)=>b[1].lb-a[1].lb);
  if(!rows.length)return"";
  const mx=Math.max(1,...rows.map(r=>Math.abs(r[1].lb)));
  const medal=["🥇","🥈","🥉"];
  return `<div class="panel kn-panel"><h3>Laba per Produk <span class="mini" style="font-weight:400">sebelum diskon &amp; biaya lain</span></h3><div class="kn-pl">${rows.map(([pid,o],i)=>`<div class="kn-pr"><div class="kn-pk">${medal[i]||`<small>${i+1}</small>`}</div><div class="kn-pm"><div class="kn-pn"><span>${esc((prod(pid)||{}).name||"?")}</span><b style="color:${o.lb>=0?"var(--green)":"var(--red)"}">${rp(o.lb)}</b></div><div class="kn-pbar"><span class="${o.lb>=0?"":"neg"}" style="width:${Math.abs(o.lb)/mx*100}%;animation-delay:${i*60}ms"></span></div><div class="kn-ps">${o.q} terjual · omzet ${rp(o.om)} · margin ${o.om>0?(o.lb/o.om*100).toFixed(0):0}%</div></div></div>`).join("")}</div></div>`;
}
// donut + legenda kategori kas keluar
function keuKasCatHtml(k){
  const tot=k.kasKeluarP;if(!(tot>0))return"";
  let cum=0;const items=CASH_CATS.map(([v,t])=>[v,t,k.byCat(v)]).filter(c=>c[2]>0);
  const segs=items.map(([v,t,a],i)=>{const pc=a/tot*100,s=`<circle class="kn-seg" cx="21" cy="21" r="15.915" fill="none" stroke="${KN_COL[v]||"#C9B3A6"}" stroke-width="6.5" stroke-dasharray="${Math.max(0,pc-.8).toFixed(2)} ${(100-Math.max(0,pc-.8)).toFixed(2)}" stroke-dashoffset="${(-cum).toFixed(2)}" style="animation-delay:${.15+i*.15}s"/>`;cum+=pc;return s;}).join("");
  return `<div class="panel kn-panel"><h3>Kas Keluar per Kategori <span class="mini" style="font-weight:400">${monthLabelFull(FILTER_MONTH)}</span></h3><div class="kn-donut"><div class="kn-dn"><svg viewBox="0 0 42 42"><circle cx="21" cy="21" r="15.915" fill="none" stroke="#f6ece6" stroke-width="6.5"/><g transform="rotate(-90 21 21)">${segs}</g></svg><div class="kn-dc"><small>Total</small><b>${rp(tot)}</b></div></div>
    <div class="kn-lg">${items.map(([v,t,a])=>`<div class="kn-lr"><i style="background:${KN_COL[v]||"#C9B3A6"}"></i><span>${cashCatIcon(v)} ${t}</span><b>${rp(a)}</b><em>${(a/tot*100).toFixed(0)}%</em></div>`).join("")}</div></div></div>`;
}
function exportKas(){
  const co=cashOut().filter(c=>FILTER_MONTH==="all"||(c.date||"").slice(0,7)===FILTER_MONTH).sort((a,b)=>(a.date||"").localeCompare(b.date||""));
  if(!co.length){toast("Tak ada kas keluar pada periode ini.");return;}
  const rows=[["Tanggal","Kategori","Jumlah","Catatan"]];co.forEach(c=>rows.push([c.date,cashCatLabel(c.category),+c.amount||0,c.note||""]));
  rows.push(["TOTAL","",co.reduce((a,c)=>a+(+c.amount||0),0),""]);
  downloadCSV("kas-keluar-"+(FILTER_MONTH==="all"?"semua":FILTER_MONTH)+".csv",rows);
}
// ganti tab: indikator geser dulu, baru render ulang
function knTab(t){
  if(t===KEU_TAB)return;KEU_TAB=t;
  const el=document.querySelector("#v-keuangan .kn-tabs");
  if(el){el.dataset.t=t;el.querySelectorAll("button").forEach(b=>b.classList.toggle("on",b.dataset.t===t));}
  const reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion:reduce)").matches;
  setTimeout(rKeuangan,reduce?0:230);
}
function knKasRows(k){
  const list=k.co.filter(c=>!KAS_CAT||c.category===KAS_CAT).sort((a,b)=>(b.date||"").localeCompare(a.date||""));
  if(!list.length)return `<div class="empty">${KAS_CAT?"Tak ada kas keluar di kategori ini.":"Belum ada kas keluar."}</div>`;
  return `<div class="kn-kl">${list.map(c=>`<div class="kn-kr" onclick="editCash('${c.id}')"><div class="kn-ki" style="background:${KN_COL[c.category]||"#C9B3A6"}1f;color:${KN_COL[c.category]||"#8a6f60"}">${cashCatIcon(c.category)}</div><div class="kn-km"><div class="kn-kt">${cashCatLabel(c.category)}</div><div class="kn-ks">${fmtDate(c.date)}${c.note?" · "+esc(c.note):""}</div></div><div class="kn-ka">−${rp(c.amount)}</div><button class="kn-kd" aria-label="Hapus" onclick="event.stopPropagation();delCash('${c.id}')">✕</button></div>`).join("")}</div>`;
}
function rKeuangan(){
  const k=keuCalc();
  const tabs=`<div class="kn-tabs" data-t="${KEU_TAB}"><button data-t="lr" class="${KEU_TAB==='lr'?'on':''}" onclick="knTab('lr')">📊 Laba Rugi</button><button data-t="kas" class="${KEU_TAB==='kas'?'on':''}" onclick="knTab('kas')">💵 Kas</button></div>`;
  let body;
  if(KEU_TAB==='lr'){
    body=knHero(k,"lr")+tabs+knInsights(k)+knWaterfall(k)+keuSummaryHtml(k)+knPajak(k)
      +`<p class="kn-note">HPP sudah termasuk bahan + operasional produksi. Kas diambil pemilik (prive) tidak masuk laba rugi — lihat tab <b>Kas</b>.</p>`+keuTrendHtml()+keuProdukHtml(k);
  } else {
    const big=k.co.slice().sort((a,b)=>(+b.amount||0)-(+a.amount||0))[0];
    const duo=`<div class="kn-duo"><div class="kn-mini in"><div class="kn-mi">⬇️</div><div><div class="kn-ml">Kas masuk</div><div class="kn-mv">${cnt(k.kasMasukP)}</div><div class="kn-ms">${monthLabelFull(FILTER_MONTH)}</div></div></div><div class="kn-mini out"><div class="kn-mi">⬆️</div><div><div class="kn-ml">Kas keluar</div><div class="kn-mv">${cnt(k.kasKeluarP)}</div><div class="kn-ms">prive ${rp(k.byCat('prive'))}</div></div></div></div>`;
    const ins=big?`<div class="kn-ins"><div class="kn-in-c"><span class="kn-in-i">🔎</span><div><div class="kn-in-t">Pengeluaran terbesar</div><div class="kn-in-v">${cashCatLabel(big.category)} — ${rp(big.amount)}${big.note?" · "+esc(big.note):""}</div></div></div><div class="kn-in-c"><span class="kn-in-i">🧾</span><div><div class="kn-in-t">Jumlah catatan</div><div class="kn-in-v">${k.co.length} kas keluar periode ini</div></div></div></div>`:"";
    const pills=[["","Semua"],...CASH_CATS].map(([v,t])=>`<button class="kn-chip ${KAS_CAT===v?"on":""}" onclick="KAS_CAT='${v}';rKeuangan()">${v?cashCatIcon(v)+" ":""}${t}</button>`).join("");
    body=knHero(k,"kas")+tabs+duo+ins+keuKasCatHtml(k)
      +`<div class="panel kn-panel"><h3>Rincian Kas Keluar <span class="kn-h3r"><button class="kn-btn" onclick="editCash()">＋ Tambah</button><button class="kn-btn ghost" onclick="exportKas()">⬇️ CSV</button></span></h3><div class="kn-chips">${pills}</div>${knKasRows(k)}</div>`;
  }
  const el=document.getElementById("v-keuangan");
  el.innerHTML=`<div class="kn">${body}</div>`;
  if(typeof dashAnimate==="function")dashAnimate(el.firstElementChild);
}
function knPickCat(btn,v){window._c.category=v;btn.parentNode.querySelectorAll("button").forEach(b=>b.classList.toggle("on",b===btn));}
function editCash(id){
  const c=id?JSON.parse(JSON.stringify(cashOut().find(x=>x.id===id))):{id:null,date:today(),category:"prive",amount:"",note:""};
  window._c=c;
  openModal(`<div class="kn-mod"><button class="close" onclick="closeModal()">×</button><div class="kn-mh"><div class="kn-mhi">${c.id?"✏️":"💸"}</div><div><h3>${c.id?"Edit":"Catat"} Kas Keluar</h3><div class="kn-mhs">Uang yang keluar dari usaha — prive, biaya, atau beli bahan.</div></div></div>
  <label class="f">Jumlah</label><div class="kn-amt"><span>Rp</span><input inputmode="numeric" value="${c.amount?grp(c.amount):""}" oninput="this.value=grp(this.value);_c.amount=digits(this.value)" placeholder="0"></div>
  <label class="f">Kategori</label><div class="kn-cats">${CASH_CATS.map(([v,t])=>`<button type="button" class="${c.category===v?"on":""}" onclick="knPickCat(this,'${v}')"><span>${cashCatIcon(v)}</span>${t}</button>`).join("")}</div>
  <div class="grid2"><div><label class="f">Tanggal</label><input type="date" value="${c.date}" oninput="_c.date=this.value"></div><div><label class="f">Catatan</label><input value="${esc(c.note||"")}" oninput="_c.note=this.value" placeholder="mis. ambil untuk keperluan pribadi"></div></div>
  <div class="kn-mf">${c.id?`<button class="kn-mdel" onclick="closeModal();delCash('${c.id}')">🗑 Hapus</button>`:"<span></span>"}<span><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveCash()">Simpan</button></span></div></div>`);
}
async function saveCash(){const c=window._c;if(!(+c.amount>0)){toast("Isi jumlah.");return;}await api("saveCashOut",{cash:c});await reload();closeModal();toast("Kas keluar dicatat ✓");rKeuangan();}
async function delCash(id){if(confirm("Hapus catatan kas keluar ini?")){await api("deleteCashOut",{id});await reload();rKeuangan();}}
