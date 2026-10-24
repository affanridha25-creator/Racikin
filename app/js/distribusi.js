"use strict";
// distribusi.js — distribusi / nota (v2: hero, KPI, kartu nota, form nota bergaya kartu)
// ================= DISTRIBUSI (per NOTA) =================
// ---- Distribusi: ringkasan, filter status/toko/cari, peringatan nomor kembar, produk terkirim ----
let DIST_ST="all",DIST_STORE="",DIST_Q="";
const dsAvatar=nm=>{const h=avHue(nm);return `<div class="ds-av" style="background:hsl(${h},75%,92%);color:hsl(${h},55%,32%)">${esc(((nm||"?")[0]||"?").toUpperCase())}</div>`;};
const dsJualQ=n=>notaItems(n).filter(it=>(it.kind||"jual")==="jual").reduce((a,it)=>a+(+it.qty||0),0);
const dsFreeQ=n=>notaItems(n).filter(it=>(it.kind||"jual")!=="jual").reduce((a,it)=>a+(+it.qty||0),0);
const dsPct=n=>{const t=notaTotal(n);return t>0?Math.max(0,Math.min(100,Math.round(notaPaid(n)/t*100))):100;};
function distMonthNotas(){return S.notas.filter(n=>!n.sessionId&&inMonth(n.date));}
function distList(){
  const q=DIST_Q.trim().toLowerCase();
  return distMonthNotas()
    .filter(n=>!DIST_ST||DIST_ST==="all"||notaStatus(n)===DIST_ST)
    .filter(n=>!DIST_STORE||n.storeId===DIST_STORE)
    .filter(n=>!q||(n.notaNo||"").toLowerCase().includes(q)||((store(n.storeId)||{}).name||"").toLowerCase().includes(q))
    .sort((a,b)=>(b.date||"").localeCompare(a.date||"")||(b.notaNo||"").localeCompare(a.notaNo||""));
}
function distBodyHtml(){
  const list=distList();
  const dup={};S.notas.forEach(n=>{if(n.notaNo)dup[n.notaNo]=(dup[n.notaNo]||0)+1;});
  const warn=n=>dup[n.notaNo]>1?` <span class="ds-warn" title="Nomor nota dipakai ${dup[n.notaNo]} nota — ubah lewat Edit">⚠</span>`:"";
  if(!list.length)return `<div class="ds-empty"><div class="ds-empty-i">🧾</div><b>Belum ada nota di sini</b><span>Tak ada nota yang cocok dengan periode/filter ini. Coba ganti filter atau buat nota baru.</span><button class="btn" onclick="editNota()">+ Nota Baru</button></div>`;
  const nm=it=>esc((prod(it.productId)||{}).name||"?");
  // produk terkirim
  const pm={};list.forEach(n=>notaItems(n).forEach(it=>{const o=pm[it.productId]||(pm[it.productId]={j:0,f:0});if((it.kind||"jual")==="jual")o.j+=+it.qty||0;else o.f+=+it.qty||0;}));
  const pe=Object.entries(pm).sort((a,b)=>(b[1].j+b[1].f)-(a[1].j+a[1].f)),pmax=Math.max(1,...pe.map(([,o])=>o.j+o.f));
  const prodRows=pe.map(([pid,o],i)=>`<div class="ds-pr"><div class="ds-pr-top"><span class="ds-pr-n">${esc((prod(pid)||{}).name||"?")}</span><span class="ds-pr-q"><b>${o.j}</b> terjual${o.f?` <em>+${o.f} gratis</em>`:""}</span></div><div class="ds-pr-bar"><i style="width:${Math.round((o.j+o.f)/pmax*100)}%;animation-delay:${.25+Math.min(i,8)*.07}s"></i></div></div>`).join("");
  const prodPanel=`<div class="ds-card"><h3>Produk Terkirim <span class="mini">(sesuai filter)</span></h3>${prodRows}</div>`;
  let listHtml;
  if(isMobile()){
    listHtml=`<div class="ds-ncs">${list.map((n,i)=>{const items=notaItems(n),st=notaStatus(n),sname=(store(n.storeId)||{}).name||"?",d=notaDue(n);
      const shown=items.slice(0,3).map(it=>`<span class="ds-chip-i">${it.kind&&it.kind!=="jual"?`<span class="pill-mini ${it.kind}">${kindLabel(it.kind)}</span> `:""}${nm(it)} ×${it.qty}</span>`).join("");
      const more=items.length>3?`<span class="ds-chip-i more">+${items.length-3}</span>`:"";
      return `<div class="ds-nc" style="--i:${Math.min(i,10)}" onclick="editNota('${n.id}')">
        <div class="ds-nc-top">${dsAvatar(sname)}<div class="ds-nc-id"><div class="ds-nc-store">${esc(sname)}</div><div class="ds-nc-sub">${esc(n.notaNo||"-")}${warn(n)} · ${fmtDate(n.date)}</div></div><span class="pill ds-st ${st}">${st.toUpperCase()}</span></div>
        <div class="ds-chips">${shown}${more}</div>
        <div class="ds-nc-mid"><div><span>Total</span><b>${rp(notaTotal(n))}</b></div><div><span>Laba</span><b class="g">${rp(notaProfit(n))}</b></div><div><span>Sisa</span><b class="${d>0?"a":""}">${d>0?rp(d):"—"}</b></div></div>
        <div class="ds-pbar ${st}"><i style="width:${dsPct(n)}%"></i></div>
        <div class="ds-nc-act"><button class="ds-ab" onclick="event.stopPropagation();strukModal('${n.id}')">🖨 Struk</button><button class="ds-ab" onclick="event.stopPropagation();editNota('${n.id}')">✎ Edit</button><button class="ds-ab del" onclick="event.stopPropagation();delNota('${n.id}')">✕</button></div></div>`;}).join("")}</div>`;
  }else{
    const rows=list.map((n,i)=>{
      const jualIt=notaItems(n).filter(it=>(it.kind||"jual")==="jual");
      const freeIt=notaItems(n).filter(it=>(it.kind||"jual")!=="jual");
      const li=(inner,q)=>`<div class="ds-li"><span>${inner}</span><span class="ds-q">×${q}</span></div>`;
      const jualTxt=jualIt.map(it=>li(nm(it),it.qty)).join("")||'<div class="ds-li muted">—</div>';
      const freeTxt=freeIt.length?`<div class="ds-free">${freeIt.map(it=>li(`<span class="pill-mini ${it.kind}">${kindLabel(it.kind)}</span> ${nm(it)}`,it.qty)).join("")}</div>`:"";
      const d=notaDue(n),st=notaStatus(n),sname=(store(n.storeId)||{}).name||"?";
      return `<tr style="--i:${Math.min(i,12)}"><td class="ds-td-d">${fmtDate(n.date)}</td><td><b>${esc(n.notaNo||"-")}</b>${warn(n)}</td><td><div class="ds-sto">${dsAvatar(sname)}<span>${esc(sname)}</span></div></td><td><div class="ds-itemcell">${jualTxt}${freeTxt}</div></td><td class="num"><b>${rp(notaTotal(n))}</b><div class="ds-pbar sm ${st}"><i style="width:${dsPct(n)}%"></i></div></td><td class="num g">${rp(notaProfit(n))}</td><td class="num ${d>0?"a":"m"}">${d>0?rp(d):"—"}</td><td><span class="pill ds-st ${st}">${st.toUpperCase()}</span></td><td class="num"><div class="ds-acts"><button class="ds-ab" onclick="strukModal('${n.id}')">🖨 Struk</button><button class="ds-ab" onclick="editNota('${n.id}')">Edit</button><button class="ds-ab del" onclick="delNota('${n.id}')">✕</button></div></td></tr>`;}).join("");
    listHtml=`<div class="ds-tbl"><table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th>Item</th><th class="num">Total</th><th class="num">Laba</th><th class="num">Sisa</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`;
  }
  const nDup=[...new Set(list.filter(n=>dup[n.notaNo]>1).map(n=>n.notaNo))].length;
  const dupNote=nDup?`<div class="ds-dup">⚠ ${nDup} nomor nota dipakai lebih dari satu nota. Buka <b>Edit</b> pada nota yang bersangkutan untuk mengganti nomornya.</div>`:"";
  return `<div class="ds-card"><h3>Riwayat Nota Distribusi <span class="ds-count">${list.length} nota</span></h3>${dupNote}${listHtml}</div>`+prodPanel;
}
function distRefreshBody(){const el=document.getElementById("distBody");if(el)el.innerHTML=distBodyHtml();}
function setDistSt(v){DIST_ST=v;document.querySelectorAll("#v-distribusi .ds-chip[data-st]").forEach(b=>b.classList.toggle("on",b.dataset.st===v));distRefreshBody();}
function setDistStore(v){DIST_STORE=v;distRefreshBody();}
function setDistQ(v){DIST_Q=v;distRefreshBody();}
function rDistribusi(){
  const mob=isMobile();
  const mon=distMonthNotas();
  const tot=mon.reduce((a,n)=>a+notaTotal(n),0),lab=mon.reduce((a,n)=>a+notaProfit(n),0),due=mon.reduce((a,n)=>a+Math.max(0,notaDue(n)),0);
  const qj=mon.reduce((a,n)=>a+dsJualQ(n),0),qf=mon.reduce((a,n)=>a+dsFreeQ(n),0);
  const nStores=new Set(mon.map(n=>n.storeId)).size;
  const cntSt=k=>k==="all"?mon.length:mon.filter(n=>notaStatus(n)===k).length;
  const margin=tot>0?Math.round(lab/tot*100):0;
  const hero=`<div class="ds-hero">
    <div class="dh-bg"><i></i><i></i><i></i></div>
    <div class="ds-h-main">
      <div class="ds-h-top"><div><div class="ds-h-kick">🚚 Distribusi ke Toko</div><div class="ds-h-lbl">Penjualan ${esc(monthLabelFull(FILTER_MONTH))}</div></div><div class="ds-h-month">${monthBar()}</div></div>
      <div class="ds-h-val">${cnt(tot)}</div>
      <div class="ds-h-pills"><span class="ds-hp">🧾 ${cnt(mon.length,"num")} nota</span><span class="ds-hp">📈 laba ${cnt(lab)}${tot>0?` · ${margin}%`:""}</span>${due>0?`<span class="ds-hp warn">⏳ belum terbayar ${cnt(due)}</span>`:`<span class="ds-hp">✅ semua terbayar</span>`}</div>
      <div class="ds-h-act"><button onclick="editNota()">＋ Nota Baru</button><button onclick="exportPenjualan('distribusi')">⬇ CSV</button></div>
      ${S.stores.length===0?'<div class="ds-h-note">⚠️ Tambah toko dulu di menu Toko.</div>':""}
    </div></div>`;
  const kpi=(ic,bg,l,v,s)=>`<div class="ds-kpi"><div class="ds-kpi-i" style="background:${bg}">${ic}</div><div class="ds-kpi-l">${l}</div><div class="ds-kpi-v">${v}</div><div class="ds-kpi-s">${s}</div></div>`;
  const kpis=`<div class="ds-kpis">${kpi("⏳","var(--amber-bg)","Belum Terbayar",cnt(due),`${mon.filter(n=>notaDue(n)>0).length} nota menunggu`)}${kpi("📦","var(--blue-bg)","Item Terjual",cnt(qj,"num"),qf?`+ ${qf} gratis (bonus/tester)`:"tanpa bonus")}${kpi("🧮","var(--green-bg)","Rata-rata / Nota",cnt(mon.length?tot/mon.length:0),"nilai tagihan")}${kpi("🏪","#fdeaf0","Toko Dilayani",cnt(nStores,"num"),"toko bulan ini")}</div>`;
  const chip=(v,l)=>`<button class="ds-chip${DIST_ST===v?" on":""}" data-st="${v}" onclick="setDistSt('${v}')">${l}<em>${cntSt(v)}</em></button>`;
  const tools=`<div class="ds-tools"><div class="ds-chips-row">${chip("all","Semua")}${chip("belum","Belum")}${chip("sebagian","Sebagian")}${chip("lunas","Lunas")}</div>
    <div class="ds-tools-r"><select onchange="setDistStore(this.value)"><option value="">🏪 Semua toko</option>${S.stores.map(s=>`<option value="${s.id}"${DIST_STORE===s.id?" selected":""}>${esc(s.name)}</option>`).join("")}</select>
    <input type="search" placeholder="🔍 Cari nota / toko…" value="${esc(DIST_Q)}" oninput="setDistQ(this.value)"></div></div>`;
  const el=document.getElementById("v-distribusi");
  el.innerHTML=`<div class="ds-wrap ds-in" id="dsWrap">${hero}${kpis}${tools}<div id="distBody">${distBodyHtml()}</div></div>`;
  [...document.getElementById("dsWrap").children].forEach((c,i)=>c.style.setProperty("--i",i));
  runCounts(el);
  setTimeout(()=>{const w=document.getElementById("dsWrap");if(w)w.classList.remove("ds-in");},1500);   // animasi masuk hanya sekali (tak ulang saat filter)
}
const NOTA_FREE_KINDS=[["bonus","Bonus"],["endorse","Endorse"],["tester","Tester"]];
const kindLabel=k=>({jual:"",bonus:"BONUS",endorse:"ENDORSE",tester:"TESTER"}[k]||"");
function notaItemDefault(){return {productId:"",qty:"",harga:"",hpp:0,kind:"jual"};}
function notaFreeDefault(){return {productId:"",qty:"",harga:0,hpp:0,kind:"bonus"};}
function editNota(id){
  if(S.stores.length===0){toast("Tambah toko dulu di menu Toko.");return;}
  if(S.products.length===0){toast("Tambah produk dulu di menu Produk.");return;}
  const n=id?JSON.parse(JSON.stringify(S.notas.find(x=>x.id===id))):{id:null,date:today(),storeId:"",notaNo:nextNotaNo(today()),items:[notaItemDefault()],payments:[]};
  if(!id)n._auto=n.notaNo;
  // qty asli nota ini per produk (sudah terpotong dari stok) → untuk petunjuk stok saat edit
  window._nOrig={};if(id)notaItems(n).forEach(it=>{window._nOrig[it.productId]=(window._nOrig[it.productId]||0)+(+it.qty||0);});
  window._nPrev={};
  window._n=n;openModal(notaForm());nSum();
}
function notaProdOpts(sel){return `<option value="" ${!sel?"selected":""}>— pilih produk —</option>`+S.products.map(p=>`<option value="${p.id}" ${p.id===sel?"selected":""}>${esc(p.name)} — stok ${stock(p.id)}</option>`).join("");}
function prodCombo(curId,cb){return combo(S.products.map(p=>({id:p.id,label:p.name+" · stok "+stock(p.id)})),curId,cb,"— cari / pilih produk —");}
// petunjuk stok per baris (hanya saran; validasi stok tetap di server)
function nHint(it){
  if(!it.productId)return "";
  const n=window._n,orig=(window._nOrig||{})[it.productId]||0;
  const used=n.items.filter(x=>x.productId===it.productId).reduce((a,x)=>a+(+x.qty||0),0);
  const left=stock(it.productId)+orig-used;
  return left<0?`<span class="ds-stk bad">⚠ melebihi stok (${left+used} tersedia)</span>`:`<span class="ds-stk">📦 sisa stok ${left}</span>`;
}
function notaForm(){const n=window._n;comboReset();
  const idx=n.items.map((it,i)=>({it,i}));
  const del=i=>`<button class="ds-ic-x" title="Hapus baris" onclick="_n.items.splice(${i},1);remoN()">×</button>`;
  const qtyField=(it,i)=>`<div><label class="f">Jumlah</label><div class="ds-step"><button type="button" onclick="nStep(${i},-1)">−</button><input id="nq${i}" type="number" inputmode="numeric" placeholder="0" value="${it.qty}" oninput="_n.items[${i}].qty=this.value;nSum()"><button type="button" onclick="nStep(${i},1)">＋</button></div></div>`;
  // baris JUAL: produk (combo) | qty + harga | subtotal
  const jualRows=idx.filter(x=>(x.it.kind||"jual")==="jual").map(({it,i})=>`<div class="ds-ic">
    <div class="ds-ic-top">${prodCombo(it.productId,(id)=>notaPickProd(i,id))}${del(i)}</div>
    <div class="ds-ic-grid">${qtyField(it,i)}<div><label class="f">Harga satuan</label><input inputmode="numeric" placeholder="Rp" value="${it.harga?grp(it.harga):""}" oninput="this.value=grp(this.value);_n.items[${i}].harga=digits(this.value);nSum()"></div></div>
    <div class="ds-ic-foot"><span id="nhint${i}">${nHint(it)}</span><span class="ds-ic-sub" id="nsub${i}">${rp((+it.qty||0)*(+it.harga||0))}</span></div></div>`).join("");
  // baris GRATIS: produk (combo) | qty + jenis
  const freeRows=idx.filter(x=>(x.it.kind||"jual")!=="jual").map(({it,i})=>`<div class="ds-ic free">
    <div class="ds-ic-top">${prodCombo(it.productId,(id)=>notaPickProd(i,id))}${del(i)}</div>
    <div class="ds-ic-grid">${qtyField(it,i)}<div><label class="f">Jenis pemberian</label><div class="ds-kinds">${NOTA_FREE_KINDS.map(([v,t])=>`<button type="button" class="${it.kind===v?"on":""}" onclick="notaSetKind(${i},'${v}')">${t}</button>`).join("")}</div></div></div>
    <div class="ds-ic-foot"><span id="nhint${i}">${nHint(it)}</span><span class="ds-ic-sub" id="nsub${i}"><span class="fr">GRATIS</span></span></div></div>`).join("");
  const prev=window._nPrev||{};
  const sr=(l,id,f,cls)=>`<div class="ds-sr ${cls||""}"><span>${l}</span><b class="cnt" id="${id}" data-v="${prev[id]||0}" data-f="${f||"rp"}">${f==="pct"?(prev[id]||0)+"%":rp(prev[id]||0)}</b></div>`;
  return `<div class="ds-form"><button class="close" onclick="closeModal()">×</button>
  <div class="ds-mh"><div class="ds-mh-i">🧾</div><div><h3>${n.id?"Edit":"Tambah"} Nota / Faktur</h3><div class="ds-mh-s">${n.id?"Ubah isi nota — stok & tagihan ikut diperbarui.":"Catat pengiriman/titip ke toko. Jadi tagihan di menu Pembayaran."}</div></div></div>
  <div class="ds-sec"><div class="ds-sec-t"><i>1</i> Toko &amp; tanggal</div>
    <div class="grid2"><div><label class="f">Tanggal</label><input type="date" value="${n.date}" oninput="_n.date=this.value;autoNotaNo()"></div><div><label class="f">Toko / Penerima</label>${combo(S.stores.map(s=>({id:s.id,label:s.name})),n.storeId,(id)=>{window._n.storeId=id;},"— cari / pilih toko —")}</div></div>
    <div style="margin-top:12px"><label class="f">No. Nota / Faktur <span class="mini">(otomatis, bisa diubah)</span></label><input id="nNo" value="${esc(n.notaNo||"")}" oninput="_n.notaNo=this.value" placeholder="mis. NOTA-20260704-001"></div></div>
  <div class="ds-sec"><div class="ds-sec-t"><i>2</i> Produk dijual <span class="mini">ditagih ke toko</span></div>${jualRows||'<div class="ds-none">Belum ada produk jual.</div>'}
    <button class="ds-add" onclick="_n.items.push(notaItemDefault());remoN()">＋ Tambah item jual</button></div>
  <div class="ds-sec fr"><div class="ds-sec-t"><i>🎁</i> Bonus / Endorse / Tester <span class="mini">GRATIS — tak ditagih, tetap potong stok &amp; tercatat</span></div>${freeRows||'<div class="ds-none">Tidak ada. Tambahkan kalau ada produk gratis.</div>'}
    <button class="ds-add alt" onclick="_n.items.push(notaFreeDefault());remoN()">＋ Bonus / endorse / tester</button></div>
  <div class="ds-sec"><div class="ds-sec-t"><i>3</i> Ringkasan</div>
    <div class="ds-sum">
      <div class="ds-sr"><span>Jumlah item</span><b id="nsInfo">—</b></div>
      ${sr("Subtotal","nsSub")}
      <div class="ds-sr disc"><span>Diskon <small>(opsional)</small></span><input id="nDisc" inputmode="numeric" placeholder="0" value="${+n.discount>0?grp(n.discount):""}" oninput="this.value=grp(this.value);_n.discount=+digits(this.value)||0;nSum()"></div>
      ${+n.service>0?sr("Service","nsSvc"):""}${+n.tax>0?sr("Pajak","nsTax"):""}
    </div></div>
  <div class="ds-foot"><div class="ds-foot-t"><div><span>Total tagihan</span><b class="cnt" id="nsTot" data-v="${prev.nsTot||0}" data-f="rp">${rp(prev.nsTot||0)}</b></div><div class="r"><span>Estimasi laba</span><b class="cnt g" id="nsLab" data-v="${prev.nsLab||0}" data-f="rp">${rp(prev.nsLab||0)}</b><small id="nsMar"></small></div></div>
    <div class="ds-foot-b"><button class="btn gray" onclick="closeModal()">Batal</button><button class="btn" onclick="saveNota()">Simpan Nota</button></div></div></div>`;
}
function notaPickProd(i,val){const it=window._n.items[i];const p=prod(val);it.productId=val;if(p){it.hpp=p.hpp;if((it.kind||"jual")==="jual")it.harga=p.harga;}remoN();}
function notaSetKind(i,val){const it=window._n.items[i];it.kind=val;
  if(val!=="jual"){it.harga=0;}else{const p=prod(it.productId);if(p&&!(+it.harga>0))it.harga=p.harga;}
  remoN();}
