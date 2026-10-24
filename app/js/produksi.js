"use strict";
// produksi.js — produksi: batch, detail, bandingkan, draft (v2: hero, kartu batch, form batch bersheet)
// ================= PRODUKSI =================
// urutan batch: tanggal lalu id (stabil)
const prOrd=(a,b)=>(a.date||"").localeCompare(b.date||"")||(String(a.id)<String(b.id)?-1:String(a.id)>String(b.id)?1:0);
// hitung semua batch sekali + tren HPP tiap produk vs batch sebelumnya (pecahan, null = belum ada pembanding)
function prData(){
  const asc=S.batches.slice().sort(prOrd),calc={},tr={},last={};
  asc.forEach(b=>{const c=batchCalc(b);calc[b.id]=c;tr[b.id]={};
    Object.keys(c.perProduct).forEach(pid=>{const v=c.perProduct[pid];if(last[pid]>0)tr[b.id][pid]=(v-last[pid])/last[pid];last[pid]=v;});});
  return{asc,calc,tr};
}
// penanda ▲▼ (HPP naik = merah, turun = hijau)
function prArrow(f){if(f==null||!isFinite(f))return"";const p=Math.abs(f*100);if(p<0.5)return`<span class="pr-tr eq">＝</span>`;return`<span class="pr-tr ${f>0?"up":"dn"}">${f>0?"▲":"▼"}${p.toFixed(0)}%</span>`;}
const PR={q:"",p:"",m:""};   // filter halaman (bertahan selama sesi)
function prSet(k,v){PR[k]=v;prList();}
function prCard(b,c,tr,i){
  const outs=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0);
  const chips=outs.map(o=>`<span class="pr-chip">${esc((prod(o.productId)||{}).name||"?")} <b>×${o.qty}</b> ${prArrow(tr[o.productId])}</span>`).join("")||'<span class="muted">—</span>';
  const tot=c.total||0,pm=tot>0?c.mat/tot*100:0,po=tot>0?c.ops/tot*100:0;
  return `<div class="pr-card" style="--i:${Math.min(i,14)}">
    <div class="pr-ch"><span class="pr-no">${esc(b.batchNo||"Batch")}</span><span class="pr-dt">${fmtDate(b.date)}</span>${b.note?`<span class="pr-nt">${esc(b.note)}</span>`:""}</div>
    <div class="pr-chips">${chips}</div>
    <div class="pr-nums"><div><small>HPP rata-rata / item</small><b>${rp(c.avg)}</b></div><div><small>Total modal</small><b>${rp(c.total)}</b></div><div><small>Item jadi</small><b>${c.bottles}</b></div></div>
    <div class="pr-bar" title="Bahan ${pm.toFixed(0)}% · Operasional ${po.toFixed(0)}%"><span class="m" style="width:${pm}%"></span><span class="o" style="width:${po}%"></span></div>
    <div class="pr-leg"><span><i class="m"></i>Bahan ${rp(c.mat)}</span><span><i class="o"></i>Operasional ${rp(c.ops)}</span></div>
    <div class="pr-acts"><button class="btn sm ghost" onclick="viewBatch('${b.id}')">👁 Detail</button><button class="btn sm" onclick="printBatch('${b.id}')">🖨 Cetak</button><button class="btn sm gray" onclick="editBatch('${b.id}')">✏️ Edit</button><button class="btn sm del" onclick="delBatch('${b.id}')" aria-label="Hapus">✕</button></div>
  </div>`;
}
// isi daftar kartu sesuai filter (tanpa merender ulang hero/kolom cari → fokus input aman)
function prList(){
  const box=document.getElementById("prList");if(!box)return;
  const d=prData(),q=PR.q.trim().toLowerCase();
  let arr=d.asc.slice().reverse();
  if(PR.p)arr=arr.filter(b=>(b.outputs||[]).some(o=>o.productId===PR.p));
  if(PR.m)arr=arr.filter(b=>(b.date||"").slice(0,7)===PR.m);
  if(q)arr=arr.filter(b=>[b.batchNo,b.note,...(b.outputs||[]).map(o=>(prod(o.productId)||{}).name)].filter(Boolean).join(" ").toLowerCase().includes(q));
  const ct=document.getElementById("prCount");if(ct)ct.textContent=arr.length===S.batches.length?`${arr.length} batch`:`${arr.length} dari ${S.batches.length} batch`;
  box.innerHTML=S.batches.length===0?'<div class="pr-empty"><div>🏭</div><b>Belum ada batch</b><p>Mulai racik batch pertamamu — HPP dihitung otomatis.</p><button class="btn" onclick="editBatch()">+ Batch Baru</button></div>'
    :arr.length===0?'<div class="pr-empty"><div>🔍</div><b>Tidak ada batch yang cocok</b><p>Coba ubah kata kunci atau filter.</p></div>'
    :arr.map((b,i)=>prCard(b,d.calc[b.id],d.tr[b.id],i)).join("");
}
function rProduksi(){
  const d=prData(),all=d.asc.map(b=>d.calc[b.id]);
  const nB=S.batches.length,modal=all.reduce((a,c)=>a+c.total,0),items=all.reduce((a,c)=>a+c.bottles,0),avg=items>0?modal/items:0;
  // insight: perubahan HPP terbesar pada batch terbaru vs batch sebelumnya
  let ins="";
  const lb=d.asc[d.asc.length-1];
  if(lb){const t=d.tr[lb.id],ks=Object.keys(t).sort((x,y)=>Math.abs(t[y])-Math.abs(t[x]));
    if(ks.length){const f=t[ks[0]],nm=(prod(ks[0])||{}).name||"produk",p=Math.abs(f*100);
      ins=Math.abs(f)<0.005?`✨ HPP ${esc(nm)} stabil dibanding batch sebelumnya.`:`${f>0?"📈":"📉"} HPP ${esc(nm)} di batch terbaru ${f>0?"naik":"turun"} ${p.toFixed(0)}% dari batch sebelumnya${f>0?" — cek harga bahan.":" — kerja bagus!"}`;}
    else ins="💡 Catat batch untuk produk yang sama, nanti kelihatan tren HPP-nya (▲▼).";}
  else ins="💡 Racik batch pertamamu — bahan + biaya jadi HPP otomatis.";
  const mons=[...new Set(S.batches.map(b=>(b.date||"").slice(0,7)).filter(Boolean))].sort().reverse();
  if(PR.m&&!mons.includes(PR.m))PR.m="";
  if(PR.p&&!prod(PR.p))PR.p="";
  const pids=[...new Set(S.batches.flatMap(b=>(b.outputs||[]).map(o=>o.productId)).filter(Boolean))].filter(prod);
  const el=document.getElementById("v-produksi");
  el.innerHTML=`<div class="pr-root">
  <div class="pr-hero"><div class="dh-bg"><i></i><i></i><i></i></div>
    <div class="pr-hrow"><div><div class="pr-hk">🏭 Produksi</div><h2>Produksi &amp; HPP per Batch</h2><p>Racik bahan + biaya → HPP tiap batch dihitung otomatis.</p></div>
      <div class="pr-hbtn"><button class="btn pr-bw" onclick="editBatch()">+ Batch Baru</button><button class="btn pr-bg" onclick="cmpBatch()">⚖ Bandingkan</button></div></div>
    <div class="pr-stats"><div><small>Jumlah batch</small><b>${cnt(nB,"num")}</b></div><div><small>Total modal</small><b>${cnt(modal)}</b></div><div><small>Rata-rata HPP / item</small><b>${cnt(avg)}</b></div><div><small>Item dihasilkan</small><b>${cnt(items,"num")}</b></div></div>
    <div class="pr-ins">${ins}</div></div>
  <div class="pr-filt"><input class="pr-q" type="search" placeholder="🔍 Cari no. batch, catatan, produk…" value="${esc(PR.q)}" oninput="prSet('q',this.value)">
    <select onchange="prSet('p',this.value)"><option value="">Semua produk</option>${pids.map(id=>`<option value="${id}"${PR.p===id?" selected":""}>${esc(prod(id).name)}</option>`).join("")}</select>
    <select onchange="prSet('m',this.value)"><option value="">Semua bulan</option>${mons.map(k=>`<option value="${k}"${PR.m===k?" selected":""}>${monthLabelFull(k)}</option>`).join("")}</select>
    <span class="pr-count" id="prCount"></span></div>
  <div class="pr-list" id="prList"></div></div>`;
  prList();
  dashAnimate(el.querySelector(".pr-root"));
}
// ---- bandingkan batch untuk produk yang sama: perubahan harga/qty bahan + item yang hilang/baru ----
function cmpBatch(pid){
  const withProd=id=>S.batches.filter(b=>(b.outputs||[]).some(o=>o.productId===id&&+o.qty>0));
  const cands=S.products.filter(p=>withProd(p.id).length>=2);
  if(!cands.length){toast("Perlu minimal 2 batch untuk produk yang sama.");return;}
  if(!pid||!cands.some(p=>p.id===pid))pid=cands[0].id;
  const bs=withProd(pid).sort((x,y)=>(x.date||"").localeCompare(y.date||"")||(x.id>y.id?1:-1));
  const cs=bs.map(b=>batchCalc(b));
  const pq=bs.map(b=>b.outputs.filter(o=>o.productId===pid).reduce((a,o)=>a+(+o.qty||0),0));
  const num=x=>String(Number((+x).toPrecision(3)));
  const rows={},ops={};
  bs.forEach((b,i)=>{
    (b.materials||[]).forEach(m=>{
      const t=m.targetProduct;if(t&&t!==pid&&(b.outputs||[]).some(o=>o.productId===t))return;   // bahan khusus produk lain
      const own=t===pid,den=own?pq[i]:cs[i].bottles;if(!(den>0))return;
      const k=(m.materialId||(m.name||"").trim().toLowerCase());
      const r=rows[k]||(rows[k]={name:m.name,unit:m.unit,c:[]});
      const c=r.c[i]||(r.c[i]={qty:0,cost:0,price:+m.price||0});
      c.qty+=(+m.qty||0)/den;c.cost+=(+m.qty||0)*(+m.price||0)/den;c.price=+m.price||0;
    });
    (b.ops||[]).forEach(o=>{
      if(!cs[i].bottles)return;
      const k=(o.name||"").trim().toLowerCase();
      const r=ops[k]||(ops[k]={name:o.name,c:[]});
      const c=r.c[i]||(r.c[i]={cost:0});c.cost+=(+o.amount||0)/cs[i].bottles;
    });
  });
  const dl=(cur,prev)=>{if(prev==null||!prev||cur===prev)return"";const d=(cur-prev)/prev*100;return ` <span class="pr-tr ${d>0?"up":"dn"}">${d>0?"▲":"▼"}${Math.abs(d).toFixed(0)}%</span>`;};
  const miss=`<span class="pr-miss">⚠ tidak ada</span>`;
  const itemRow=r=>{let prevP=null,prevQ=null;
    const tds=bs.map((b,i)=>{const c=r.c[i];if(!c)return `<td class="num">${miss}</td>`;
      const h=`<td class="num">${rp(c.price)}${dl(c.price,prevP)}<div class="mini">${num(c.qty)} ${esc(r.unit||"")}/item${dl(c.qty,prevQ)}</div></td>`;prevP=c.price;prevQ=c.qty;return h;}).join("");
    const gone=r.c.filter(Boolean).length<bs.length;
    return `<tr${gone?' class="warn"':""}><td>${esc(r.name||"?")}</td>${tds}</tr>`;};
  const opRow=r=>{let prev=null;
    const tds=bs.map((b,i)=>{const c=r.c[i];if(!c)return `<td class="num">${miss}</td>`;const h=`<td class="num">${rp(Math.round(c.cost))}${dl(c.cost,prev)}</td>`;prev=c.cost;return h;}).join("");
    return `<tr${r.c.filter(Boolean).length<bs.length?' class="warn"':""}><td>${esc(r.name||"?")}</td>${tds}</tr>`;};
  const sec=t=>`<tr class="sec"><td colspan="${bs.length+1}">${t}</td></tr>`;
  let pv=null;
  const hppTds=bs.map((b,i)=>{const h=cs[i].perProduct[pid]||0;const out=`<td class="num"><b>${rp(Math.round(h))}</b>${dl(h,pv)}</td>`;pv=h;return out;}).join("");
  const sel=`<select class="pr-psel" onchange="cmpBatch(this.value)">${cands.map(p=>`<option value="${p.id}"${p.id===pid?" selected":""}>${esc(p.name)}</option>`).join("")}</select>`;
  openModal(`<div class="pr-wrap pr-cmp"><div class="pr-dh"><button class="close" onclick="closeModal()">×</button><div class="pr-hk">⚖ Bandingkan</div><h3>Bandingkan Batch</h3>${sel}</div>
  <div class="pr-db"><p class="mini" style="margin-bottom:10px">Harga bahan &amp; qty per item tiap batch, urut dari terlama. ▲▼ = perubahan vs batch sebelumnya. Baris kuning ⚠ = item ada di batch lain tapi tidak di batch ini (mungkin lupa diinput).</p>
  <div class="pr-tw pr-cmpw"><table class="cmpt"><thead><tr><th></th>${bs.map((b,i)=>`<th class="num">${esc(b.batchNo||"-")}<div class="mini">${fmtDate(b.date)} · ${pq[i]} item</div></th>`).join("")}</tr></thead><tbody>
  <tr class="tot"><td><b>HPP / item</b></td>${hppTds}</tr>
  ${sec("Bahan baku (harga satuan · qty per item)")}${Object.values(rows).map(itemRow).join("")}
  ${Object.keys(ops).length?sec("Biaya operasional (per item)")+Object.values(ops).map(opRow).join(""):""}
  </tbody></table></div></div></div>`);
}
// cetak dengan judul dokumen sementara → jadi nama file default saat "Simpan sebagai PDF"
function printTitled(title){
  const old=document.title;document.title=String(title||old).replace(/[\\/:*?"<>|]+/g," ").replace(/\s+/g," ").trim()||old;
  const back=()=>{document.title=old;window.removeEventListener("afterprint",back);};
  window.addEventListener("afterprint",back);
  window.print();
  setTimeout(back,60000);   // jaga-jaga kalau afterprint tak terpanggil
}
// ---- detail batch (preview sebelum cetak/edit) ----
function viewBatch(id){
  const b=S.batches.find(x=>x.id===id);if(!b)return;
  const c=batchCalc(b),d=prData(),tr=d.tr[b.id]||{};
  const mats=(b.materials||[]),maxM=Math.max(1,...mats.map(m=>(+m.qty||0)*(+m.price||0)));
  const matRows=mats.map(m=>{const sub=(+m.qty||0)*(+m.price||0);
    const tgt=m.targetProduct?`<span class="pr-tg">→ ${esc((prod(m.targetProduct)||{}).name||"?")}</span>`:"";
    return `<div class="pr-li"><div class="pr-lt"><b>${esc(m.name||"?")}</b>${tgt}<small>${+m.qty||0} ${esc(m.unit||"")} × ${rp(m.price)}</small></div><div class="pr-lv">${rp(sub)}</div><div class="pr-lb"><span style="width:${sub/maxM*100}%"></span></div></div>`;}).join("");
  const opsRows=(b.ops||[]).map(o=>`<div class="pr-li"><div class="pr-lt"><b>${esc(o.name||"?")}</b></div><div class="pr-lv">${rp(o.amount)}</div></div>`).join("");
  const outRows=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0).map(o=>{const p=prod(o.productId)||{};
    const hpp=Math.round(c.perProduct[o.productId]||0),harga=+p.harga||0,untung=harga-hpp,margin=hpp>0?untung/hpp*100:0;
    return `<div class="pr-pc"><div class="pr-pt"><b>${esc(p.name||"?")}</b><span class="pr-chip">×${o.qty}</span></div>
      <div class="pr-pn"><div><small>HPP ${prArrow(tr[o.productId])}</small><b>${rp(hpp)}</b></div><div><small>Harga jual</small><b>${rp(harga)}</b></div><div><small>Untung/item</small><b style="color:${untung<0?"var(--red)":"var(--green)"}">${rp(untung)}</b><em>${margin.toFixed(0)}%</em></div></div></div>`;}).join("");
  const tot=c.total||0,pm=tot>0?c.mat/tot*100:0,po=tot>0?c.ops/tot*100:0;
  const sec=t=>`<div class="pr-sh"><h4>${t}</h4></div>`;
  openModal(`<div class="pr-wrap pr-det"><div class="pr-dh"><button class="close" onclick="closeModal()">×</button>
    <div class="pr-hk">🏭 Detail Batch${b.batchNo?` · ${esc(b.batchNo)}`:""}</div><h3>${esc(batchName(b))}</h3><div class="pr-dsub">${fmtDate(b.date)}</div>
    <div class="pr-dfig"><div><small>Total modal</small><b>${cnt(c.total)}</b></div><div><small>HPP rata-rata/item</small><b>${cnt(c.avg)}</b></div><div><small>Item jadi</small><b>${cnt(c.bottles,"num")}</b></div></div></div>
  <div class="pr-db">
    <div class="pr-bar big"><span class="m" style="width:${pm}%"></span><span class="o" style="width:${po}%"></span></div>
    <div class="pr-leg"><span><i class="m"></i>Bahan ${rp(c.mat)} (${pm.toFixed(0)}%)</span><span><i class="o"></i>Operasional ${rp(c.ops)} (${po.toFixed(0)}%)</span></div>
    ${sec("Produk jadi")}${outRows||'<div class="pr-none">—</div>'}
    ${sec("Bahan baku")}<div class="pr-lst">${matRows||'<div class="pr-none">—</div>'}<div class="pr-li tot"><div class="pr-lt"><b>Total bahan</b></div><div class="pr-lv">${rp(c.mat)}</div></div></div>
    ${sec("Biaya operasional")}<div class="pr-lst">${opsRows||'<div class="pr-none">—</div>'}<div class="pr-li tot"><div class="pr-lt"><b>Total operasional</b></div><div class="pr-lv">${rp(c.ops)}</div></div></div>
    <div class="pr-dfoot"><button class="btn gray" onclick="closeModal()">Tutup</button><button class="btn gray" onclick="closeModal();editBatch('${b.id}')">Edit</button><button class="btn" onclick="printBatch('${b.id}')">🖨 Cetak</button></div>
  </div></div>`);
  runCounts(mEl);
}
function printBatch(id){
  const b=S.batches.find(x=>x.id===id);if(!b)return;
  const c=batchCalc(b);
  const matRows=(b.materials||[]).map(m=>{const sub=(+m.qty||0)*(+m.price||0);
    const tgt=(m.targetProduct)?` <i>→ ${esc((prod(m.targetProduct)||{}).name||"?")}</i>`:"";
    return `<tr><td>${esc(m.name||"?")}${tgt}</td><td class="num">${(+m.qty||0)} ${esc(m.unit||"")}</td><td class="num">${rp(m.price)}</td><td class="num">${rp(sub)}</td></tr>`;}).join("");
  const opsRows=(b.ops||[]).map(o=>`<tr><td>${esc(o.name||"?")}</td><td class="num">${rp(o.amount)}</td></tr>`).join("");
  const outRows=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0).map(o=>{const p=prod(o.productId)||{};
    const hpp=Math.round(c.perProduct[o.productId]||0),harga=+p.harga||0,untung=harga-hpp,margin=hpp>0?untung/hpp*100:0;
    return `<tr><td>${esc(p.name||"?")}</td><td class="num">${o.qty}</td><td class="num">${rp(harga)}</td><td class="num">${rp(hpp)}</td><td class="num">${rp(untung)}</td><td class="num">${margin.toFixed(0)}%</td></tr>`;}).join("");
  document.getElementById("printArea").innerHTML=`<div class="preport">
    ${printHead("LAPORAN HPP"+(b.note?" — "+esc(b.note):" — Batch Produksi"),"Produksi • "+(b.batchNo?b.batchNo+" • ":"")+fmtDate(b.date)+" • "+c.bottles+" item")}
    <div class="psec">1. Biaya Bahan Baku</div>
    <table><thead><tr><th>Bahan</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
      <tbody>${matRows||'<tr><td colspan="4">—</td></tr>'}<tr class="tot"><td colspan="3">TOTAL BAHAN BAKU</td><td class="num">${rp(c.mat)}</td></tr></tbody></table>
    <div class="psec">2. Biaya Operasional</div>
    <table><thead><tr><th>Item</th><th class="num">Subtotal</th></tr></thead>
      <tbody>${opsRows||'<tr><td colspan="2">—</td></tr>'}<tr class="tot"><td>TOTAL OPERASIONAL</td><td class="num">${rp(c.ops)}</td></tr></tbody></table>
    <div class="psec">3. HPP &amp; Harga Jual per Item</div>
    <table><thead><tr><th>Produk</th><th class="num">Item</th><th class="num">Harga Jual</th><th class="num">HPP</th><th class="num">Untung/item</th><th class="num">Margin</th></tr></thead>
      <tbody>${outRows||'<tr><td colspan="6">—</td></tr>'}</tbody></table>
    <div class="psec">4. Ringkasan Batch</div>
    <table><tbody>
      <tr><td>Total Modal (bahan + operasional)</td><td class="num"><b>${rp(c.total)}</b></td></tr>
      <tr><td>Total Item Jadi</td><td class="num">${c.bottles} item</td></tr>
      <tr><td>HPP Rata-rata / item</td><td class="num">${rp(c.avg)}</td></tr>
    </tbody></table>
    <div class="psub" style="margin-top:18px;text-align:left">Dicetak ${fmtDate(today())} — Racikin</div>
  </div>`;
  printTitled([b.batchNo||"Batch",b.note,BIZ.name].filter(Boolean).join(" - "));
}
function matDefault(){return {materialId:"",name:"",unit:"kg",qty:"",price:"",targetProduct:"",custom:false};}
// ---- draft batch (disimpan di browser, tidak menyentuh database) ----
const DRAFT_KEY="anna_batch_draft";
function hasDraft(){return !!localStorage.getItem(DRAFT_KEY);}
function persistDraft(){if(window._b&&!window._b.id){try{localStorage.setItem(DRAFT_KEY,JSON.stringify(window._b));}catch(e){}}}
function clearDraft(){try{localStorage.removeItem(DRAFT_KEY);}catch(e){}}
function saveDraft(){persistDraft();closeModal();toast("Draft batch disimpan 💾");}
function discardDraft(){if(confirm("Buang draft batch ini?")){clearDraft();closeModal();toast("Draft dibuang.");rProduksi();}}
function editBatch(id){
  let b=null;
  if(id) b=JSON.parse(JSON.stringify(S.batches.find(x=>x.id===id)));
  else if(hasDraft()){try{b=JSON.parse(localStorage.getItem(DRAFT_KEY));toast("Draft dipulihkan 📝");}catch(e){b=null;}}
  if(!b) b={id:null,date:today(),note:"",materials:[matDefault()],ops:[{name:"Gas",amount:""},{name:"Tenaga kerja",amount:""}],outputs:[{productId:"",qty:""}]};
  window._b=b;for(const k in BSV)delete BSV[k];openModal(batchForm());bSum(true);
}
function batchForm(){
  const b=window._b;comboReset();
  // produk jadi berbeda dalam batch ini — alokasi bahan hanya relevan kalau ada ≥2 produk
  const outProds=[...new Set((b.outputs||[]).filter(o=>o.productId).map(o=>o.productId))];
  const showAlloc=outProds.length>=2;
  const matOpts=[...S.materials.map(x=>({id:x.id,label:x.name+" ("+rp(x.price)+"/"+x.unit+")"})),{id:"__c",label:"— lainnya (manual) —"}];
  const matRows=b.materials.map((m,i)=>{
    const isCustom=m.custom||(!m.materialId&&!!m.name);
    const nameInput=isCustom?`<input placeholder="Nama bahan" value="${esc(m.name)}" oninput="_b.materials[${i}].name=this.value">`:"";
    const allocSel=showAlloc?`<select class="pr-alloc" onchange="_b.materials[${i}].targetProduct=this.value;bSum()" title="Alokasi biaya bahan ini">
        <option value="" ${!m.targetProduct?"selected":""}>↔ Dibagi rata ke semua produk</option>
        ${outProds.map(pid=>`<option value="${pid}" ${m.targetProduct===pid?"selected":""}>→ khusus ${esc((prod(pid)||{}).name||"?")}</option>`).join("")}
      </select>`:"";
    return `<div class="itemrow pr-row">
      <div class="irtop">${combo(matOpts,isCustom?"__c":m.materialId,(id)=>pickMat(i,id),"— cari / pilih bahan —")}<button class="x" onclick="_b.materials.splice(${i},1);remo(batchForm())">×</button></div>
      ${nameInput}
      <div class="irbot"><input type="number" inputmode="decimal" placeholder="Qty" value="${m.qty}" oninput="_b.materials[${i}].qty=this.value;bSum()"><input inputmode="numeric" placeholder="Harga" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value);_b.materials[${i}].price=digits(this.value);bSum()"></div>
      ${allocSel}
      <div class="irsub" id="matsub${i}">${rp((+m.qty||0)*(+m.price||0))}</div></div>`;}).join("");
  const opsRows=b.ops.map((o,i)=>`<div class="dynrow ops pr-row"><input placeholder="Nama biaya" value="${esc(o.name)}" oninput="_b.ops[${i}].name=this.value"><input inputmode="numeric" placeholder="Rp" value="${o.amount?grp(o.amount):""}" oninput="this.value=grp(this.value);_b.ops[${i}].amount=digits(this.value);bSum()"><button class="x" onclick="_b.ops.splice(${i},1);remo(batchForm())">×</button></div>`).join("");
  const outRows=b.outputs.map((o,i)=>`<div class="itemrow pr-row"><div class="irtop">${combo(S.products.map(p=>({id:p.id,label:p.name})),o.productId,(id)=>{window._b.outputs[i].productId=id;addReqMats(id);remo(batchForm());},"— cari / pilih produk —")}<button class="x" onclick="_b.outputs.splice(${i},1);remo(batchForm())">×</button></div><div class="irbot one"><input type="number" inputmode="numeric" placeholder="Qty" value="${o.qty}" oninput="_b.outputs[${i}].qty=this.value;bSum()"></div></div>`).join("");
  return `<div class="pr-wrap pr-form"><div class="pr-fh"><div><div class="pr-hk">🏭 ${b.id?"Edit batch":"Batch baru"}</div><h3>${b.id?"Edit":"Tambah"} Batch Produksi${b.batchNo?` <span class="pr-no">${esc(b.batchNo)}</span>`:""}</h3></div><button class="close" onclick="closeModal()">×</button></div>
  <div class="pr-fb">
  ${!b.id?`<div class="pr-note">📝 Batch ini belum tersimpan ke database. Klik <b>Simpan Draft</b> untuk menyimpan sementara di browser &amp; lanjut nanti (tetap ada walau modal ditutup / halaman di-refresh).</div>`:""}
  <div class="pr-fs"><div class="grid2"><div><label class="f">Tanggal</label><input type="date" value="${b.date}" oninput="_b.date=this.value"></div><div><label class="f">Catatan</label><input value="${esc(b.note)}" oninput="_b.note=this.value" placeholder="opsional"></div></div></div>
  <div class="pr-fs"><div class="pr-fst"><span class="pr-fi">🧂</span><div><label class="f">Bahan Baku</label><span class="mini">pilih dari master; harga bisa disesuaikan — subtotal per baris otomatis</span></div></div>${matRows}
  ${showAlloc?`<p class="pr-tip">💡 Ada ${outProds.length} produk jadi — pakai dropdown <b>alokasi</b> tiap bahan: <b>khusus</b> satu produk (mis. daging→Premium, tulang→Campur) atau <b>dibagi rata</b>. Operasional selalu dibagi rata per bobot.</p>`:""}
  <button class="btn sm ghost pr-add" onclick="_b.materials.push(matDefault());remo(batchForm())">+ bahan</button></div>
  <div class="pr-fs"><div class="pr-fst"><span class="pr-fi">⚡</span><div><label class="f">Biaya Operasional</label><span class="mini">gas, tenaga kerja, kemasan, dll.</span></div></div>${opsRows}
  <button class="btn sm ghost pr-add" onclick="_b.ops.push({name:'',amount:''});remo(batchForm())">+ biaya</button></div>
  <div class="pr-fs"><div class="pr-fst"><span class="pr-fi">📦</span><div><label class="f">Produk Jadi &amp; Qty</label><span class="mini">hasil batch ini</span></div></div>${outRows}
  <button class="btn sm ghost pr-add" onclick="_b.outputs.push({productId:'',qty:''});remo(batchForm())">+ produk</button></div>
  </div>
  <div class="pr-foot"><div class="sumbox pr-sum" id="bSum"></div>
  <div class="pr-fbtn">
    ${!b.id&&hasDraft()?`<button class="btn gray" onclick="discardDraft()">🗑 Buang Draft</button>`:""}
    <button class="btn gray" onclick="closeModal()">Tutup</button>
    ${!b.id?`<button class="btn ghost" onclick="saveDraft()">💾 Simpan Draft</button>`:""}
    <button class="btn" onclick="saveBatch()">Simpan Batch</button>
  </div></div></div>`;
}
// tambah bahan wajib produk ke batch (kalau belum ada) — qty dikosongkan, harga dari master
function addReqMats(pid){const p=prod(pid);if(!p||!(p.reqMats||[]).length)return;const b=window._b;
  const first=b.materials.length===1&&!b.materials[0].materialId&&!b.materials[0].name&&!+b.materials[0].qty&&!+b.materials[0].price;
  if(first)b.materials=[];
  p.reqMats.forEach(id=>{const x=mat(id);if(!x||b.materials.some(m=>m.materialId===id))return;
    b.materials.push({materialId:x.id,name:x.name,unit:x.unit,qty:"",price:x.price,targetProduct:"",custom:false});});}
