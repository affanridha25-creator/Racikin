"use strict";
// rekap.js — rekap kasir & sesi (v2: hero, kartu per kasir, kartu sesi, modal detail)
// ================= REKAP KASIR =================
let _userNames={};   // email -> nama (dari daftar pengguna)
function kasirName(email){return _userNames[email]||email||"(tak tercatat)";}
// avatar inisial berwarna (pakai avHue dari toko.js)
function rkAv(name,cls){const h=avHue(name);return `<div class="rk-av ${cls||""}" style="background:hsl(${h},75%,92%);color:hsl(${h},55%,32%)">${esc((String(name||"?").trim()[0]||"?").toUpperCase())}</div>`;}
// selisih kas -> kelas + label
function rkDiff(d){d=+d||0;return d===0?{c:"ok",l:"Pas",t:"Rp 0"}:d<0?{c:"neg",l:"Kurang",t:"−"+rp(Math.abs(d))}:{c:"pos",l:"Lebih",t:"+"+rp(d)};}
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
  const mob=isMobile();
  const pT=tot.omzet>0?tot.tunai/tot.omzet*100:0,pN=tot.omzet>0?100-pT:0;
  const avg=tot.n?tot.omzet/tot.n:0;
  const expBtn=fnotas.length?`<button class="rk-exp" onclick="exportPenjualan('pos')">⬇️ CSV</button>`:"";
  const hero=`<div class="rk-hero">
    <div class="rk-hbg" aria-hidden="true"><i></i><i></i></div>
    <div class="rk-htop"><div><div class="rk-ht">${mob?"Kasir (POS)":"Penjualan Kasir (POS)"}</div><div class="rk-hs">Rekap per kasir &amp; per sesi laci</div></div><div class="rk-hm">${monthBar()}${expBtn}</div></div>
    <div class="rk-hl">🛒 Omzet kasir · ${monthLabelFull(FILTER_MONTH)}</div>
    <div class="rk-hv">${cnt(tot.omzet)}</div>
    <div class="rk-hp"><span class="hpill">🧾 ${tot.n} transaksi</span><span class="hpill">Rata-rata ${rp(avg)}</span></div>
    <div class="rk-split"><div class="rk-seg" title="Tunai vs non-tunai">${tot.omzet>0?`<i class="t" style="width:${pT.toFixed(1)}%"></i><i class="n" style="width:${pN.toFixed(1)}%"></i>`:""}</div>
      <div class="rk-leg"><span><b class="dt"></b>Tunai ${rp(tot.tunai)} <em>${pT.toFixed(0)}%</em></span><span><b class="dn"></b>Non-tunai ${rp(tot.nontunai)} <em>${pN.toFixed(0)}%</em></span></div></div>
  </div>`;
  let kas;
  if(rows.length===0)kas=`<div class="empty">Belum ada penjualan kasir pada periode ini. 🛒</div>`;
  else kas=`<div class="rk-sec"><h3>👥 Per Kasir</h3><span>${rows.length} kasir</span></div>
    <div class="rk-grid">${rows.map((r,k)=>{const nm=kasirName(r.k),share=tot.omzet>0?r.omzet/tot.omzet*100:0,tp=r.omzet>0?r.tunai/r.omzet*100:0;
      return `<div class="rk-card" style="--k:${k}">
        <div class="rk-ctop">${rkAv(nm)}<div class="rk-cid"><div class="rk-cn">${esc(nm)}${k===0&&rows.length>1?' <span class="rk-crown" title="Penjualan tertinggi">🏆</span>':""}</div><div class="rk-cs">${r.n} transaksi · rata-rata ${rp(r.n?r.omzet/r.n:0)}</div></div><div class="rk-cv">${rp(r.omzet)}</div></div>
        <div class="rk-bar" title="Kontribusi ${share.toFixed(0)}% dari total"><i style="width:${share.toFixed(1)}%"></i></div>
        <div class="rk-cmeta"><span>Kontribusi <b>${share.toFixed(0)}%</b></span><span class="g">Tunai <b>${rp(r.tunai)}</b></span><span class="a">Non-tunai <b>${rp(r.nontunai)}</b></span></div>
        <div class="rk-mix" title="Porsi tunai"><i style="width:${tp.toFixed(1)}%"></i></div>
      </div>`;}).join("")}</div>`;
  el.innerHTML=`${hero}${kas}${sesi}`;
  dashAnimate(el);
}
// Riwayat sesi kasir (buka/tutup laci) untuk bulan terpilih
function regLogHtml(){
  const log=(S.registerLog||[]).filter(s=>inMonth(String(s.closedAt||s.openedAt||"").slice(0,10)));
  if(!log.length)return "";
  const bad=log.filter(s=>(+s.diff||0)!==0).length;
  const cards=log.map((s,k)=>{const df=rkDiff(s.diff),nm=kasirName(s.openedBy);
    return `<div class="rk-sess ${df.c}" style="--k:${k}" onclick="sessionDetail('${esc(s.id)}')">
      <div class="rk-stop">${rkAv(nm,"sm")}<div class="rk-cid"><div class="rk-cn">${esc(nm)}</div><div class="rk-cs">${fmtClock(s.openedAt)} → ${fmtClock(s.closedAt)} · ${s.txnCount} nota</div></div><div class="rk-df ${df.c}"><small>${df.l}</small>${df.t}</div></div>
      <div class="rk-sgrid"><div><span>Modal</span><b>${rp(s.openingFloat)}</b></div><div><span>Tunai</span><b>${rp(s.cashSales)}</b></div><div><span>Seharusnya</span><b>${rp(s.expected)}</b></div><div><span>Dihitung</span><b>${rp(s.closing)}</b></div></div>
    </div>`;}).join("");
  return `<div class="rk-sec"><h3>🧾 Riwayat Sesi Kasir</h3><span>${bad?`<b class="rk-warn">${bad} sesi selisih</b>`:"semua kas pas ✓"}</span></div>
    <div class="rk-note">Ketuk sesi untuk detail transaksi &amp; cetak ulang. Selisih = uang fisik − kas seharusnya.</div>
    <div class="rk-grid">${cards}</div>`;
}
// ---- Detail sesi kasir: settlement + daftar transaksi + cetak ulang ----
function sessionDetail(sid){
  const s=(S.registerLog||[]).find(x=>x.id===sid);if(!s){toast("Sesi tak ditemukan.");return;}
  const txns=S.notas.filter(n=>n.sessionId===sid).sort((a,b)=>(b.notaNo||"").localeCompare(a.notaNo||""));
  const df=rkDiff(s.diff),nm=kasirName(s.openedBy);
  const rows=txns.map(n=>`<div class="rk-trx"><div class="rk-ti">🧾</div><div class="rk-tm"><b>${esc(n.notaNo||"-")}</b><span>${fmtDate(n.date)} · ${notaItems(n).length} item · ${esc(n.payMethod||"Tunai")}</span></div><div class="rk-ta">${rp(notaTotal(n))}</div><button class="rk-tp" title="Cetak ulang struk" onclick="strukModal('${n.id}')">🖨</button></div>`).join("");
  const rs=(l,v,cl)=>`<div class="rk-rs ${cl||""}"><span>${l}</span><b>${v}</b></div>`;
  openModal(`<div class="rk-md">
    <div class="rk-mh"><button class="close" onclick="closeModal()">×</button>
      <div class="rk-mhrow">${rkAv(nm,"sm")}<div><div class="rk-mt">Sesi Kasir — ${esc(nm)}</div><div class="rk-ms">${fmtClock(s.openedAt)} → ${fmtClock(s.closedAt)} · ${s.txnCount} nota</div></div></div>
      <div class="rk-mdiff ${df.c}"><span>Selisih kas · ${df.l}</span><b>${df.t}</b></div></div>
    <div class="rk-mb">
      <div class="rk-rsum">${rs("Modal awal",rp(s.openingFloat))}${rs("Penjualan tunai",rp(s.cashSales))}${rs("Non-tunai",rp(s.noncashSales))}${rs("Kas seharusnya",rp(s.expected),"b")}${rs("Uang dihitung",rp(s.closing))}${rs("Selisih",df.t,"b "+df.c)}</div>
      <button class="btn rk-pbtn" onclick="printRawBT(settlementText('${sid}'))">🖨 Cetak Ulang Laporan Settlement</button>
      <div class="rk-tt">Transaksi (${txns.length})</div>
      ${txns.length===0?'<div class="empty">Tak ada transaksi.</div>':`<div class="rk-tl">${rows}</div>`}
    </div></div>`);
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

