"use strict";
// bahan.js — bahan baku & stok masuk
// ================= BAHAN BAKU =================
function rBahan(){
  if(isMobile()){
    const rows=S.materials.map(m=>{const s=matStat(m);const si=matStockInfo(m);
      const chg=s.chg==null?"":(s.chg>0?`<span class="up">▲ ${(s.chg*100).toFixed(0)}%</span>`:s.chg<0?`<span class="down">▼ ${(Math.abs(s.chg)*100).toFixed(0)}%</span>`:"");
      const stok=si.tracked?`<span style="${si.low?'color:var(--red);font-weight:700':''}">Stok ${fmtQty(si.stock)} ${esc(m.unit)}${si.low?' ⚠️':''}</span>`:'<span class="mini">stok belum dilacak</span>';
      const acts=`<div class="cacts"><button class="btn sm wa" style="background:#1E8449" onclick="event.stopPropagation();matBeli('${m.id}')">📦</button><button class="btn sm gray" onclick="event.stopPropagation();editMat('${m.id}')">✏️</button></div>`;
      return crow({icon:"🧂",title:esc(m.name),sub:stok,badges:chg?`<div style="margin-top:2px">${chg}</div>`:"",amt:rp(m.cur??m.price),acts,onclick:`matHist('${m.id}')`,del:`delMat('${m.id}')`});}).join("");
    document.getElementById("v-bahan").innerHTML=`<div class="desc">Ketuk bahan untuk lihat grafik tren harga &amp; riwayat; tombol ✏️ untuk ubah harga.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editMat()">+ Bahan Baru</button>${S.materials.length===0?'<div class="empty">Belum ada bahan.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.materials.map(m=>{const s=matStat(m);const si=matStockInfo(m);
    const chg=chgBadge(s.chg,'<span class="flat">—</span>');
    const stok=si.tracked?`<b style="${si.low?'color:var(--red)':''}">${fmtQty(si.stock)} ${esc(m.unit)}</b>${si.low?' <span class="pill belum">menipis</span>':''}`:'<span class="mini">belum dilacak</span>';
    return `<tr><td><a href="#" onclick="matHist('${m.id}');return false" style="color:var(--red);font-weight:600;text-decoration:none">${esc(m.name)}</a></td><td>${esc(m.unit)}</td><td class="num"><b>${rp(m.cur??m.price)}</b></td><td class="num">${stok}</td><td class="num">${chg}</td><td class="num"><div class="acts"><button class="btn sm wa" style="background:#1E8449" onclick="matBeli('${m.id}')">📦 Beli</button><button class="btn sm" onclick="editMat('${m.id}')">Ubah Harga</button><button class="btn sm gray" onclick="matHist('${m.id}')">📈</button><button class="btn sm del" onclick="delMat('${m.id}')">✕</button></div></td></tr>`;}).join("");
  document.getElementById("v-bahan").innerHTML=`<h2 class="title">Master Bahan Baku &amp; Stok</h2>
  <div class="desc">Klik nama bahan untuk grafik tren harga. Tombol <b>📦 Beli</b> untuk catat stok masuk (opsional — stok tak wajib diisi, produksi tetap jalan).</div>
  <div class="flexbtns"><button class="btn" onclick="editMat()">+ Bahan Baru</button></div>
  <div class="panel">${S.materials.length===0?'<div class="empty">Belum ada bahan.</div>':`<table><thead><tr><th>Bahan</th><th>Satuan</th><th class="num">Harga</th><th class="num">Stok</th><th class="num">Perubahan</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
}
function editMat(id){const m=id?S.materials.find(x=>x.id===id):{id:null,name:"",unit:"kg",price:"",minStock:0};
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${id?"Ubah Harga":"Tambah"} Bahan</h3>
  <label class="f">Nama bahan</label><input id="mn" value="${esc(m.name)}">
  <div class="grid2" style="margin:12px 0"><div><label class="f">Satuan</label><input id="mu" value="${esc(m.unit||"kg")}"></div><div><label class="f">Harga / satuan</label><input id="mp" inputmode="numeric" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value)"></div></div>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal berlaku</label><input id="md" type="date" value="${today()}"></div><div><label class="f">Stok minimum <span class="mini">(opsional)</span></label><input id="mms" inputmode="decimal" value="${m.minStock||""}" placeholder="0 = tak dipakai"></div></div>
  <p class="mini">Kalau harga berubah, otomatis tercatat di riwayat. Stok minimum untuk peringatan "menipis" (kosongkan kalau tak butuh).</p>
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveMat('${id||""}')">Simpan</button></div>`);}
async function saveMat(id){const o={id:id||null,name:document.getElementById("mn").value.trim(),unit:document.getElementById("mu").value.trim()||"kg",price:+digits(document.getElementById("mp").value)||0,minStock:parseFloat((document.getElementById("mms").value||"0").replace(",","."))||0};
  if(!o.name){toast("Nama wajib.");return;}await api("saveMaterial",{material:o,date:document.getElementById("md").value});await reload();closeModal();toast("Bahan tersimpan ✓");rBahan();}
async function delMat(id){if(confirm("Hapus bahan ini beserta riwayat harganya?")){await api("deleteMaterial",{id});await reload();rBahan();}}
// ---- catat pembelian / stok masuk bahan ----
function matBeli(id){const m=S.materials.find(x=>x.id===id);if(!m){toast("Bahan tak ditemukan.");return;}const si=matStockInfo(m);
  const hist=(m.purchases||[]).slice(0,8).map(p=>`<tr><td>${fmtDate(p.date)}</td><td class="num">${fmtQty(p.qty)} ${esc(m.unit)}</td><td class="num">${p.price?rp(p.price):"-"}</td><td>${esc(p.note||"")}</td><td class="num"><button class="btn sm gray" onclick="delMatBeli('${p.id}','${id}')">×</button></td></tr>`).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>📦 Stok Masuk — ${esc(m.name)}</h3>
  <div class="regsum" style="background:var(--bg);border-radius:12px;padding:6px 14px;margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;padding:6px 0"><span>Total dibeli</span><b>${fmtQty(si.bought)} ${esc(m.unit)}</b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--line)"><span>Terpakai (produksi)</span><b>${fmtQty(si.used)} ${esc(m.unit)}</b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--line);font-weight:800"><span>Stok saat ini</span><b style="${si.low?'color:var(--red)':'color:var(--green)'}">${si.tracked?fmtQty(si.stock)+" "+esc(m.unit):"belum dilacak"}</b></div>
  </div>
  <div class="grid2" style="margin-bottom:10px"><div><label class="f">Jumlah beli (${esc(m.unit)})</label><input id="mbq" inputmode="decimal" placeholder="mis. 5"></div><div><label class="f">Tanggal</label><input id="mbd" type="date" value="${today()}"></div></div>
  <div class="grid2" style="margin-bottom:10px"><div><label class="f">Harga/${esc(m.unit)} <span class="mini">(opsional)</span></label><input id="mbp" inputmode="numeric" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value)"></div><div><label class="f">Catatan</label><input id="mbn" placeholder="mis. beli di pasar"></div></div>
  <button class="btn" style="width:100%" onclick="saveMatBeli('${id}')">+ Catat Stok Masuk</button>
  ${hist?`<h4 style="margin:16px 0 6px;font-size:14px">Riwayat Pembelian</h4><div class="panel" style="padding:0"><table><thead><tr><th>Tgl</th><th class="num">Jumlah</th><th class="num">Harga</th><th>Catatan</th><th></th></tr></thead><tbody>${hist}</tbody></table></div>`:""}
  <p class="mini" style="margin-top:12px">Stok bahan opsional — kalau kamu langsung beli-produksi tanpa nyetok, abaikan saja. Produksi tetap jalan.</p>`);}
async function saveMatBeli(id){const qty=parseFloat((document.getElementById("mbq").value||"").replace(",","."));if(!(qty>0)){toast("Jumlah beli harus lebih dari 0.");return;}
  const purchase={materialId:id,qty,price:+digits(document.getElementById("mbp").value)||0,date:document.getElementById("mbd").value,note:document.getElementById("mbn").value.trim()};
  await api("saveMatPurchase",{purchase});await reload();closeModal();toast("Stok masuk dicatat ✓");rBahan();}
async function delMatBeli(pid,id){if(confirm("Hapus catatan pembelian ini?")){await api("deleteMatPurchase",{id:pid});await reload();matBeli(id);}}
// nama batch yang enak dibaca: catatan → produk yang dihasilkan → tanggal
function batchName(b){
  if(b.note)return b.note;
  const ps=[...new Set((b.outputs||[]).map(o=>(prod(o.productId)||{}).name).filter(Boolean))];
  return ps.length?ps.join(", "):fmtDate(b.date);
}
// sumber satu titik harga — kalau dari batch, tulis batch mana
function priceSrcLabel(h){
  if(h.source==="batch"){
    const b=h.ref?S.batches.find(x=>x.id===h.ref):null;
    if(b)return "dari batch: "+esc(batchName(b));
    return "dari batch ("+fmtDate(h.date)+")";
  }
  return srcLabel(h.source);
}
// grafik garis tren harga satu bahan
function priceTrend(h){
  if(h.length<2)return '<div class="empty" style="padding:16px">Belum cukup data untuk grafik (minimal 2 titik harga).</div>';
  const W=520,H=150,padL=10,padR=10,padT=22,padB=24,n=h.length;
  const ps=h.map(x=>x.price);const mn=Math.min(...ps),mx=Math.max(...ps),span=mx-mn||1;
  const X=i=>padL+(W-padL-padR)*(i/(n-1));
  const Y=p=>H-padB-(H-padT-padB)*((p-mn)/span);
  const pts=h.map((x,i)=>[X(i),Y(x.price)]);
  const line=pts.map((p,i)=>(i?"L":"M")+p[0].toFixed(1)+" "+p[1].toFixed(1)).join(" ");
  const area=`M${pts[0][0].toFixed(1)} ${H-padB} `+pts.map(p=>`L${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(" ")+` L${pts[n-1][0].toFixed(1)} ${H-padB} Z`;
  const dots=h.map((x,i)=>`<circle cx="${X(i).toFixed(1)}" cy="${Y(x.price).toFixed(1)}" r="3.5" fill="#fff" stroke="var(--red)" stroke-width="2"><title>${fmtDate(x.date)}: ${rp(x.price)}</title></circle>`).join("");
  return `<div class="pchart"><svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="xMidYMid meet" style="min-width:300px">
    <defs><linearGradient id="ptg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--orange)" stop-opacity=".30"/><stop offset="1" stop-color="var(--orange)" stop-opacity="0"/></linearGradient></defs>
    <path d="${area}" fill="url(#ptg)"/>
    <path d="${line}" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${dots}
    <text x="${padL}" y="13" font-size="10.5" fill="#8a8f99">Tertinggi ${rp(mx)}</text>
    <text x="${W-padR}" y="13" font-size="10.5" fill="#8a8f99" text-anchor="end">Terendah ${rp(mn)}</text>
  </svg></div>`;
}
function matHist(id){const m=S.materials.find(x=>x.id===id);if(!m){toast("Bahan tak ditemukan.");return;}
  const s=matStat(m);
  const rows=s.h.slice().reverse().map((h,idx,arr)=>{const older=arr[idx+1];const c=older?(h.price-older.price)/older.price:null;
    const cc=chgBadge(c,"");
    return `<tr><td>${fmtDate(h.date)}</td><td class="num">${rp(h.price)}</td><td class="num">${cc}</td><td class="mini">${priceSrcLabel(h)}</td><td class="num"><button class="btn sm gray" onclick="delPrice(${h.id},'${id}')">×</button></td></tr>`;}).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>📈 ${esc(m.name)} — Tren Harga</h3>
    <div class="mini" style="margin:-8px 0 12px">Harga sekarang <b style="color:var(--ink);font-size:14px">${rp(s.cur??m.price)}</b> / ${esc(m.unit)} · ${s.updates}× update${s.chg!=null?" · "+chgBadge(s.chg,""):""}</div>
    ${priceTrend(s.h)}
    <div style="margin-top:16px"><div class="mini" style="font-weight:700;margin-bottom:6px;color:var(--ink)">Riwayat Harga</div>
    ${s.h.length===0?'<div class="empty">Belum ada riwayat.</div>':`<table><thead><tr><th>Tanggal</th><th class="num">Harga</th><th class="num">vs sblm</th><th>Sumber</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>
    <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Tutup</button> <button class="btn" onclick="editMat('${id}')">Ubah Harga</button></div>`);}
async function delPrice(pid,id){if(confirm("Hapus titik harga ini?")){await api("deletePricePoint",{id:pid});await reload();matHist(id);}}

