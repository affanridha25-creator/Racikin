"use strict";
// tenant.js — hak akses per-user & tipe usaha (produksi/fnb)
// ---- hak akses per-user ----
const GRANT_VIEWS=[["dashboard","📊","Dashboard"],["pos","🛒","Kasir (POS)"],["distribusi","🚚","Distribusi"],["pembayaran","💰","Pembayaran"],["keuangan","💹","Keuangan"],["produksi","🏭","Produksi"],["bahan","🧂","Bahan Baku"],["produk","🫙","Produk"],["toko","🏪","Toko"]];
const OWNER_ONLY=["rekap","users","profile","backup"];
const isOwner=()=>(BIZ.role||"owner")==="owner";
// ---- tipe usaha: 'produksi' (default, alur batch→distribusi) vs 'fnb' (resto/warung, fokus kasir) ----
const FNB_HIDDEN=["produksi","distribusi","pembayaran","toko"];   // menu yang tak relevan untuk F&B
function bizType(){try{return (S.profile&&S.profile.biz_type)==="fnb"?"fnb":"produksi";}catch(e){return "produksi";}}
// daftar perms yang relevan sesuai tipe usaha (utk checkbox akses staf)
function grantViews(){return bizType()==="fnb"?GRANT_VIEWS.filter(([v])=>!FNB_HIDDEN.includes(v)):GRANT_VIEWS;}
function canView(v){
  if(bizType()==="fnb"&&FNB_HIDDEN.includes(v))return false;   // berlaku juga utk pemilik
  if(isOwner())return true;if(OWNER_ONLY.includes(v))return false;return (BIZ.perms||[]).includes(v);}
// sesuaikan label & tombol nav dengan tipe usaha (idempoten — aman dipanggil berulang)
function adaptNavForType(){
  const fnb=bizType()==="fnb";
  const pb=document.querySelector('#nav button[data-v="produk"]');
  if(pb){pb.dataset.label=fnb?"Menu":"Produk";pb.innerHTML=`<span class="ic">${fnb?"🍽️":"🫙"}</span> ${fnb?"Menu":"Produk"}`;}
  const sp=[...document.querySelectorAll('.sheet button')].find(b=>(b.getAttribute("onclick")||"").includes("go('produk')"));
  if(sp)sp.innerHTML=`<span>${fnb?"🍽️":"🫙"}</span> ${fnb?"Menu":"Produk"}`;
  // botnav mobile: slot Distribusi/Produksi dipakai ulang jadi Kasir/Menu saat F&B
  const b2=document.getElementById("bnSlot2"),b4=document.getElementById("bnSlot4");
  if(b2){const v=fnb?"pos":"distribusi";b2.dataset.v=v;b2.setAttribute("onclick",`go('${v}')`);b2.innerHTML=`<span class="bic">${fnb?"🛒":"🚚"}</span>${fnb?"Kasir":"Distribusi"}`;}
  if(b4){const v=fnb?"produk":"produksi";b4.dataset.v=v;b4.setAttribute("onclick",`go('${v}')`);b4.innerHTML=`<span class="bic">${fnb?"🍽️":"🏭"}</span>${fnb?"Menu":"Produksi"}`;}
  const tb=document.querySelector('#nav button[data-v="'+curView+'"]');
  const tt=document.getElementById("topTitle");if(tb&&tt)tt.textContent=tb.dataset.label||"";
}
function applyPerms(){
  adaptNavForType();
  document.querySelectorAll('#nav button[data-v]').forEach(b=>{b.style.display=canView(b.dataset.v)?"":"none";});
  document.querySelectorAll('.botnav button[data-v]').forEach(b=>{b.style.display=canView(b.dataset.v)?"":"none";});
  document.querySelectorAll('.sheet button[onclick]').forEach(b=>{const mm=(b.getAttribute('onclick')||"").match(/go\('([^']+)'\)/);if(mm)b.style.display=canView(mm[1])?"":"none";});
  if(curView&&!canView(curView)){const f=[...document.querySelectorAll('#nav button[data-v]')].find(b=>canView(b.dataset.v));if(f)f.click();}
}
function updateBizUI(){const r=document.querySelector(".sidebar .role");if(r)r.textContent=BIZ.name||"";const t=document.querySelector(".topbar .t-right");if(t)t.innerHTML=`👋 Halo, <b>${esc(BIZ.user||"")}</b>`;applyLogos();}
// logo usaha (kalau sudah upload di Profil) menggantikan logo Racikin; kalau belum → default bowl
function applyLogos(){
  const lg=(S.profile&&S.profile.logo)||"";
  const side=document.getElementById("sideLogo");
  if(side)side.src=lg||"icons/logo-bowl.png";
}

