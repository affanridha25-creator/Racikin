"use strict";
// produksi.js — produksi: batch, detail, bandingkan, draft
// ================= PRODUKSI =================
function rProduksi(){
  if(isMobile()){
    const rows=S.batches.map(b=>{const c=batchCalc(b);
      const prods=(b.outputs||[]).map(o=>esc((prod(o.productId)||{}).name||"?")).join(", ");
      const acts=`<div class="cacts"><button class="btn sm" onclick="event.stopPropagation();printBatch('${b.id}')">🖨 Cetak</button></div>`;
      return crow({icon:"🏭",title:(b.note?esc(b.note):prods)||"Batch",sub:`${b.batchNo?esc(b.batchNo)+" · ":""}${fmtDate(b.date)} · ${c.bottles} item · HPP ${rp(c.avg)}`,amt:rp(c.total),acts,onclick:`viewBatch('${b.id}')`,del:`delBatch('${b.id}')`});}).join("");
    document.getElementById("v-produksi").innerHTML=`<div class="desc">Racik bahan + biaya → HPP per batch otomatis.</div><button class="btn" style="width:100%;margin-bottom:8px" onclick="editBatch()">+ Batch Baru</button><button class="btn gray" style="width:100%;margin-bottom:14px" onclick="cmpBatch()">⚖ Bandingkan Batch</button>${S.batches.length===0?'<div class="empty">Belum ada batch.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.batches.map(b=>{const c=batchCalc(b);const o=(b.outputs||[]).map(x=>`<div class="li"><span>${esc((prod(x.productId)||{}).name||"?")}</span><span class="q">×${x.qty}</span></div>`).join("")||'<span class="muted">—</span>';
    return `<tr><td>${esc(b.batchNo||"-")}</td><td>${fmtDate(b.date)}${b.note?`<div class="mini">${esc(b.note)}</div>`:""}</td><td><div class="itemcell">${o}</div></td><td class="num">${c.bottles}</td><td class="num">${rp(c.mat)}</td><td class="num">${rp(c.ops)}</td><td class="num"><b>${rp(c.total)}</b></td><td class="num">${rp(c.avg)}</td><td class="num"><div class="acts"><button class="btn sm ghost" onclick="viewBatch('${b.id}')">👁 Detail</button><button class="btn sm" onclick="printBatch('${b.id}')">🖨 Cetak</button><button class="btn sm gray" onclick="editBatch('${b.id}')">Edit</button><button class="btn sm del" onclick="delBatch('${b.id}')">✕</button></div></td></tr>`;}).join("");
  document.getElementById("v-produksi").innerHTML=`
  <h2 class="title">Produksi &amp; HPP per Batch</h2>
  <div class="desc">Pilih bahan baku (harga otomatis dari master) + biaya → tentukan produk jadi &amp; qty → HPP per batch dihitung otomatis.</div>
  <div class="flexbtns"><button class="btn" onclick="editBatch()">+ Batch Baru</button><button class="btn gray" onclick="cmpBatch()">⚖ Bandingkan Batch</button></div>
  <div class="panel"><h3>Riwayat Batch</h3>${S.batches.length===0?'<div class="empty">Belum ada batch.</div>':`<table><thead><tr><th>No. Batch</th><th>Tanggal</th><th>Produk Jadi</th><th class="num">Item</th><th class="num">Bahan</th><th class="num">Ops</th><th class="num">Total Modal</th><th class="num">HPP rata-rata/item</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
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
  const dl=(cur,prev)=>{if(prev==null||!prev||cur===prev)return"";const d=(cur-prev)/prev*100;return ` <span style="color:${d>0?"#c0392b":"#1e8e3e"};font-size:11px">${d>0?"▲":"▼"}${Math.abs(d).toFixed(0)}%</span>`;};
  const miss=`<span style="color:var(--amber);font-size:11px">⚠ tidak ada</span>`;
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
  const sel=`<select onchange="cmpBatch(this.value)" style="width:100%;margin-bottom:10px">${cands.map(p=>`<option value="${p.id}"${p.id===pid?" selected":""}>${esc(p.name)}</option>`).join("")}</select>`;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Bandingkan Batch</h3>${sel}
  <p class="mini" style="margin-bottom:8px">Harga bahan &amp; qty per item tiap batch, urut dari terlama. ▲▼ = perubahan vs batch sebelumnya. Baris kuning ⚠ = item ada di batch lain tapi tidak di batch ini (mungkin lupa diinput).</p>
  <div style="overflow-x:auto"><table class="cmpt"><thead><tr><th></th>${bs.map((b,i)=>`<th class="num">${esc(b.batchNo||"-")}<div class="mini">${fmtDate(b.date)} · ${pq[i]} item</div></th>`).join("")}</tr></thead><tbody>
  <tr class="tot"><td><b>HPP / item</b></td>${hppTds}</tr>
  ${sec("Bahan baku (harga satuan · qty per item)")}${Object.values(rows).map(itemRow).join("")}
  ${Object.keys(ops).length?sec("Biaya operasional (per item)")+Object.values(ops).map(opRow).join(""):""}
  </tbody></table></div>`);
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
  const c=batchCalc(b);
  const matRows=(b.materials||[]).map(m=>{const sub=(+m.qty||0)*(+m.price||0);
    const tgt=m.targetProduct?`<div class="mini">→ ${esc((prod(m.targetProduct)||{}).name||"?")}</div>`:"";
    return `<tr><td>${esc(m.name||"?")}${tgt}</td><td class="num">${+m.qty||0} ${esc(m.unit||"")}</td><td class="num">${rp(m.price)}</td><td class="num">${rp(sub)}</td></tr>`;}).join("");
  const opsRows=(b.ops||[]).map(o=>`<tr><td>${esc(o.name||"?")}</td><td class="num">${rp(o.amount)}</td></tr>`).join("");
  const outRows=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0).map(o=>{const p=prod(o.productId)||{};
    const hpp=Math.round(c.perProduct[o.productId]||0),harga=+p.harga||0,untung=harga-hpp,margin=hpp>0?untung/hpp*100:0;
    return `<tr><td>${esc(p.name||"?")}</td><td class="num">${o.qty}</td><td class="num">${rp(hpp)}</td><td class="num">${rp(harga)}</td><td class="num" style="color:${untung<0?"var(--red)":"var(--green)"}">${rp(untung)} <span class="mini">${margin.toFixed(0)}%</span></td></tr>`;}).join("");
  const sec=t=>`<h4 style="margin:16px 0 6px;font-size:14px">${t}</h4>`;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Detail Batch <span class="mini">${esc(b.batchNo||"")}</span></h3>
  <div class="mini" style="margin-bottom:8px">${fmtDate(b.date)}${b.note?` · ${esc(b.note)}`:""}</div>
  <div class="grid2" style="margin-bottom:4px"><div class="poskembali" style="justify-content:space-between"><span>Total modal</span><b>${rp(c.total)}</b></div><div class="poskembali" style="justify-content:space-between"><span>HPP rata-rata/item</span><b>${rp(c.avg)}</b></div></div>
  ${sec("Produk jadi")}<div style="overflow-x:auto"><table><thead><tr><th>Produk</th><th class="num">Item</th><th class="num">HPP</th><th class="num">Harga jual</th><th class="num">Untung/item</th></tr></thead><tbody>${outRows||'<tr><td colspan="5">—</td></tr>'}</tbody></table></div>
  ${sec("Bahan baku")}<div style="overflow-x:auto"><table><thead><tr><th>Bahan</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead><tbody>${matRows||'<tr><td colspan="4">—</td></tr>'}<tr><td colspan="3"><b>Total bahan</b></td><td class="num"><b>${rp(c.mat)}</b></td></tr></tbody></table></div>
  ${sec("Biaya operasional")}<table><tbody>${opsRows||'<tr><td colspan="2">—</td></tr>'}<tr><td><b>Total operasional</b></td><td class="num"><b>${rp(c.ops)}</b></td></tr></tbody></table>
  <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap"><button class="btn gray" onclick="closeModal()">Tutup</button><button class="btn gray" onclick="closeModal();editBatch('${b.id}')">Edit</button><button class="btn" onclick="printBatch('${b.id}')">🖨 Cetak</button></div>`);
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
  window._b=b;openModal(batchForm());
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
    const allocSel=showAlloc?`<select onchange="_b.materials[${i}].targetProduct=this.value;bSum()" style="font-size:12px" title="Alokasi biaya bahan ini">
        <option value="" ${!m.targetProduct?"selected":""}>↔ Dibagi rata ke semua produk</option>
        ${outProds.map(pid=>`<option value="${pid}" ${m.targetProduct===pid?"selected":""}>→ khusus ${esc((prod(pid)||{}).name||"?")}</option>`).join("")}
      </select>`:"";
    return `<div class="itemrow">
      <div class="irtop">${combo(matOpts,isCustom?"__c":m.materialId,(id)=>pickMat(i,id),"— cari / pilih bahan —")}<button class="x" onclick="_b.materials.splice(${i},1);remo(batchForm())">×</button></div>
      ${nameInput}
      <div class="irbot"><input type="number" inputmode="decimal" placeholder="Qty" value="${m.qty}" oninput="_b.materials[${i}].qty=this.value;bSum()"><input inputmode="numeric" placeholder="Harga" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value);_b.materials[${i}].price=digits(this.value);bSum()"></div>
      ${allocSel}
      <div class="irsub" id="matsub${i}">${rp((+m.qty||0)*(+m.price||0))}</div></div>`;}).join("");
  const opsRows=b.ops.map((o,i)=>`<div class="dynrow ops"><input placeholder="Nama biaya" value="${esc(o.name)}" oninput="_b.ops[${i}].name=this.value"><input inputmode="numeric" placeholder="Rp" value="${o.amount?grp(o.amount):""}" oninput="this.value=grp(this.value);_b.ops[${i}].amount=digits(this.value);bSum()"><button class="x" onclick="_b.ops.splice(${i},1);remo(batchForm())">×</button></div>`).join("");
  const outRows=b.outputs.map((o,i)=>`<div class="itemrow"><div class="irtop">${combo(S.products.map(p=>({id:p.id,label:p.name})),o.productId,(id)=>{window._b.outputs[i].productId=id;addReqMats(id);remo(batchForm());},"— cari / pilih produk —")}<button class="x" onclick="_b.outputs.splice(${i},1);remo(batchForm())">×</button></div><div class="irbot one"><input type="number" inputmode="numeric" placeholder="Qty" value="${o.qty}" oninput="_b.outputs[${i}].qty=this.value;bSum()"></div></div>`).join("");
  return `<button class="close" onclick="closeModal()">×</button><h3>${b.id?"Edit":"Tambah"} Batch Produksi${b.batchNo?` <span class="mini">${esc(b.batchNo)}</span>`:""}</h3>
  ${!b.id?`<div class="mini" style="background:var(--amber-bg);color:var(--amber);padding:7px 10px;border-radius:8px;margin-bottom:12px">📝 Batch ini belum tersimpan ke database. Klik <b>Simpan Draft</b> untuk menyimpan sementara di browser &amp; lanjut nanti (tetap ada walau modal ditutup / halaman di-refresh).</div>`:""}
  <div class="grid2" style="margin-bottom:14px"><div><label class="f">Tanggal</label><input type="date" value="${b.date}" oninput="_b.date=this.value"></div><div><label class="f">Catatan</label><input value="${esc(b.note)}" oninput="_b.note=this.value" placeholder="opsional"></div></div>
  <label class="f">Bahan Baku <span class="mini">(pilih dari master; harga bisa disesuaikan — subtotal per baris otomatis)</span></label>${matRows}
  ${showAlloc?`<p class="mini" style="margin:2px 0 4px">💡 Ada ${outProds.length} produk jadi — pakai dropdown <b>alokasi</b> tiap bahan: <b>khusus</b> satu produk (mis. daging→Premium, tulang→Campur) atau <b>dibagi rata</b>. Operasional selalu dibagi rata per bobot.</p>`:""}
  <button class="btn sm ghost" onclick="_b.materials.push(matDefault());remo(batchForm())">+ bahan</button>
  <label class="f" style="margin-top:14px">Biaya Operasional</label>${opsRows}
  <button class="btn sm ghost" onclick="_b.ops.push({name:'',amount:''});remo(batchForm())">+ biaya</button>
  <label class="f" style="margin-top:14px">Produk Jadi &amp; Qty</label>${outRows}
  <button class="btn sm ghost" onclick="_b.outputs.push({productId:'',qty:''});remo(batchForm())">+ produk</button>
  <div class="sumbox" id="bSum"></div>
  <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
    ${!b.id&&hasDraft()?`<button class="btn gray" onclick="discardDraft()">🗑 Buang Draft</button>`:""}
    <button class="btn gray" onclick="closeModal()">Tutup</button>
    ${!b.id?`<button class="btn ghost" onclick="saveDraft()">💾 Simpan Draft</button>`:""}
    <button class="btn" onclick="saveBatch()">Simpan Batch</button>
  </div>`;
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
function bSum(){const c=batchCalc(window._b),box=document.getElementById("bSum");
  (window._b.materials||[]).forEach((m,i)=>{const el=document.getElementById("matsub"+i);if(el)el.textContent=rp((+m.qty||0)*(+m.price||0));});
  persistDraft();
  if(!box)return;
  box.innerHTML=`<div><span>Total bahan baku</span><span>${rp(c.mat)}</span></div><div><span>Total operasional</span><span>${rp(c.ops)}</span></div><div class="big"><span>Total modal batch</span><span>${rp(c.total)}</span></div><div style="margin-top:6px"><span>Total item jadi</span><span>${c.bottles} item</span></div><div><span>HPP rata-rata / item</span><span><b>${rp(c.avg)}</b></span></div>${(window._b.outputs||[]).filter(o=>o.productId&&o.qty).map(o=>`<div class="mini" style="display:flex;justify-content:space-between"><span>↳ HPP ${esc((prod(o.productId)||{}).name||"?")}</span><span>${rp(c.perProduct[o.productId])}</span></div>`).join("")}`;
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

