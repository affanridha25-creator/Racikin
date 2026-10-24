"use strict";
// boot.js — boot aplikasi + pendaftaran service worker (HARUS file terakhir)
async function bootApp(){
  try{await reload();
    const v=(()=>{try{return localStorage.getItem("anna_view");}catch(e){return null;}})()||"dashboard";
    const btn=document.querySelector(`#nav button[data-v="${v}"]`);
    if(btn&&canView(v))btn.click();
    else{const f=[...document.querySelectorAll('#nav button[data-v]')].find(b=>canView(b.dataset.v));(f||document.querySelector("#nav button")).click();}
  }catch(e){document.getElementById("loginScreen").style.display="none";document.getElementById("v-dashboard").innerHTML='<div class="panel"><h3>Gagal terhubung ke database</h3><p class="mini">Pastikan Apache &amp; MySQL di XAMPP sudah <b>Start</b>, lalu refresh.</p></div>';}
}
(async()=>{
  // link reset password dari email: ?reset=selector.token
  const rt=new URLSearchParams(location.search).get("reset");
  if(rt){window._resetToken=rt;document.getElementById("loginScreen").style.display="flex";renderLogin("reset");return;}
  let st;try{st=await api("authStatus");}catch(e){st=await offlineAuthFallback();}
  if(st&&st.loggedIn){BIZ=st;await afterAuth();if(navigator.onLine)syncQueue();}   // sinkron antrean sisa dari sesi offline sebelumnya
  else{renderLogin("login");}
})();
// ---- PWA: daftarkan service worker ----
if("serviceWorker" in navigator){window.addEventListener("load",()=>navigator.serviceWorker.register("sw.js").catch(()=>{}));}
