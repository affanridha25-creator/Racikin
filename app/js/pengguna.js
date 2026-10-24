"use strict";
// pengguna.js — pengguna (multi-user) v2: hero, kartu beravatar, matriks izin sakelar
// ================= PENGGUNA (multi-user, khusus pemilik) =================
async function rUsers(){
  const el=document.getElementById("v-users");
  el.innerHTML=`<div class="rk-hero rk-hero-s"><div class="rk-hbg" aria-hidden="true"><i></i><i></i></div><div class="rk-ht">Pengguna</div><div class="rk-hs">Memuat…</div></div><div class="empty">Memuat…</div>`;
  let users=[];
  try{const r=await api("usersList");users=r.users||[];}catch(e){el.innerHTML=`<div class="empty">Gagal memuat pengguna.</div>`;return;}
  _userNames={};users.forEach(u=>_userNames[u.email]=u.name);
  window._usersCache=users;
  const allV=grantViews();
  const permOf=p=>allV.find(x=>x[0]===p)||GRANT_VIEWS.find(x=>x[0]===p);
  const staff=users.filter(u=>u.role!=="owner"),noAkses=staff.filter(u=>!(u.perms&&u.perms.length)).length;
  const hero=`<div class="rk-hero rk-hero-s">
    <div class="rk-hbg" aria-hidden="true"><i></i><i></i></div>
    <div class="rk-htop"><div><div class="rk-ht">Pengguna</div><div class="rk-hs">Pemilik akses penuh · staf hanya menu yang diizinkan. Login pakai <b>kode usaha sama</b> + email &amp; password sendiri.</div></div></div>
    <div class="rk-hrow"><div class="rk-hstats"><div><b>${cnt(users.length,"num")}</b><span>akun</span></div><div><b>${cnt(staff.length,"num")}</b><span>staf / kasir</span></div>${noAkses?`<div class="warn"><b>${cnt(noAkses,"num")}</b><span>belum ada akses</span></div>`:""}</div>
      <button class="rk-add" onclick="editUser()">＋ Tambah Pengguna</button></div>
  </div>`;
  const cards=users.map((u,k)=>{
    const isOwn=u.role==="owner",perms=u.perms||[];
    const chips=isOwn?'<span class="rk-chip all">✨ Semua menu</span>':perms.length?perms.map(p=>{const g=permOf(p);return `<span class="rk-chip">${g?g[1]:"•"} ${esc(g?g[2]:p)}</span>`;}).join(""):'<span class="rk-chip none">belum ada akses</span>';
    const pc=isOwn?100:(allV.length?Math.round(perms.length/allV.length*100):0);
    return `<div class="rk-ucard ${isOwn?"own":""}" style="--k:${k}"${isOwn?"":` onclick="editUserE('${esc(u.email)}')"`}>
      <div class="rk-utop">${rkAv(u.name||u.email)}<div class="rk-cid"><div class="rk-cn">${esc(u.name||u.email)}</div><div class="rk-cs">${esc(u.email)}</div></div>
        <span class="rk-role ${isOwn?"own":""}">${isOwn?"👑 Pemilik":"🧑‍💼 Staf"}</span></div>
      <div class="rk-chips">${chips}</div>
      <div class="rk-ufoot"><div class="rk-bar" title="Akses menu ${pc}%"><i style="width:${pc}%"></i></div><span>${isOwn?"akses penuh":perms.length+"/"+allV.length+" menu"}</span>
        ${isOwn?"":`<button class="rk-ubtn" onclick="event.stopPropagation();editUserE('${esc(u.email)}')">Edit</button><button class="rk-ubtn del" title="Hapus" onclick="event.stopPropagation();delUser('${esc(u.email)}')">✕</button>`}</div>
    </div>`;}).join("");
  el.innerHTML=`${hero}<div class="rk-ugrid">${cards}</div>`;
  dashAnimate(el);
}
function editUserE(email){const u=(window._usersCache||[]).find(x=>x.email===email);if(u)editUser(u);}
// hitung izin terpilih + sinkron kartu (dipanggil dari sakelar)
function rkPermSync(){
  const all=[...document.querySelectorAll(".permgrid input[type=checkbox]")];
  all.forEach(c=>{const r=c.closest(".rk-perm");if(r)r.classList.toggle("on",c.checked);});
  const n=all.filter(c=>c.checked).length,t=document.getElementById("rkPermN");
  if(t)t.textContent=n+" dari "+all.length+" menu";
  const b=document.getElementById("rkPermBar");if(b)b.style.width=(all.length?n/all.length*100:0)+"%";
}
function rkPermAll(on){document.querySelectorAll(".permgrid input[type=checkbox]").forEach(c=>c.checked=on);rkPermSync();}
function editUser(u){
  const isEdit=!!u; u=u||{email:"",name:"",perms:[]};
  const checks=grantViews().map(([v,ic,lbl],k)=>`<label class="rk-perm ${(u.perms||[]).includes(v)?"on":""}" style="--k:${k}"><span class="rk-pi">${ic}</span><b>${lbl}</b><span class="sw"><input type="checkbox" value="${v}" ${(u.perms||[]).includes(v)?"checked":""} onchange="rkPermSync()"></span></label>`).join("");
  const nm=u.name||u.email||"";
  openModal(`<div class="rk-md">
    <div class="rk-mh"><button class="close" onclick="closeModal()">×</button>
      <div class="rk-mhrow">${isEdit?rkAv(nm,"sm"):'<div class="rk-av sm" style="background:#fff3ec;color:var(--red)">＋</div>'}<div><div class="rk-mt">${isEdit?"Edit":"Tambah"} Pengguna</div><div class="rk-ms">${isEdit?esc(u.email):"Staf login pakai kode usaha yang sama"}</div></div></div></div>
    <div class="rk-mb">
      <div class="grid2" style="margin-bottom:12px"><div><label class="f">Nama</label><input id="uName" value="${esc(u.name||"")}" placeholder="nama staf"></div><div><label class="f">Email (untuk login)</label><input id="uEmail" type="email" value="${esc(u.email||"")}" ${isEdit?"readonly style='background:#f4f4f4'":""} placeholder="email@staf.com" autocapitalize="none"></div></div>
      <label class="f">Password ${isEdit?"<span class='mini'>(kosongkan kalau tak diubah)</span>":"(min 8 karakter)"}</label><input id="uPass" type="password" placeholder="${isEdit?"••••••":"buat password staf"}" style="margin-bottom:16px">
      <div class="rk-ph"><label class="f" style="margin:0">Boleh akses menu</label><span class="rk-pq"><button type="button" onclick="rkPermAll(true)">Semua</button><button type="button" onclick="rkPermAll(false)">Kosongkan</button></span></div>
      <div class="rk-pmeter"><div class="rk-bar"><i id="rkPermBar" style="width:0"></i></div><span id="rkPermN"></span></div>
      <div class="permgrid rk-pgrid">${checks}</div>
      <div class="rk-mact"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveUser(${isEdit?"true":"false"})">Simpan</button></div>
    </div></div>`);
  rkPermSync();
}
async function saveUser(isEdit){
  const email=document.getElementById("uEmail").value.trim().toLowerCase();
  const name=document.getElementById("uName").value.trim();
  const password=document.getElementById("uPass").value;
  const perms=[...document.querySelectorAll(".permgrid input:checked")].map(c=>c.value);
  if(!email){toast("Email wajib.");return;}
  if((!isEdit||password!=="")){const pe=pwProblem(password);if(pe){toast(pe);return;}}
  try{await api("userSave",{email,name,password,perms});await closeModal();toast("Pengguna tersimpan ✓");rUsers();}catch(e){}
}
async function delUser(email){if(confirm("Hapus pengguna "+email+"?")){await api("userDelete",{email});toast("Pengguna dihapus.");rUsers();}}
