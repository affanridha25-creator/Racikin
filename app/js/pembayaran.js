"use strict";
// pembayaran.js — pembayaran & piutang, tagih WA (v2: hero piutang, meter umur, kartu toko & tagihan)
// ================= PEMBAYARAN (per NOTA) =================
// ---- Pembayaran & Piutang: ringkasan, umur piutang, filter, per toko, export ----
let PAY_ST="due",PAY_Q="";   // filter status: due (belum lunas) | lunas | all ; pencarian nota/toko
const notaAge=n=>Math.max(0,Math.floor((new Date(today()+"T00:00:00")-new Date((n.date||today())+"T00:00:00"))/864e5));
const ageColor=d=>d>=30?"var(--red)":d>=14?"var(--amber)":"var(--muted)";
const payNotas=()=>S.notas.filter(n=>!n.sessionId);
const pyAvatar=nm=>{const h=avHue(nm);return `<div class="py-av" style="background:hsl(${h},75%,92%);color:hsl(${h},55%,32%)">${esc(((nm||"?")[0]||"?").toUpperCase())}</div>`;};
const pyPct=n=>{const t=notaTotal(n);return t>0?Math.max(0,Math.min(100,Math.round(notaPaid(n)/t*100))):100;};
const pyAgeCls=d=>d>=30?"old":d>=14?"mid":"new";
function payList(){
  const q=PAY_Q.trim().toLowerCase();
  return payNotas().filter(n=>inMonth(n.date)).filter(n=>{const st=notaStatus(n);return PAY_ST==="all"||(PAY_ST==="due"?st!=="lunas":st==="lunas");})
    .filter(n=>!q||(n.notaNo||"").toLowerCase().includes(q)||((store(n.storeId)||{}).name||"").toLowerCase().includes(q))
    .sort((a,b)=>{const o={belum:0,sebagian:1,lunas:2};return o[notaStatus(a)]-o[notaStatus(b)]||(a.date||"").localeCompare(b.date||"");});   // belum lunas: tertua dulu
}
function payListHtml(){
  const list=payList();
  if(!list.length)return `<div class="py-empty"><div class="py-empty-i">${PAY_ST==="due"?"🎉":"🧾"}</div><b>${PAY_ST==="due"?"Tidak ada tagihan tertunggak":"Tak ada tagihan yang cocok"}</b><span>${PAY_ST==="due"?"Semua nota pada periode ini sudah lunas atau belum ada nota.":"Coba ganti filter, kata kunci, atau periode."}</span></div>`;
  if(isMobile())return `<div class="py-ncs">${list.map((n,i)=>{const st=notaStatus(n),due=st!=="lunas",age=notaAge(n),sname=(store(n.storeId)||{}).name||"?";
    return `<div class="py-nc" style="--i:${Math.min(i,10)}" onclick="${due?`payModal('${n.id}')`:`payHist('${n.id}')`}">
      <div class="py-nc-top">${pyAvatar(sname)}<div class="py-nc-id"><div class="py-nc-store">${esc(sname)}</div><div class="py-nc-sub">${esc(n.notaNo||"-")} · ${fmtDate(n.date)}</div></div>${due?`<span class="py-age ${pyAgeCls(age)}">${age} hr</span>`:`<span class="pill ds-st lunas">LUNAS</span>`}</div>
      <div class="py-nc-mid"><div><span>Tagihan</span><b>${rp(notaTotal(n))}</b></div><div><span>Terbayar</span><b class="g">${rp(notaPaid(n))}</b></div><div><span>Sisa</span><b class="${due?"a":"m"}">${due?rp(notaDue(n)):"—"}</b></div></div>
      <div class="py-pbar ${st}"><i style="width:${pyPct(n)}%"></i></div>
      <div class="py-nc-act">${due?`<button class="py-ab wa" onclick="event.stopPropagation();waTagih('${n.id}')">💬 Tagih</button><button class="py-ab pri" onclick="event.stopPropagation();payModal('${n.id}')">Bayar</button>`:""}<button class="py-ab" onclick="event.stopPropagation();payHist('${n.id}')">🕘 Riwayat</button></div></div>`;}).join("")}</div>`;
  return `<div class="py-tbl"><table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th class="num">Item</th><th class="num">Tagihan</th><th>Terbayar</th><th class="num">Sisa</th><th class="num">Umur</th><th>Status</th><th></th></tr></thead><tbody>${list.map((n,i)=>{const st=notaStatus(n),due=st!=="lunas",age=notaAge(n),sname=(store(n.storeId)||{}).name||"?";
    return `<tr style="--i:${Math.min(i,12)}"><td>${fmtDate(n.date)}</td><td><b>${esc(n.notaNo||"-")}</b></td><td><div class="py-sto">${pyAvatar(sname)}<span>${esc(sname)}</span></div></td><td class="num">${notaItems(n).length} item</td><td class="num">${rp(notaTotal(n))}</td><td><div class="py-paid"><span class="g">${rp(notaPaid(n))}</span><div class="py-pbar sm ${st}"><i style="width:${pyPct(n)}%"></i></div></div></td><td class="num a"><b>${rp(notaDue(n))}</b></td><td class="num">${due?`<span class="py-age ${pyAgeCls(age)}">${age} hr</span>`:"—"}</td><td><span class="pill ds-st ${st}">${st.toUpperCase()}</span></td><td class="num"><div class="py-acts">${due?`<button class="py-ab wa" onclick="waTagih('${n.id}')">💬 Tagih</button><button class="py-ab pri" onclick="payModal('${n.id}')">Bayar</button>`:""}<button class="py-ab" onclick="payHist('${n.id}')">Riwayat</button></div></td></tr>`;}).join("")}</tbody></table></div>`;
}
function setPaySt(v){PAY_ST=v;document.querySelectorAll("#v-pembayaran .py-chip[data-st]").forEach(b=>b.classList.toggle("on",b.dataset.st===v));const el=document.getElementById("payList");if(el)el.innerHTML=payListHtml();}
function setPayQ(v){PAY_Q=v;const el=document.getElementById("payList");if(el)el.innerHTML=payListHtml();}
function payByStore(i){const o=(window._payStores||[])[i];if(!o)return;PAY_Q=o.nm;PAY_ST="due";FILTER_MONTH="all";rPembayaran();const t=document.getElementById("payTagihan");if(t)t.scrollIntoView({behavior:"smooth",block:"start"});}
function exportPiutang(){
  const list=payNotas().filter(n=>notaStatus(n)!=="lunas").sort((a,b)=>(a.date||"").localeCompare(b.date||""));
  if(!list.length){toast("Tak ada piutang.");return;}
  const rows=[["Tanggal","No Nota","Toko","Telepon","Tagihan","Terbayar","Sisa","Umur (hari)","Status"]];
  list.forEach(n=>{const st=store(n.storeId)||{};rows.push([n.date,n.notaNo||"",st.name||"",st.contact||"",notaTotal(n),notaPaid(n),notaDue(n),notaAge(n),notaStatus(n)]);});
  rows.push(["TOTAL","","","",list.reduce((a,n)=>a+notaTotal(n),0),list.reduce((a,n)=>a+notaPaid(n),0),list.reduce((a,n)=>a+notaDue(n),0),"",""]);
  downloadCSV("piutang-"+today()+".csv",rows);
}
function rPembayaran(){
  const per=payNotas().filter(n=>inMonth(n.date));
  const tagihan=per.reduce((a,n)=>a+notaTotal(n),0),terbayar=per.reduce((a,n)=>a+Math.min(notaPaid(n),notaTotal(n)),0),sisa=per.reduce((a,n)=>a+notaDue(n),0);
  const nDue=per.filter(n=>notaStatus(n)!=="lunas").length;
  const rate=tagihan>0?Math.round(terbayar/tagihan*100):0;
  // umur piutang & piutang per toko: seluruh periode (piutang lama tetap harus ditagih)
  const open=payNotas().filter(n=>notaStatus(n)!=="lunas"&&notaDue(n)>0);
  const bucket=[["0–14 hari",0,13,"new","🌱","Masih segar"],["15–30 hari",14,29,"mid","⏰","Mulai perlu diingatkan"],["> 30 hari",30,1e9,"old","🔥","Segera ditagih"]].map(([l,a,b,c,ic,hint])=>{const xs=open.filter(n=>{const d=notaAge(n);return d>=a&&d<=b;});return{l,a,c,ic,hint,n:xs.length,v:xs.reduce((s,n)=>s+notaDue(n),0)};});
  const totalOpen=open.reduce((a,n)=>a+notaDue(n),0);
  const byStore={};open.forEach(n=>{const nm=(store(n.storeId)||{}).name||"?";const o=byStore[nm]||(byStore[nm]={nm,sid:n.storeId,n:0,v:0,old:0,ns:[]});o.n++;o.v+=notaDue(n);o.old=Math.max(o.old,notaAge(n));o.ns.push(n);});
  const stores=Object.values(byStore).sort((a,b)=>b.v-a.v).slice(0,8);window._payStores=stores;
  const old=bucket[2];
  const hero=`<div class="py-hero">
    <div class="dh-bg"><i></i><i></i><i></i></div>
    <div class="py-h-main">
      <div class="py-h-top"><div><div class="py-h-kick">💰 Pembayaran &amp; Piutang</div><div class="py-h-lbl">Total piutang berjalan <span>(semua periode)</span></div></div><div class="py-h-month">${monthBar()}</div></div>
      <div class="py-h-val">${cnt(totalOpen)}</div>
      <div class="py-h-pills"><span class="py-hp">🧾 ${cnt(open.length,"num")} nota belum lunas</span>${old.v>0?`<span class="py-hp hot">🔥 &gt; 30 hari: ${cnt(old.v)} · ${old.n} nota</span>`:`<span class="py-hp">✅ tak ada yang lewat 30 hari</span>`}</div>
      <div class="py-h-act"><button onclick="exportPiutang()">⬇ Export piutang</button>${open.length?`<button onclick="document.getElementById('payTagihan').scrollIntoView({behavior:'smooth'})">Lihat tagihan ↓</button>`:""}</div>
    </div>
    <div class="py-h-side"><div class="py-ringrow"><div class="py-ring"><svg viewBox="0 0 84 84"><circle cx="42" cy="42" r="36" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="9"/><circle class="rg" cx="42" cy="42" r="36" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" pathLength="1" transform="rotate(-90 42 42)" style="--off:${(1-Math.min(1,rate/100)).toFixed(3)}"/></svg><b>${cnt(rate,"pct")}</b></div><div><div class="py-rl">Tertagih bulan ini</div><div class="py-rs">${esc(monthLabelFull(FILTER_MONTH))}: ${rp(terbayar)} dari ${rp(tagihan)}</div></div></div></div>
  </div>`;
  const kpi=(ic,bg,l,v,s)=>`<div class="py-kpi"><div class="py-kpi-i" style="background:${bg}">${ic}</div><div class="py-kpi-l">${l}</div><div class="py-kpi-v">${v}</div><div class="py-kpi-s">${s}</div></div>`;
  const kpis=`<div class="py-kpis">${kpi("⏳","var(--amber-bg)","Sisa Piutang",cnt(sisa),esc(monthLabelFull(FILTER_MONTH)))}${kpi("🧾","var(--blue-bg)","Total Tagihan",cnt(tagihan),`${per.length} nota`)}${kpi("✅","var(--green-bg)","Terbayar",cnt(terbayar),`${rate}% dari tagihan`)}${kpi("📬","#fdeaf0","Nota Belum Lunas",cnt(nDue,"num"),"periode terpilih")}</div>`;
  // meter umur piutang: bar bertumpuk + 3 tingkat
  const seg=bucket.map(b=>`<i class="${b.c}" style="flex:${Math.max(b.v,0)};${b.v>0?"":"display:none"}"></i>`).join("");
  const agePanel=`<div class="py-card"><h3>Umur Piutang <span class="mini">semua periode · total ${rp(totalOpen)}</span></h3>
    ${totalOpen>0?`<div class="py-stack">${seg}</div>`:""}
    <div class="py-meters">${bucket.map((b,i)=>`<div class="py-meter ${b.c}"><div class="py-m-top"><span class="py-m-i">${b.ic}</span><div class="py-m-t"><div class="py-m-l">${b.l}</div><div class="py-m-h">${b.hint}</div></div><div class="py-m-v"><b>${rp(b.v)}</b><span>${b.n} nota</span></div></div><div class="py-m-bar"><i style="width:${totalOpen>0?Math.round(b.v/totalOpen*100):0}%;animation-delay:${.3+i*.12}s"></i></div></div>`).join("")}</div></div>`;
  const storePanel=stores.length?`<div class="py-card"><h3>Piutang per Toko <span class="mini">tagih langsung lewat WhatsApp</span></h3><div class="py-stores">${stores.map((o,i)=>{const ns=o.ns.sort((a,b)=>(a.date||"").localeCompare(b.date||""));
    return `<div class="py-sc ${pyAgeCls(o.old)}" style="--i:${i}"><div class="py-sc-top">${pyAvatar(o.nm)}<div class="py-sc-id"><div class="py-sc-n">${esc(o.nm)}</div><div class="py-sc-s">${o.n} nota · tertua <span class="py-age ${pyAgeCls(o.old)}">${o.old} hr</span></div></div><div class="py-sc-v">${rp(o.v)}</div></div>
      <div class="py-sc-list">${ns.slice(0,3).map(n=>`<div><span>${esc(n.notaNo||"-")} · ${fmtDate(n.date)}</span><b>${rp(notaDue(n))}</b></div>`).join("")}${ns.length>3?`<div class="more">+${ns.length-3} nota lainnya</div>`:""}</div>
      <div class="py-sc-act"><button class="py-ab wa" onclick="waTagihToko(${i})">💬 Tagih WA</button><button class="py-ab" onclick="payByStore(${i})">Lihat nota</button></div></div>`;}).join("")}</div></div>`:"";
  const cntSt=k=>k==="all"?per.length:k==="due"?nDue:per.length-nDue;
  const chip=(v,l)=>`<button class="py-chip${PAY_ST===v?" on":""}" data-st="${v}" onclick="setPaySt('${v}')">${l}<em>${cntSt(v)}</em></button>`;
  const tools=`<div class="py-tools"><div class="py-chips-row">${chip("due","Belum lunas")}${chip("lunas","Lunas")}${chip("all","Semua")}</div><input type="search" placeholder="🔍 Cari nota / toko…" value="${esc(PAY_Q)}" oninput="setPayQ(this.value)"></div>`;
  const el=document.getElementById("v-pembayaran");
  el.innerHTML=`<div class="py-wrap py-in" id="pyWrap">${hero}${kpis}${agePanel}${storePanel}
  <div class="py-card" id="payTagihan"><h3>Daftar Tagihan <span class="mini">per nota · ${esc(monthLabelFull(FILTER_MONTH))}</span></h3>${tools}<div id="payList">${payListHtml()}</div></div></div>`;
  [...document.getElementById("pyWrap").children].forEach((c,i)=>c.style.setProperty("--i",i));
  runCounts(el);
  setTimeout(()=>{const w=document.getElementById("pyWrap");if(w)w.classList.remove("py-in");},1500);   // animasi masuk hanya sekali
}
function payModal(id){const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}window._pid=id;
  const sname=(store(n.storeId)||{}).name||"?";
  openModal(`<div class="py-form"><button class="close" onclick="closeModal()">×</button>
  <div class="py-mh"><div class="py-mh-i">💰</div><div><h3>Catat Pembayaran</h3><div class="py-mh-s">${esc(n.notaNo||"-")} · ${esc(sname)}</div></div></div>
  <div class="py-info"><div class="py-info-r"><div><span>Tagihan</span><b>${rp(notaTotal(n))}</b></div><div><span>Terbayar</span><b class="g">${rp(notaPaid(n))}</b></div><div><span>Sisa</span><b class="a">${rp(notaDue(n))}</b></div></div><div class="py-pbar ${notaStatus(n)}"><i style="width:${pyPct(n)}%"></i></div></div>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal bayar</label><input type="date" id="payDate" value="${today()}"></div><div><label class="f">Jumlah</label><input inputmode="numeric" id="payAmt" value="${grp(notaDue(n))}" oninput="this.value=grp(this.value)"></div></div>
  <div class="py-quick"><button type="button" onclick="document.getElementById('payAmt').value=grp(${Math.max(0,Math.round(notaDue(n)))})">Lunasi sisa</button><button type="button" onclick="document.getElementById('payAmt').value=grp(${Math.max(0,Math.round(notaDue(n)/2))})">Setengah sisa</button></div>
  <label class="f">Catatan</label><input id="payNote" placeholder="mis. transfer / tunai">
  <div class="py-quick" style="margin-top:8px">${["Tunai","Transfer","QRIS"].map(m=>`<button type="button" onclick="document.getElementById('payNote').value='${m}'">${m}</button>`).join("")}</div>
  <div class="py-foot"><button class="btn gray" onclick="closeModal()">Batal</button><button class="btn" onclick="addPay()">Simpan Pembayaran</button></div></div>`);
}
async function addPay(){const amt=+digits(document.getElementById("payAmt").value);if(!(amt>0)){toast("Jumlah tidak valid.");return;}
  await api("addPayment",{notaId:window._pid,date:document.getElementById("payDate").value,amount:amt,note:document.getElementById("payNote").value});await reload();closeModal();toast("Pembayaran dicatat ✓");rPembayaran();}