// bahan wajib yang belum diisi (qty>0) untuk tiap produk jadi di batch
function missingReq(b){const out=[];
  [...new Set((b.outputs||[]).filter(o=>o.productId&&+o.qty>0).map(o=>o.productId))].forEach(pid=>{const p=prod(pid);
    (p&&p.reqMats||[]).forEach(id=>{const ok=(b.materials||[]).some(m=>m.materialId===id&&+m.qty>0&&(!m.targetProduct||m.targetProduct===pid));
      if(!ok){const x=mat(id);out.push(`${x?x.name:"?"} (${p.name})`);}});});
  return out;}
function pickMat(i,val){const m=window._b.materials[i];
  if(val==="__c"){m.custom=true;m.materialId="";}
  else if(val===""){m.custom=false;m.materialId="";m.name="";m.price="";m.unit="kg";}
  else{const x=mat(val);m.custom=false;m.materialId=x.id;m.name=x.name;m.unit=x.unit;m.price=x.price;}
  remo(batchForm());}
function remo(h){mEl.innerHTML=h;bSum();}
// angka ringkasan menghitung-naik halus (nilai sebelumnya disimpan di BSV supaya tidak mulai dari 0 tiap ketikan)
const BSV={};
function bsTo(id,to,kind){
  const el=document.getElementById(id);if(!el)return;
  const fmt=x=>kind==="n"?Math.round(x)+" item":rp(x),from=BSV[id]||0;BSV[id]=to;
  const reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion:reduce)").matches;
  if(el._raf)cancelAnimationFrame(el._raf);
  if(reduce||from===to){el.textContent=fmt(to);return;}
  const t0=performance.now(),dur=380,step=now=>{const p=Math.min(1,(now-t0)/dur),e=1-Math.pow(1-p,3);el.textContent=fmt(from+(to-from)*e);if(p<1&&el.isConnected)el._raf=requestAnimationFrame(step);};
  el._raf=requestAnimationFrame(step);
}
function bSum(quiet){const c=batchCalc(window._b),box=document.getElementById("bSum");
  (window._b.materials||[]).forEach((m,i)=>{const el=document.getElementById("matsub"+i);if(el)el.textContent=rp((+m.qty||0)*(+m.price||0));});
  if(!quiet)persistDraft();
  if(!box)return;
  if(!document.getElementById("bs_total"))box.innerHTML=`<div class="pr-sm3"><div><small>Total modal batch</small><b id="bs_total"></b></div><div><small>Total item jadi</small><b id="bs_items"></b></div><div><small>HPP rata-rata / item</small><b id="bs_avg"></b></div></div>
    <details class="pr-smd"><summary>Rincian biaya</summary><div class="pr-smr"><div><span>Total bahan baku</span><span id="bs_mat"></span></div><div><span>Total operasional</span><span id="bs_ops"></span></div><div id="bsProd"></div></div></details>`;
  bsTo("bs_total",c.total);bsTo("bs_items",c.bottles,"n");bsTo("bs_avg",c.avg);bsTo("bs_mat",c.mat);bsTo("bs_ops",c.ops);
  const pp=document.getElementById("bsProd");
  if(pp)pp.innerHTML=(window._b.outputs||[]).filter(o=>o.productId&&o.qty).map(o=>`<div class="mini"><span>↳ HPP ${esc((prod(o.productId)||{}).name||"?")}</span><span>${rp(c.perProduct[o.productId])}</span></div>`).join("");
}
async function saveBatch(){
  const b=window._b;
  b.materials=b.materials.filter(m=>(m.materialId||m.name)&&(+m.qty||+m.price));
  b.ops=b.ops.filter(o=>o.name&&+o.amount);
  b.outputs=b.outputs.filter(o=>o.productId&&+o.qty>0);
  if(b.outputs.length===0){toast("Isi minimal 1 produk jadi + qty.");return;}
  const miss=missingReq(b);
  if(miss.length&&!confirm("Bahan wajib belum diisi (qty kosong/tidak ada):\n• "+miss.join("\n• ")+"\n\nTetap simpan batch ini?"))return;
  await api("saveBatch",{batch:b});clearDraft();await reload();closeModal();toast("Batch tersimpan ✓");rProduksi();
}
async function delBatch(id){if(confirm("Hapus batch ini? Stok berkurang.")){await api("deleteBatch",{id});await reload();rProduksi();}}

