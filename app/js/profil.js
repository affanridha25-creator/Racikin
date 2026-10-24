"use strict";
// profil.js — profil usaha
// ================= PROFIL USAHA =================
let _prof={};
function rProfile(){
  _prof=JSON.parse(JSON.stringify(S.profile||{}));
  const logo=_prof.logo||"",fnb=_prof.biz_type==="fnb";
  // kelengkapan profil
  const chk=[["Logo",!!logo],["Alamat",!!(_prof.address||"").trim()],["Telepon / WA",!!((_prof.phone||_prof.whatsapp)||"").trim()],["Sosmed",!!((_prof.instagram||_prof.facebook||_prof.tiktok)||"").trim()],["Pesan struk",!!(_prof.footer||"").trim()],["QRIS",!!(_prof.qris||"").trim()]];
  const done=chk.filter(c=>c[1]).length,pct=Math.round(done/chk.length*100);
  const sw=(id,on)=>`<span class="sw"><input type="checkbox" id="${id}" ${on?"checked":""}></span>`;
  const row=(ic,t,desc,ctl)=>`<div class="pf-row"><div class="pf-ri">${ic}</div><div class="pf-rt"><b>${t}</b><span>${desc}</span></div>${ctl}</div>`;
  const rate=(id,v)=>`<div class="pf-rate"><input id="${id}" inputmode="decimal" value="${v&&+v?(+v):""}" placeholder="0"><b>%</b></div>`;
  const qv=_prof.qris?(qrisValid(_prof.qris)?'<span style="color:var(--green)">✓ QRIS valid — nanti muncul di Kasir saat pilih QRIS.</span>':'<span style="color:var(--red)">⚠ Kode QRIS belum valid.</span>'):'Scan sticker QRIS-mu pakai app pembaca QR, salin teksnya ke sini. Kosongkan kalau belum ada.';
  const el=document.getElementById("v-profile");
  el.innerHTML=`<div class="pf-head"><h2 class="title">Profil Usaha</h2><div class="desc">Logo &amp; kontak muncul di aplikasi, <b>struk PDF</b>, &amp; kop laporan cetak. <span class="mini">(Struk thermal 58mm hanya teks — tak memuat gambar logo.)</span></div></div>
  <div class="pf-grid">
   <div class="pf-col">
    <div class="pfc pf-hero" style="--k:0">
      <div class="pf-hbg" aria-hidden="true"><i></i><i></i></div>
      <div class="pf-hmain"><div id="logoPrev" class="logobox">${logo?`<img src="${logo}" alt="logo">`:`<span>🍲</span>`}</div>
        <div class="pf-hid"><div class="pf-name">${esc(BIZ.name||"Usaha")}</div><div class="pf-badge">${fnb?"🍽️ F&amp;B / Warung":"🏭 Produksi &amp; Distribusi"}</div>
          <div class="flexbtns" style="margin:10px 0 0"><button class="btn sm" style="background:#fff;color:var(--red)" onclick="document.getElementById('logoInp').click()">⬆ Unggah Logo</button><button class="btn sm" style="background:rgba(255,255,255,.2)${logo?"":";display:none"}" id="logoDel" onclick="removeLogo()">Hapus</button></div>
          <input type="file" id="logoInp" accept="image/*" style="display:none" onchange="pickLogo(this)"></div></div>
      <div class="pf-meter"><div class="pf-mt"><span>Kelengkapan profil</span><b>${pct}%</b></div><div class="pf-mbar"><i style="width:${pct}%"></i></div>
        <div class="pf-chips">${chk.map(c=>`<span class="${c[1]?"ok":""}">${c[1]?"✓":"○"} ${c[0]}</span>`).join("")}</div></div>
    </div>
    <div class="pfc" style="--k:1"><h3>📇 Info &amp; Kontak</h3>
      <label class="f">Alamat</label><textarea id="pfAddr" rows="2" style="margin-bottom:14px" placeholder="mis. Jl. Melati No. 10, Bandung">${esc(_prof.address||"")}</textarea>
      <div class="grid2" style="margin-bottom:14px"><div><label class="f">No. Telepon</label><input id="pfPhone" value="${esc(_prof.phone||"")}" placeholder="0812xxxx"></div><div><label class="f">WhatsApp</label><input id="pfWa" value="${esc(_prof.whatsapp||"")}" placeholder="0812xxxx"></div></div>
      <div class="grid2" style="margin-bottom:14px"><div><label class="f">Instagram</label><input id="pfIg" value="${esc(_prof.instagram||"")}" placeholder="@usaha"></div><div><label class="f">Facebook</label><input id="pfFb" value="${esc(_prof.facebook||"")}" placeholder="nama halaman"></div></div>
      <label class="f">TikTok / sosmed lain</label><input id="pfTt" value="${esc(_prof.tiktok||"")}" placeholder="@usaha">
    </div>
    <div class="pfc" style="--k:2"><h3>🧾 Struk &amp; QRIS</h3>
      <label class="f">Pesan bawah struk <span class="mini">(kaki struk kasir — kosongkan = "Terima kasih :)")</span></label><input id="pfFooter" value="${esc(_prof.footer||"")}" placeholder="mis. Terima kasih & sampai jumpa lagi!" maxlength="120" style="margin-bottom:16px">
      <label class="f">Kode QRIS <span class="mini">(untuk pembayaran di Kasir)</span></label>
      <textarea id="pfQris" rows="3" placeholder="Tempel string QRIS dari hasil scan sticker usahamu (mulai 0002...)" oninput="qrisCheck(this.value)" style="font-size:12px;font-family:monospace">${esc(_prof.qris||"")}</textarea>
      <div id="qrisMsg" class="mini" style="margin-top:6px">${qv}</div>
    </div>
   </div>
   <div class="pf-col">
    <div class="pfc" style="--k:1"><h3>🏷️ Jenis Usaha</h3>
      <div class="pf-types" id="bizTypeSeg">
        <button class="${!fnb?"on":""}" onclick="setBizType('produksi')"><i>🏭</i><b>Produksi &amp; Distribusi</b><span>Batch &amp; HPP → titip ke toko → piutang</span></button>
        <button class="${fnb?"on":""}" onclick="setBizType('fnb')"><i>🍽️</i><b>F&amp;B / Warung</b><span>Fokus Kasir &amp; menu</span></button>
      </div>
      <div class="mini" id="bizTypeHint" style="margin-top:10px">${fnb?"Fokus Kasir — menu Produksi, Distribusi, Pembayaran &amp; Toko disembunyikan.":"Alur lengkap: produksi batch (HPP) → distribusi/titip ke toko → piutang."}</div>
    </div>
    <div class="pfc" style="--k:2"><h3>💳 Service Charge &amp; Pajak</h3>
      ${row("🛎️","Service charge","dihitung dari subtotal setelah diskon",sw("pfSvcOn",+_prof.svc_enabled)+rate("pfSvcRate",_prof.svc_rate))}
      ${row("🏛️","Pajak / PPN","dihitung di atas subtotal − diskon + service",sw("pfTaxOn",+_prof.tax_enabled)+rate("pfTaxRate",_prof.tax_rate))}
      <div class="mini" style="margin-top:10px">Muncul terpisah di struk &amp; "Pajak Terkumpul" di Laba-Rugi (bukan laba).</div>
    </div>
    <div class="pfc" style="--k:3"><h3>📦 Stok</h3>
      ${row("🚦","Boleh jual walau stok habis","stok boleh jadi minus (cocok untuk pre-order)",sw("pfOversell",+_prof.oversell))}
      <div class="mini" style="margin-top:10px">Kalau nonaktif, penjualan melebihi stok ditolak.</div>
    </div>
    ${NativePrinter.isNative()?`<div class="pfc" style="--k:4"><h3>🖨 Printer Struk</h3>
      <p class="mini" style="margin-bottom:12px">Cetak langsung ke printer thermal Bluetooth (58mm), lengkap dengan logo — tanpa app RawBT. ${(()=>{const c=NativePrinter.saved();return c?`Printer aktif: <b>${esc(c.name||c.address)}</b>.`:"Belum ada printer dipilih.";})()}</p>
      <button class="btn ghost" onclick="printerSettingsModal()">Atur Printer</button></div>`:""}
    <div class="pfc" style="--k:5"><h3>🔒 Keamanan</h3>
      <label class="f">Password Lama</label>${pwField("cpOld","password sekarang")}
      <label class="f" style="margin-top:12px">Password Baru <span class="mini">(min 8 karakter)</span></label>${pwField("cpNew","password baru")}
      <div style="text-align:right;margin-top:14px"><button class="btn ghost" onclick="doChangePassword()">Ubah Password</button></div>
    </div>
   </div>
  </div>
  <div class="pf-save"><div class="pf-savein"><span class="pf-dirty" id="pfDirty">● Belum disimpan</span><button class="btn" onclick="saveProfile()">💾 Simpan Profil</button></div></div>`;
  el.oninput=el.onchange=()=>{const d=document.getElementById("pfDirty");if(d)d.classList.add("on");};
}
// pilih jenis usaha di Profil (tanpa rerender penuh — isian lain di form tak hilang)
function setBizType(t){_prof.biz_type=t==="fnb"?"fnb":"produksi";
  document.querySelectorAll("#bizTypeSeg button").forEach((b,i)=>b.classList.toggle("on",(i===1)===(t==="fnb")));
  const h=document.getElementById("bizTypeHint");
  if(h)h.innerHTML=t==="fnb"?"Fokus Kasir — menu Produksi, Distribusi, Pembayaran &amp; Toko disembunyikan.":"Alur lengkap: produksi batch (HPP) → distribusi/titip ke toko → piutang.";}
