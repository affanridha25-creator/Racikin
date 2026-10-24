"use strict";
// dashboard.js — dashboard
// ================= DASHBOARD =================
// --- helper waktu & delta ---
function monthOffset(base,delta){const d=new Date(base+"-01T00:00");const x=new Date(d.getFullYear(),d.getMonth()+delta,1);return x.getFullYear()+"-"+String(x.getMonth()+1).padStart(2,"0");}
function lastMonths(n){const base=FILTER_MONTH==="all"?curMonth():FILTER_MONTH;const arr=[];for(let i=n-1;i>=0;i--)arr.push(monthOffset(base,-i));return arr;}
function daysSince(dateStr){if(!dateStr)return 0;const a=new Date(dateStr+"T00:00"),b=new Date(today()+"T00:00");return Math.max(0,Math.round((b-a)/86400000));}
function momDelta(byMonthObj){if(FILTER_MONTH==="all")return null;const pk=monthOffset(FILTER_MONTH,-1);const pv=byMonthObj[pk]||0,cv=byMonthObj[FILTER_MONTH]||0;return pv===0?null:(cv-pv)/pv;}
function momBadge(frac){if(frac==null)return '<span style="opacity:.75">belum ada pembanding</span>';const up=frac>=0;return `<span style="color:${up?'#c8f2d4':'#ffd3c7'};font-weight:700">${up?'▲':'▼'} ${Math.abs(frac*100).toFixed(0)}% vs bln lalu</span>`;}
function deltaTag(frac){if(frac==null)return '<span class="mini flat">baru</span>';const up=frac>=0;return `<span class="mini" style="color:${up?'var(--green)':'var(--red)'};font-weight:700">${up?'▲':'▼'} ${Math.abs(frac*100).toFixed(0)}% vs bln lalu</span>`;}

