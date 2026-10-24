"use strict";
// pengguna.js — pengguna (multi-user)
// ================= PENGGUNA (multi-user, khusus pemilik) =================
async function rUsers(){
  const el=document.getElementById("v-users");
  el.innerHTML=`<h2 class="title">Pengguna</h2><div class="desc">Kelola akun staf/kasir untuk usahamu.</div><div class="empty">Memuat…</div>`;
  let users=[];
  try{const r=await api("usersList");users=r.users||[];}catch(e){el.innerHTML=`<div class="empty">Gagal memuat pengguna.</div>`;return;}
  _userNames={};users.forEach(u=>_userNames[u.email]=u.name);
  const permLabel=p=>{const g=GRANT_VIEWS.find(x=>x[0]===p);return g?g[2]:p;};
  const rows=users.map(u=>{
    const isOwn=u.role==="owner";
    const akses=isOwn?'<span class="pill lunas">Semua (Pemilik)</span>':(u.perms&&u.perms.length?u.perms.map(p=>`<span class="tag-cat">${esc(permLabel(p))}</span>`).join(" "):'<span class="mini">belum ada akses</span>');
    const acts=isOwn?'':`<div class="acts"><button class="btn sm gray" onclick="editUserE('${esc(u.email)}')">Edit</button><button class="btn sm del" onclick="delUser('${esc(u.email)}')">✕</button></div>`;
    if(isMobile())return crow({icon:isOwn?"👑":"🧑‍💼",title:esc(u.name),sub:esc(u.email),badges:`<div style="margin-top:4px">${akses}</div>`,acts,onclick:isOwn?"":`editUserE('${esc(u.email)}')`});
    return `<tr><td>${esc(u.name)}${isOwn?' 👑':''}</td><td>${esc(u.email)}</td><td>${akses}</td><td class="num">${acts||'<span class="mini">—</span>'}</td></tr>`;
  }).join("");
  window._usersCache=users;
  if(isMobile()){
    el.innerHTML=`<div class="desc">Kelola akun staf/kasir.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editUser()">+ Tambah Pengguna</button><div class="clist">${rows}</div>`;
    return;
  }
  el.innerHTML=`<h2 class="title">Pengguna</h2><div class="desc">Pemilik akses penuh. Staf hanya menu yang dicentang. Tiap staf login pakai <b>kode usaha sama</b> + email &amp; password sendiri.</div>
    <div class="flexbtns"><button class="btn" onclick="editUser()">+ Tambah Pengguna</button></div>
    <div class="panel"><table><thead><tr><th>Nama</th><th>Email</th><th>Akses</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`;
}
function editUserE(email){const u=(window._usersCache||[]).find(x=>x.email===email);if(u)editUser(u);}
function editUser(u){
  const isEdit=!!u; u=u||{email:"",name:"",perms:[]};
  const checks=grantViews().map(([v,ic,lbl])=>`<label class="permchk"><input type="checkbox" value="${v}" ${(u.perms||[]).includes(v)?"checked":""}> ${ic} ${lbl}</label>`).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${isEdit?"Edit":"Tambah"} Pengguna</h3>
    <div class="grid2" style="margin-bottom:12px"><div><label class="f">Nama</label><input id="uName" value="${esc(u.name||"")}" placeholder="nama staf"></div><div><label class="f">Email (untuk login)</label><input id="uEmail" type="email" value="${esc(u.email||"")}" ${isEdit?"readonly style='background:#f4f4f4'":""} placeholder="email@staf.com" autocapitalize="none"></div></div>
    <label class="f">Password ${isEdit?"<span class='mini'>(kosongkan kalau tak diubah)</span>":"(min 8 karakter)"}</label><input id="uPass" type="password" placeholder="${isEdit?"••••••":"buat password staf"}" style="margin-bottom:14px">
    <label class="f">Boleh akses menu:</label>
    <div class="permgrid">${checks}</div>
    <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveUser(${isEdit?"true":"false"})">Simpan</button></div>`);
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