async function doChangePassword(){
  const o=document.getElementById("cpOld").value,n=document.getElementById("cpNew").value;
  if(!o){toast("Isi password lama dulu.");return;}
  {const pe=pwProblem(n);if(pe){toast(pe);return;}}
  try{await api("authChangePassword",{oldPassword:o,newPassword:n});toast("Password diubah ✓");document.getElementById("cpOld").value="";document.getElementById("cpNew").value="";}catch(e){}
}
function qrisCheck(v){v=(v||"").replace(/[\r\n\t]+/g,"").trim();const el=document.getElementById("qrisMsg");if(!el)return;
  if(!v){el.innerHTML="Kosongkan kalau belum punya QRIS.";return;}
  el.innerHTML=qrisValid(v)?'<span style="color:var(--green)">✓ QRIS valid — siap dipakai di Kasir.</span>':'<span style="color:var(--red)">⚠ Kode belum valid (cek lagi salin-tempelnya).</span>';}
function pickLogo(inp){const f=inp.files[0];if(!f)return;
  if(!/^image\//.test(f.type)){toast("File harus berupa gambar.");return;}
  if(f.size>2*1024*1024){toast("Ukuran maksimal 2MB.");return;}
  const rd=new FileReader();
  rd.onload=e=>{const img=new Image();img.onload=()=>{
    const max=512;let w=img.width,h=img.height;
    if(w>h&&w>max){h=Math.round(h*max/w);w=max;}else if(h>=w&&h>max){w=Math.round(w*max/h);h=max;}
    const cv=document.createElement("canvas");cv.width=w;cv.height=h;cv.getContext("2d").drawImage(img,0,0,w,h);
    const url=cv.toDataURL("image/png");_prof.logo=url;
    document.getElementById("logoPrev").innerHTML=`<img src="${url}" alt="logo">`;
    const db=document.getElementById("logoDel");if(db)db.style.display="";
  };img.onerror=()=>toast("Gambar tak bisa dibaca.");img.src=e.target.result;};
  rd.readAsDataURL(f);}
function removeLogo(){_prof.logo="";document.getElementById("logoPrev").innerHTML=`<span>🍲</span>`;const db=document.getElementById("logoDel");if(db)db.style.display="none";}
async function saveProfile(){
  const g=id=>document.getElementById(id).value.trim();
  const qris=document.getElementById("pfQris").value.replace(/[\r\n\t]+/g,"").trim();
  if(qris&&!qrisValid(qris)){toast("Kode QRIS belum valid — cek lagi.");return;}
  const nrate=id=>Math.max(0,Math.min(100,+document.getElementById(id).value||0));
  const prof={address:g("pfAddr"),phone:g("pfPhone"),whatsapp:g("pfWa"),instagram:g("pfIg"),facebook:g("pfFb"),tiktok:g("pfTt"),footer:g("pfFooter"),
    svc_enabled:document.getElementById("pfSvcOn").checked?1:0,svc_rate:nrate("pfSvcRate"),
    tax_enabled:document.getElementById("pfTaxOn").checked?1:0,tax_rate:nrate("pfTaxRate"),
    oversell:document.getElementById("pfOversell").checked?1:0,
    biz_type:_prof.biz_type==="fnb"?"fnb":"produksi",
    logo:_prof.logo||"",qris};
  await api("saveProfile",{profile:prof});await reload();toast("Profil tersimpan ✓");rProfile();
}
// kop laporan untuk cetak (logo + nama usaha + kontak/sosmed)
function printHead(title,sub){
  const p=S.profile||{};
  const contacts=[p.address,p.phone?"Telp "+p.phone:"",p.whatsapp?"WA "+p.whatsapp:"",p.instagram?"IG "+p.instagram:"",p.facebook?"FB "+p.facebook:"",p.tiktok?"TikTok "+p.tiktok:""].filter(Boolean).map(esc).join(" · ");
  return `<div class="phead">${p.logo?`<img class="plogo" src="${p.logo}" alt="logo">`:""}<div><div class="pbiz">${esc(BIZ.name||"Usaha")}</div>${contacts?`<div class="pcontact">${contacts}</div>`:""}</div></div>
  <h1>${title}</h1>${sub?`<div class="psub">${sub}</div>`:""}`;
}