function payHist(id){const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  const sname=(store(n.storeId)||{}).name||"?",due=notaStatus(n)!=="lunas";
  openModal(`<div class="py-form"><button class="close" onclick="closeModal()">×</button>
  <div class="py-mh"><div class="py-mh-i">🕘</div><div><h3>Riwayat Pembayaran</h3><div class="py-mh-s">${esc(n.notaNo||"-")} · ${esc(sname)}</div></div></div>
  <div class="py-info"><div class="py-info-r"><div><span>Tagihan</span><b>${rp(notaTotal(n))}</b></div><div><span>Terbayar</span><b class="g">${rp(notaPaid(n))}</b></div><div><span>Sisa</span><b class="${due?"a":"m"}">${due?rp(notaDue(n)):"Lunas"}</b></div></div><div class="py-pbar ${notaStatus(n)}"><i style="width:${pyPct(n)}%"></i></div></div>
  ${(n.payments||[]).length===0?'<div class="py-none">Belum ada pembayaran.</div>':`<div class="py-tl">${n.payments.map(p=>`<div class="py-tl-i"><i></i><div class="py-tl-m"><b>${rp(p.amount)}</b><span>${fmtDate(p.date)}${p.note?" · "+esc(p.note):""}</span></div><button class="py-tl-x" title="Hapus pembayaran" onclick="delPay(${p.id},'${id}')">×</button></div>`).join("")}</div>`}
  <div class="py-foot wrap">${due?`<button class="btn wa" onclick="waTagih('${id}')">💬 Tagih WA</button>`:""}<button class="btn ghost" onclick="waReceipt('${id}',${notaPaid(n)},0,'')">🧾 Kirim struk WA</button><button class="btn gray" onclick="closeModal()">Tutup</button></div></div>`);}
