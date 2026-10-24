"use strict";
// auth.js — login, daftar, lupa & reset password
// ---------- boot ----------
// ---------- login ----------
const llink='style="color:var(--red);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none"';
function pwField(id,ph){return `<div class="pwwrap"><input id="${id}" type="password" placeholder="${ph}"><button type="button" class="pwtog" onclick="togPw(this)" tabindex="-1" aria-label="Tampilkan password">👁️</button></div>`;}
function togPw(b){const i=b.parentNode.querySelector("input"),show=i.type==="password";i.type=show?"text":"password";b.textContent=show?"🙈":"👁️";}
function lBusy(on,txt){const b=document.querySelector("#loginBox .lsubmit");if(!b)return;if(on){b.dataset.t=b.innerHTML;b.innerHTML=txt||"Memproses…";b.classList.add("busy");}else{if(b.dataset.t)b.innerHTML=b.dataset.t;b.classList.remove("busy");}}
function renderLogin(mode){
  const box=document.getElementById("loginBox");
  const prevMode=box.dataset.mode;box.dataset.mode=mode;
  const slide=(prevMode==="login"&&mode==="reg")||(prevMode==="reg"&&mode==="login");
  if(!box.dataset.shown){box.dataset.shown="1";}else box.classList.remove("intro");   // animasi masuk kartu hanya sekali
  let form;
  if(mode==='forgot'){
    form=`<div class="lerr" id="lerr"></div>
      <h3 style="text-align:center;margin-bottom:4px">Lupa Password</h3>
      <p class="lsub" style="margin-bottom:16px">Isi kode usaha &amp; email. Link reset akan dikirim ke email itu.</p>
      <div class="lfield"><label>Kode Usaha</label><input id="fCode" placeholder="mis. anna" autocapitalize="none"></div>
      <div class="lfield"><label>Email</label><input id="fEmail" type="email" placeholder="email terdaftar" autocapitalize="none"></div>
      <button class="btn lsubmit" style="width:100%;margin-top:6px" onclick="doForgot()">Kirim Link Reset</button>
      <div style="text-align:center;margin-top:14px"><a ${llink} onclick="renderLogin('login')">← Kembali masuk</a></div>`;
  } else if(mode==='reset'){
    form=`<div class="lerr" id="lerr"></div>
      <h3 style="text-align:center;margin-bottom:4px">Buat Password Baru</h3>
      <p class="lsub" style="margin-bottom:16px">Masukkan password baru untuk akunmu.</p>
      <div class="lfield"><label>Password Baru (min 8 karakter)</label>${pwField("rpNew","password baru")}</div>
      <div class="lfield"><label>Ulangi Password</label>${pwField("rpNew2","ulangi password")}</div>
      <button class="btn lsubmit" style="width:100%;margin-top:6px" onclick="doResetConfirm()">Simpan Password</button>`;
  } else {
    const tab=`<div class="ltabs${slide?' slide':''}" data-m="${mode==='reg'?'reg':'login'}"><button class="${mode!=='reg'?'on':''}" onclick="renderLogin('login')">Masuk</button><button class="${mode==='reg'?'on':''}" onclick="renderLogin('reg')">Daftar Usaha</button></div><div class="lerr" id="lerr"></div>`;
    form=mode==='reg'
    ?`${tab}<div class="lfield"><label>Nama Usaha</label><input id="rName" placeholder="mis. ANNA Snack & Kitchen"></div>
      <div class="lfield"><label>Kode Usaha <span style="font-weight:400">(huruf kecil/angka, tanpa spasi)</span></label><input id="rCode" placeholder="mis. anna" autocapitalize="none"></div>
      <div class="lfield"><label>Nama Kamu</label><input id="rUser" placeholder="mis. Anna"></div>
      <div class="lfield"><label>Email</label><input id="rEmail" type="email" placeholder="email@usaha.com" autocapitalize="none"></div>
      <div class="lfield"><label>Nomor WhatsApp <span style="font-weight:400">(untuk kami hubungi)</span></label><input id="rWa" type="tel" inputmode="tel" placeholder="mis. 0812xxxxxxxx" autocomplete="tel"></div>
      <div class="lfield"><label>Password (min 8 karakter)</label>${pwField("rPass","buat password")}</div>
      <label class="lremember"><input type="checkbox" id="rRemember" checked> Ingat saya (tetap masuk 30 hari)</label>
      <button class="btn lsubmit" style="width:100%;margin-top:6px" onclick="doRegister()">Daftar &amp; Masuk</button>`
    :`${tab}<div class="lfield"><label>Kode Usaha</label><input id="lCode" placeholder="mis. anna" autocapitalize="none"></div>
      <div class="lfield"><label>Email</label><input id="lEmail" type="email" placeholder="email kamu" autocapitalize="none"></div>
      <div class="lfield"><label>Password</label>${pwField("lPass","password")}</div>
      <label class="lremember"><input type="checkbox" id="lRemember" checked> Ingat saya (tetap masuk 30 hari)</label>
      <button class="btn lsubmit" style="width:100%;margin-top:6px" onclick="doLogin()">Masuk</button>
      <div style="text-align:center;margin-top:14px"><a ${llink} onclick="renderLogin('forgot')">Lupa password?</a></div>`;
  }
  const head=mode==='login'?`<div class="lhead"><h2>Selamat datang kembali 👋</h2><p>Yuk lanjut racik usahamu — cek untung, stok, dan tagihan hari ini.</p></div>`:mode==='reg'?`<div class="lhead"><h2>Mulai racik usahamu 🚀</h2><p>Daftar sekarang dan rapikan produksi, HPP, nota, sampai kasir dalam satu aplikasi.</p></div>`:"";
  box.innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div class="lsub ltag">Tahu untung tiap produk. Tagih piutang tanpa ribet.</div>${head}<div class="lform">${form}</div>${mode==='login'||mode==='reg'?'<div class="lfoot">🔒 Data tiap usaha tersimpan terpisah</div>':""}${supportLine(mode==='reg'?"Daftar":(mode==='forgot'?"Lupa password":(mode==='reset'?"Reset password":"Masuk")))}<div class="lcopy">© ${new Date().getFullYear()} Racikin · Developer: Ahmad Affan Ridha</div>`;
  box.onkeydown=e=>{if(e.key!=="Enter"||e.target.tagName!=="INPUT"||e.target.type==="checkbox")return;const b=box.querySelector(".lsubmit");if(b&&!b.classList.contains("busy")){e.preventDefault();b.click();}};
}
async function doForgot(){
  const code=document.getElementById("fCode").value.trim().toLowerCase(),email=document.getElementById("fEmail").value.trim().toLowerCase();
  if(!code||!email){loginErr("Isi kode usaha & email.");return;}
  try{await api("authResetRequest",{code,email},{silent:true});}catch(e){}
  document.getElementById("loginBox").innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div style="text-align:center;padding:6px 0"><div style="font-size:42px">📧</div><h3 style="margin:8px 0 6px">Cek Email Kamu</h3><p class="lsub">Kalau email terdaftar, link reset password sudah dikirim (berlaku 1 jam). Cek juga folder spam ya.</p><button class="btn ghost" style="width:100%;margin-top:16px" onclick="renderLogin('login')">Kembali ke Masuk</button></div>`;
}
async function doResetConfirm(){
  const p1=document.getElementById("rpNew").value,p2=document.getElementById("rpNew2").value;
  {const pe=pwProblem(p1);if(pe){loginErr(pe);return;}}
  if(p1!==p2){loginErr("Password tidak sama.");return;}
  try{
    await api("authResetConfirm",{token:window._resetToken,password:p1},{silent:true});
    try{history.replaceState({},"",location.pathname);}catch(e){}
    document.getElementById("loginBox").innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div style="text-align:center;padding:6px 0"><div style="font-size:42px">✅</div><h3 style="margin:8px 0 6px">Password Diubah</h3><p class="lsub">Silakan masuk dengan password baru.</p><button class="btn" style="width:100%;margin-top:16px" onclick="renderLogin('login')">Masuk Sekarang</button></div>`;
  }catch(e){loginErr(e.message||"Link reset tidak valid atau kadaluarsa.");}
}
function loginErr(m){const e=document.getElementById("lerr");if(e){e.textContent=m;e.style.display="block";e.style.animation="none";void e.offsetWidth;e.style.animation="";}}
// transisi login: panel bergeser keluar → layar loading sudah menunggu di bawahnya (tanpa jeda) → iris menutup → dashboard
async function loginTransition(){
  const bt=document.querySelector("#loginBox .lsubmit");if(bt){bt.classList.remove("busy");bt.innerHTML="✓ Berhasil";}
  const ls=document.getElementById("loginScreen");
  const w=document.createElement("div");w.className="wsplash";w.id="wsplash";
  w.innerHTML=`<div class="wbowl"><img src="icons/logo-bowl.png" alt=""></div><div class="wh">${greetWord()}, ${esc(BIZ.user||"")}!</div><div class="wsub">Menyiapkan ${esc(BIZ.name||"usahamu")}…</div><div class="wbar"><i></i></div>`;
  document.body.appendChild(w);   // z-index di bawah layar login → baru terlihat saat panel login bergeser pergi
  const t0=performance.now(),wait=ms=>new Promise(r=>setTimeout(r,ms));
  ls.classList.add("leaving");
  setTimeout(()=>w.classList.add("go"),380);
  await wait(760);
  try{await afterAuth();}catch(e){w.remove();ls.classList.remove("leaving");throw e;}
  ls.classList.remove("leaving");
  const lock=document.getElementById("subLock");
  if(lock&&lock.style.display!=="none"){w.remove();return;}   // langganan habis → layar kunci, tanpa sambutan
  await wait(Math.max(0,1950-(performance.now()-t0)));
  w.classList.add("out");
  if(curView==="dashboard")try{renderCur();}catch(e){}
  setTimeout(()=>w.remove(),640);
}
async function doLogin(){const code=document.getElementById("lCode").value.trim().toLowerCase(),email=document.getElementById("lEmail").value.trim().toLowerCase(),password=document.getElementById("lPass").value,remember=document.getElementById("lRemember").checked;if(!code||!email||!password){loginErr("Isi kode usaha, email & password.");return;}lBusy(true,"Masuk…");let ok=false;try{BIZ=await api("authLogin",{code,email,password,remember},{silent:true});ok=true;await loginTransition();}catch(e){if(!ok){lBusy(false);loginErr(e.message||"Gagal masuk.");}else throw e;}}
async function doRegister(){const name=document.getElementById("rName").value.trim(),code=document.getElementById("rCode").value.trim().toLowerCase(),user=document.getElementById("rUser").value.trim(),email=document.getElementById("rEmail").value.trim().toLowerCase(),wa=document.getElementById("rWa").value.trim(),password=document.getElementById("rPass").value;if(!name||!code||!user||!email||!wa||!password){loginErr("Lengkapi semua kolom.");return;}
  if(wa.replace(/\D/g,"").length<9){loginErr("Nomor WhatsApp belum benar (mis. 0812xxxxxxxx).");return;}
  {const pe=pwProblem(password);if(pe){loginErr(pe);return;}}
  lBusy(true,"Mendaftar…");
  try{const r=await api("authRegister",{name,code,user,email,wa,password},{silent:true});
    if(r.trial){   // free trial aktif → langsung masuk app
      BIZ=r;await afterAuth();toast(`Selamat datang! Trial gratis ${r.trialDays||30} hari aktif 🎉`);return;
    }
    document.getElementById("loginBox").innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div style="text-align:center;padding:6px 0"><div style="font-size:42px">🎉</div><h3 style="margin:8px 0 6px">Pendaftaran Berhasil!</h3><p class="lsub">Usaha <b>${esc(r.name||name)}</b> (kode: <b>${esc(r.alias||code)}</b>) terdaftar. Kami siapkan akunmu dulu (biasanya beberapa jam), lalu kamu dapat <b>30 hari GRATIS</b> — kami kabari via WhatsApp untuk mulai. 🙏</p><button class="btn ghost" style="width:100%;margin-top:16px" onclick="renderLogin('login')">Kembali ke Masuk</button></div>`;
  }catch(e){lBusy(false);loginErr(e.message||"Gagal daftar.");}}
async function doLogout(){if(!confirm("Keluar dari "+(BIZ.name||"usaha")+"?"))return;try{await api("authLogout");}catch(e){}location.reload();}
