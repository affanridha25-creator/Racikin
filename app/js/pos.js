"use strict";
// pos.js — POS / kasir + sesi kasir
// ================= POS / KASIR =================
const POS_NAME="Umum (Kasir)";
let POS={cart:{},bayar:"",method:"Tunai",customer:"",q:"",disc:0};
function posStore(){return S.stores.find(s=>s.name===POS_NAME);}
function posSubtotal(){return Object.entries(POS.cart).reduce((a,[pid,q])=>a+q*((prod(pid)||{}).harga||0),0);}
function posDisc(){return Math.max(0,Math.min(+POS.disc||0,posSubtotal()));}
function posSvcRate(){const p=S.profile||{};return +p.svc_enabled?Math.max(0,+p.svc_rate||0):0;}   // +() krn TINYINT datang sbg string "0"/"1" (keduanya truthy di JS)
function posTaxRate(){const p=S.profile||{};return +p.tax_enabled?Math.max(0,+p.tax_rate||0):0;}
function posBase(){return posSubtotal()-posDisc();}
function posService(){return Math.round(posBase()*posSvcRate()/100);}
function posTax(){return Math.round((posBase()+posService())*posTaxRate()/100);}   // pajak atas base+service
function posTotal(){return posBase()+posService()+posTax();}
function canOversell(){return !!+((S.profile||{}).oversell);}   // +coerce: TINYINT datang sbg string "0"/"1"
// produk "dilacak stoknya"? — F&B made-to-order set 0 → jual bebas tanpa blokir/label stok
const tracksStock=p=>!p||p.trackStock==null||+p.trackStock!==0;
function posCount(){return Object.values(POS.cart).reduce((a,q)=>a+q,0);}
// ---- sesi kasir (buka/tutup laci) ----
function regOpen(){return S.register||null;}
function fmtClock(dt){if(!dt)return "";const m=String(dt).match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);return m?`${m[3]}/${m[2]} ${m[4]}:${m[5]}`:esc(String(dt));}
function sessNotas(){const r=regOpen();return r?S.notas.filter(n=>n.sessionId===r.id):[];}
function sessTotals(){let cash=0,noncash=0,cnt=0;sessNotas().forEach(n=>{const t=notaTotal(n);cnt++;if(n.payMethod==="Tunai")cash+=t;else noncash+=t;});return {cash,noncash,cnt};}
// Daftar + pembatalan transaksi sesi kasir yang SEDANG BERJALAN (stok balik & pembayaran dihapus).
// Sesi yang sudah ditutup tak bisa diubah (dijaga server) supaya settlement tetap konsisten.
function sessionTxnsModal(){
  if(!regOpen()){toast("Kasir belum dibuka.");return;}
  const list=sessNotas().sort((a,b)=>(b.notaNo||"").localeCompare(a.notaNo||""));
  const rows=list.map(n=>`<div class="crow"><div class="ci">🧾</div><div class="cmain"><div class="ctitle">${esc(n.notaNo||"-")}</div><div class="csub">${notaItems(n).length} item · ${esc(n.payMethod||"Tunai")} · ${rp(notaTotal(n))}</div></div><div style="display:flex;gap:6px"><button class="btn sm gray" onclick="strukModal('${n.id}')">🖨</button><button class="btn sm del" onclick="voidPosNota('${n.id}')">✕ Batal</button></div></div>`).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Transaksi Sesi Ini (${list.length})</h3>
    <p class="mini" style="margin-bottom:12px">Batalkan transaksi keliru — <b>stok kembali</b> &amp; pembayaran dihapus. Hanya berlaku selama sesi belum ditutup.</p>
    ${list.length===0?'<div class="empty">Belum ada transaksi di sesi ini.</div>':`<div class="clist">${rows}</div>`}`);
}
async function voidPosNota(id){
  const n=S.notas.find(x=>x.id===id);if(!n)return;
  if(!isOwner()){voidOtpModal(id);return;}   // staf → wajib persetujuan owner (OTP)
  if(!confirm(`Batalkan transaksi ${n.notaNo||""}? Stok kembali & pembayaran dihapus.`))return;
  await doVoidPos(id,null);
}
async function doVoidPos(id,otp){
  try{
    await api("deleteNota",otp?{id,otp}:{id});
    await reload();
    toast("Transaksi dibatalkan ✓");
    if(regOpen())sessionTxnsModal();else closeModal();
    rPOS();
  }catch(e){/* api sudah menampilkan toast error */ throw e;}
}
// Alur persetujuan owner via OTP (untuk staf)
function voidOtpModal(id){
  const n=S.notas.find(x=>x.id===id);if(!n)return;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>🔒 Perlu Izin Pemilik</h3>
    <p class="mini" style="margin-bottom:12px">Pembatalan transaksi <b>${esc(n.notaNo||"-")}</b> (${rp(notaTotal(n))}) harus disetujui pemilik. Sistem akan mengirim <b>kode OTP</b> ke email pemilik.</p>
    <button class="btn" id="otpReqBtn" style="width:100%" onclick="requestVoidOtp('${id}')">📧 Minta Kode ke Pemilik</button>
    <div id="otpArea"></div>`);
}
async function requestVoidOtp(id){
  const btn=document.getElementById("otpReqBtn");if(btn){btn.disabled=true;btn.textContent="Mengirim…";}
  try{
    const r=await api("requestVoidOtp",{notaId:id});
    const owners=(r.owners||[]).join(", ")||"email pemilik";
    document.getElementById("otpArea").innerHTML=`
      <div class="mini" style="margin:14px 0 6px">Kode dikirim ke <b>${esc(owners)}</b>. Minta kodenya ke pemilik lalu masukkan (berlaku 10 menit):</div>
      <input id="voidOtp" inputmode="numeric" maxlength="6" placeholder="6 digit" style="text-align:center;letter-spacing:8px;font-size:22px;font-weight:700" onkeydown="if(event.key==='Enter')submitVoidOtp('${id}')">
      <button class="btn del" style="width:100%;margin-top:12px" id="voidGoBtn" onclick="submitVoidOtp('${id}')">Batalkan Transaksi</button>
      <div class="mini" style="text-align:center;margin-top:8px"><a href="#" onclick="event.preventDefault();requestVoidOtp('${id}')">Kirim ulang kode</a></div>`;
    if(btn)btn.style.display="none";
    const f=document.getElementById("voidOtp");if(f)f.focus();
  }catch(e){if(btn){btn.disabled=false;btn.textContent="📧 Minta Kode ke Pemilik";}}
}
async function submitVoidOtp(id){
  const el=document.getElementById("voidOtp");const otp=(el?el.value:"").replace(/\D/g,"");
  if(otp.length<6){toast("Masukkan 6 digit kode.");return;}
  const b=document.getElementById("voidGoBtn");if(b){b.disabled=true;b.textContent="Memproses…";}
  try{await doVoidPos(id,otp);}
  catch(e){if(b){b.disabled=false;b.textContent="Batalkan Transaksi";}if(el){el.value="";el.focus();}}
}