async function delPay(pid,id){await api("deletePayment",{id:pid});await reload();payHist(id);}
// ---- Tagih via WhatsApp (klik-to-WA, gratis) ----
function waPhone(raw){let d=(raw||"").replace(/\D/g,"");if(!d)return "";if(d.startsWith("0"))d="62"+d.slice(1);else if(d.startsWith("62"))d=d;else if(d.startsWith("8"))d="62"+d;else d="62"+d;return d;}
// tagih semua nota piutang satu toko sekaligus (satu pesan WA)
function waTagihToko(i){
  const o=(window._payStores||[])[i];if(!o)return;
  if(o.ns.length===1){waTagih(o.ns[0].id);return;}
  const st=store(o.sid)||{};const phone=waPhone(st.contact);
  let msg=`Halo${st.name?" "+st.name:""} 🙏\n\nPengingat tagihan dari *${BIZ.name||"kami"}*:\n\n`;
  o.ns.forEach(n=>{msg+=`• ${n.notaNo||"-"} (${fmtDate(n.date)}) — sisa ${rp(notaDue(n))}\n`;});
  msg+=`\n*Total sisa tagihan: ${rp(o.v)}*\n\nMohon diselesaikan ya. Terima kasih 🙏`;
  window.open((phone?`https://wa.me/${phone}`:`https://wa.me/`)+"?text="+encodeURIComponent(msg),"_blank");
  if(!phone)toast("Kontak toko kosong — pilih nomor tujuan di WhatsApp.");
}
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
