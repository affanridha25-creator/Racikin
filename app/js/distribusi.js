"use strict";
// distribusi.js — distribusi / nota
// ================= DISTRIBUSI (per NOTA) =================
// ---- Distribusi: ringkasan, filter status/toko/cari, peringatan nomor kembar, produk terkirim ----
let DIST_ST="all",DIST_STORE="",DIST_Q="";
function distList(){
  const q=DIST_Q.trim().toLowerCase();
  return S.notas.filter(n=>!n.sessionId&&inMonth(n.date))
    .filter(n=>!DIST_ST||DIST_ST==="all"||notaStatus(n)===DIST_ST)
    .filter(n=>!DIST_STORE||n.storeId===DIST_STORE)
    .filter(n=>!q||(n.notaNo||"").toLowerCase().includes(q)||((store(n.storeId)||{}).name||"").toLowerCase().includes(q))
    .sort((a,b)=>(b.date||"").localeCompare(a.date||"")||(b.notaNo||"").localeCompare(a.notaNo||""));
}
function distBodyHtml(){
  const list=distList();
  const dup={};S.notas.forEach(n=>{if(n.notaNo)dup[n.notaNo]=(dup[n.notaNo]||0)+1;});
  const warn=n=>dup[n.notaNo]>1?` <span title="Nomor nota dipakai ${dup[n.notaNo]} nota — ubah lewat Edit" style="color:var(--amber);cursor:help">⚠</span>`:"";
  const jualQ=n=>notaItems(n).filter(it=>(it.kind||"jual")==="jual").reduce((a,it)=>a+(+it.qty||0),0);
  const freeQ=n=>notaItems(n).filter(it=>(it.kind||"jual")!=="jual").reduce((a,it)=>a+(+it.qty||0),0);
  const tot=list.reduce((a,n)=>a+notaTotal(n),0),lab=list.reduce((a,n)=>a+notaProfit(n),0),due=list.reduce((a,n)=>a+Math.max(0,notaDue(n)),0);
  const qj=list.reduce((a,n)=>a+jualQ(n),0),qf=list.reduce((a,n)=>a+freeQ(n),0);
  const cards=`<div class="cards"><div class="card"><div class="lbl">Jumlah Nota</div><div class="val">${list.length}</div></div><div class="card"><div class="lbl">Total Penjualan</div><div class="val">${rp(tot)}</div></div><div class="card green"><div class="lbl">Laba</div><div class="val">${rp(lab)}</div></div><div class="card amber"><div class="lbl">Belum Terbayar</div><div class="val">${rp(due)}</div></div><div class="card"><div class="lbl">Item Terkirim</div><div class="val">${qj}${qf?` <span class="mini">+${qf} gratis</span>`:""}</div></div></div>`;
  if(!list.length)return cards+`<div class="empty">Tak ada nota yang cocok pada periode/filter ini.</div>`;
  // produk terkirim
  const pm={};list.forEach(n=>notaItems(n).forEach(it=>{const o=pm[it.productId]||(pm[it.productId]={j:0,f:0});if((it.kind||"jual")==="jual")o.j+=+it.qty||0;else o.f+=+it.qty||0;}));
  const prodRows=Object.entries(pm).sort((a,b)=>(b[1].j+b[1].f)-(a[1].j+a[1].f)).map(([pid,o])=>`<tr><td>${esc((prod(pid)||{}).name||"?")}</td><td class="num">${o.j}</td><td class="num">${o.f||"—"}</td></tr>`).join("");
  const prodPanel=`<div class="panel"><h3>Produk Terkirim</h3><div style="overflow-x:auto"><table><thead><tr><th>Produk</th><th class="num">Terjual</th><th class="num">Gratis</th></tr></thead><tbody>${prodRows}</tbody></table></div></div>`;
  let listHtml;
  if(isMobile()){
    listHtml=`<div class="clist">${list.map(n=>{const items=notaItems(n);const free=items.filter(it=>(it.kind||"jual")!=="jual");
      const badges=free.length?`<div style="margin-top:4px">${free.map(it=>`<span class="pill-mini ${it.kind}">${kindLabel(it.kind)}</span>`).join(" ")}</div>`:"";
      const d=notaDue(n);
      return crow({icon:"🧾",title:esc(n.notaNo||"-")+warn(n),sub:`${esc((store(n.storeId)||{}).name||"?")} · ${items.length} item · ${fmtDate(n.date)}${d>0&&notaPaid(n)>0?` · sisa ${rp(d)}`:""}`,badges,amt:rp(notaTotal(n)),status:notaStatus(n),acts:`<div class="cacts"><button class="btn sm gray" onclick="event.stopPropagation();strukModal('${n.id}')">🖨</button></div>`,onclick:`editNota('${n.id}')`,del:`delNota('${n.id}')`});}).join("")}</div>`;
  }else{
    const nm=it=>esc((prod(it.productId)||{}).name||"?");
    const rows=list.map(n=>{
      const jualIt=notaItems(n).filter(it=>(it.kind||"jual")==="jual");
      const freeIt=notaItems(n).filter(it=>(it.kind||"jual")!=="jual");
      const li=(inner,q)=>`<div class="li"><span>${inner}</span><span class="q">×${q}</span></div>`;
      const jualTxt=jualIt.map(it=>li(nm(it),it.qty)).join("")||'<div class="li muted">—</div>';
      const freeTxt=freeIt.length?`<div class="free">${freeIt.map(it=>li(`<span class="pill-mini ${it.kind}">${kindLabel(it.kind)}</span> ${nm(it)}`,it.qty)).join("")}</div>`:"";
      const d=notaDue(n);
      return `<tr><td>${fmtDate(n.date)}</td><td><b>${esc(n.notaNo||"-")}</b>${warn(n)}</td><td>${esc((store(n.storeId)||{}).name||"?")}</td><td><div class="itemcell">${jualTxt}${freeTxt}</div></td><td class="num"><b>${rp(notaTotal(n))}</b></td><td class="num" style="color:var(--green)">${rp(notaProfit(n))}</td><td class="num" style="color:${d>0?"var(--amber)":"var(--muted)"}">${d>0?rp(d):"—"}</td><td><span class="pill ${notaStatus(n)}">${notaStatus(n).toUpperCase()}</span></td><td class="num"><div class="acts"><button class="btn sm" onclick="strukModal('${n.id}')">🖨 Struk</button><button class="btn sm gray" onclick="editNota('${n.id}')">Edit</button><button class="btn sm del" onclick="delNota('${n.id}')">✕</button></div></td></tr>`;}).join("");
    listHtml=`<div style="overflow-x:auto"><table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th>Item</th><th class="num">Total</th><th class="num">Laba</th><th class="num">Sisa</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`;
  }
  const nDup=[...new Set(list.filter(n=>dup[n.notaNo]>1).map(n=>n.notaNo))].length;
  const dupNote=nDup?`<div class="mini" style="background:var(--amber-bg);color:var(--amber);padding:7px 10px;border-radius:8px;margin-bottom:10px">⚠ ${nDup} nomor nota dipakai lebih dari satu nota. Buka <b>Edit</b> pada nota yang bersangkutan untuk mengganti nomornya.</div>`:"";
  return cards+`<div class="panel"><h3>Riwayat Nota Distribusi</h3>${dupNote}${listHtml}</div>`+prodPanel;
}
function setDistSt(v){DIST_ST=v;rDistribusi();}
function setDistStore(v){DIST_STORE=v;const el=document.getElementById("distBody");if(el)el.innerHTML=distBodyHtml();}
function setDistQ(v){DIST_Q=v;const el=document.getElementById("distBody");if(el)el.innerHTML=distBodyHtml();}
function rDistribusi(){
  const mob=isMobile();
  const chip=(v,l)=>`<button class="btn sm ${DIST_ST===v?"":"gray"}" onclick="setDistSt('${v}')">${l}</button>`;
  const tools=`<div class="flexbtns" style="margin:0 0 14px;align-items:center;flex-wrap:wrap">${chip("all","Semua")}${chip("belum","Belum")}${chip("sebagian","Sebagian")}${chip("lunas","Lunas")}
    <select onchange="setDistStore(this.value)" style="max-width:200px"><option value="">Semua toko</option>${S.stores.map(s=>`<option value="${s.id}"${DIST_STORE===s.id?" selected":""}>${esc(s.name)}</option>`).join("")}</select>
    <input type="search" placeholder="Cari nota / toko…" value="${esc(DIST_Q)}" oninput="setDistQ(this.value)" style="flex:1;min-width:140px"></div>`;
  const head=mob?`<div class="desc">Nota kirim/titip ke toko (faktur). Penjualan kasir POS ada di menu <b>Rekap Kasir</b>.</div><div style="display:flex;gap:8px;margin-bottom:14px"><button class="btn" style="flex:1" onclick="editNota()">+ Nota Baru</button><button class="btn gray" onclick="exportPenjualan('distribusi')">⬇️ CSV</button></div>${monthBar()}`
    :`<h2 class="title">Distribusi ke Toko</h2><div class="desc">Nota/faktur kirim &amp; titip ke toko (jadi tagihan di menu Pembayaran). Penjualan kasir POS terpisah di menu <b>Rekap Kasir</b>.</div>
  <div class="flexbtns"><button class="btn" onclick="editNota()">+ Nota Baru</button><button class="btn gray" onclick="exportPenjualan('distribusi')">⬇️ Export CSV</button>${monthBar()}${S.stores.length===0?'<span class="mini" style="align-self:center">⚠️ Tambah toko dulu.</span>':""}</div>`;
  document.getElementById("v-distribusi").innerHTML=`${head}${tools}<div id="distBody">${distBodyHtml()}</div>`;
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
  window._n=n;openModal(notaForm());
}
function notaProdOpts(sel){return `<option value="" ${!sel?"selected":""}>— pilih produk —</option>`+S.products.map(p=>`<option value="${p.id}" ${p.id===sel?"selected":""}>${esc(p.name)} — stok ${stock(p.id)}</option>`).join("");}
function prodCombo(curId,cb){return combo(S.products.map(p=>({id:p.id,label:p.name+" · stok "+stock(p.id)})),curId,cb,"— cari / pilih produk —");}
function notaForm(){const n=window._n;comboReset();
  const idx=n.items.map((it,i)=>({it,i}));
  // baris JUAL: produk (combo) | qty + harga | subtotal
  const jualRows=idx.filter(x=>(x.it.kind||"jual")==="jual").map(({it,i})=>`<div class="itemrow">
    <div class="irtop">${prodCombo(it.productId,(id)=>notaPickProd(i,id))}<button class="x" onclick="_n.items.splice(${i},1);remoN()">×</button></div>
    <div class="irbot"><input type="number" inputmode="numeric" placeholder="Qty" value="${it.qty}" oninput="_n.items[${i}].qty=this.value;nSum()"><input inputmode="numeric" placeholder="Harga satuan" value="${it.harga?grp(it.harga):""}" oninput="this.value=grp(this.value);_n.items[${i}].harga=digits(this.value);nSum()"></div>
    <div class="irsub" id="nsub${i}">${rp((+it.qty||0)*(+it.harga||0))}</div></div>`).join("");
  // baris GRATIS: produk (combo) | qty + jenis
  const freeRows=idx.filter(x=>(x.it.kind||"jual")!=="jual").map(({it,i})=>`<div class="itemrow">
    <div class="irtop">${prodCombo(it.productId,(id)=>notaPickProd(i,id))}<button class="x" onclick="_n.items.splice(${i},1);remoN()">×</button></div>
    <div class="irbot"><input type="number" inputmode="numeric" placeholder="Qty" value="${it.qty}" oninput="_n.items[${i}].qty=this.value;nSum()"><select onchange="notaSetKind(${i},this.value)" title="Jenis pemberian gratis">${NOTA_FREE_KINDS.map(([v,t])=>`<option value="${v}" ${it.kind===v?"selected":""}>${t}</option>`).join("")}</select></div></div>`).join("");
  return `<button class="close" onclick="closeModal()">×</button><h3>${n.id?"Edit":"Tambah"} Nota / Faktur</h3>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal</label><input type="date" value="${n.date}" oninput="_n.date=this.value;autoNotaNo()"></div><div><label class="f">Toko / Penerima</label>${combo(S.stores.map(s=>({id:s.id,label:s.name})),n.storeId,(id)=>{window._n.storeId=id;},"— cari / pilih toko —")}</div></div>
  <div style="margin-bottom:12px"><label class="f">No. Nota / Faktur <span class="mini">(otomatis, bisa diubah)</span></label><input id="nNo" value="${esc(n.notaNo||"")}" oninput="_n.notaNo=this.value" placeholder="mis. NOTA-20260704-001"></div>
  <label class="f">Produk Dijual <span class="mini">(ditagih ke toko)</span></label>${jualRows||'<div class="mini" style="margin-bottom:6px">Belum ada produk jual.</div>'}
  <button class="btn sm ghost" onclick="_n.items.push(notaItemDefault());remoN()">+ item jual</button>
  <div style="border-top:1px dashed var(--line);margin:16px 0 10px"></div>
  <label class="f">Bonus / Endorse / Tester <span class="mini">(GRATIS — tak ditagih, tapi tetap potong stok &amp; tercatat)</span></label>${freeRows||'<div class="mini" style="margin-bottom:6px">Tidak ada. Klik di bawah kalau mau tambah produk gratis.</div>'}
  <button class="btn sm ghost" onclick="_n.items.push(notaFreeDefault());remoN()">+ bonus / endorse / tester</button>
  <div class="sumbox" id="nSum"></div>
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveNota()">Simpan Nota</button></div>`;
}
function notaPickProd(i,val){const it=window._n.items[i];const p=prod(val);it.productId=val;if(p){it.hpp=p.hpp;if((it.kind||"jual")==="jual")it.harga=p.harga;}remoN();}
function notaSetKind(i,val){const it=window._n.items[i];it.kind=val;
  if(val!=="jual"){it.harga=0;}else{const p=prod(it.productId);if(p&&!(+it.harga>0))it.harga=p.harga;}
  remoN();}
