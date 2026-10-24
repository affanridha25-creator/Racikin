"use strict";
// toko.js — master toko
// ================= TOKO =================
// ---- Master Toko: kartu per toko, ringkasan, cari, urut, tagih ----
let TOKO_SORT="omzet",TOKO_Q="";
const avHue=n=>{let h=0;for(const c of String(n||"?"))h=(h*31+c.charCodeAt(0))%360;return h;};
function tokoData(){
  return S.stores.map(s=>{const ns=S.notas.filter(n=>n.storeId===s.id);
    const omz=ns.reduce((a,n)=>a+notaOmzet(n),0),due=ns.reduce((a,n)=>a+Math.max(0,notaDue(n)),0);
    const open=ns.filter(n=>notaStatus(n)!=="lunas"&&notaDue(n)>0).sort((x,y)=>(x.date||"").localeCompare(y.date||""));
    const last=ns.reduce((m,n)=>(n.date||"")>m?(n.date||""):m,"");
    return {s,omz,due,nNota:ns.length,open,last,oldest:open.length?open[0]:null};});
}
function tokoListHtml(){
  const q=TOKO_Q.trim().toLowerCase();
  let list=tokoData().filter(x=>!q||x.s.name.toLowerCase().includes(q)||(x.s.contact||"").toLowerCase().includes(q)||(x.s.address||"").toLowerCase().includes(q));
  const cmp={omzet:(a,b)=>b.omz-a.omz,piutang:(a,b)=>b.due-a.due,terbaru:(a,b)=>(b.last||"").localeCompare(a.last||""),nama:(a,b)=>a.s.name.localeCompare(b.s.name)}[TOKO_SORT];
  list=list.sort(cmp);
  if(!list.length)return `<div class="empty">${S.stores.length?"Tak ada toko yang cocok.":"Belum ada toko. Tambah toko pertama kamu 🏪"}</div>`;
  const mx=Math.max(1,...list.map(x=>x.omz));
  return `<div class="tokogrid">${list.map((x,k)=>{const s=x.s,h=avHue(s.name),ph=waPhone(s.contact),pct=x.omz>0?Math.min(100,x.due/x.omz*100):0;
    const age=x.oldest?daysSince(x.oldest.date):0;
    return `<div class="tcard" style="--k:${k}" onclick="editStore('${s.id}')">
      <div class="tc-top"><div class="tc-av" style="background:hsl(${h},75%,92%);color:hsl(${h},55%,32%)">${esc((s.name[0]||"?").toUpperCase())}</div>
        <div class="tc-id"><div class="tc-name">${esc(s.name)}</div><div class="tc-sub">${s.contact?(ph?`<a href="https://wa.me/${ph}" target="_blank" rel="noopener" onclick="event.stopPropagation()">📞 ${esc(s.contact)}</a>`:`📞 ${esc(s.contact)}`):`<span class="muted">tanpa kontak</span>`}</div></div>
        <button class="tc-x" title="Hapus" onclick="event.stopPropagation();delStore('${s.id}')">✕</button></div>
      ${s.address?`<div class="tc-addr">📍 ${esc(s.address)}</div>`:""}
      <div class="tc-stats"><div><span>Omzet</span><b>${rp(x.omz)}</b></div><div><span>Piutang</span><b style="color:${x.due>0?"var(--amber)":"var(--green)"}">${rp(x.due)}</b></div><div><span>Nota</span><b>${x.nNota}</b></div></div>
      <div class="tc-bar" title="Omzet dibanding toko lain"><i style="width:${x.omz/mx*100}%"></i></div>
      <div class="tc-foot">${x.due>0?`<button class="btn sm wa" onclick="event.stopPropagation();waTagih('${x.oldest.id}')">💬 Tagih</button><span class="mini" style="color:${age>=30?"var(--red)":age>=14?"var(--amber)":"var(--muted)"}">${x.open.length} nota · tertua ${age} hr</span>`:`<span class="mini" style="color:var(--green);font-weight:700">✓ Tidak ada piutang</span>${x.last?`<span class="mini">terakhir ${fmtDate(x.last)}</span>`:""}`}</div>
    </div>`;}).join("")}</div>`;
}
function setTokoQ(v){TOKO_Q=v;const el=document.getElementById("tokoList");if(el)el.innerHTML=tokoListHtml();}
function setTokoSort(v){TOKO_SORT=v;rToko();}
function rToko(){
  const d=tokoData();
  const totO=d.reduce((a,x)=>a+x.omz,0),totD=d.reduce((a,x)=>a+x.due,0),nD=d.filter(x=>x.due>0).length;
  const mob=isMobile();
  const card=(l,v,cl)=>`<div class="card ${cl||""}"><div class="lbl">${l}</div><div class="val">${v}</div></div>`;
  const chip=(v,l)=>`<button class="btn sm ${TOKO_SORT===v?"":"gray"}" onclick="setTokoSort('${v}')">${l}</button>`;
  const el=document.getElementById("v-toko");
  el.innerHTML=`${mob?"":`<h2 class="title">Master Toko</h2><div class="desc">Daftar toko/warung tempat titip atau jual produk — lengkap dengan omzet &amp; piutang tiap toko.</div>`}
  <div class="cards">${card("Jumlah Toko",cnt(d.length,"num"))}${card("Total Omzet",cnt(totO))}${card("Total Piutang",cnt(totD),"amber")}${card("Toko Berpiutang",cnt(nD,"num"))}</div>
  <div class="flexbtns" style="align-items:center;flex-wrap:wrap;margin-bottom:16px"><button class="btn" onclick="editStore()">+ Toko Baru</button>
    <input type="search" placeholder="Cari toko / kontak / alamat…" value="${esc(TOKO_Q)}" oninput="setTokoQ(this.value)" style="flex:1;min-width:150px">
    ${chip("omzet","Omzet")}${chip("piutang","Piutang")}${chip("terbaru","Terbaru")}${chip("nama","A–Z")}</div>
  <div id="tokoList">${tokoListHtml()}</div>`;
  runCounts(el);
}
function editStore(id){const s=id?S.stores.find(x=>x.id===id):{id:null,name:"",contact:"",address:""};
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${id?"Edit":"Tambah"} Toko</h3>
  <label class="f">Nama toko</label><input id="sn" value="${esc(s.name)}">
  <div style="margin:12px 0"><label class="f">Kontak / No. HP</label><input id="sc" value="${esc(s.contact||"")}"></div>
  <label class="f">Alamat</label><input id="sa" value="${esc(s.address||"")}">
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveStore('${id||""}')">Simpan</button></div>`);}
async function saveStore(id){const o={id:id||null,name:document.getElementById("sn").value.trim(),contact:document.getElementById("sc").value.trim(),address:document.getElementById("sa").value.trim()};
  if(!o.name){toast("Nama toko wajib.");return;}await api("saveStore",{store:o});await reload();closeModal();toast("Toko tersimpan ✓");rToko();}
async function delStore(id){if(S.notas.some(n=>n.storeId===id)){toast("Toko punya nota/distribusi.");return;}if(confirm("Hapus toko ini?")){await api("deleteStore",{id});await reload();rToko();}}

