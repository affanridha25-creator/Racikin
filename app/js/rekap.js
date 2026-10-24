"use strict";
// rekap.js — rekap kasir & sesi
// ================= REKAP KASIR =================
let _userNames={};   // email -> nama (dari daftar pengguna)
function kasirName(email){return _userNames[email]||email||"(tak tercatat)";}
async function rRekap(){
  // ambil nama pengguna sekali (biar tampil nama, bukan email) — owner-only, sudah dijaga server
  if(Object.keys(_userNames).length===0){try{const r=await api("usersList");(r.users||[]).forEach(u=>_userNames[u.email]=u.name);}catch(e){}}
  const fnotas=S.notas.filter(n=>n.sessionId&&inMonth(n.date));   // POS saja (punya sesi kasir)
  const by={};
  fnotas.forEach(n=>{const k=n.createdBy||"";if(!by[k])by[k]={omzet:0,tunai:0,nontunai:0,n:0};by[k].omzet+=notaTotal(n);if(n.payMethod==="Tunai")by[k].tunai+=notaTotal(n);else by[k].nontunai+=notaTotal(n);by[k].n++;});
  const rows=Object.entries(by).map(([k,v])=>({k,...v})).sort((a,b)=>b.omzet-a.omzet);
  const tot=rows.reduce((a,r)=>({omzet:a.omzet+r.omzet,tunai:a.tunai+r.tunai,nontunai:a.nontunai+r.nontunai,n:a.n+r.n}),{omzet:0,tunai:0,nontunai:0,n:0});
  const el=document.getElementById("v-rekap");
  const sesi=regLogHtml();
  const head=`<h2 class="title">Penjualan Kasir (POS)</h2><div class="desc">Rekap transaksi kasir per pengguna &amp; per sesi. Ketuk sesi untuk lihat detail + cetak ulang struk/settlement.</div>`;
  const expBtn=fnotas.length?`<button class="btn sm gray" onclick="exportPenjualan('pos')">⬇️ CSV</button>`:"";
  if(rows.length===0){el.innerHTML=`${head}${monthBar()}${sesi||'<div class="empty">Belum ada penjualan kasir pada periode ini.</div>'}`;return;}
  if(isMobile()){
    const cards=rows.map(r=>crow({icon:"🧑‍💼",title:esc(kasirName(r.k)),sub:`${r.n} transaksi · tunai ${rp(r.tunai)}`,amt:rp(r.omzet),badges:(r.nontunai>0?`<div class="csub">Non-tunai ${rp(r.nontunai)}</div>`:"")})).join("");
    el.innerHTML=`${head}${monthBar()}
      <div class="card accent" style="margin-bottom:14px"><div class="lbl">Total Penjualan Kasir · ${monthLabelFull(FILTER_MONTH)}</div><div class="val">${rp(tot.omzet)}</div></div>
      <div style="margin-bottom:10px">${expBtn}</div>
      <div class="clist">${cards}</div>${sesi}`;
    return;
  }
  const trs=rows.map(r=>`<tr><td>${esc(kasirName(r.k))}</td><td class="num">${r.n}</td><td class="num">${rp(r.omzet)}</td><td class="num" style="color:var(--green)">${rp(r.tunai)}</td><td class="num" style="color:var(--amber)">${rp(r.nontunai)}</td></tr>`).join("");
  el.innerHTML=`${head}<div class="flexbtns">${expBtn}${monthBar()}</div>
    <div class="panel"><table><thead><tr><th>Kasir</th><th class="num">Transaksi</th><th class="num">Penjualan</th><th class="num">Tunai</th><th class="num">Non-tunai</th></tr></thead>
    <tbody>${trs}<tr class="tot" style="font-weight:700;border-top:2px solid var(--line)"><td>TOTAL</td><td class="num">${tot.n}</td><td class="num">${rp(tot.omzet)}</td><td class="num">${rp(tot.tunai)}</td><td class="num">${rp(tot.nontunai)}</td></tr></tbody></table></div>${sesi}`;
}
// Riwayat sesi kasir (buka/tutup laci) untuk bulan terpilih
function regLogHtml(){
  const log=(S.registerLog||[]).filter(s=>inMonth(String(s.closedAt||s.openedAt||"").slice(0,10)));
  if(!log.length)return "";
  if(isMobile()){
    const cards=log.map(s=>{const d=s.diff;return crow({icon:"🧾",title:esc(kasirName(s.openedBy)),sub:`${fmtClock(s.openedAt)} → ${fmtClock(s.closedAt)} · ${s.txnCount} nota`,amt:rp(s.cashSales),badges:`<div class="csub">Modal ${rp(s.openingFloat)} · Selisih <b style="color:${d===0?"var(--green)":"var(--red)"}">${(d>0?"+":"")+rp(d)}</b></div>`,onclick:`sessionDetail('${s.id}')`});}).join("");
    return `<div class="dsec" style="margin-top:22px"><h2 class="title" style="font-size:17px">Riwayat Sesi Kasir</h2></div><div class="clist">${cards}</div>`;
  }
  const trs=log.map(s=>{const d=s.diff;return `<tr style="cursor:pointer" onclick="sessionDetail('${s.id}')"><td>${esc(kasirName(s.openedBy))}<br><span class="mini">${fmtClock(s.openedAt)} → ${fmtClock(s.closedAt)}</span></td><td class="num">${s.txnCount}</td><td class="num">${rp(s.openingFloat)}</td><td class="num">${rp(s.cashSales)}</td><td class="num">${rp(s.expected)}</td><td class="num">${rp(s.closing)}</td><td class="num" style="color:${d===0?"var(--green)":"var(--red)"};font-weight:700">${(d>0?"+":"")+rp(d)}</td></tr>`;}).join("");
  return `<h2 class="title" style="font-size:18px;margin-top:26px">Riwayat Sesi Kasir</h2><div class="desc">Buka/tutup laci pada periode terpilih. <b>Ketuk sesi</b> untuk detail transaksi + cetak ulang. Selisih = uang fisik − kas seharusnya.</div>
    <div class="panel"><table><thead><tr><th>Kasir / Waktu</th><th class="num">Nota</th><th class="num">Modal</th><th class="num">Tunai</th><th class="num">Seharusnya</th><th class="num">Dihitung</th><th class="num">Selisih</th></tr></thead><tbody>${trs}</tbody></table></div>`;
}
// ---- Detail sesi kasir: settlement + daftar transaksi + cetak ulang ----
function sessionDetail(sid){
  const s=(S.registerLog||[]).find(x=>x.id===sid);if(!s){toast("Sesi tak ditemukan.");return;}
  const txns=S.notas.filter(n=>n.sessionId===sid).sort((a,b)=>(b.notaNo||"").localeCompare(a.notaNo||""));
  const d=s.diff;
  const rows=txns.map(n=>`<div class="crow"><div class="ci">🧾</div><div class="cmain"><div class="ctitle">${esc(n.notaNo||"-")}</div><div class="csub">${fmtDate(n.date)} · ${notaItems(n).length} item · ${esc(n.payMethod||"Tunai")}</div></div><div class="cright"><div class="camt">${rp(notaTotal(n))}</div></div><button class="crow-del" style="color:var(--red);font-size:16px" title="Cetak ulang struk" onclick="strukModal('${n.id}')">🖨</button></div>`).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Sesi Kasir — ${esc(kasirName(s.openedBy))}</h3>
    <div class="mini" style="margin:-6px 0 12px">${fmtClock(s.openedAt)} → ${fmtClock(s.closedAt)}</div>
    <div class="regsum" style="background:var(--bg);border-radius:12px;padding:6px 14px;margin-bottom:14px">
      <div class="rs" style="display:flex;justify-content:space-between;padding:7px 0"><span>Modal awal</span><b>${rp(s.openingFloat)}</b></div>
      <div class="rs" style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--line)"><span>Penjualan tunai</span><b>${rp(s.cashSales)}</b></div>
      <div class="rs" style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--line)"><span>Non-tunai</span><b>${rp(s.noncashSales)}</b></div>
      <div class="rs" style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--line);font-weight:800"><span>Kas seharusnya</span><b>${rp(s.expected)}</b></div>
      <div class="rs" style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--line)"><span>Uang dihitung</span><b>${rp(s.closing)}</b></div>
      <div class="rs" style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--line);font-weight:800"><span>Selisih</span><b style="color:${d===0?'var(--green)':'var(--red)'}">${(d>0?'+':'')+rp(d)}</b></div>
    </div>
    <button class="btn" style="width:100%;margin-bottom:14px" onclick="printRawBT(settlementText('${sid}'))">🖨 Cetak Ulang Laporan Settlement</button>
    <div class="mini" style="font-weight:700;color:var(--ink);margin-bottom:6px">Transaksi (${txns.length})</div>
    ${txns.length===0?'<div class="empty">Tak ada transaksi.</div>':`<div class="clist">${rows}</div>`}`);
}
function settlementText(sid){
  const s=(S.registerLog||[]).find(x=>x.id===sid);if(!s)return "";
  const {ctr,lr,dash}=rcptFmt();
  let t=ctr((BIZ.name||"Racikin").toUpperCase())+"\n"+ctr("LAPORAN TUTUP KASIR")+"\n"+dash+"\n";
  t+=lr("Kasir",kasirName(s.openedBy))+"\n"+lr("Buka",fmtClock(s.openedAt))+"\n"+lr("Tutup",fmtClock(s.closedAt))+"\n"+lr("Transaksi",s.txnCount+" nota")+"\n"+dash+"\n";
  t+=lr("Modal awal",rp(s.openingFloat))+"\n"+lr("Penjualan tunai",rp(s.cashSales))+"\n"+lr("Kas seharusnya",rp(s.expected))+"\n"+lr("Uang dihitung",rp(s.closing))+"\n"+lr("Selisih",(s.diff>0?"+":"")+rp(s.diff))+"\n"+lr("Penjualan non-tunai",rp(s.noncashSales))+"\n"+dash+"\n";
  t+=ctr("-- "+(BIZ.name||"Racikin")+" --")+"\n\n\n\n";
  return t;
}
// ---- Cetak ulang struk transaksi ----
function strukModal(id){const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  const method=n.payMethod||"Tunai";const bayar=notaPaid(n);   // uang yg benar-benar diterima (bukan diasumsikan lunas)
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>🖨 Cetak Ulang Struk</h3>
    <p class="mini" style="margin-bottom:14px">${esc(n.notaNo||"-")} · ${esc((store(n.storeId)||{}).name||"?")} · ${fmtDate(n.date)} · ${rp(notaTotal(n))}</p>
    <div style="display:flex;flex-direction:column;gap:8px">
      <button class="btn" onclick="printRawBT(posReceiptText('${id}',${bayar},0,'${esc(method)}'))">🖨 ${NativePrinter.isNative()?"Cetak Struk (Printer Bluetooth)":"Struk Printer Bluetooth (RawBT)"}</button>
      <button class="btn ghost" onclick="printReceipt('${id}',${bayar},0,'${esc(method)}')">Struk PDF / Printer biasa</button>
      <button class="btn ghost" onclick="waReceipt('${id}',${bayar},0,'${esc(method)}')">💬 Kirim via WhatsApp</button>
      <button class="btn ghost" onclick="emailReceiptModal('${id}')">✉️ Kirim ke Email</button>
    </div>
    ${NativePrinter.isNative()?"":'<div class="mini" style="text-align:center;margin-top:10px">Printer Bluetooth perlu app RawBT (Android).</div>'}`);}
// ---- Kirim struk ke email pelanggan ----
function emailReceiptModal(id){const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>✉️ Kirim Struk ke Email</h3>
    <p class="mini" style="margin-bottom:12px">${esc(n.notaNo||"-")} · ${rp(notaTotal(n))}</p>
    <label class="f">Email pelanggan</label>
    <input id="rcptEmail" type="email" inputmode="email" placeholder="nama@email.com" autofocus onkeydown="if(event.key==='Enter')sendReceiptEmail('${id}')">
    <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" id="rcptSendBtn" onclick="sendReceiptEmail('${id}')">Kirim</button></div>`);}
async function sendReceiptEmail(id){
  const email=(document.getElementById("rcptEmail").value||"").trim();
  if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){toast("Email tidak valid.");return;}
  const btn=document.getElementById("rcptSendBtn");if(btn){btn.disabled=true;btn.textContent="Mengirim…";}
  try{const r=await api("emailReceipt",{notaId:id,email});closeModal();toast(r&&r.sent?("Struk dikirim ke "+email+" ✓"):"Struk diproses — jika email tak masuk, cek pengaturan email hosting.");}
  catch(e){if(btn){btn.disabled=false;btn.textContent="Kirim";}}
}

