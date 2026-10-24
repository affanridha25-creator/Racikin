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
