"use strict";
// nav.js — navigasi, modal
// ---------- nav ----------
document.querySelectorAll("#nav button").forEach(btn=>btn.onclick=()=>{
  if(!canView(btn.dataset.v)){toast("Kamu tak punya akses ke menu ini.");return;}
  document.querySelectorAll("#nav button").forEach(b=>b.classList.remove("active"));
  btn.classList.add("active");
  document.querySelectorAll(".view").forEach(v=>v.classList.remove("active"));
  document.getElementById("v-"+btn.dataset.v).classList.add("active");
  const tt=document.getElementById("topTitle");if(tt)tt.textContent=btn.dataset.label||"";
  const bv=btn.dataset.v;
  document.querySelectorAll(".botnav button[data-v]").forEach(b=>b.classList.toggle("active",b.dataset.v===bv));
  const botVs=[...document.querySelectorAll('.botnav button[data-v]')].map(b=>b.dataset.v);
  const more=document.getElementById("btnMore");if(more)more.classList.toggle("active",!botVs.includes(bv));
  curView=bv;try{localStorage.setItem("anna_view",curView);}catch(e){}renderCur();
  navSync(true);
  // transisi masuk antar halaman: anak langsung view muncul berurutan (CSS .view.enter)
  const vw=document.getElementById("v-"+bv);if(vw){vw.classList.remove("enter");void vw.offsetWidth;vw.classList.add("enter");clearTimeout(vw._en);vw._en=setTimeout(()=>vw.classList.remove("enter"),800);}
});
function go(v){const b=document.querySelector('#nav button[data-v="'+v+'"]');if(b)b.click();closeMore();window.scrollTo(0,0);}
function openMore(){document.getElementById("sheetBg").classList.add("open");document.body.classList.add("sheet-open");}
function closeMore(e){if(e&&e.target!==e.currentTarget)return;document.getElementById("sheetBg").classList.remove("open");document.body.classList.remove("sheet-open");}
// ---- v2: indikator aktif sidebar (pil putih yang bergeser) & botnav (pil krem), sapaan topbar, grup menu ----
function navSync(anim){
  try{
    const nav=document.getElementById("nav"),ind=nav&&nav.querySelector(".nav-ind"),act=nav&&nav.querySelector("button.active");
    // grup menu disembunyikan bila semua itemnya tak boleh diakses (mis. staf / F&B)
    if(nav){let g=null,any=false;const done=()=>{if(g){const d=any?"":"none";if(g.style.display!==d)g.style.display=d;}};
      [...nav.children].forEach(el=>{if(el.classList.contains("nav-grp")){done();g=el;any=false;}else if(el.tagName==="BUTTON"&&el.style.display!=="none")any=true;});done();}
    if(ind&&act&&act.offsetHeight){
      if(!ind.dataset.on){ind.style.transition="none";}
      ind.style.transform="translateY("+act.offsetTop+"px)";ind.style.height=act.offsetHeight+"px";ind.style.opacity=1;
      if(!ind.dataset.on){void ind.offsetWidth;ind.style.transition="";ind.dataset.on="1";}
    }else if(ind)ind.style.opacity=0;
    const bn=document.getElementById("botnav"),bi=bn&&bn.querySelector(".bn-ind"),ba=bn&&bn.querySelector("button.active:not(.fab)");
    if(bi&&ba&&ba.offsetWidth){
      if(!bi.dataset.on)bi.style.transition="none";
      bi.style.transform="translateX("+ba.offsetLeft+"px)";bi.style.width=ba.offsetWidth+"px";bi.style.opacity=1;
      if(!bi.dataset.on){void bi.offsetWidth;bi.style.transition="";bi.dataset.on="1";}
    }else if(bi)bi.style.opacity=0;
    const hi=document.getElementById("topHi");if(hi&&typeof greetWord==="function")hi.textContent=greetWord()+(BIZ&&BIZ.user?", "+BIZ.user:"")+" 👋";
  }catch(e){}
}
try{
  const nv=document.getElementById("nav");
  // abaikan perubahan yang kita buat sendiri (indikator & judul grup) supaya tak berputar
  const mine=r=>/\b(nav-ind|bn-ind|nav-grp)\b/.test(String(r.target.className||""));
  const obs=recs=>{if(recs.some(r=>!mine(r)))navSync();};
  if(nv)new MutationObserver(obs).observe(nv,{attributes:true,subtree:true,attributeFilter:["style","class"]});
  const bnv=document.getElementById("botnav");
  if(bnv)new MutationObserver(obs).observe(bnv,{attributes:true,subtree:true,attributeFilter:["style","class"]});
  window.addEventListener("resize",()=>navSync());window.addEventListener("load",()=>navSync());
  if(document.fonts&&document.fonts.ready)document.fonts.ready.then(()=>navSync());
}catch(e){}
function isMobile(){return window.matchMedia("(max-width:820px)").matches;}
try{window.matchMedia("(max-width:820px)").addEventListener("change",()=>{try{renderCur();}catch(e){}});}catch(e){}
function fabAction(){
  if(bizType()==="fnb"&&curView==="dashboard"){go("pos");return;}   // F&B: aksi utama = ke Kasir
  const map={dashboard:"editNota",distribusi:"editNota",pembayaran:"editNota",keuangan:"editCash",produksi:"editBatch",bahan:"editMat",produk:"editProd",toko:"editStore"};const fn=map[curView];if(fn&&typeof window[fn]==="function")window[fn]();else toast("Tak ada aksi tambah di halaman ini.");}