// --- kalkulasi terpusat dashboard (dipakai versi mobile & desktop) ---
function computeDash(){
  const fnotas=S.notas.filter(n=>inMonth(n.date));
  const omzet=fnotas.reduce((a,n)=>a+notaOmzet(n),0);   // tanpa pajak, konsisten dg Laba-Rugi
  const laba=fnotas.reduce((a,n)=>a+notaProfit(n),0);
  const margin=omzet>0?laba/omzet*100:0;
  const byOmz={},byLab={};S.notas.forEach(n=>{const k=(n.date||"").slice(0,7);if(!k)return;byOmz[k]=(byOmz[k]||0)+notaOmzet(n);byLab[k]=(byLab[k]||0)+notaProfit(n);});
  const dOmz=momDelta(byOmz),dLab=momDelta(byLab);
  // posisi keuangan (saldo/piutang/stok = kondisi SEKARANG, lintas semua bulan)
  const saldoKas=keuCalc().saldo;
  const piutang=S.notas.reduce((a,n)=>a+Math.max(0,notaDue(n)),0);
  const nilaiStok=S.products.reduce((a,p)=>a+Math.max(0,stock(p.id))*(+p.hpp||0),0);
  // tren 6 bulan (omzet + laba)
  const trend=lastMonths(6).map(m=>({m,omzet:byOmz[m]||0,laba:byLab[m]||0}));
  // produk paling menguntungkan (bulan terpilih)
  const labaByP={},omzByP={};
  fnotas.forEach(n=>notaItems(n).forEach(it=>{const p=it.productId;labaByP[p]=(labaByP[p]||0)+(+it.qty||0)*((+it.harga||0)-(+it.hpp||0));omzByP[p]=(omzByP[p]||0)+(+it.qty||0)*(+it.harga||0);}));
  const prodRank=S.products.map(p=>({n:p.name,laba:labaByP[p.id]||0,omzet:omzByP[p.id]||0})).filter(x=>x.omzet!==0||x.laba!==0).sort((a,b)=>b.laba-a.laba);
  // top penunggak (lintas waktu) + umur piutang
  const dueBy={};S.notas.forEach(n=>{const due=notaDue(n);if(due>0&&notaStatus(n)!=="lunas"){const sid=n.storeId;if(!dueBy[sid])dueBy[sid]={due:0,oldest:n.date||""};dueBy[sid].due+=due;if((n.date||"")&&(n.date<dueBy[sid].oldest||!dueBy[sid].oldest))dueBy[sid].oldest=n.date;}});
  const penunggak=Object.entries(dueBy).map(([sid,v])=>({n:(store(sid)||{}).name||"?",due:v.due,days:daysSince(v.oldest)})).sort((a,b)=>b.due-a.due);
  // pusat aksi
  const low=S.products.filter(p=>stock(p.id)<20).map(p=>({n:p.name,s:stock(p.id)})).sort((a,b)=>a.s-b.s);
  const overdue=S.notas.filter(n=>notaStatus(n)!=="lunas"&&daysSince(n.date)>14);
  const priceUp=S.materials.map(m=>{const st=matStat(m);return{n:m.name,chg:st.chg,cur:st.cur??m.price};}).filter(x=>x.chg!=null&&x.chg>0.005).sort((a,b)=>b.chg-a.chg);
  const recent=[...fnotas].slice(0,6);
  const tNotas=S.notas.filter(n=>n.date===today());const todayOmzet=tNotas.reduce((a,n)=>a+notaOmzet(n),0),todayN=tNotas.length;
  return {todayOmzet,todayN,fnotas,omzet,laba,margin,dOmz,dLab,saldoKas,piutang,nilaiStok,trend,prodRank,penunggak,low,overdue,priceUp,recent};
}
// --- grafik garis tren 6 bulan (Omzet oranye + Laba biru, satu sumbu Rupiah) ---
function trendChart(trend){
  const W=560,H=172,padL=10,padR=10,padT=16,padB=26,n=trend.length;
  const vals=trend.flatMap(t=>[t.omzet,t.laba]);const mx=Math.max(1,...vals);
  const X=i=>padL+(W-padL-padR)*(n<=1?0.5:i/(n-1));
  const Y=v=>H-padB-(H-padT-padB)*(v/mx);
  const path=key=>trend.map((t,i)=>(i?"L":"M")+X(i).toFixed(1)+" "+Y(t[key]).toFixed(1)).join(" ");
  const dots=(key,color)=>trend.map((t,i)=>`<circle class="dpop" style="animation-delay:${1+i*.1}s" cx="${X(i).toFixed(1)}" cy="${Y(t[key]).toFixed(1)}" r="3" fill="${color}"><title>${monthLabelFull(t.m)} — ${key==="omzet"?"Omzet":"Laba"} ${rp(t[key])}</title></circle>`).join("");
  const labels=trend.map((t,i)=>`<text x="${X(i).toFixed(1)}" y="${H-8}" font-size="10" fill="#8a8f99" text-anchor="middle">${monthLabel(t.m)}</text>`).join("");
  if(vals.every(v=>v===0))return '<div class="empty">Belum ada data penjualan.</div>';
  const areaO=`M${X(0).toFixed(1)} ${(H-padB).toFixed(1)} `+trend.map((t,i)=>`L${X(i).toFixed(1)} ${Y(t.omzet).toFixed(1)}`).join(" ")+` L${X(n-1).toFixed(1)} ${(H-padB).toFixed(1)} Z`;
  return `<div class="pchart"><svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="xMidYMid meet" style="min-width:340px">
    <defs><linearGradient id="tg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--orange)" stop-opacity=".28"/><stop offset="1" stop-color="var(--orange)" stop-opacity="0"/></linearGradient></defs>
    <line x1="${padL}" y1="${H-padB}" x2="${W-padR}" y2="${H-padB}" stroke="#eee"/>
    <path class="ar-fade" d="${areaO}" fill="url(#tg)"/>
    <path class="ln" pathLength="1" d="${path("omzet")}" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    <path class="ln l2" pathLength="1" d="${path("laba")}" fill="none" stroke="#2471a3" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${dots("omzet","var(--red)")}${dots("laba","#2471a3")}${labels}
  </svg></div><div class="legend2"><span><i style="background:var(--red)"></i>Omzet</span><span><i style="background:#2471a3"></i>Laba</span></div>`;
}
// --- bar produk berdasarkan LABA (hijau untung, merah rugi) ---
const prodShort=n=>String(n||"").replace(/\s*\([^)]*\)\s*$/,"").trim();   // buang ukuran "(150gr)" utk label ringkas
function prodProfitBars(prodRank){
  const rank=prodRank.slice(0,6);
  if(!rank.length)return '<div class="empty">Belum ada penjualan.</div>';
  const mx=Math.max(1,...rank.map(x=>Math.abs(x.laba)));
  return rank.map(x=>{const m=x.omzet>0?x.laba/x.omzet*100:0;const neg=x.laba<0;return `<div class="barrow2"><div class="brtop"><span class="bname">${esc(prodShort(x.n))}</span><b style="color:${neg?"var(--red)":"var(--green)"}">${rp(x.laba)}</b></div><div class="brbar"><span style="width:${Math.abs(x.laba)/mx*100}%;background:${neg?"var(--red)":"var(--green)"}"></span></div><div class="brsub">margin ${m.toFixed(0)}%${x.omzet>0?` · omzet ${rp(x.omzet)}`:""}</div></div>`;}).join("");
}
// --- daftar penunggak + umur piutang ---
function penunggakList(penunggak){
  if(!penunggak.length)return '<div class="empty">Tidak ada piutang. 🎉</div>';
  const mx=Math.max(1,...penunggak.map(x=>x.due));
  return penunggak.slice(0,6).map(s=>`<div class="barrow2"><div class="brtop"><span>${esc(s.n)} <span class="mini">${s.days} hari</span></span><b style="color:var(--amber)">${rp(s.due)}</b></div><div class="brbar"><span style="width:${s.due/mx*100}%;background:linear-gradient(90deg,var(--orange-l),var(--amber))"></span></div></div>`).join("");
}
// --- pusat aksi: sinyal yang perlu ditindak ---
function actionCenter(d){
  const items=[];
  d.low.slice(0,5).forEach(x=>items.push(`<div class="actrow"><span class="actdot" style="background:var(--red)"></span><div><b>Stok menipis:</b> ${esc(x.n)} — ${x.s} item</div></div>`));
  if(d.overdue.length)items.push(`<div class="actrow"><span class="actdot" style="background:var(--amber)"></span><div><b>${d.overdue.length} nota</b> lewat 14 hari belum lunas</div></div>`);
  d.priceUp.slice(0,4).forEach(x=>items.push(`<div class="actrow"><span class="actdot" style="background:#b8560f"></span><div><b>Harga bahan naik:</b> ${esc(x.n)} +${(x.chg*100).toFixed(0)}% → ${rp(x.cur)}</div></div>`));
  return items.length?items.join(""):'<div class="empty">Semua aman 👍 tak ada yang perlu ditindak.</div>';
}
function heroPill(frac){
  if(frac==null)return '<span class="hpill">bulan pertama</span>';
  const up=frac>=0;return `<span class="hpill ${up?"up":"dn"}">${up?"▲":"▼"} ${Math.abs(frac*100).toFixed(0)}% vs bln lalu</span>`;
}
// kartu sorotan otomatis — pilih kabar paling penting (mirip kartu "Sales revenue increased" di referensi)
function dashHighlight(d){
  let icon="📈",head="",sub="",link="Lihat",dest="keuangan";
  if(d.dOmz!=null&&d.dOmz>0.001){icon="🎉";head=`Omzet naik ${(d.dOmz*100).toFixed(0)}% dari bulan lalu`;sub="Pertahankan momentum penjualan";dest="keuangan";link="Laba rugi";}
  else if(d.dOmz!=null&&d.dOmz<-0.001){icon="📉";head=`Omzet turun ${Math.abs(d.dOmz*100).toFixed(0)}% dari bulan lalu`;sub="Cek produk & pelanggan yang melambat";dest="keuangan";link="Laba rugi";}
  else if(d.low.length){icon="📦";head=`${d.low.length} produk stok menipis`;sub="Segera produksi biar tak kehabisan";dest="produksi";link="Buat batch";}
  else if(d.piutang>0){icon="💸";head=`${rp(d.piutang)} piutang belum tertagih`;sub=`Dari ${d.penunggak.length} toko`;dest="pembayaran";link="Tagih";}
  else if(d.prodRank.length){icon="⭐";head=`${esc(prodShort(d.prodRank[0].n))} paling cuan`;sub=`Laba ${rp(d.prodRank[0].laba)} bulan ini`;dest="produk";link="Produk";}
  else {icon="👋";head="Selamat datang di Racikin";sub="Mulai catat produksi & penjualanmu";dest="produksi";link="Mulai";}
  return `<div class="hlcard" onclick="go('${dest}')"><div class="hli">${icon}</div><div class="hlmain"><div class="hlh">${head}</div><div class="hls">${sub}</div></div><div class="hlgo">${link} ›</div></div>`;
}
// daftar penunggak gaya baris (lingkaran inisial + nilai kanan) — mirip referensi
function penunggakRows(penunggak){
  if(!penunggak.length)return '<div class="empty">Tidak ada piutang. 🎉</div>';
  return `<div class="clist">${penunggak.slice(0,5).map(s=>`<div class="crow" onclick="go('pembayaran')"><div class="ci mono">${esc((s.n[0]||"?").toUpperCase())}</div><div class="cmain"><div class="ctitle">${esc(s.n)}</div><div class="csub">piutang · ${s.days} hari</div></div><div class="cright"><div class="camt" style="color:var(--amber)">${rp(s.due)}</div></div></div>`).join("")}</div>`;
}
// ---- animasi dashboard: sapaan, hitung-naik angka, stagger, sparkline ----
function greetWord(){const h=new Date().getHours();return h<11?"Selamat pagi":h<15?"Selamat siang":h<18?"Selamat sore":"Selamat malam";}
function cnt(v,f){v=Math.round(+v||0);return `<span class="cnt" data-v="${v}" data-f="${f||"rp"}">${f==="pct"?v+"%":f==="num"?v:rp(v)}</span>`;}
function runCounts(root){
  const reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion:reduce)").matches;
  (root||document).querySelectorAll(".cnt").forEach(el=>{
    const v=+el.dataset.v||0,f=el.dataset.f,fmt=x=>f==="pct"?Math.round(x)+"%":f==="num"?String(Math.round(x)):rp(Math.round(x));
    if(reduce||!v){el.textContent=fmt(v);return;}
    const t0=performance.now()+(+el.dataset.d||250),dur=1100;el.textContent=fmt(0);
    const step=now=>{const p=Math.min(1,Math.max(0,(now-t0)/dur)),e=1-Math.pow(1-p,3);el.textContent=fmt(v*e);if(p<1&&el.isConnected)requestAnimationFrame(step);};
    requestAnimationFrame(step);
  });
}
function dashAnimate(el){el.classList.remove("dash-in");[...el.children].forEach((c,i)=>c.style.setProperty("--i",i));void el.offsetWidth;el.classList.add("dash-in");runCounts(el);}
function sparkline(trend){
  const W=300,H=120,pad=8,n=trend.length,mx=Math.max(1,...trend.map(t=>t.omzet));
  if(trend.every(t=>!t.omzet))return `<div class="dh-nospark">Belum ada data</div>`;
  const X=i=>pad+(W-2*pad)*(n<=1?.5:i/(n-1)),Y=v=>H-pad-(H-2*pad)*(v/mx);
  const line=trend.map((t,i)=>(i?"L":"M")+X(i).toFixed(1)+" "+Y(t.omzet).toFixed(1)).join(" ");
  const area=`M${X(0).toFixed(1)} ${H-pad} `+trend.map((t,i)=>`L${X(i).toFixed(1)} ${Y(t.omzet).toFixed(1)}`).join(" ")+` L${X(n-1).toFixed(1)} ${H-pad} Z`;
  return `<svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="none" class="spark"><defs><linearGradient id="spg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".38"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs><path class="sp-area" d="${area}" fill="url(#spg)"/><path class="sp-line ln" pathLength="1" d="${line}" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>${trend.map((t,i)=>`<circle class="sp-dot" style="animation-delay:${1.1+i*.12}s" cx="${X(i).toFixed(1)}" cy="${Y(t.omzet).toFixed(1)}" r="3.2" fill="#fff"><title>${monthLabelFull(t.m)} — ${rp(t.omzet)}</title></circle>`).join("")}</svg>`;
}
function rDashM(){
  const d=computeDash();
  const uname=BIZ.user||"Racikin";
  const el=document.getElementById("v-dashboard");
  el.innerHTML=`
  <div class="dgreet"><div><h2>${greetWord()}, ${esc(uname)} 👋</h2><p>Ringkasan ${esc(BIZ.name||"usaha")}</p></div></div>
  ${monthBar()}
  <div class="hero2">
    <div class="hl">💰 Omzet · ${monthLabelFull(FILTER_MONTH)}</div>
    <div class="hv">${cnt(d.omzet)}</div>
    <div class="hpills">${heroPill(d.dOmz)}<span class="hpill">Laba ${rp(d.laba)} · ${d.margin.toFixed(0)}%</span></div>
    <div class="hact">
      <button onclick="go('pos')"><span class="hai">🛒</span>Kasir</button>
      <button onclick="editNota()"><span class="hai">🧾</span>Nota</button>
      <button onclick="go('pembayaran')"><span class="hai">💰</span>Bayar</button>
    </div>
  </div>
  ${dashHighlight(d)}
  <div class="tiles">
    <div class="mcard"><div class="mi" style="background:var(--green-bg);color:var(--green)">🏦</div><div class="ml">Saldo Kas</div><div class="mv" style="color:${d.saldoKas>=0?"var(--green)":"var(--red)"}">${cnt(d.saldoKas)}</div><div class="ms muted">posisi sekarang</div></div>
    <div class="mcard"><div class="mi" style="background:var(--amber-bg);color:var(--amber)">⏳</div><div class="ml">Piutang</div><div class="mv" style="color:var(--amber)">${cnt(d.piutang)}</div><div class="ms muted">belum tertagih</div></div>
    <div class="mcard"><div class="mi" style="background:var(--green-bg);color:var(--green)">💵</div><div class="ml">Laba Kotor</div><div class="mv">${cnt(d.laba)}</div><div class="ms">${deltaTag(d.dLab)}</div></div>
    <div class="mcard"><div class="mi" style="background:var(--blue-bg);color:var(--blue)">📦</div><div class="ml">Nilai Stok</div><div class="mv">${cnt(d.nilaiStok)}</div><div class="ms muted">HPP di gudang</div></div>
  </div>
  <div class="dsec"><h3>Tren 6 Bulan</h3></div>
  <div class="card2">${trendChart(d.trend)}</div>
  <div class="dsec"><h3>Produk Terlaris</h3><a onclick="go('produk')">Semua</a></div>
  <div class="card2">${prodProfitBars(d.prodRank)}</div>
  <div class="dsec"><h3>💸 Top Penunggak</h3><a onclick="go('pembayaran')">Tagih</a></div>
  ${penunggakRows(d.penunggak)}
  <div class="dsec"><h3>⚡ Perlu Tindakan</h3></div>
  <div class="card2">${actionCenter(d)}</div>
  <div class="dsec"><h3>Nota Terbaru</h3><a onclick="go('distribusi')">Semua</a></div>
  ${d.recent.length===0?'<div class="empty">Belum ada nota.</div>':`<div class="clist">${d.recent.map(n=>crow({icon:"🧾",title:esc(n.notaNo||"-"),sub:`${esc((store(n.storeId)||{}).name||"?")} · ${fmtDate(n.date)}`,amt:rp(notaTotal(n)),status:notaStatus(n),onclick:"go('distribusi')"})).join("")}</div>`}`;
  dashAnimate(el);
}
function rDash(){
  if(isMobile())return rDashM();
  const d=computeDash();
  const uname=BIZ.user||"Racikin",fnb=bizType()==="fnb";
  const tl=new Date().toLocaleDateString("id-ID",{weekday:"long",day:"numeric",month:"long",year:"numeric"});
  const kpi=(ic,bg,fg,lbl,val,sub,go)=>`<div class="kpi"${go?` onclick="go('${go}')"`:""}><div class="kpi-i" style="background:${bg};color:${fg}">${ic}</div><div class="kpi-l">${lbl}</div><div class="kpi-v">${val}</div><div class="kpi-s">${sub}</div></div>`;
  const el=document.getElementById("v-dashboard");
  el.innerHTML=`
  <div class="dhero">
    <div class="dh-bg" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="dh-main">
      <div class="dh-top"><div><div class="dh-hi">${greetWord()}, ${esc(uname)} 👋</div><div class="dh-sub">${esc(BIZ.name||"")} · ${tl}</div></div><div class="dh-month">${monthBar()}</div></div>
      <div class="dh-lbl">💰 Omzet · ${monthLabelFull(FILTER_MONTH)}</div>
      <div class="dh-val">${cnt(d.omzet)}</div>
      <div class="dh-pills">${heroPill(d.dOmz)}<span class="hpill">Laba ${rp(d.laba)} · ${d.margin.toFixed(0)}%</span><span class="hpill">🗓 Hari ini ${rp(d.todayOmzet)} · ${d.todayN} nota</span></div>
      <div class="dh-act"><button onclick="go('pos')">🛒 Kasir</button><button onclick="editNota()">🧾 Nota Baru</button><button onclick="go('pembayaran')">💰 Catat Bayar</button>${fnb?"":`<button onclick="editBatch()">🏭 Batch Baru</button>`}</div>
    </div>
    <div class="dh-side">
      <div class="dh-ringrow"><div class="dh-ring"><svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="8"/><circle class="rg" cx="40" cy="40" r="32" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round" pathLength="1" style="--off:${(1-Math.max(0,Math.min(1,d.margin/100))).toFixed(3)}" transform="rotate(-90 40 40)"/></svg><b>${cnt(Math.max(0,d.margin),"pct")}</b></div><div><div class="dh-rl">Margin laba</div><div class="dh-rs">laba kotor per rupiah omzet bulan ini</div></div></div>
      <div class="dh-spark">${sparkline(d.trend)}<div class="dh-lab">${d.trend.map(t=>`<span>${monthLabel(t.m)}</span>`).join("")}</div><div class="dh-sl">Omzet 6 bulan terakhir</div></div>
    </div>
  </div>
  ${dashHighlight(d)}
  <div class="kpis">
    ${kpi("💵","var(--green-bg)","var(--green)","Laba Kotor",cnt(d.laba),`${deltaTag(d.dLab)}`)}
    ${kpi("🏦","var(--blue-bg)","var(--blue)","Saldo Kas",`<span style="color:${d.saldoKas>=0?"var(--green)":"var(--red)"}">${cnt(d.saldoKas)}</span>`,"posisi sekarang","keuangan")}
    ${kpi("⏳","var(--amber-bg)","var(--amber)","Piutang",`<span style="color:var(--amber)">${cnt(d.piutang)}</span>`,`${d.penunggak.length} toko belum lunas`,"pembayaran")}
    ${kpi("📦","#efe9ff","#6b4bd1","Nilai Stok",cnt(d.nilaiStok),"HPP di gudang","produk")}
  </div>
  <div class="grid2">
    <div class="panel"><h3>Tren 6 Bulan — Omzet &amp; Laba</h3>${trendChart(d.trend)}</div>
    <div class="panel"><h3>Produk Paling Menguntungkan</h3>${prodProfitBars(d.prodRank)}</div>
  </div>
  <div class="grid2">
    <div class="panel"><h3>💸 Top Penunggak <span class="mini" style="font-weight:400">(umur piutang)</span></h3>${penunggakList(d.penunggak)}</div>
    <div class="panel"><h3>⚡ Perlu Tindakan</h3>${actionCenter(d)}</div>
  </div>
  <div class="panel"><h3>Nota Terbaru</h3>${d.recent.length===0?'<div class="empty">Belum ada nota.</div>':`<table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th class="num">Item</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>${d.recent.map(n=>`<tr><td>${fmtDate(n.date)}</td><td>${esc(n.notaNo||"-")}</td><td>${esc((store(n.storeId)||{}).name||"?")}</td><td class="num">${notaItems(n).length}</td><td class="num">${rp(notaTotal(n))}</td><td><span class="pill ${notaStatus(n)}">${notaStatus(n).toUpperCase()}</span></td></tr>`).join("")}</tbody></table>`}</div>`;
  dashAnimate(el);
}

