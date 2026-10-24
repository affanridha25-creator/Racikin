"use strict";
// print-thermal.js — cetak thermal & pengaturan printer
// ====== Cetak thermal ======
// Di dalam APLIKASI ANDROID (Capacitor): cetak LANGSUNG ke printer Bluetooth (Classic SPP / BLE)
// lewat plugin native ThermalPrinter — bisa sekalian cetak LOGO (raster ESC/POS).
// Di web/PWA biasa: fallback ke app RawBT (perlu install terpisah), teks saja.
const NativePrinter={
  get plugin(){try{return (window.Capacitor&&window.Capacitor.isNativePlatform&&window.Capacitor.isNativePlatform()&&window.Capacitor.Plugins&&window.Capacitor.Plugins.ThermalPrinter)||null;}catch(e){return null;}},
  isNative(){return !!this.plugin;},
  saved(){try{return JSON.parse(localStorage.getItem("racikin_printer")||"null");}catch(e){return null;}},
  save(p){try{localStorage.setItem("racikin_printer",JSON.stringify(p));}catch(e){}},
  clear(){try{localStorage.removeItem("racikin_printer");}catch(e){}},
  logoOn(){return localStorage.getItem("racikin_print_logo")!=="0";},   // default nyala
  setLogo(v){try{localStorage.setItem("racikin_print_logo",v?"1":"0");}catch(e){}},
  async ensurePerm(){const p=this.plugin;if(!p)return true;try{const r=await p.requestPermissions();return !!(r&&r.granted);}catch(e){return false;}},
};
function b64bytes(u8){let s="";const CH=0x8000;for(let i=0;i<u8.length;i+=CH)s+=String.fromCharCode.apply(null,u8.subarray(i,i+CH));return btoa(s);}
function loadImg(src){return new Promise((res,rej)=>{const im=new Image();im.onload=()=>res(im);im.onerror=rej;im.src=src;});}
// Ubah logo usaha jadi raster 1-bit selebar printer (default 384 dot = 58mm)
async function logoRaster(widthDots){
  const src=(S.profile&&S.profile.logo)||"";if(!src)return null;
  let img;try{img=await loadImg(src);}catch(e){return null;}
  const W=widthDots||384;let w=img.width,h=img.height;const sc=Math.min(1,W/w);w=Math.round(w*sc);h=Math.round(h*sc);
  if(h>240){const s2=240/h;h=240;w=Math.round(w*s2);}
  const cv=document.createElement("canvas");cv.width=w;cv.height=h;const ctx=cv.getContext("2d");
  ctx.fillStyle="#fff";ctx.fillRect(0,0,w,h);ctx.drawImage(img,0,0,w,h);
  const px=ctx.getImageData(0,0,w,h).data,wb=Math.ceil(w/8),data=new Uint8Array(wb*h);
  for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4;const lum=px[i]*0.299+px[i+1]*0.587+px[i+2]*0.114;if(px[i+3]>128&&lum<150)data[y*wb+(x>>3)]|=(0x80>>(x&7));}
  return {wb,h,data};
}
// Rangkai payload ESC/POS: init + (logo tengah) + teks struk + feed + potong → base64
async function escposPayload(text){
  const bytes=[];const push=(...b)=>{for(const x of b)bytes.push(x&0xff);};
  push(0x1B,0x40);                       // ESC @  (reset)
  if(NativePrinter.logoOn()){
    try{const r=await logoRaster(384);if(r){
      push(0x1B,0x61,0x01);              // rata tengah
      push(0x1D,0x76,0x30,0x00, r.wb&0xff,(r.wb>>8)&0xff, r.h&0xff,(r.h>>8)&0xff);
      for(let i=0;i<r.data.length;i++)bytes.push(r.data[i]);
      push(0x1B,0x61,0x00,0x0A);         // rata kiri + feed
    }}catch(e){}
  }
  const t=(text||"").replace(/\r/g,"");  // teks struk (sudah ber-newline, ASCII 32 kolom)
  for(let i=0;i<t.length;i++){const c=t.charCodeAt(i);bytes.push(c>255?63:c);}
  push(0x0A,0x0A,0x0A,0x0A);
  push(0x1D,0x56,0x42,0x00);             // potong sebagian (diabaikan printer tanpa cutter)
  return b64bytes(new Uint8Array(bytes));
}
// Titik masuk cetak dari seluruh app (posSuccess/strukModal/tutup kasir memanggil ini).
async function printRawBT(text){
  if(!text){toast("Struk kosong.");return;}
  if(NativePrinter.isNative()){
    const dev=NativePrinter.saved();
    if(!dev){toast("Pilih printer dulu.");printerSettingsModal();return;}
    try{
      if(!(await NativePrinter.ensurePerm())){toast("Izin Bluetooth ditolak.");return;}
      const data=await escposPayload(text);
      await NativePrinter.plugin.print({address:dev.address,type:dev.type||"classic",data});
      toast("Struk dikirim ke printer ✓");
    }catch(e){toast((e&&e.message)||"Gagal cetak — cek printer & Bluetooth.");}
    return;
  }
  // fallback web/PWA: RawBT (encodeURIComponent agar newline tak hilang)
  try{window.location.href="rawbt:"+encodeURIComponent(text);}catch(e){toast("Gagal buka RawBT. Pastikan app RawBT terpasang.");}
}
// ---- Pengaturan printer (hanya di aplikasi Android) ----
async function printerSettingsModal(){
  if(!NativePrinter.isNative()){toast("Pengaturan printer hanya tersedia di aplikasi Android.");return;}
  const cur=NativePrinter.saved();
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>🖨 Printer Struk</h3>
    <div class="mini" style="margin-bottom:10px">Pilih printer thermal Bluetooth. Pastikan printer sudah <b>di-pair</b> di Pengaturan Bluetooth HP lebih dulu.</div>
    ${cur?`<div class="poskembali" style="justify-content:space-between;margin-bottom:10px"><span>Printer aktif</span><b>${esc(cur.name||cur.address)}</b></div>`:""}
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer"><input type="checkbox" id="ppLogo" ${NativePrinter.logoOn()?"checked":""} onchange="NativePrinter.setLogo(this.checked)"> Cetak logo usaha di struk</label>
    <div style="display:flex;gap:8px;margin-bottom:12px"><button class="btn gray" style="flex:1" onclick="loadPairedPrinters()">🔄 Muat Terpair</button><button class="btn gray" style="flex:1" onclick="scanBlePrinters()">📡 Scan BLE</button></div>
    <div id="printerList"><div class="empty">Menyiapkan…</div></div>
    <div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end">${cur?`<button class="btn" onclick="testPrintPrinter()">🧾 Tes Cetak</button>`:""}<button class="btn gray" onclick="closeModal()">Tutup</button></div>`);
  await NativePrinter.ensurePerm();
  loadPairedPrinters();
}
function renderPrinterList(devs){
  const cur=NativePrinter.saved();
  const el=document.getElementById("printerList");if(!el)return;
  if(!devs||!devs.length){el.innerHTML='<div class="empty">Tak ada printer. Pastikan Bluetooth aktif & printer sudah di-pair, lalu coba lagi.</div>';return;}
  el.innerHTML=`<div class="clist">${devs.map(d=>{const on=cur&&cur.address===d.address;
    return `<div class="crow" onclick='pickPrinter(${JSON.stringify(d).replace(/'/g,"&#39;")})'><div class="ci">${d.type==="ble"?"📡":"🖨"}</div><div class="cmain"><div class="ctitle">${esc(d.name||"(tanpa nama)")}</div><div class="csub">${esc(d.address)} · ${d.type==="ble"?"BLE":"Classic"}</div></div>${on?'<span class="pill lunas">AKTIF</span>':""}</div>`;}).join("")}</div>`;
}
async function loadPairedPrinters(){
  const el=document.getElementById("printerList");if(el)el.innerHTML='<div class="empty">Memuat printer terpair…</div>';
  try{if(!(await NativePrinter.ensurePerm())){if(el)el.innerHTML='<div class="empty">Izin Bluetooth ditolak.</div>';return;}
    const r=await NativePrinter.plugin.listPaired();renderPrinterList(r&&r.devices);}
  catch(e){if(el)el.innerHTML='<div class="empty">'+esc((e&&e.message)||"Gagal memuat printer.")+'</div>';}
}
async function scanBlePrinters(){
  const el=document.getElementById("printerList");if(el)el.innerHTML='<div class="empty">Scan BLE… (±6 detik)</div>';
  try{if(!(await NativePrinter.ensurePerm())){if(el)el.innerHTML='<div class="empty">Izin Bluetooth ditolak.</div>';return;}
    const r=await NativePrinter.plugin.scanBle({seconds:6});renderPrinterList(r&&r.devices);}
  catch(e){if(el)el.innerHTML='<div class="empty">'+esc((e&&e.message)||"Scan gagal.")+'</div>';}
}
function pickPrinter(d){NativePrinter.save(d);toast("Printer disimpan: "+(d.name||d.address));printerSettingsModal();}
async function testPrintPrinter(){await printRawBT(("").padEnd(0)+testStrukText());}
function testStrukText(){const {ctr,dash}=rcptFmt();return ctr((BIZ.name||"Racikin").toUpperCase())+"\n"+ctr("TES CETAK")+"\n"+dash+"\n"+ctr("Printer siap dipakai :)")+"\n\n\n";}
function printReceipt(id,bayar,kembali,method){
  const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  const p=S.profile||{};
  const contacts=(p.phone&&p.whatsapp)?(p.phone===p.whatsapp?"Telp/WA "+esc(p.phone):"Telp "+esc(p.phone)+" · WA "+esc(p.whatsapp)):(p.phone?"Telp "+esc(p.phone):(p.whatsapp?"WA "+esc(p.whatsapp):""));
  const items=notaItems(n).map(it=>{const pr=prod(it.productId)||{};const sub=(+it.qty||0)*(+it.harga||0);return `<div class="rrow"><span class="ri">${esc(pr.name||"?")}<br><span style="color:#555">${it.qty} × ${rp(it.harga)}</span></span><span>${rp(sub)}</span></div>`;}).join("");
  const total=notaTotal(n);
  document.getElementById("printArea").innerHTML=`<div class="preceipt">
    <img class="rlogo" src="${p.logo||"icons/logo-bowl.png"}" alt="">
    <h4>${esc(BIZ.name||"Racikin")}</h4>
    ${(p.address||contacts)?`<div class="rc">${p.address?esc(p.address):""}${p.address&&contacts?"<br>":""}${contacts}</div>`:""}
    <div class="rmeta">${esc(n.notaNo||"")}<br>${(()=>{const w=notaWhen(n);return esc(w.label+" "+fmtStamp(w.stamp," · "));})()} · Kasir${(()=>{const st=store(n.storeId);return st&&st.name&&st.name!==POS_NAME?`<br>Kepada: ${esc(st.name)}`:"";})()}</div>
    ${items}
    <div class="rtot">${(notaDisc(n)>0||notaService(n)>0||notaTax(n)>0)?`<div class="rrow"><span>Subtotal</span><span>${rp(notaSubtotal(n))}</span></div>${notaDisc(n)>0?`<div class="rrow"><span>Diskon</span><span>-${rp(notaDisc(n))}</span></div>`:""}${notaService(n)>0?`<div class="rrow"><span>Service${ratePct(notaSvcRate(n))}</span><span>${rp(notaService(n))}</span></div>`:""}${notaTax(n)>0?`<div class="rrow"><span>Pajak${ratePct(notaTaxRate(n))}</span><span>${rp(notaTax(n))}</span></div>`:""}`:""}<div class="rrow"><span>TOTAL</span><span>${rp(total)}</span></div>${bayar!=null?`<div class="rrow"><span>Bayar (${esc(method||"Tunai")})</span><span>${rp(bayar)}</span></div><div class="rrow"><span>Kembalian</span><span>${rp(kembali||0)}</span></div>`:""}${notaDue(n)>0?`<div class="rrow"><span>Sisa</span><span>${rp(notaDue(n))}</span></div>`:""}</div>
    <div class="rthx">${(p.footer||"").trim()?esc((p.footer||"").trim()):"Terima kasih 🙏"}<br>— ${esc(BIZ.name||"Racikin")} —</div>
  </div>`;
  printTitled(["Struk",n.notaNo].filter(Boolean).join(" "));
}

