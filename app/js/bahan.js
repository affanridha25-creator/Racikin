"use strict";
// bahan.js — bahan baku & stok masuk (v2: hero, kartu bahan + sparkline harga, modal bergaya v2)
// ================= BAHAN BAKU =================
const PB={q:"",f:""};   // pencarian & filter (bertahan selama sesi): f = "" | "naik" | "low"
function pbSet(k,v){PB[k]=v;if(k==="f")document.querySelectorAll("#v-bahan .pb-chipf").forEach(b=>b.classList.toggle("on",b.dataset.f===v));pbList();}
// sparkline kecil dari riwayat harga (pakai .ln → tergambar sendiri); naik = merah, turun = hijau
function pbSpark(h,chg){
  if(h.length<2)return '<div class="pb-nospark">belum ada tren</div>';
  const pts=h.slice(-12),W=120,H=36,p=3,n=pts.length,ps=pts.map(x=>x.price),mn=Math.min(...ps),mx=Math.max(...ps),sp=mx-mn||1;
  const X=i=>p+(W-2*p)*(i/(n-1)),Y=v=>H-p-(H-2*p)*((v-mn)/sp);
  const line=pts.map((x,i)=>(i?"L":"M")+X(i).toFixed(1)+" "+Y(x.price).toFixed(1)).join(" ");
  const col=chg>0?"var(--red)":chg<0?"var(--green)":"var(--orange)";
  return `<svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="none" class="pb-spark"><path class="ln" pathLength="1" d="${line}" fill="none" stroke="${col}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/><circle class="dpop" style="animation-delay:1.3s" cx="${X(n-1).toFixed(1)}" cy="${Y(pts[n-1].price).toFixed(1)}" r="3" fill="${col}"/></svg>`;
}
function pbCard(m,i){
  const s=matStat(m),si=matStockInfo(m);
  const chg=s.chg==null||s.chg===0?"":`<span class="pb-chg ${s.chg>0?"up":"dn"}">${s.chg>0?"▲":"▼"} ${(Math.abs(s.chg)*100).toFixed(0)}%</span>`;
  const stok=si.tracked?`<span class="pb-stk${si.low?" low":""}">${si.low?"⚠️ Stok menipis · ":"📦 Stok "}<b>${fmtQty(si.stock)} ${esc(m.unit)}</b></span>`:'<span class="pb-stk off">stok belum dilacak</span>';
  return `<div class="pb-card${si.low?" low":""}" style="--i:${Math.min(i,14)}" onclick="matHist('${m.id}')">
    <div class="pb-top"><span class="pb-ic">🧂</span><div class="pb-nm"><b>${esc(m.name)}</b><small>per ${esc(m.unit)}${s.last?` · ${fmtDate(s.last)}`:""}</small></div>${chg}</div>
    <div class="pb-mid"><div class="pb-price">${rp(m.cur??m.price)}<small>/${esc(m.unit)}</small></div><div class="pb-sp">${pbSpark(s.h,s.chg)}</div></div>
    <div class="pb-stkrow">${stok}</div>
    <div class="pb-acts"><button class="btn sm pb-buy" onclick="event.stopPropagation();matBeli('${m.id}')">📦 Beli</button><button class="btn sm" onclick="event.stopPropagation();editMat('${m.id}')">✏️ Ubah Harga</button><button class="btn sm gray" onclick="event.stopPropagation();matHist('${m.id}')" aria-label="Riwayat harga">📈</button><button class="btn sm del" onclick="event.stopPropagation();delMat('${m.id}')" aria-label="Hapus">✕</button></div>
  </div>`;
}
function pbList(){
  const box=document.getElementById("pbList");if(!box)return;
  const q=PB.q.trim().toLowerCase();
  let arr=S.materials.filter(m=>!q||(m.name||"").toLowerCase().includes(q));
  if(PB.f==="naik")arr=arr.filter(m=>{const c=matStat(m).chg;return c!=null&&c>0;});
  if(PB.f==="low")arr=arr.filter(m=>matStockInfo(m).low);
  box.innerHTML=S.materials.length===0?'<div class="pb-empty"><div>🧂</div><b>Belum ada bahan</b><p>Tambah bahan baku & harganya — dipakai otomatis saat membuat batch.</p><button class="btn" onclick="editMat()">+ Bahan Baru</button></div>'
    :arr.length===0?'<div class="pb-empty"><div>🔍</div><b>Tidak ada bahan yang cocok</b></div>'
    :arr.map(pbCard).join("");
}
function rBahan(){
  const stats=S.materials.map(m=>({m,s:matStat(m),si:matStockInfo(m)}));
  const naik=stats.filter(x=>x.s.chg!=null&&x.s.chg>0),low=stats.filter(x=>x.si.low);
  const nilai=stats.reduce((a,x)=>a+(x.si.tracked&&x.si.stock>0?x.si.stock*(+x.m.price||0):0),0);
  const top=naik.slice().sort((a,b)=>b.s.chg-a.s.chg)[0];
  const ins=!S.materials.length?"💡 Tambah bahan baku pertamamu — harganya otomatis dipakai di batch."
    :top?`📈 Kenaikan terbesar: <b>${esc(top.m.name)}</b> ▲${(top.s.chg*100).toFixed(0)}% dari harga sebelumnya.`
    :low.length?`⚠️ ${low.length} bahan stoknya menipis — saatnya belanja.`:"✨ Harga bahan stabil, tidak ada yang naik.";
  document.getElementById("v-bahan").innerHTML=`<div class="pb-root">
  <div class="pb-hero"><div class="dh-bg"><i></i><i></i><i></i></div>
    <div class="pb-hrow"><div><div class="pb-hk">🧂 Bahan Baku</div><h2>Master Bahan Baku &amp; Stok</h2><p>Harga terkini, tren, dan stok (opsional) — produksi tetap jalan walau stok tak diisi.</p></div>
      <div class="pb-hbtn"><button class="btn pb-bw" onclick="editMat()">+ Bahan Baru</button></div></div>
    <div class="pb-stats"><div><small>Jumlah bahan</small><b>${cnt(S.materials.length,"num")}</b></div><div><small>Harga naik</small><b>${cnt(naik.length,"num")}</b></div><div><small>Stok menipis</small><b>${cnt(low.length,"num")}</b></div><div><small>Nilai stok</small><b>${cnt(nilai)}</b></div></div>
    <div class="pb-ins">${ins}</div></div>
  <div class="pb-filt"><input class="pb-q" type="search" placeholder="🔍 Cari bahan…" value="${esc(PB.q)}" oninput="pbSet('q',this.value)">
    <div class="pb-chips"><button class="pb-chipf${PB.f===""?" on":""}" data-f="" onclick="pbSet('f','')">Semua</button><button class="pb-chipf${PB.f==="naik"?" on":""}" data-f="naik" onclick="pbSet('f','naik')">▲ Harga naik</button><button class="pb-chipf${PB.f==="low"?" on":""}" data-f="low" onclick="pbSet('f','low')">⚠ Menipis</button></div></div>
  <div class="pb-grid" id="pbList"></div></div>`;
  pbList();
  dashAnimate(document.querySelector("#v-bahan .pb-root"));
}
function editMat(id){const m=id?S.materials.find(x=>x.id===id):{id:null,name:"",unit:"kg",price:"",minStock:0};
  openModal(`<div class="pb-wrap"><div class="pb-mh"><button class="close" onclick="closeModal()">×</button><div class="pb-hk">🧂 Bahan Baku</div><h3>${id?"Ubah Harga":"Tambah"} Bahan</h3></div>
  <div class="pb-mb">
  <label class="f">Nama bahan</label><input id="mn" value="${esc(m.name)}">
  <div class="grid2" style="margin:12px 0"><div><label class="f">Satuan</label><input id="mu" value="${esc(m.unit||"kg")}"></div><div><label class="f">Harga / satuan</label><input id="mp" inputmode="numeric" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value)"></div></div>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal berlaku</label><input id="md" type="date" value="${today()}"></div><div><label class="f">Stok minimum <span class="mini">(opsional)</span></label><input id="mms" inputmode="decimal" value="${m.minStock||""}" placeholder="0 = tak dipakai"></div></div>
  <p class="pb-tip">Kalau harga berubah, otomatis tercatat di riwayat. Stok minimum untuk peringatan "menipis" (kosongkan kalau tak butuh).</p>
  <div class="pb-mf"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveMat('${id||""}')">Simpan</button></div></div></div>`);}