function nStep(i,d){const it=window._n.items[i];it.qty=Math.max(0,(+it.qty||0)+d);const el=document.getElementById("nq"+i);if(el)el.value=it.qty;nSum();}
function remoN(){mEl.innerHTML=notaForm();nSum();}
// angka ringkasan menghitung halus ke nilai baru (tanpa mengulang dari 0 tiap ketikan)
function dsTween(id,to,f){
  const el=document.getElementById(id);if(!el)return;
  const fmt=x=>f==="pct"?Math.round(x)+"%":rp(Math.round(x));
  const from=+el.dataset.v||0;to=Math.round(to);el.dataset.v=to;
  (window._nPrev=window._nPrev||{})[id]=to;
  cancelAnimationFrame(el._raf);
  const reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion:reduce)").matches;
  if(reduce||from===to){el.textContent=fmt(to);return;}
  const t0=performance.now(),dur=380;
  const step=now=>{const p=Math.min(1,(now-t0)/dur),e=1-Math.pow(1-p,3);el.textContent=fmt(from+(to-from)*e);if(p<1&&el.isConnected)el._raf=requestAnimationFrame(step);};
  el._raf=requestAnimationFrame(step);
}
function nSum(){const n=window._n;if(!n)return;
  n.items.forEach((it,i)=>{
    const el=document.getElementById("nsub"+i);if(el){const free=(it.kind||"jual")!=="jual";el.innerHTML=free?'<span class="fr">GRATIS</span>':rp((+it.qty||0)*(+it.harga||0));}
    const h=document.getElementById("nhint"+i);if(h)h.innerHTML=nHint(it);});
  const valid=n.items.filter(it=>it.productId&&+it.qty>0);
  const freeQty=valid.filter(it=>(it.kind||"jual")!=="jual").reduce((a,it)=>a+(+it.qty||0),0);
  const info=document.getElementById("nsInfo");if(info)info.textContent=`${valid.length} baris · ${notaQty(n)} item${freeQty?` (${freeQty} gratis)`:""}`;
  dsTween("nsSub",notaSubtotal(n));
  dsTween("nsSvc",notaService(n));dsTween("nsTax",notaTax(n));
  dsTween("nsTot",notaTotal(n));dsTween("nsLab",notaProfit(n));
  const mar=document.getElementById("nsMar");if(mar){const t=notaTotal(n);mar.textContent=t>0?`margin ${Math.round(notaProfit(n)/t*100)}%`:"";}
}
// nomor otomatis ikut tanggal selama belum diubah manual (hanya nota baru)
function autoNotaNo(){const n=window._n;if(!n||n.id||n.notaNo!==n._auto||!n.date)return;n.notaNo=n._auto=nextNotaNo(n.date);const el=document.getElementById("nNo");if(el)el.value=n.notaNo;}
async function saveNota(){const n=window._n;if(!n.storeId){toast("Pilih toko/penerima dulu.");return;}if(n.items.filter(it=>it.productId&&+it.qty>0).length===0){toast("Isi minimal 1 item + qty.");return;}await api("saveNota",{nota:n});await reload();closeModal();toast("Nota tersimpan ✓");rDistribusi();}
async function delNota(id){if(confirm("Hapus nota ini beserta pembayarannya? Stok kembali.")){await api("deleteNota",{id});await reload();rDistribusi();}}
