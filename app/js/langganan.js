"use strict";
// langganan.js — langganan & perpanjangan
// ================= LANGGANAN (perpanjangan + bukti transfer) =================
let SUB={};   // status langganan terkini {paidUntil,daysLeft,expired}
let _proofData="";
// setelah login/authStatus: kalau langganan habis → layar kunci; kalau normal → app + banner
async function afterAuth(){
  document.getElementById("loginScreen").style.display="none";
  SUB=(BIZ&&BIZ.sub)||{};
  if(SUB.expired){showSubLock();return;}
  document.getElementById("subLock").style.display="none";
  await bootApp();
  subBanner();
  initPush();   // daftar notifikasi (hanya di aplikasi Android)
}

// layar kunci langganan: gaya login terbelah (hero gradasi + kartu)
function showSubLock(){
  document.getElementById("loginScreen").style.display="none";
  const el=document.getElementById("subLock");el.style.display="flex";
  document.getElementById("subLockBox").innerHTML=`<div class="sl-badge"><span>🔒</span></div>
    <div class="lhead sl-head"><h2>Langganan Berakhir</h2><p class="lsub">Perpanjang untuk kembali mengakses <b>${esc(BIZ.name||"usaha")}</b>. Semua datamu aman tersimpan.</p></div>
    <div id="renewArea" class="sl-area">Memuat…</div>
    <div class="sl-out"><a class="llink" onclick="doLogout()">Keluar dari akun</a></div>`;
  renderRenewArea("renewArea");
}
// banner peringatan H-7 di dalam app
function subBanner(){
  const el=document.getElementById("subBanner");if(!el)return;
  const d=SUB&&SUB.daysLeft;
  if(SUB&&!SUB.expired&&d!=null&&d<=14){
    el.style.display="block";
    el.innerHTML=`<div class="subbn"><span class="sb-ic">⏰</span><span class="sb-tx">Langganan habis ${d<=0?"hari ini":"dalam <b>"+d+" hari</b>"} <small>(${esc(SUB.paidUntil||"")})</small></span><button onclick="openRenew()">Perpanjang</button></div>`;
  }else{el.style.display="none";el.innerHTML="";}
}
// modal perpanjang saat masih aktif (dari banner)
function openRenew(){
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Perpanjang Langganan</h3><div id="renewArea" style="margin-top:8px">Memuat…</div>`);
  renderRenewArea("renewArea");
}
// area renewal dipakai bersama (layar kunci & modal)
async function renderRenewArea(targetId){
  const el=document.getElementById(targetId);if(!el)return;
  let info;try{info=await api("subInfo");}catch(e){el.innerHTML='<p class="mini">Gagal memuat info langganan.</p>';return;}
  window._subInfo=info;
  // sudah aktif lagi (mis. baru disetujui admin) & masih di layar kunci → langsung masuk app
  if(info.sub&&!info.sub.expired&&document.getElementById("subLock").style.display!=="none"){
    SUB=info.sub;BIZ.sub=info.sub;document.getElementById("subLock").style.display="none";
    toast("Langganan aktif kembali ✓");await bootApp();subBanner();return;
  }
  if(info.request&&info.request.status==="pending"){
    el.innerHTML=`<div class="renbox rn-wait"><div class="rn-ico">⏳</div>
      <p>Bukti transfer <b>Rp${grp(info.request.amount)}</b> sedang <b>diverifikasi admin</b>. Biasanya 1×24 jam — nanti aktif otomatis setelah disetujui. 🙏</p>
      <button class="btn ghost" style="width:100%;margin-top:8px" onclick="renderRenewArea('${targetId}')">↻ Cek status</button></div>`;
    return;
  }
  const plans=[["1bln","1 Bulan",1],["3bln","3 Bulan",3],["1thn","1 Tahun",12]];
  const base=(info.prices||{})["1bln"]||0;
  const cards=plans.map(([k,lbl,mo])=>{const pr=(info.prices||{})[k]||0;const dis=pr<=0;
    const save=(!dis&&base>0&&mo>1)?Math.round((1-pr/(base*mo))*100):0;   // hemat dibanding bayar per bulan
    return `<button class="planc${dis?' dis':''}${k==="1thn"&&!dis?' best':''}" ${dis?"disabled":""} onclick="renewPick('${k}','${targetId}')">${save>0?`<i class="pl-save">Hemat ${save}%</i>`:""}<div class="pl">${lbl}</div><div class="pp">${dis?"—":"Rp"+grp(pr)}</div></button>`;}).join("");
  el.innerHTML=`<div class="renbox"><p class="mini" style="margin-bottom:10px">Pilih paket perpanjangan:</p><div class="plans">${cards}</div>
    ${(info.prices&&(info.prices["1bln"]||info.prices["3bln"]||info.prices["1thn"]))?"":'<p class="mini" style="color:var(--red);margin-top:8px">Harga belum diatur admin. Hubungi admin dulu ya.</p>'}</div>`;
}
async function renewPick(plan,targetId){
  let r;try{r=await api("startRenewal",{plan});}catch(e){return;}
  _proofData="";
  const el=document.getElementById(targetId);
  el.innerHTML=`<div class="renbox rn-pay">
    <p class="mini">Transfer <b>TEPAT</b> sejumlah ini${r.uniq?` (sudah termasuk kode unik +${r.uniq} biar gampang dicek admin)`:""}:</p>
    <div class="amtbox">Rp${grp(r.amount)}</div>
    ${r.bank?`<div class="bankbox">${esc(r.bank).replace(/\n/g,"<br>")}</div>`:'<p class="mini" style="color:var(--amber)">Info rekening belum diatur admin — hubungi admin untuk nomor rekening.</p>'}
    <label class="f" style="margin-top:12px">Foto bukti transfer</label>
    <div id="proofPrev"></div>
    <button class="btn ghost" style="width:100%" onclick="document.getElementById('proofInp').click()">📷 Pilih Foto Bukti</button>
    <input type="file" id="proofInp" accept="image/*" style="display:none" onchange="renewPickFile(this)">
    <label class="f" style="margin-top:10px">Catatan <span class="mini">(opsional)</span></label>
    <input id="proofNote" placeholder="mis. a.n. pengirim transfer">
    <button class="btn" style="width:100%;margin-top:12px" onclick="renewSubmit('${targetId}')">Kirim Bukti Transfer</button>
    <button class="btn ghost" style="width:100%;margin-top:6px" onclick="renderRenewArea('${targetId}')">← Ganti paket</button>
  </div>`;
}
function renewPickFile(inp){const f=inp.files[0];if(!f)return;
  if(!/^image\//.test(f.type)){toast("File harus gambar.");return;}
  if(f.size>5*1024*1024){toast("Ukuran maksimal 5MB.");return;}
  const rd=new FileReader();
  rd.onload=e=>{const img=new Image();img.onload=()=>{
    const max=1000;let w=img.width,h=img.height;
    if(w>h&&w>max){h=Math.round(h*max/w);w=max;}else if(h>=w&&h>max){w=Math.round(w*max/h);h=max;}
    const cv=document.createElement("canvas");cv.width=w;cv.height=h;cv.getContext("2d").drawImage(img,0,0,w,h);
    _proofData=cv.toDataURL("image/jpeg",0.85);
    const pv=document.getElementById("proofPrev");if(pv)pv.innerHTML=`<img src="${_proofData}" style="max-width:100%;max-height:200px;border-radius:10px;margin-bottom:8px;border:1px solid var(--line)">`;
  };img.onerror=()=>toast("Gambar tak terbaca.");img.src=e.target.result;};
  rd.readAsDataURL(f);}
async function renewSubmit(targetId){
  if(!_proofData){toast("Pilih foto bukti transfer dulu.");return;}
  const note=(document.getElementById("proofNote")||{}).value||"";
  try{await api("submitProof",{proof:_proofData,note});}catch(e){return;}
  _proofData="";
  toast("Bukti terkirim ✓ Menunggu verifikasi admin.");
  renderRenewArea(targetId);
}

