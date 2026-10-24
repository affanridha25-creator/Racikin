"use strict";
// core.js — util angka, combobox, API
const rp=n=>"Rp"+Math.round(n||0).toLocaleString("id-ID");
const uid=()=>Date.now().toString(36)+Math.random().toString(36).slice(2,6);
const today=()=>new Date().toISOString().slice(0,10);
// Cek kekuatan password (cermin dari server) — pesan jelas apa kurangnya, "" bila lolos.
function pwProblem(p){p=String(p||"");const n=[...p].length;
  if(n<8)return `Password terlalu pendek — minimal 8 karakter (baru ${n}). Tambah ${8-n} lagi.`;
  if(/^(.)\1+$/u.test(p))return 'Password jangan satu karakter diulang terus (mis. "aaaaaaaa"). Campur huruf & angka.';
  if(/^(0123456789|123456789|12345678|1234567890|9876543210|87654321|abcdefgh|abcdefghij)$/i.test(p))return 'Password jangan berurutan (mis. "12345678"). Buat yang acak.';
  if(["12345678","123456789","1234567890","password","password1","passw0rd","qwerty123","qwertyui","11111111","00000000","iloveyou","admin123","racikin123","88888888","asdfasdf","1q2w3e4r","123123123","sayang123"].includes(p.toLowerCase()))return "Password ini terlalu umum & gampang ditebak. Pilih yang lebih unik (campur huruf, angka, atau simbol).";
  return "";}
const fmtDate=s=>s?new Date(s+"T00:00").toLocaleDateString("id-ID",{day:"2-digit",month:"short",year:"numeric"}):"-";
const esc=s=>(s==null?"":String(s)).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
let S={products:[],stores:[],materials:[],batches:[],notas:[],cashOut:[],profile:{}};
let curView="dashboard";

// ---- angka: pemisah ribuan utk input nominal ----
const digits=s=>String(s==null?"":s).replace(/\D/g,"");
const grp=s=>{const d=digits(s);return d?(+d).toLocaleString("id-ID"):"";};
// input uang siap-pakai: tampil "50.000", simpan angka murni lewat setter
function moneyIn(setter,val,ph){return `<input inputmode="numeric" placeholder="${ph||"0"}" value="${val?grp(val):""}" oninput="this.value=grp(this.value);${setter}">`;}

// ---- combobox bisa diketik (pengganti dropdown panjang) ----
let COMBO={n:0};
function comboReset(){COMBO={n:0};}
function combo(opts,curId,cb,ph){
  const cid="cb"+(++COMBO.n);COMBO[cid]={opts,cb};
  const cur=opts.find(o=>o.id===curId);
  return `<div class="combo" id="${cid}"><input class="combo-in" autocomplete="off" placeholder="${esc(ph||"— pilih —")}" value="${cur?esc(cur.label):""}" oninput="comboType('${cid}',this.value)" onfocus="comboType('${cid}',this.value)" onblur="comboBlur('${cid}')"><div class="combo-menu"></div></div>`;
}
function comboDraw(cid,filter){const c=COMBO[cid];if(!c)return;const m=document.querySelector("#"+cid+" .combo-menu");if(!m)return;const f=(filter||"").toLowerCase().trim();const list=c.opts.filter(o=>!f||o.label.toLowerCase().includes(f));m.innerHTML=list.length?list.slice(0,60).map(o=>`<div class="combo-opt" onmousedown="comboPick('${cid}','${o.id}')"><i class="co-dot"></i><span>${esc(o.label)}</span></div>`).join(""):'<div class="combo-empty"><span>🔍</span>Tak ada yang cocok</div>';}
function comboType(cid,v){const el=document.getElementById(cid);if(el)el.classList.add("open");comboDraw(cid,v);}
function comboBlur(cid){setTimeout(()=>{const el=document.getElementById(cid);if(el)el.classList.remove("open");},160);}
function comboPick(cid,id){const c=COMBO[cid];if(!c)return;const o=c.opts.find(x=>x.id===id);const el=document.getElementById(cid);if(el){const inp=el.querySelector(".combo-in");if(inp)inp.value=o?o.label:"";el.classList.remove("open");}if(c.cb)c.cb(id,o?o.label:"");}

// ---------- API ----------
async function api(action,payload={},opts={}){
  const r=await fetch("api.php",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"fetch"},body:JSON.stringify({action,...payload})});
  const j=await r.json();
  // langganan habis di tengah sesi → tampilkan layar kunci perpanjangan
  if(r.status===403&&j&&j.expired){SUB=j.sub||{expired:true};if(typeof showSubLock==="function")showSubLock();throw new Error(j.error||"Langganan berakhir.");}
  if(j&&j.error){if(!opts.silent)toast("⚠️ "+j.error);throw new Error(j.error);}
  return j;
}
let BIZ={};