function remoN(){mEl.innerHTML=notaForm();nSum();}
function nSum(){const n=window._n;
  n.items.forEach((it,i)=>{const el=document.getElementById("nsub"+i);if(el){const free=(it.kind||"jual")!=="jual";el.innerHTML=free?'<span style="color:var(--amber)">GRATIS</span>':rp((+it.qty||0)*(+it.harga||0));}});
  const box=document.getElementById("nSum");if(!box)return;
  const valid=n.items.filter(it=>it.productId&&+it.qty>0);
  const freeQty=valid.filter(it=>(it.kind||"jual")!=="jual").reduce((a,it)=>a+(+it.qty||0),0);
  box.innerHTML=`<div><span>Jumlah item</span><span>${valid.length} baris · ${notaQty(n)} item${freeQty?` (${freeQty} gratis)`:""}</span></div><div class="big"><span>Total tagihan</span><span>${rp(notaTotal(n))}</span></div><div><span>Estimasi laba</span><span style="color:var(--green)"><b>${rp(notaProfit(n))}</b></span></div>`;
}
// nomor otomatis ikut tanggal selama belum diubah manual (hanya nota baru)
function autoNotaNo(){const n=window._n;if(!n||n.id||n.notaNo!==n._auto||!n.date)return;n.notaNo=n._auto=nextNotaNo(n.date);const el=document.getElementById("nNo");if(el)el.value=n.notaNo;}
async function saveNota(){const n=window._n;if(!n.storeId){toast("Pilih toko/penerima dulu.");return;}if(n.items.filter(it=>it.productId&&+it.qty>0).length===0){toast("Isi minimal 1 item + qty.");return;}await api("saveNota",{nota:n});await reload();closeModal();toast("Nota tersimpan ✓");rDistribusi();}
async function delNota(id){if(confirm("Hapus nota ini beserta pembayarannya? Stok kembali.")){await api("deleteNota",{id});await reload();rDistribusi();}}

