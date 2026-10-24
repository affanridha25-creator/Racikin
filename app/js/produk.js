"use strict";
// produk.js — master produk & opname stok
// ================= PRODUK =================
function rProduk(){
  if(isMobile()){
    const rows=S.products.map(p=>crow({icon:p.photo?`<img src="${p.photo}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">`:"🫙",title:esc(p.name),sub:`${esc(p.cat)} · ${p.gram}gr${tracksStock(p)?` · stok ${stock(p.id)}`:""}`,badges:`<div class="csub">HPP ${rp(p.hpp)} · margin ${rp(p.harga-p.hpp)}</div>`,amt:rp(p.harga),acts:tracksStock(p)?`<div class="cacts"><button class="btn sm" onclick="event.stopPropagation();stockAdjModal('${p.id}')">⚖️</button></div>`:"",onclick:`editProd('${p.id}')`,del:`delProd('${p.id}')`})).join("");
    document.getElementById("v-produk").innerHTML=`<div class="desc">${bizType()==="fnb"?"Daftar menu &amp; harga jual. HPP bisa diisi manual dari perkiraan bahan.":"HPP terisi otomatis dari batch; bisa diubah manual."}</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editProd()">+ ${bizType()==="fnb"?"Menu":"Produk"} Baru</button>${S.products.length===0?'<div class="empty">Belum ada produk.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.products.map(p=>`<tr><td><div style="display:flex;align-items:center;gap:9px">${p.photo?`<img src="${p.photo}" class="ptmb">`:'<span class="ptmb noimg">🫙</span>'}<span>${esc(p.name)}</span></div></td><td>${esc(p.cat)}</td><td class="num">${p.gram} gr</td><td class="num">${rp(p.hpp)}</td><td class="num">${rp(p.harga)}</td><td class="num" style="color:${p.harga-p.hpp>=0?'var(--green)':'var(--red)'}">${rp(p.harga-p.hpp)}</td><td class="num"><b>${tracksStock(p)?stock(p.id):"—"}</b></td><td class="num"><div class="acts">${tracksStock(p)?`<button class="btn sm" onclick="stockAdjModal('${p.id}')">⚖️ Stok</button>`:""}<button class="btn sm gray" onclick="editProd('${p.id}')">Edit</button><button class="btn sm del" onclick="delProd('${p.id}')">✕</button></div></td></tr>`).join("");
  document.getElementById("v-produk").innerHTML=`<h2 class="title">${bizType()==="fnb"?"Menu":"Master Produk"}</h2><div class="desc">${bizType()==="fnb"?"Daftar menu &amp; harga jual. HPP bisa diisi manual dari perkiraan bahan. Stok hanya dilacak untuk item yang diaktifkan.":"HPP terisi otomatis dari batch terakhir, tapi bisa diubah manual."}</div>
  <div class="flexbtns"><button class="btn" onclick="editProd()">+ ${bizType()==="fnb"?"Menu":"Produk"} Baru</button></div>
  <div class="panel"><table><thead><tr><th>Nama</th><th>Kategori</th><th class="num">Ukuran</th><th class="num">HPP</th><th class="num">Harga</th><th class="num">Margin</th><th class="num">Stok</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`;
}
let _prodPhoto=null;   // null = tak diubah; "" = hapus; data URI = foto baru
function editProd(id){const p=id?S.products.find(x=>x.id===id):{id:null,name:"",cat:"",gram:"",hpp:"",harga:"",photo:""};
  _prodPhoto=null;
  const fnb=bizType()==="fnb",uw=fnb?"porsi":"item";
  // produk baru: F&B default TIDAK lacak stok (made-to-order); produksi default lacak
  const trk=id?tracksStock(p):!fnb;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${id?"Edit":"Tambah"} ${fnb?"Menu":"Produk"}</h3>
  <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px">
    <div id="prodPhotoPrev" class="logobox" style="width:74px;height:74px">${p.photo?`<img src="${p.photo}" alt="">`:`<span>🫙</span>`}</div>
    <div><div class="flexbtns" style="margin-bottom:0"><button class="btn sm ghost" onclick="document.getElementById('prodPhotoInp').click()">📷 Foto Produk</button>${p.photo?`<button class="btn sm gray" onclick="rmProdPhoto()">Hapus</button>`:""}</div><div class="mini" style="margin-top:6px">Opsional — tampil di grid kasir. Maks 5MB.</div></div>
    <input type="file" id="prodPhotoInp" accept="image/*" style="display:none" onchange="pickProdPhoto(this)">
  </div>
  <label class="f">Nama produk</label><input id="pn" value="${esc(p.name)}">
  <div class="grid2" style="margin:12px 0"><div><label class="f">Kategori</label><input id="pc" value="${esc(p.cat)}"></div><div><label class="f">Ukuran (gram)</label><input id="pg" type="number" value="${p.gram}"></div></div>
  <div class="grid2"><div><label class="f">HPP / ${uw}</label><input id="ph" inputmode="numeric" value="${p.hpp?grp(p.hpp):""}" oninput="this.value=grp(this.value)"></div><div><label class="f">Harga jual</label><input id="phj" inputmode="numeric" value="${p.harga?grp(p.harga):""}" oninput="this.value=grp(this.value)"></div></div>
  ${fnb?"":`<p class="mini" style="margin-top:8px">Ukuran (gram) dipakai membagi HPP antar produk dalam 1 batch (per bobot).</p>`}
  ${fnb?"":reqMatsBox(p)}
  <label style="display:flex;align-items:center;gap:8px;margin-top:14px;cursor:pointer"><input type="checkbox" id="pTrack" ${trk?"checked":""}> <span class="f" style="margin:0">Lacak stok ${fnb?"menu":"produk"} ini</span></label>
  <p class="mini" style="margin-top:4px">${fnb?"Menu dimasak saat dipesan → biarkan mati (jual bebas). Nyalakan untuk item stok jadi (mis. air mineral, kemasan).":"Kalau dimatikan, penjualan tak dibatasi/dihitung stok (cocok untuk item pre-order / jasa)."}</p>
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveProd('${id||""}')">Simpan</button></div>`);}
function reqMatsBox(p){const sel=new Set(p.reqMats||[]);
  if(!S.materials.length)return"";
  return `<label class="f" style="margin-top:14px">Bahan wajib tiap batch <span class="mini">(otomatis ditambah ke batch &amp; diperingatkan kalau lupa diisi)</span></label>
  <div style="max-height:160px;overflow:auto;border:1px solid #e3e3e3;border-radius:10px;padding:6px 10px">${S.materials.map(m=>`<label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer"><input type="checkbox" class="rqm" value="${m.id}"${sel.has(m.id)?" checked":""}> <span>${esc(m.name)}</span></label>`).join("")}</div>`;}
function pickProdPhoto(inp){const f=inp.files[0];if(!f)return;if(!/^image\//.test(f.type)){toast("File harus gambar.");return;}if(f.size>5*1024*1024){toast("Maks 5MB.");return;}
  const rd=new FileReader();rd.onload=e=>{const img=new Image();img.onload=()=>{const max=360;let w=img.width,h=img.height;if(w>h&&w>max){h=Math.round(h*max/w);w=max;}else if(h>=w&&h>max){w=Math.round(w*max/h);h=max;}const cv=document.createElement("canvas");cv.width=w;cv.height=h;cv.getContext("2d").drawImage(img,0,0,w,h);_prodPhoto=cv.toDataURL("image/jpeg",0.82);document.getElementById("prodPhotoPrev").innerHTML=`<img src="${_prodPhoto}" alt="">`;};img.onerror=()=>toast("Gambar tak terbaca.");img.src=e.target.result;};rd.readAsDataURL(f);}
function rmProdPhoto(){_prodPhoto="";document.getElementById("prodPhotoPrev").innerHTML=`<span>🫙</span>`;}
async function saveProd(id){const o={id:id||null,name:document.getElementById("pn").value.trim(),cat:document.getElementById("pc").value.trim()||"Umum",gram:+document.getElementById("pg").value||1,hpp:+digits(document.getElementById("ph").value)||0,harga:+digits(document.getElementById("phj").value)||0,trackStock:document.getElementById("pTrack").checked?1:0};
  if(document.querySelector(".rqm")||bizType()!=="fnb")o.reqMats=[...document.querySelectorAll(".rqm:checked")].map(x=>x.value);
  if(_prodPhoto!==null)o.photo=_prodPhoto;   // hanya kirim kalau diubah (biar tak terhapus saat REPLACE)
  if(!o.name){toast("Nama wajib.");return;}await api("saveProduct",{product:o});await reload();closeModal();toast("Produk tersimpan ✓");rProduk();}
// ---- penyesuaian / opname stok produk ----
function stockAdjModal(id){const p=S.products.find(x=>x.id===id);if(!p){toast("Produk tak ditemukan.");return;}const cur=stock(id);
  const hist=(S.stockAdj||[]).filter(a=>a.productId===id).slice(0,8).map(a=>`<tr><td>${fmtDate(a.date)}</td><td class="num" style="color:${a.qty<0?'var(--red)':'var(--green)'}">${a.qty>0?'+':''}${a.qty}</td><td>${esc(adjReason(a.reason))}</td><td>${esc(a.note||"")}</td><td class="num"><button class="btn sm gray" onclick="delStockAdj('${a.id}','${id}')">×</button></td></tr>`).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>⚖️ Penyesuaian Stok — ${esc(p.name)}</h3>
  <div class="poskembali" style="justify-content:space-between;margin-bottom:14px"><span>Stok tercatat sekarang</span><b>${cur}</b></div>
  <label class="f">Jenis</label>
  <select id="adjReason" onchange="adjModeChange()" style="margin-bottom:12px">
    <option value="rusak">Rusak</option><option value="expired">Kadaluarsa</option><option value="hilang">Hilang</option><option value="pakai">Dipakai sendiri</option><option value="koreksi">Koreksi (+/−)</option><option value="opname">Stok Opname (set jumlah fisik)</option>
  </select>
  <div id="adjQtyWrap"><label class="f" id="adjQtyLbl">Jumlah berkurang</label><input id="adjQty" inputmode="numeric" placeholder="mis. 3"></div>
  <label class="f" style="margin-top:10px">Catatan <span class="mini">(opsional)</span></label><input id="adjNote" placeholder="mis. jatuh / bocor">
  <button class="btn" style="width:100%;margin-top:14px" onclick="saveStockAdj('${id}')">Simpan Penyesuaian</button>
  ${hist?`<h4 style="margin:16px 0 6px;font-size:14px">Riwayat Penyesuaian</h4><div class="panel" style="padding:0"><table><thead><tr><th>Tgl</th><th class="num">Qty</th><th>Jenis</th><th>Catatan</th><th></th></tr></thead><tbody>${hist}</tbody></table></div>`:""}
  <p class="mini" style="margin-top:12px">Rusak/kadaluarsa/hilang/pakai = stok berkurang. Opname = set jumlah hasil hitung fisik (sistem hitung selisihnya). Koreksi = tambah/kurang manual (pakai − untuk kurang).</p>`);}
function adjReason(r){return {rusak:"Rusak",expired:"Kadaluarsa",hilang:"Hilang",pakai:"Dipakai sendiri",koreksi:"Koreksi",opname:"Opname"}[r]||r;}
function adjModeChange(){const r=document.getElementById("adjReason").value;const lbl=document.getElementById("adjQtyLbl");
  lbl.textContent=r==="opname"?"Jumlah fisik hasil hitung":(r==="koreksi"?"Perubahan (+/−)":"Jumlah berkurang");}
async function saveStockAdj(id){const r=document.getElementById("adjReason").value;let raw=document.getElementById("adjQty").value.trim().replace(/\./g,"");let n=parseInt(raw,10);if(isNaN(n)){toast("Isi jumlah.");return;}
  let qty;const cur=stock(id);
  if(r==="opname"){qty=n-cur;if(qty===0){toast("Jumlah fisik sama dengan stok tercatat — tak ada perubahan.");return;}}
  else if(r==="koreksi"){qty=n;}          // boleh + atau − (user ketik tandanya)
  else {qty=-Math.abs(n);}                 // rusak/hilang/pakai/expired = selalu mengurangi
  if(qty===0){toast("Jumlah tidak boleh 0.");return;}
  await api("saveStockAdj",{adj:{productId:id,qty,reason:r,note:document.getElementById("adjNote").value.trim(),date:today()}});
  await reload();closeModal();toast("Stok disesuaikan ✓");rProduk();}
async function delStockAdj(aid,id){if(confirm("Hapus penyesuaian ini? Stok akan kembali seperti semula.")){await api("deleteStockAdj",{id:aid});await reload();stockAdjModal(id);}}
async function delProd(id){const used=S.batches.some(b=>(b.outputs||[]).some(o=>o.productId===id))||S.notas.some(n=>notaItems(n).some(it=>it.productId===id));if(used){toast("Produk dipakai di batch/nota.");return;}if(confirm("Hapus produk ini?")){await api("deleteProduct",{id});await reload();rProduk();}}