async function saveMat(id){const o={id:id||null,name:document.getElementById("mn").value.trim(),unit:document.getElementById("mu").value.trim()||"kg",price:+digits(document.getElementById("mp").value)||0,minStock:parseFloat((document.getElementById("mms").value||"0").replace(",","."))||0};
  if(!o.name){toast("Nama wajib.");return;}await api("saveMaterial",{material:o,date:document.getElementById("md").value});await reload();closeModal();toast("Bahan tersimpan ✓");rBahan();}
async function delMat(id){if(confirm("Hapus bahan ini beserta riwayat harganya?")){await api("deleteMaterial",{id});await reload();rBahan();}}
// ---- catat pembelian / stok masuk bahan ----
function matBeli(id){const m=S.materials.find(x=>x.id===id);if(!m){toast("Bahan tak ditemukan.");return;}const si=matStockInfo(m);
  const hist=(m.purchases||[]).slice(0,8).map(p=>`<tr><td>${fmtDate(p.date)}</td><td class="num">${fmtQty(p.qty)} ${esc(m.unit)}</td><td class="num">${p.price?rp(p.price):"-"}</td><td>${esc(p.note||"")}</td><td class="num"><button class="btn sm gray" onclick="delMatBeli('${p.id}','${id}')">×</button></td></tr>`).join("");
  openModal(`<div class="pb-wrap"><div class="pb-mh"><button class="close" onclick="closeModal()">×</button><div class="pb-hk">📦 Stok Masuk</div><h3>${esc(m.name)}</h3>
    <div class="pb-mfig"><div><small>Total dibeli</small><b>${fmtQty(si.bought)} ${esc(m.unit)}</b></div><div><small>Terpakai (produksi)</small><b>${fmtQty(si.used)} ${esc(m.unit)}</b></div><div><small>Stok saat ini</small><b class="${si.low?"low":""}">${si.tracked?fmtQty(si.stock)+" "+esc(m.unit):"belum dilacak"}</b></div></div></div>
  <div class="pb-mb">
  <div class="grid2" style="margin-bottom:10px"><div><label class="f">Jumlah beli (${esc(m.unit)})</label><input id="mbq" inputmode="decimal" placeholder="mis. 5"></div><div><label class="f">Tanggal</label><input id="mbd" type="date" value="${today()}"></div></div>
  <div class="grid2" style="margin-bottom:10px"><div><label class="f">Harga/${esc(m.unit)} <span class="mini">(opsional)</span></label><input id="mbp" inputmode="numeric" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value)"></div><div><label class="f">Catatan</label><input id="mbn" placeholder="mis. beli di pasar"></div></div>
  <button class="btn" style="width:100%" onclick="saveMatBeli('${id}')">+ Catat Stok Masuk</button>
  ${hist?`<h4 class="pb-h4">Riwayat Pembelian</h4><div class="pb-tw"><table><thead><tr><th>Tgl</th><th class="num">Jumlah</th><th class="num">Harga</th><th>Catatan</th><th></th></tr></thead><tbody>${hist}</tbody></table></div>`:""}
  <p class="pb-tip" style="margin-top:12px">Stok bahan opsional — kalau kamu langsung beli-produksi tanpa nyetok, abaikan saja. Produksi tetap jalan.</p></div></div>`);}
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
// grafik garis tren harga satu bahan (menggambar sendiri)
function priceTrend(h){
  if(h.length<2)return '<div class="empty" style="padding:16px">Belum cukup data untuk grafik (minimal 2 titik harga).</div>';
  const W=520,H=150,padL=10,padR=10,padT=22,padB=24,n=h.length;
  const ps=h.map(x=>x.price);const mn=Math.min(...ps),mx=Math.max(...ps),span=mx-mn||1;
  const X=i=>padL+(W-padL-padR)*(i/(n-1));
  const Y=p=>H-padB-(H-padT-padB)*((p-mn)/span);
  const pts=h.map((x,i)=>[X(i),Y(x.price)]);
  const line=pts.map((p,i)=>(i?"L":"M")+p[0].toFixed(1)+" "+p[1].toFixed(1)).join(" ");
  const area=`M${pts[0][0].toFixed(1)} ${H-padB} `+pts.map(p=>`L${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(" ")+` L${pts[n-1][0].toFixed(1)} ${H-padB} Z`;
  const dots=h.map((x,i)=>`<circle class="dpop" style="animation-delay:${.5+i*.06}s" cx="${X(i).toFixed(1)}" cy="${Y(x.price).toFixed(1)}" r="3.5" fill="#fff" stroke="var(--red)" stroke-width="2"><title>${fmtDate(x.date)}: ${rp(x.price)}</title></circle>`).join("");
  return `<div class="pchart"><svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="xMidYMid meet" style="min-width:300px">
    <defs><linearGradient id="ptg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--orange)" stop-opacity=".30"/><stop offset="1" stop-color="var(--orange)" stop-opacity="0"/></linearGradient></defs>
    <path class="ar-fade" d="${area}" fill="url(#ptg)"/>
    <path class="ln" pathLength="1" d="${line}" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
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
  openModal(`<div class="pb-wrap"><div class="pb-mh"><button class="close" onclick="closeModal()">×</button><div class="pb-hk">📈 Tren Harga</div><h3>${esc(m.name)}</h3>
    <div class="pb-mfig"><div><small>Harga sekarang</small><b>${rp(s.cur??m.price)}<em>/${esc(m.unit)}</em></b></div><div><small>Perubahan terakhir</small><b>${s.chg!=null?chgBadge(s.chg,""):"—"}</b></div><div><small>Update harga</small><b>${s.updates}×</b></div></div></div>
    <div class="pb-mb">
    ${priceTrend(s.h)}
    <h4 class="pb-h4">Riwayat Harga</h4>
    ${s.h.length===0?'<div class="empty">Belum ada riwayat.</div>':`<div class="pb-tw"><table><thead><tr><th>Tanggal</th><th class="num">Harga</th><th class="num">vs sblm</th><th>Sumber</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`}
    <div class="pb-mf"><button class="btn gray" onclick="closeModal()">Tutup</button> <button class="btn" onclick="editMat('${id}')">Ubah Harga</button></div></div></div>`);}
async function delPrice(pid,id){if(confirm("Hapus titik harga ini?")){await api("deletePricePoint",{id:pid});await reload();matHist(id);}}
