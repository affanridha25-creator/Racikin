"use strict";
// pembayaran.js — pembayaran & piutang, tagih WA
// ================= PEMBAYARAN (per NOTA) =================
// ---- Pembayaran & Piutang: ringkasan, umur piutang, filter, per toko, export ----
let PAY_ST="due",PAY_Q="";   // filter status: due (belum lunas) | lunas | all ; pencarian nota/toko
const notaAge=n=>Math.max(0,Math.floor((new Date(today()+"T00:00:00")-new Date((n.date||today())+"T00:00:00"))/864e5));
const ageColor=d=>d>=30?"var(--red)":d>=14?"var(--amber)":"var(--muted)";
const payNotas=()=>S.notas.filter(n=>!n.sessionId);
function payList(){
  const q=PAY_Q.trim().toLowerCase();
  return payNotas().filter(n=>inMonth(n.date)).filter(n=>{const st=notaStatus(n);return PAY_ST==="all"||(PAY_ST==="due"?st!=="lunas":st==="lunas");})
    .filter(n=>!q||(n.notaNo||"").toLowerCase().includes(q)||((store(n.storeId)||{}).name||"").toLowerCase().includes(q))
    .sort((a,b)=>{const o={belum:0,sebagian:1,lunas:2};return o[notaStatus(a)]-o[notaStatus(b)]||(a.date||"").localeCompare(b.date||"");});   // belum lunas: tertua dulu
}
function payListHtml(){
  const list=payList();
  if(!list.length)return `<div class="empty">Tak ada tagihan yang cocok.</div>`;
  if(isMobile())return `<div class="clist">${list.map(n=>{const st=notaStatus(n),due=st!=="lunas",age=notaAge(n);
    const acts=`<div class="cacts">${due?`<button class="btn sm wa" onclick="event.stopPropagation();waTagih('${n.id}')">💬</button><button class="btn sm" onclick="event.stopPropagation();payModal('${n.id}')">Bayar</button>`:""}<button class="btn sm gray" onclick="event.stopPropagation();payHist('${n.id}')">🕘</button></div>`;
    return crow({icon:"💰",title:esc(n.notaNo||"-"),sub:`${esc((store(n.storeId)||{}).name||"?")} · ${fmtDate(n.date)}${due?` · <span style="color:${ageColor(age)}">${age} hr</span>`:""}`,amt:rp(notaDue(n)),amtColor:"var(--amber)",status:st,onclick:due?`payModal('${n.id}')`:`payHist('${n.id}')`,acts});}).join("")}</div>`;
  return `<table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th class="num">Item</th><th class="num">Tagihan</th><th class="num">Terbayar</th><th class="num">Sisa</th><th class="num">Umur</th><th>Status</th><th></th></tr></thead><tbody>${list.map(n=>{const st=notaStatus(n),due=st!=="lunas",age=notaAge(n);
    return `<tr><td>${fmtDate(n.date)}</td><td>${esc(n.notaNo||"-")}</td><td>${esc((store(n.storeId)||{}).name||"?")}</td><td class="num">${notaItems(n).length} item</td><td class="num">${rp(notaTotal(n))}</td><td class="num" style="color:var(--green)">${rp(notaPaid(n))}</td><td class="num" style="color:var(--amber)"><b>${rp(notaDue(n))}</b></td><td class="num" style="color:${ageColor(age)}">${due?age+" hr":"—"}</td><td><span class="pill ${st}">${st.toUpperCase()}</span></td><td class="num"><div class="acts">${due?`<button class="btn sm wa" onclick="waTagih('${n.id}')">💬 Tagih</button><button class="btn sm" onclick="payModal('${n.id}')">Bayar</button>`:""}<button class="btn sm gray" onclick="payHist('${n.id}')">Riwayat</button></div></td></tr>`;}).join("")}</tbody></table>`;
}
function setPaySt(v){PAY_ST=v;rPembayaran();}
function setPayQ(v){PAY_Q=v;const el=document.getElementById("payList");if(el)el.innerHTML=payListHtml();}
function payByStore(i){const o=(window._payStores||[])[i];if(!o)return;PAY_Q=o.nm;PAY_ST="due";FILTER_MONTH="all";rPembayaran();}
function exportPiutang(){
  const list=payNotas().filter(n=>notaStatus(n)!=="lunas").sort((a,b)=>(a.date||"").localeCompare(b.date||""));
  if(!list.length){toast("Tak ada piutang.");return;}
  const rows=[["Tanggal","No Nota","Toko","Telepon","Tagihan","Terbayar","Sisa","Umur (hari)","Status"]];
  list.forEach(n=>{const st=store(n.storeId)||{};rows.push([n.date,n.notaNo||"",st.name||"",st.contact||"",notaTotal(n),notaPaid(n),notaDue(n),notaAge(n),notaStatus(n)]);});
  rows.push(["TOTAL","","","",list.reduce((a,n)=>a+notaTotal(n),0),list.reduce((a,n)=>a+notaPaid(n),0),list.reduce((a,n)=>a+notaDue(n),0),"",""]);
  downloadCSV("piutang-"+today()+".csv",rows);
}
function rPembayaran(){
  const mob=isMobile();
  const per=payNotas().filter(n=>inMonth(n.date));
  const tagihan=per.reduce((a,n)=>a+notaTotal(n),0),terbayar=per.reduce((a,n)=>a+Math.min(notaPaid(n),notaTotal(n)),0),sisa=per.reduce((a,n)=>a+notaDue(n),0);
  const nDue=per.filter(n=>notaStatus(n)!=="lunas").length;
  // umur piutang & piutang per toko: seluruh periode (piutang lama tetap harus ditagih)
  const open=payNotas().filter(n=>notaStatus(n)!=="lunas"&&notaDue(n)>0);
  const bucket=[["0–14 hari",0,13],["15–30 hari",14,29],["> 30 hari",30,1e9]].map(([l,a,b])=>{const xs=open.filter(n=>{const d=notaAge(n);return d>=a&&d<=b;});return{l,a,n:xs.length,v:xs.reduce((s,n)=>s+notaDue(n),0)};});
  const totalOpen=open.reduce((a,n)=>a+notaDue(n),0);
  const byStore={};open.forEach(n=>{const nm=(store(n.storeId)||{}).name||"?";const o=byStore[nm]||(byStore[nm]={nm,n:0,v:0,old:0,ns:[]});o.n++;o.v+=notaDue(n);o.old=Math.max(o.old,notaAge(n));o.ns.push(n);});
  const stores=Object.values(byStore).sort((a,b)=>b.v-a.v).slice(0,8);window._payStores=stores;
  const agePanel=`<div class="panel"><h3>Umur Piutang <span class="mini">(semua periode · total ${rp(totalOpen)})</span></h3><div class="grid3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">${bucket.map(b=>`<div style="background:var(--bg);border-radius:12px;padding:10px 12px"><div class="mini">${b.l}</div><div style="font-weight:700;color:${ageColor(b.a)}">${rp(b.v)}</div><div class="mini">${b.n} nota</div></div>`).join("")}</div></div>`;
  const storePanel=stores.length?`<div class="panel"><h3>Piutang per Toko <span class="mini">(ketuk untuk lihat notanya)</span></h3><div class="clist">${stores.map((o,i)=>crow({icon:"🏪",title:esc(o.nm),sub:`${o.n} nota · tertua ${o.old} hr`,badges:`<div class="mini" style="margin-top:4px;line-height:1.55">${o.ns.sort((a,b)=>(a.date||"").localeCompare(b.date||"")).map(n=>`<div>${esc(n.notaNo||"-")} · ${fmtDate(n.date)} · <span style="color:${ageColor(notaAge(n))}">${notaAge(n)} hr</span> · ${rp(notaDue(n))}</div>`).join("")}</div>`,amt:rp(o.v),amtColor:"var(--amber)",onclick:`payByStore(${i})`})).join("")}</div></div>`:"";
  const chip=(v,l)=>`<button class="btn sm ${PAY_ST===v?"":"gray"}" onclick="setPaySt('${v}')">${l}</button>`;
  const tools=`<div class="flexbtns" style="margin:12px 0;align-items:center;flex-wrap:wrap">${chip("due","Belum lunas")}${chip("lunas","Lunas")}${chip("all","Semua")}<input type="search" placeholder="Cari nota / toko…" value="${esc(PAY_Q)}" oninput="setPayQ(this.value)" style="flex:1;min-width:140px"><button class="btn sm ghost" onclick="exportPiutang()">⬇ Export piutang</button></div>`;
  const cards=`<div class="cards"><div class="card amber"><div class="lbl">Sisa Piutang (${monthLabelFull(FILTER_MONTH)})</div><div class="val">${rp(sisa)}</div></div><div class="card"><div class="lbl">Total Tagihan</div><div class="val">${rp(tagihan)}</div></div><div class="card green"><div class="lbl">Terbayar</div><div class="val">${rp(terbayar)}</div></div><div class="card"><div class="lbl">Nota Belum Lunas</div><div class="val">${nDue}</div></div></div>`;
  document.getElementById("v-pembayaran").innerHTML=`${mob?"":`<h2 class="title">Pembayaran &amp; Piutang</h2><div class="desc">Tagihan dilacak per nota. Bisa cicil; sisa piutang update otomatis.</div>`}
  ${monthBar()}${cards}${agePanel}${storePanel}
  <div class="panel"><h3>Daftar Tagihan (per Nota)</h3>${tools}<div id="payList" style="overflow-x:auto">${payListHtml()}</div></div>`;
}
function payModal(id){const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}window._pid=id;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Catat Pembayaran</h3><p class="mini" style="margin-bottom:12px">${esc(n.notaNo||"-")} · ${esc((store(n.storeId)||{}).name||"?")} — sisa <b>${rp(notaDue(n))}</b></p>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal bayar</label><input type="date" id="payDate" value="${today()}"></div><div><label class="f">Jumlah</label><input inputmode="numeric" id="payAmt" value="${grp(notaDue(n))}" oninput="this.value=grp(this.value)"></div></div>
  <label class="f">Catatan</label><input id="payNote" placeholder="mis. transfer / tunai">
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="addPay()">Simpan</button></div>`);
}
async function addPay(){const amt=+digits(document.getElementById("payAmt").value);if(!(amt>0)){toast("Jumlah tidak valid.");return;}
  await api("addPayment",{notaId:window._pid,date:document.getElementById("payDate").value,amount:amt,note:document.getElementById("payNote").value});await reload();closeModal();toast("Pembayaran dicatat ✓");rPembayaran();}
function payHist(id){const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Riwayat Pembayaran</h3><p class="mini" style="margin-bottom:8px">${esc(n.notaNo||"-")} · ${esc((store(n.storeId)||{}).name||"?")}</p>${(n.payments||[]).length===0?'<div class="empty">Belum ada pembayaran.</div>':`<table><thead><tr><th>Tgl</th><th>Jumlah</th><th>Catatan</th><th></th></tr></thead><tbody>${n.payments.map(p=>`<tr><td>${fmtDate(p.date)}</td><td>${rp(p.amount)}</td><td>${esc(p.note||"")}</td><td class="num"><button class="btn sm gray" onclick="delPay(${p.id},'${id}')">×</button></td></tr>`).join("")}</tbody></table>`}<div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Tutup</button></div>`);}
async function delPay(pid,id){await api("deletePayment",{id:pid});await reload();payHist(id);}
// ---- Tagih via WhatsApp (klik-to-WA, gratis) ----
function waPhone(raw){let d=(raw||"").replace(/\D/g,"");if(!d)return "";if(d.startsWith("0"))d="62"+d.slice(1);else if(d.startsWith("62"))d=d;else if(d.startsWith("8"))d="62"+d;else d="62"+d;return d;}
function waTagih(id){
  const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  const st=store(n.storeId)||{};const phone=waPhone(st.contact);
  const total=notaTotal(n),paid=notaPaid(n),due=notaDue(n);
  const items=notaItems(n).map(it=>{const p=prod(it.productId)||{};return `• ${p.name||"?"} ×${it.qty}`;}).join("\n");
  const prof=S.profile||{};
  let msg=`Halo${st.name?" "+st.name:""} 🙏\n\nPengingat tagihan dari *${BIZ.name||"kami"}*:\n`;
  msg+=`No. Nota: ${n.notaNo||"-"}\nTanggal: ${fmtDate(n.date)}\n\n${items}\n\n`;
  msg+=`Total: ${rp(total)}\nSudah bayar: ${rp(paid)}\n*Sisa tagihan: ${rp(due)}*\n\n`;
  const cara=[];if(prof.qris)cara.push("QRIS");if(prof.whatsapp||prof.phone)cara.push("transfer");
  msg+=`Mohon diselesaikan ya${cara.length?" (bisa "+cara.join(" / ")+")":""}. Terima kasih 🙏`;
  const url=(phone?`https://wa.me/${phone}`:`https://wa.me/`)+"?text="+encodeURIComponent(msg);
  window.open(url,"_blank");
  if(!phone)toast("Kontak toko kosong — pilih nomor tujuan di WhatsApp.");
}
// ---- Kirim struk via WhatsApp (teks polos, rapi di layar chat — bukan format 32 kolom) ----
function waReceiptText(id,bayar,kembali,method){
  const n=S.notas.find(x=>x.id===id);if(!n)return "";
  const st=store(n.storeId)||{};
  let t=`*${BIZ.name||"Racikin"}* — Struk\n`;
  if(n.notaNo)t+=`${n.notaNo}\n`;
  const w=notaWhen(n);t+=`${w.label} ${fmtStamp(w.stamp," · ")}\n`;
  if(st.name&&st.name!==POS_NAME)t+=`Kepada: ${st.name}\n`;
  t+="\n"+notaItems(n).map(it=>{const p=prod(it.productId)||{};return `${it.qty}× ${p.name||"?"} = ${rp((+it.qty||0)*(+it.harga||0))}`;}).join("\n")+"\n";
  const disc=notaDisc(n),svc=notaService(n),tax=notaTax(n);
  if(disc>0||svc>0||tax>0)t+=`\nSubtotal: ${rp(notaSubtotal(n))}`;
  if(disc>0)t+=`\nDiskon: -${rp(disc)}`;
  if(svc>0)t+=`\nService${ratePct(notaSvcRate(n))}: ${rp(svc)}`;
  if(tax>0)t+=`\nPajak${ratePct(notaTaxRate(n))}: ${rp(tax)}`;
  t+=`\n*TOTAL: ${rp(notaTotal(n))}*`;
  if(method){t+=`\nBayar (${method}): ${rp(bayar)}`;if(method==="Tunai")t+=`\nKembalian: ${rp(kembali||0)}`;}
  if(notaDue(n)>0)t+=`\nSisa tagihan: ${rp(notaDue(n))}`;
  const f=((S.profile||{}).footer||"").trim();
  t+=`\n\n${f||"Terima kasih 🙏"}\n— ${BIZ.name||"Racikin"} —`;
  return t;
}
function waReceipt(id,bayar,kembali,method){
  const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  const st=store(n.storeId)||{};
  const phone=(st.name&&st.name!==POS_NAME)?waPhone(st.contact):"";   // toko internal kasir tak punya nomor
  const url=(phone?`https://wa.me/${phone}`:`https://wa.me/`)+"?text="+encodeURIComponent(waReceiptText(id,bayar,kembali,method));
  window.open(url,"_blank");
  if(!phone)toast("Pilih kontak tujuan di WhatsApp.");
}