function rPOS(){
  const reg=regOpen(),el=document.getElementById("v-pos");
  const popId=window._posPop||null;window._posPop=null;   // produk yg baru diketuk (animasi pop; hanya tampilan)
  if(!reg){
    el.innerHTML=`<div class="pk-lock"><div class="pk-lockic">🔒</div><h3>Kasir belum dibuka</h3>
      <p class="mini">Buka kasir dulu &amp; catat modal awal (uang tunai/kembalian di laci) sebelum mulai berjualan. Semua transaksi akan tercatat dalam sesi ini.</p>
      <button class="btn pk-pill" onclick="openRegisterModal()">🔓 Buka Kasir</button></div>`;
    return;
  }
  const st=sessTotals(),laci=reg.openingFloat+st.cash;
  const td=today(),omzetHariIni=S.notas.filter(n=>n.sessionId&&String(n.date||"").slice(0,10)===td).reduce((a,n)=>a+notaOmzet(n),0);
  const grid=S.products.map((p,i)=>{const tr=tracksStock(p);const s=stock(p.id);const q=POS.cart[p.id]||0;
    const hue=typeof avHue==="function"?avHue(p.name):20;
    const stl=!tr?"":(s<=0?"stok habis":(s<=5?"menipis · "+s:"stok "+s));
    const stc=!tr?"":(s<=0?"out":(s<=5?"low":"ok"));
    return `<button class="pcard pk-card ${tr&&s<=0?(canOversell()?"low":"out"):""} ${p.photo?"haspic":""} ${p.id===popId?"pk-pop":""}" style="--i:${Math.min(i,14)}" data-n="${esc(p.name.toLowerCase())}" onclick="posAdd('${p.id}')">${q>0?`<span class="qbadge">${q}</span>`:""}<div class="pcimg">${p.photo?`<img src="${p.photo}" alt="">`:`<span class="pk-ph" style="--h:${hue}">${esc((p.name||"?").trim().charAt(0).toUpperCase())}</span>`}${stc?`<span class="pk-stk ${stc}">${stl}</span>`:""}</div><div class="pcb"><div class="pn">${esc(p.name)}</div><div class="pp">${rp(p.harga)}</div></div></button>`;}).join("");
  const cnt_=posCount(),tot=posTotal();
  el.innerHTML=`<div class="pk-wrap${popId?" pk-still":""}">
    <div class="pk-hero">
      <i class="pk-b1"></i><i class="pk-b2"></i>
      <div class="pk-htop"><div><div class="pk-ht">🛒 Kasir</div><div class="pk-hs">Dibuka oleh <b>${esc(kasirName(reg.openedBy))}</b> · ${fmtClock(reg.openedAt)}</div></div><button class="pk-close" onclick="closeRegisterModal()">🔓 Tutup Kasir</button></div>
      <div class="pk-hnum"><span>Omzet hari ini</span><b>${popId?rp(omzetHariIni):cnt(omzetHariIni,"rp")}</b></div>
      <div class="rgchips">
        <div class="rgc"><span>Modal awal</span><b>${rp(reg.openingFloat)}</b></div>
        <div class="rgc"><span>Penjualan tunai</span><b>${rp(st.cash)}</b></div>
        <div class="rgc"><span>Non-tunai</span><b>${rp(st.noncash)}</b></div>
        <div class="rgc"><span>Transaksi</span><b>${st.cnt}</b></div>
        <div class="rgc hl"><span>Kas di laci</span><b>${rp(laci)}</b></div>
      </div>
    </div>
    <div class="pk-tools"><input class="possearch" id="posSearch" placeholder="🔎 Cari produk…" oninput="posFilterGrid(this.value)" value="${esc(POS.q)}">
    <button class="pk-sess" onclick="sessionTxnsModal()">🧾 Transaksi Sesi${st.cnt?` (${st.cnt})`:""}</button></div>
    ${S.products.length===0?'<div class="empty">Belum ada produk. Tambah dulu di menu Produk.</div>':`<div class="posgrid" id="posGrid">${grid}</div>`}
    ${cnt_>0?`<div class="posbar${popId?" bump":""}"><button class="posclr" onclick="event.stopPropagation();posClear()" title="Kosongkan">🗑</button><div class="posbarmain" onclick="posCheckoutModal()"><div><div class="pc">🛍 ${cnt_} item</div><div class="pv">${rp(tot)}</div></div><div class="go">Bayar →</div></div></div>`:""}</div>`;
  if(!popId)runCounts(el);
  if(POS.q)posFilterGrid(POS.q);
}
function openRegisterModal(){
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Buka Kasir</h3>
    <p class="mini" style="margin-bottom:12px">Masukkan <b>modal awal</b> — uang tunai/kembalian yang ada di laci saat mulai shift.</p>
    <label class="f">Modal Awal (Rp)</label>
    <input id="regFloat" inputmode="numeric" placeholder="0" oninput="this.value=grp(this.value)" autofocus>
    <label class="f" style="margin-top:12px">Catatan <span class="mini">(opsional)</span></label>
    <input id="regNote" placeholder="mis. shift pagi">
    <div style="margin-top:16px;display:flex;gap:8px"><button class="btn gray" onclick="closeModal()">Batal</button><button class="btn" style="flex:1;background:var(--green)" onclick="doOpenRegister()">🔓 Buka Kasir</button></div>`);
}
async function doOpenRegister(){
  const floatV=+digits(document.getElementById("regFloat").value)||0;
  const note=document.getElementById("regNote").value.trim();
  try{await api("openRegister",{openingFloat:floatV,note});await closeModal();await reload();toast("Kasir dibuka ✓");rPOS();}catch(e){}
}
function closeRegisterModal(){
  const reg=regOpen();if(!reg){toast("Kasir belum dibuka.");return;}
  const st=sessTotals(),expected=reg.openingFloat+st.cash;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Tutup Kasir</h3>
    <div class="regsum">
      <div class="rs"><span>Modal awal</span><b>${rp(reg.openingFloat)}</b></div>
      <div class="rs"><span>Penjualan tunai</span><b>${rp(st.cash)}</b></div>
      <div class="rs tot"><span>Kas seharusnya di laci</span><b>${rp(expected)}</b></div>
      <div class="rs"><span>Penjualan non-tunai</span><b>${rp(st.noncash)}</b></div>
      <div class="rs"><span>Jumlah transaksi</span><b>${st.cnt}</b></div>
    </div>
    <label class="f" style="margin-top:12px">Uang Tunai Dihitung Fisik (Rp)</label>
    <input id="regClose" inputmode="numeric" placeholder="${grp(expected)}" oninput="regDiffCalc(this)" autofocus>
    <div class="poskembali" id="regDiffRow" style="display:none"><span>Selisih</span><b id="regDiff">—</b></div>
    <label class="f" style="margin-top:10px">Catatan <span class="mini">(opsional)</span></label>
    <input id="regCloseNote" placeholder="mis. penyebab selisih">
    <div style="margin-top:16px;display:flex;gap:8px"><button class="btn gray" onclick="closeModal()">Batal</button><button class="btn del" style="flex:1" onclick="doCloseRegister()">Tutup &amp; Simpan</button></div>`);
}
function regDiffCalc(el){el.value=grp(el.value);const reg=regOpen();if(!reg)return;const st=sessTotals(),expected=reg.openingFloat+st.cash,c=+digits(el.value)||0,d=c-expected;const row=document.getElementById("regDiffRow"),out=document.getElementById("regDiff");row.style.display="flex";out.textContent=(d>0?"+":"")+rp(d);row.style.background=d===0?"var(--green-bg)":"#fbe7e5";out.style.color=d===0?"var(--green)":"var(--red)";}
async function doCloseRegister(){
  // Jangan tutup kasir selagi ada transaksi offline belum tersinkron (bisa hilang dari rekap).
  const nq=await queueCount();
  if(nq>0){toast(`Ada ${nq} transaksi offline belum tersinkron. Sinkronkan dulu (butuh internet) sebelum tutup kasir.`);if(navigator.onLine)syncQueue(true);return;}
  if(OFFLINE||!navigator.onLine){toast("Tutup kasir butuh internet. Coba lagi saat online.");return;}
  const closing=+digits(document.getElementById("regClose").value)||0;
  const note=document.getElementById("regCloseNote").value.trim();
  try{const r=await api("closeRegister",{closingCash:closing,note});await reload();closeRegisterSummary(r.summary);}catch(e){}
}
function closeRegisterSummary(s){
  if(!s){closeModal();rPOS();return;}
  window._lastReg=s;const diff=s.diff;
  openModal(`<div class="pos-done"><div class="ok" style="background:${diff===0?"var(--green-bg)":"#fff3e0"};color:${diff===0?"var(--green)":"var(--amber)"}">${diff===0?"✓":"≈"}</div>
    <h3 style="margin-bottom:2px">Kasir Ditutup</h3><div class="mini" style="margin-bottom:12px">${esc(kasirName(s.openedBy))} · ${s.count} transaksi</div>
    <div class="regsum" style="text-align:left">
      <div class="rs"><span>Modal awal</span><b>${rp(s.openingFloat)}</b></div>
      <div class="rs"><span>Penjualan tunai</span><b>${rp(s.cashSales)}</b></div>
      <div class="rs tot"><span>Kas seharusnya</span><b>${rp(s.expected)}</b></div>
      <div class="rs"><span>Uang dihitung</span><b>${rp(s.closing)}</b></div>
      <div class="rs tot"><span>Selisih</span><b style="color:${diff===0?"var(--green)":"var(--red)"}">${(diff>0?"+":"")+rp(diff)}</b></div>
      <div class="rs"><span>Penjualan non-tunai</span><b>${rp(s.noncashSales)}</b></div>
    </div>
    <button class="btn" style="width:100%;margin-top:12px" onclick="printRawBT(regReceiptText(window._lastReg))">🖨 Cetak Laporan (Bluetooth)</button>
    <button class="btn" style="width:100%;margin-top:8px;background:var(--green)" onclick="closeModal();rPOS()">Selesai</button>
  </div>`);
}
// Helper format struk thermal 32 kolom (dipakai regReceiptText/posReceiptText/settlementText)
function rcptFmt(){const W=32;
  const cut=s=>String(s).slice(0,W);
  const ctr=s=>{s=cut(s);return " ".repeat(Math.max(0,Math.floor((W-s.length)/2)))+s;};
  const lr=(l,r)=>{l=String(l);r=String(r);if(l.length+r.length>=W)l=l.slice(0,Math.max(0,W-r.length-1));return l+" ".repeat(Math.max(1,W-l.length-r.length))+r;};
  // pecah teks panjang jadi beberapa baris ≤W (nama produk/alamat tak lagi kepotong)
  const wrap=s=>{const out=[];let line="";String(s).split(/\s+/).filter(Boolean).forEach(w=>{
    while(w.length>W){if(line){out.push(line);line="";}out.push(w.slice(0,W));w=w.slice(W);}
    if(!line)line=w;else if((line+" "+w).length<=W)line+=" "+w;else{out.push(line);line=w;}});
    if(line)out.push(line);return out.join("\n");};
  const ctrWrap=s=>wrap(s).split("\n").map(ctr).join("\n");
  return {W,cut,ctr,lr,wrap,ctrWrap,dash:"-".repeat(W)};
}
function regReceiptText(s){
  if(!s)return "";const {ctr,lr,dash}=rcptFmt();
  let t=ctr((BIZ.name||"Racikin").toUpperCase())+"\n"+ctr("LAPORAN TUTUP KASIR")+"\n"+dash+"\n";
  t+=lr("Kasir",kasirName(s.openedBy))+"\n"+lr("Transaksi",s.count+" nota")+"\n"+dash+"\n";
  t+=lr("Modal awal",rp(s.openingFloat))+"\n";
  t+=lr("Penjualan tunai",rp(s.cashSales))+"\n";
  t+=lr("Kas seharusnya",rp(s.expected))+"\n";
  t+=lr("Uang dihitung",rp(s.closing))+"\n";
  t+=lr("Selisih",(s.diff>0?"+":"")+rp(s.diff))+"\n";
  t+=lr("Penjualan non-tunai",rp(s.noncashSales))+"\n"+dash+"\n";
  t+=ctr("-- "+(BIZ.name||"Racikin")+" --")+"\n\n\n\n";
  return t;
}
function posFilterGrid(q){POS.q=q;q=(q||"").toLowerCase();document.querySelectorAll("#posGrid .pcard").forEach(c=>{c.style.display=c.dataset.n.includes(q)?"":"none";});}
function posAdd(pid){const p=prod(pid);if(!p)return;const st=stock(pid);const q=POS.cart[pid]||0;if(tracksStock(p)&&!canOversell()&&q>=st){toast("Stok "+p.name+" tinggal "+st);return;}POS.cart[pid]=q+1;window._posPop=pid;rPOS();}
function posInc(pid){const p=prod(pid);const st=stock(pid);const q=POS.cart[pid]||0;if(tracksStock(p)&&!canOversell()&&q>=st){toast("Stok tinggal "+st);return;}POS.cart[pid]=q+1;posCheckoutModal();}
function posDec(pid){const q=POS.cart[pid]||0;if(q<=1)delete POS.cart[pid];else POS.cart[pid]=q-1;if(posCount()===0){closeModal();rPOS();}else posCheckoutModal();}
function posCheckoutModal(){
  if(posCount()===0){toast("Keranjang kosong.");return;}
  const sub=posSubtotal(),tot=posTotal();
  const lines=Object.entries(POS.cart).map(([pid,q])=>{const p=prod(pid)||{};const lt=q*(+p.harga||0);
    return `<div class="crt"><div class="pk-ci">${p.photo?`<img src="${p.photo}" alt="">`:"🍽"}</div><div class="cn"><b>${esc(p.name||"?")}</b><span>${rp(p.harga)}</span></div><div class="stp"><button onclick="posDec('${pid}')">−</button><span>${q}</span><button onclick="posInc('${pid}')">+</button></div><div class="lt">${rp(lt)}</div></div>`;}).join("");
  const isCash=POS.method==="Tunai";
  const bayar=isCash?(+digits(POS.bayar)||0):tot;
  const kembali=Math.max(0,bayar-tot);
  comboReset();
  const custCombo=combo(S.stores.filter(s=>s.name!==POS_NAME).map(s=>({id:s.id,label:s.name})),POS.customer,(id)=>{POS.customer=id;},"Umum / pelanggan langsung");
  const MI={Tunai:["💵","Uang pas / kembalian"],Transfer:["🏦","Transfer bank"],QRIS:["📲","Scan QR"]};
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Pembayaran</h3>
    <div class="pk-co">
    <div class="pk-cart">
      <div class="pk-carth"><span class="mini">🛍 ${posCount()} item di keranjang</span><button class="btn sm del" onclick="posClear()">🗑 Kosongkan</button></div>
      <div class="pk-lines">${lines}</div>
    </div>
    <div class="pk-sum">
      <div class="crt" style="border:none;padding:4px 0"><div class="cn">Subtotal</div><div>${rp(sub)}</div></div>
      <div class="crt" style="border:none;padding:4px 0;align-items:center"><div class="cn">Diskon (Rp)</div><input id="posDisc" inputmode="numeric" style="width:130px;text-align:right;padding:8px 10px" placeholder="0" value="${POS.disc?grp(POS.disc):""}" oninput="posDiscInput(this)"></div>
      ${posSvcRate()>0?`<div class="crt" style="border:none;padding:4px 0"><div class="cn">Service (${posSvcRate()}%)</div><div id="posSvcL">${rp(posService())}</div></div>`:""}
      ${posTaxRate()>0?`<div class="crt" style="border:none;padding:4px 0"><div class="cn">Pajak (${posTaxRate()}%)</div><div id="posTaxL">${rp(posTax())}</div></div>`:""}
      <div class="pk-tot"><span>Total bayar</span><b id="posTotL">${rp(tot)}</b></div>
    </div>
    <label class="f" style="margin-top:12px">Pelanggan <span class="mini">(opsional)</span></label>${custCombo}
    <label class="f" style="margin-top:14px">Metode Bayar</label>
    <div class="posseg pk-meths">${["Tunai","Transfer","QRIS"].map(x=>`<button class="${POS.method===x?"on":""}" onclick="posSetMethod('${x}')"><i>${MI[x][0]}</i><b>${x}</b><small>${MI[x][1]}</small></button>`).join("")}</div>
    ${isCash?`<label class="f">Uang Diterima</label><input id="posBayar" class="pk-bayar" inputmode="numeric" placeholder="${grp(tot)}" value="${POS.bayar?grp(POS.bayar):""}" oninput="posBayarInput(this)">
    <div class="poskembali pk-kemb"><span>Kembalian</span><b id="posKembali">${rp(kembali)}</b></div>`:`<p class="mini pk-pas" style="margin-top:6px">Pembayaran ${esc(POS.method)} dianggap pas — <b id="posTotL2">${rp(tot)}</b>.</p>`}
    <div class="pk-act"><button class="btn gray pk-pill" onclick="closeModal()">Batal</button> <button class="btn pk-pill pk-go" style="flex:1" id="posSelesai" onclick="posCheckout()">✓ Selesaikan · ${rp(tot)}</button></div>
    </div>`);
}
function posDiscInput(el){el.value=grp(el.value);POS.disc=+digits(el.value)||0;const t=posTotal();
  const set=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=v;};
  set("posSvcL",rp(posService()));set("posTaxL",rp(posTax()));
  set("posTotL",rp(t));set("posTotL2",rp(t));set("posSelesai","✓ Selesaikan · "+rp(t));
  const pb=document.getElementById("posBayar");if(pb)pb.placeholder=grp(t);
  const kb=document.getElementById("posKembali");if(kb)kb.textContent=rp(Math.max(0,(+digits(POS.bayar)||0)-t));}
function posClear(){if(posCount()===0)return;if(!confirm("Kosongkan keranjang?"))return;POS.cart={};POS.bayar="";POS.disc=0;closeModal();rPOS();toast("Keranjang dikosongkan.");}
function posSetMethod(m){POS.method=m;if(m!=="Tunai")POS.bayar="";posCheckoutModal();}
function posBayarInput(el){el.value=grp(el.value);POS.bayar=digits(el.value);const k=Math.max(0,(+POS.bayar||0)-posTotal());const kb=document.getElementById("posKembali");if(kb)kb.textContent=rp(k);}
async function ensurePosStore(){let s=posStore();if(s)return s.id;await api("saveStore",{store:{id:null,name:POS_NAME,contact:"",address:"Penjualan langsung / kasir"}});await reload();s=posStore();return s?s.id:"";}
async function posCheckout(){
  const tot=posTotal();if(tot<=0){toast("Keranjang kosong.");return;}
  if(POS.method==="QRIS"){
    const qr=(S.profile&&S.profile.qris)||"";
    if(qr&&qrisValid(qr)){posShowQris(tot);return;}
    toast("Atur Kode QRIS di Profil Usaha dulu (atau pilih metode lain).");return;
  }
  const isCash=POS.method==="Tunai";
  const bayar=isCash?(+digits(POS.bayar)||0):tot;
  if(isCash&&bayar<tot){toast("Uang diterima kurang dari total.");return;}
  await posFinalize(bayar,Math.max(0,bayar-tot),POS.method);
}
function posShowQris(total){
  const dyn=qrisDynamic(S.profile.qris,total);
  if(!dyn){toast("Kode QRIS tidak valid.");return;}
  openModal(`<button class="close" onclick="posCheckoutModal()">×</button><h3>Scan untuk Bayar</h3>
    <div style="text-align:center">
      <div class="mini" style="margin-bottom:2px">${esc(BIZ.name||"")}</div>
      <div class="pk-qamt" style="font-size:27px;font-weight:800;color:var(--red);margin-bottom:12px">${rp(total)}</div>
      <div class="pk-qbox" style="display:inline-block;background:#fff;padding:12px;border-radius:16px;box-shadow:0 8px 22px rgba(0,0,0,.12)"><canvas id="qrCanvas" style="display:block;width:260px;height:260px;image-rendering:pixelated"></canvas></div>
      <div class="mini" style="margin:12px auto 2px;max-width:280px">Pelanggan scan pakai <b>GoPay / OVO / Dana / ShopeePay / m-banking</b> — nominal sudah otomatis terisi.</div>
    </div>
    <div style="margin-top:16px;display:flex;gap:8px"><button class="btn gray" onclick="posCheckoutModal()">Kembali</button><button class="btn" style="flex:1" onclick="posFinalize(${total},0,'QRIS')">✓ Sudah Dibayar</button></div>`);
  renderQR(document.getElementById("qrCanvas"),dyn);
}
async function posFinalize(bayar,kembali,method){
  if(posCount()===0){toast("Keranjang kosong.");return;}
  const tot=posTotal();
  const reg=regOpen();if(!reg){toast("Buka kasir dulu sebelum transaksi.");closeModal();rPOS();return;}
  let storeId=POS.customer;
  if(!storeId){const ps=posStore();if(ps)storeId=ps.id;else if(!OFFLINE&&navigator.onLine){try{storeId=await ensurePosStore();}catch(e){}}}
  if(!storeId){toast(OFFLINE?"Transaksi offline pertama perlu sekali online dulu.":"Gagal menyiapkan pelanggan.");return;}
  const items=Object.entries(POS.cart).map(([pid,q])=>{const p=prod(pid)||{};return {productId:pid,qty:q,harga:+p.harga||0,hpp:+p.hpp||0,kind:"jual"};});
  const base={date:today(),storeId,notaNo:nextNotaNo(today()),items,sessionId:reg.id,payMethod:method,discount:posDisc()};
  // OFFLINE → antre langsung
  if(OFFLINE||!navigator.onLine){ await posEnqueue({...base,id:cid("n")},tot,bayar,kembali,method); return; }
  try{
    // ATOMIK: simpan nota + catat pembayaran dalam satu transaksi server (cegah piutang hantu)
    const res=await api("posSale",{nota:{...base,id:null}});
    POS.cart={};POS.bayar="";POS.customer="";POS.disc=0;
    await reload();
    const sTot=(res&&res.total!=null)?res.total:tot;
    const sBayar=method==="Tunai"?bayar:sTot;
    const sKembali=method==="Tunai"?Math.max(0,bayar-sTot):0;
    posSuccess(res.id,sTot,sBayar,sKembali,method);
  }catch(e){
    if(isNetErr(e)){ OFFLINE=true; await posEnqueue({...base,id:cid("n")},tot,bayar,kembali,method); return; }
    /* error tervalidasi (mis. stok) → toast sudah tampil oleh api() */
  }
}
// Simpan transaksi ke antrean offline (aman: tulis ke IndexedDB DULU, baru tampilkan sukses).
async function posEnqueue(nota,tot,bayar,kembali,method){
  const clock=new Date().toTimeString().slice(0,5);
  const item={nota,total:tot,bayar,kembali,method,clock,ts:Date.now()};
  const q=await queueGet(); q.push(item); await queueSet(q);
  S.notas.unshift(queueItemToNota(item)); _stockCache=null;   // optimistik: langsung tampil & terhitung
  POS.cart={};POS.bayar="";POS.customer="";POS.disc=0;
  await updateOfflineUI();
  const sBayar=method==="Tunai"?bayar:tot, sKembali=method==="Tunai"?Math.max(0,bayar-tot):0;
  posSuccess(nota.id,tot,sBayar,sKembali,method,true);
}
function posSuccess(id,total,bayar,kembali,method,pending){
  openModal(`<div class="pos-done pk-done"><div class="ok"${pending?' style="background:#fff3e0;color:var(--amber)"':''}>${pending?"⏳":"✓"}</div><h3 style="margin-bottom:6px">${pending?"Tersimpan (Offline)":"Transaksi Berhasil"}</h3>
    <div class="mini" style="margin-bottom:14px">Total ${rp(total)} · ${esc(method)}${pending?" · akan tersinkron saat online":""}</div>
    ${method==="Tunai"?`<div class="poskembali pk-kemb pk-kembdone" style="justify-content:center;gap:14px;margin-bottom:16px">Kembalian <b>${rp(kembali)}</b></div>`:""}
    <div style="display:flex;flex-direction:column;gap:8px">
      <button class="btn" onclick="printRawBT(posReceiptText('${id}',${bayar},${kembali},'${esc(method)}'))">🖨 Cetak Struk (Printer Bluetooth)</button>
      <div style="display:flex;gap:8px"><button class="btn ghost" style="flex:1" onclick="printReceipt('${id}',${bayar},${kembali},'${esc(method)}')">Struk PDF</button><button class="btn ghost" style="flex:1" onclick="waReceipt('${id}',${bayar},${kembali},'${esc(method)}')">💬 WA</button><button class="btn ghost" style="flex:1" onclick="emailReceiptModal('${id}')">✉️ Email</button></div>
      <button class="btn pk-pill" style="background:var(--green)" onclick="closeModal();rPOS()">+ Transaksi Baru</button>
      ${NativePrinter.isNative()?"":'<div class="mini" style="text-align:center;margin-top:2px">Printer Bluetooth perlu app <b>RawBT</b> (Android).</div>'}
    </div></div>`);
}
// Stempel waktu struk: "11 Jul 2026" atau "11 Jul 2026 · 14.35" (dari "YYYY-MM-DD[ HH:MM]")
function fmtStamp(s,sep){
  if(!s)return "-";
  const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
  if(!m)return "-";
  const d=(+m[3])+" "+MON[(+m[2])-1]+" "+m[1];
  return m[4]?d+(sep||" ")+m[4]+"."+m[5]:d;
}
// Pilih stempel: pakai tanggal bisnis (ndate/pdate); sertakan JAM hanya bila entri sistem di hari yg sama
// (kalau nota/bayar di-backdate, jam entri tak relevan → tampilkan tanggal saja).
function stampOf(bizDate,sysTs){return (sysTs&&String(sysTs).slice(0,10)===String(bizDate||"").slice(0,10))?sysTs:(bizDate||sysTs);}
// pembayaran terakhir sebuah nota (berdasar waktu entri, jatuh ke tanggal)
function lastPay(n){const ps=n.payments||[];if(!ps.length)return null;let best=null,bk="";ps.forEach(p=>{const k=p.createdAt||((p.date||"")+" 00:00");if(k>=bk){bk=k;best=p;}});return best;}
// Stempel untuk struk: lunas → "Dibayar" + tanggal bayar; belum → "Dibuat" + tanggal nota dibuat.
function notaWhen(n){
  if(notaTotal(n)>0&&notaDue(n)<=0){const p=lastPay(n);if(p)return {label:"Dibayar",stamp:stampOf(p.date,p.createdAt)};}
  return {label:"Dibuat",stamp:stampOf(n.date,n.createdAt)};
}
// Struk teks 58mm (32 kolom) untuk printer thermal
function posReceiptText(id,bayar,kembali,method){
  const n=S.notas.find(x=>x.id===id);if(!n)return "";
  const p=S.profile||{};const {ctr,lr,dash,wrap,ctrWrap}=rcptFmt();
  let t=ctrWrap((BIZ.name||"Racikin").toUpperCase())+"\n";
  if(p.address)t+=ctrWrap(p.address)+"\n";
  // kontak: telp & WA digabung; kalau sama tak dobel
  let kontak="";
  if(p.phone&&p.whatsapp)kontak=(p.phone===p.whatsapp)?("Telp/WA "+p.phone):("Telp "+p.phone+"  WA "+p.whatsapp);
  else if(p.phone)kontak="Telp "+p.phone;else if(p.whatsapp)kontak="WA "+p.whatsapp;
  if(kontak)t+=ctrWrap(kontak)+"\n";
  t+=dash+"\n";
  if(n.notaNo)t+=wrap(n.notaNo)+"\n";
  const _w=notaWhen(n);
  t+=lr(_w.label,fmtStamp(_w.stamp))+"\n";
  t+=lr("Kasir",(BIZ.user||"-"))+"\n";
  // nama toko/pelanggan di struk (nota distribusi & POS dg pelanggan terpilih; toko internal kasir tak ditampilkan)
  const _st=store(n.storeId);if(_st&&_st.name&&_st.name!==POS_NAME)t+=wrap("Kepada: "+_st.name)+"\n";
  t+=dash+"\n";
  notaItems(n).forEach(it=>{const pr=prod(it.productId)||{};t+=wrap(pr.name||"?")+"\n"+lr("  "+it.qty+" x "+rp(it.harga),rp((+it.qty||0)*(+it.harga||0)))+"\n";});
  t+=dash+"\n";
  const svc=notaService(n),tax=notaTax(n),base=notaBase(n);
  if(notaDisc(n)>0||svc>0||tax>0){t+=lr("Subtotal",rp(notaSubtotal(n)))+"\n";if(notaDisc(n)>0)t+=lr("Diskon","-"+rp(notaDisc(n)))+"\n";}
  if(svc>0)t+=lr("Service"+ratePct(notaSvcRate(n)),rp(svc))+"\n";
  if(tax>0)t+=lr("Pajak"+ratePct(notaTaxRate(n)),rp(tax))+"\n";
  t+=lr("TOTAL",rp(notaTotal(n)))+"\n";
  if(method){t+=lr("Bayar ("+method+")",rp(bayar))+"\n";if(method==="Tunai")t+=lr("Kembalian",rp(kembali||0))+"\n";}
  if(notaDue(n)>0)t+=lr("Sisa",rp(notaDue(n)))+"\n";
  t+=dash+"\n";
  const footer=(p.footer||"").trim();
  t+=(footer?ctrWrap(footer):ctr("Terima kasih :)"))+"\n";
  t+=ctr("-- "+(BIZ.name||"Racikin")+" --")+"\n\n\n\n";
  return t;
}