const MON=["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
const monthLabel=k=>MON[(+String(k).slice(5,7))-1]||k;
function donut(segs,cl){
  const tot=segs.reduce((a,s)=>a+s.v,0);const R=54,C=2*Math.PI*R,gap=2.5;let off=0;
  const arcs=tot<=0?"":segs.filter(s=>s.v>0).map(s=>{const len=s.v/tot*C;const d=Math.max(len-gap,.5);const el=`<circle cx="70" cy="70" r="${R}" fill="none" stroke="${s.color}" stroke-width="15" stroke-dasharray="${d} ${C-d}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"/>`;off+=len;return el;}).join("");
  return `<svg viewBox="0 0 140 140" width="128" height="128" style="flex-shrink:0"><circle cx="70" cy="70" r="${R}" fill="none" stroke="#f1eeed" stroke-width="15"/>${arcs}${cl?`<text x="70" y="74" text-anchor="middle" font-size="12" font-weight="600" fill="#8a8f99">${cl}</text>`:""}</svg>`;
}
function crow(o){return `<div class="crow"${o.onclick?` onclick="${o.onclick}"`:""}>`
  +`<div class="ci">${o.icon}</div>`
  +`<div class="cmain"><div class="ctitle">${o.title}</div>${o.sub?`<div class="csub">${o.sub}</div>`:""}${o.badges||""}</div>`
  +`<div class="cright">${o.amt!=null&&o.amt!==""?`<div class="camt"${o.amtColor?` style="color:${o.amtColor}"`:""}>${o.amt}</div>`:""}${o.status?`<span class="pill ${o.status}">${o.status.toUpperCase()}</span>`:""}${o.acts||""}</div>`
  +`${o.del?`<button class="crow-del" onclick="event.stopPropagation();${o.del}">✕</button>`:""}`
  +`</div>`;}
function renderCur(){({dashboard:rDash,pos:rPOS,produksi:rProduksi,distribusi:rDistribusi,pembayaran:rPembayaran,keuangan:rKeuangan,bahan:rBahan,produk:rProduk,toko:rToko,rekap:rRekap,users:rUsers,profile:rProfile,backup:rBackup}[curView])();}

// ---------- modal ----------
const mBg=document.getElementById("modalBg"),mEl=document.getElementById("modal");
// v2: modal masuk/keluar beranimasi (kelas .closing menahan elemen sebentar sebelum dibersihkan)
let _mClose=null;
function openModal(h){clearTimeout(_mClose);_mClose=null;mBg.classList.remove("closing");mEl.innerHTML=h;mBg.classList.add("open");}
function closeModal(){
  if(!mBg.classList.contains("open"))return;
  if(window.matchMedia&&window.matchMedia("(prefers-reduced-motion:reduce)").matches){mBg.classList.remove("open");mEl.innerHTML="";return;}
  mBg.classList.add("closing");clearTimeout(_mClose);
  _mClose=setTimeout(()=>{mBg.classList.remove("open","closing");mEl.innerHTML="";_mClose=null;},200);
}
mBg.onclick=e=>{if(e.target===mBg)closeModal();};

