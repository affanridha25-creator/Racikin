<?php // ANNA Manager — frontend SPA (data via api.php + MySQL) ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Racikin — Produksi, Distribusi & Pembayaran UMKM</title>
<link rel="icon" type="image/png" sizes="64x64" href="icons/favicon.png">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#D53E0F">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Racikin">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<style>
:root{--red:#D53E0F;--red-d:#7E0306;--orange:#FA8743;--orange-l:#FCAF67;--cream:#FFDECD;--bg:#FBEEE7;--card:#fff;--ink:#2b2b2b;--muted:#8a8f99;--line:#eef0f2;--green:#1E8449;--green-bg:#e6f5ec;--amber:#C2651B;--amber-bg:#fdeede;--blue:#2471a3;--blue-bg:#e8f1f8;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background:#FBEEE7 linear-gradient(168deg,#FFF4EC 0%,#FDE9DD 34%,#FBEFE7 64%,#F4EFF3 100%) fixed;color:var(--ink);font-size:14px;line-height:1.5;display:flex;min-height:100vh}
.sidebar{width:238px;flex-shrink:0;background:linear-gradient(180deg,var(--red),var(--red-d));color:#fff;display:flex;flex-direction:column;padding:22px 16px;position:sticky;top:0;height:100vh;border-radius:0 26px 26px 0}
.sidebar .brand{display:flex;align-items:center;gap:11px;padding:4px 8px 26px}
.sidebar .brand .logo{width:52px;height:52px;border-radius:14px;background:#ffffff14;border:2px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;overflow:hidden}
.sidebar .brand .logo img{width:100%;height:100%;object-fit:contain;padding:3px}
.sidebar .brand .brand-word{height:30px;object-fit:contain}
#nav{display:flex;flex-direction:column;gap:4px;flex:1}
#nav button{display:flex;align-items:center;gap:12px;background:none;border:none;color:#ffffffcc;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;text-align:left;width:100%;transition:.15s}
#nav button .ic{font-size:16px;width:20px;text-align:center}
#nav button:hover{background:#ffffff1f;color:#fff}
#nav button.active{background:#fff;color:var(--red);box-shadow:0 8px 18px #0002}
.sidebar .logout{margin-top:auto;display:flex;align-items:center;justify-content:center;gap:8px;background:#ffffff26;color:#fff;border:none;padding:12px;border-radius:24px;font-weight:700;font-size:14px;cursor:pointer}
.sidebar .logout:hover{background:#ffffff3d}
.sidebar .role{text-align:center;font-size:11px;color:#ffffffb3;margin-top:12px;letter-spacing:.02em}
.content{flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:22px 30px 6px}
.topbar .t-title{font-size:15px;color:var(--muted);font-weight:600}
.topbar .t-right{font-size:13px;color:var(--muted)}
.t-word-img{display:none}
.topbar .t-right b{color:var(--red)}
main{flex:1;padding:16px 30px 60px}
/* ---- mobile: bottom tab bar + sheet ---- */
.botnav{display:none}
.sheet-bg{display:none;position:fixed;inset:0;background:#0006;z-index:45}
.sheet-bg.open{display:block}
.sheet{position:fixed;left:0;right:0;bottom:0;background:#fff;border-radius:20px 20px 0 0;padding:6px 12px calc(16px + env(safe-area-inset-bottom));box-shadow:0 -8px 30px #0003;animation:sheetup .18s ease}
.sheet::before{content:"";display:block;width:38px;height:4px;border-radius:2px;background:#ddd;margin:6px auto 4px}
@keyframes sheetup{from{transform:translateY(100%)}to{transform:translateY(0)}}
.sheet-h{font-weight:800;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;padding:4px 8px 6px;text-align:center}
.sheet button{display:flex;align-items:center;gap:12px;width:100%;background:none;border:none;padding:14px 12px;font-size:15px;font-weight:600;color:var(--ink);cursor:pointer;border-radius:12px;text-align:left}
.sheet button:active{background:var(--bg)}
.sheet button span:first-child{font-size:20px;width:24px;text-align:center}
/* ---- card-list rows (mobile) ---- */
.clist{display:flex;flex-direction:column;gap:10px}
.crow{display:flex;align-items:center;gap:12px;background:#fff;border-radius:18px;padding:13px 14px;box-shadow:0 6px 18px rgba(40,40,60,.05);cursor:pointer}
.crow:active{background:#fafafa}
.crow .ci{width:44px;height:44px;border-radius:14px;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.crow .cmain{flex:1;min-width:0}
.crow .ctitle{font-weight:700;font-size:14px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.crow .csub{font-size:12px;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.crow .cright{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0;text-align:right}
.crow .camt{font-weight:800;font-size:15px}
.crow .cacts{display:flex;gap:5px;margin-top:3px}
.crow-del{flex-shrink:0;width:32px;height:32px;border-radius:9px;background:#fbe7e5;color:var(--red);border:none;font-weight:700;font-size:13px;cursor:pointer}
.statpill{display:flex;background:#fff;border-radius:20px;padding:16px 4px;box-shadow:0 6px 18px rgba(40,40,60,.05);margin-bottom:14px}
.statpill>div{flex:1;text-align:center;border-right:1px solid var(--line);padding:0 4px}
.statpill>div:last-child{border-right:none}
.statpill .sp-v{font-weight:800;font-size:15px}
.statpill .sp-l{font-size:11px;color:var(--muted);margin-top:2px}
/* ---- dashboard analytics ---- */
.insight{background:linear-gradient(145deg,#8f1206,var(--red-d));color:#fff;border-radius:22px;padding:18px;margin-bottom:14px;box-shadow:0 10px 26px rgba(126,3,6,.28)}
.insight .ihead{font-size:12px;opacity:.85;display:flex;align-items:center;gap:6px;margin-bottom:8px;font-weight:600}
.insight .ibig{font-size:20px;font-weight:800;line-height:1.3}
.insight .isub{font-size:12px;opacity:.9;margin-top:8px}
.tiles{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.tile{background:#fff;border-radius:20px;padding:16px;box-shadow:0 6px 18px rgba(40,40,60,.05)}
.tile .tl2{font-size:12px;color:var(--muted)}
.tile .tv{font-size:21px;font-weight:800;margin-top:4px}
.tile .tc{font-size:11px;margin-top:6px;font-weight:700;color:var(--muted)}
.tc.chgup{color:var(--green)}.tc.chgdn{color:var(--red)}
.chartcard{background:#fff;border-radius:22px;padding:18px;box-shadow:0 6px 18px rgba(40,40,60,.05);margin-bottom:14px}
.chartcard h3{font-size:15px;margin-bottom:14px}
.barrow2{margin-bottom:11px;font-size:12px}
.barrow2 .brtop{display:flex;justify-content:space-between;gap:10px;margin-bottom:5px}
.barrow2 .brtop span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.barrow2 .brbar{height:10px;border-radius:6px;background:#f1eeed;overflow:hidden}
.barrow2 .brbar>span{display:block;height:100%;border-radius:6px;background:linear-gradient(90deg,var(--orange),var(--red))}
.donutwrap{display:flex;align-items:center;gap:14px}
.legend{display:flex;flex-direction:column;gap:9px;flex:1;min-width:0}
.legend .lg{display:flex;align-items:center;gap:8px;font-size:12px}
.legend .lg .dot{width:11px;height:11px;border-radius:4px;flex-shrink:0}
.legend .lg .lgv{margin-left:auto;font-weight:700}
.trend{display:flex;align-items:flex-end;gap:8px;height:130px}
.trend .tb{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end}
.trend .tb .tbar{width:66%;max-width:32px;background:linear-gradient(180deg,var(--orange),var(--red));border-radius:6px 6px 0 0;min-height:4px}
.trend .tb .tl{font-size:10px;color:var(--muted)}
.lrrow{display:flex;justify-content:space-between;gap:10px;padding:10px 2px;border-bottom:1px solid var(--line);font-size:14px}
.lrrow.tot{font-weight:700;border-top:2px solid var(--line);border-bottom:none;margin-top:2px}
.lrrow.big{font-size:16px;padding-top:12px}
.monthsel{max-width:230px;font-weight:600;margin-bottom:14px}
.donutcap{text-align:center;margin-top:14px;font-size:13px;color:var(--muted)}
.donutcap b{color:var(--ink);font-size:17px;margin-left:5px}
.trend .tb .tbar.on{outline:2px solid var(--red-d);outline-offset:1px}
.legend2{display:flex;gap:16px;justify-content:center;font-size:12px;color:var(--muted);margin-top:8px}
.legend2 i{display:inline-block;width:14px;height:3px;border-radius:2px;vertical-align:middle;margin-right:5px}
.actrow{display:flex;gap:10px;align-items:flex-start;padding:9px 2px;border-bottom:1px solid var(--line);font-size:13px}
.actrow:last-child{border-bottom:none}
.actdot{width:9px;height:9px;border-radius:50%;margin-top:5px;flex-shrink:0}
.logobox{width:88px;height:88px;border-radius:18px;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:40px;overflow:hidden;flex-shrink:0;border:1px solid var(--line)}
.logobox img{width:100%;height:100%;object-fit:contain}
.hero3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
@media(max-width:560px){.hero3{grid-template-columns:1fr}}
.pchart{overflow-x:auto}
.pchart svg{display:block}
/* ---- dashboard mobile (redesign v2) ---- */
.dgreet{padding:8px 4px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.dgreet h2{font-size:21px;font-weight:800;line-height:1.15}
.dgreet p{font-size:12.5px;color:var(--muted);margin-top:2px}
.dgreet .davatar{width:44px;height:44px;border-radius:14px;background:linear-gradient(145deg,var(--orange),var(--red));color:#fff;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;flex-shrink:0;box-shadow:0 6px 16px rgba(213,62,15,.3)}
.hero2{background:linear-gradient(150deg,var(--orange) -12%,var(--red) 46%,var(--red-d) 112%);color:#fff;border-radius:26px;padding:20px 20px 18px;box-shadow:0 16px 34px rgba(126,3,6,.30);position:relative;overflow:hidden;margin-bottom:16px}
.hero2::after{content:"";position:absolute;right:-40px;top:-50px;width:170px;height:170px;border-radius:50%;background:#ffffff14}
.hero2 .hl{font-size:12.5px;opacity:.9;font-weight:600;display:flex;align-items:center;gap:6px;position:relative}
.hero2 .hv{font-size:33px;font-weight:800;letter-spacing:-.5px;margin:6px 0 2px;line-height:1.05;position:relative}
.hero2 .hpills{display:flex;gap:8px;flex-wrap:wrap;margin-top:11px;position:relative}
.hpill{background:#ffffff26;border-radius:20px;padding:5px 11px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
.hpill.up{background:#eafff0;color:#0d6b34}.hpill.dn{background:#fff0eb;color:#a5240b}
.hact{display:flex;gap:9px;margin-top:16px;position:relative}
.hact button{flex:1;background:#ffffff1f;border:1px solid #ffffff33;color:#fff;border-radius:16px;padding:11px 6px;display:flex;flex-direction:column;align-items:center;gap:5px;font-size:11px;font-weight:700;cursor:pointer}
.hact button:active{background:#ffffff33}
.hact .hai{font-size:18px;line-height:1}
.dsec{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:20px 2px 11px}
.dsec h3{font-size:15.5px;font-weight:800}
.dsec a{font-size:12.5px;color:var(--red);font-weight:700;text-decoration:none;cursor:pointer}
.mcard{background:#fff;border-radius:20px;padding:15px;box-shadow:0 6px 18px rgba(40,40,60,.05)}
.mcard .mi{width:34px;height:34px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:10px}
.mcard .ml{font-size:12px;color:var(--muted)}
.mcard .mv{font-size:19px;font-weight:800;margin-top:3px;letter-spacing:-.2px}
.mcard .ms{font-size:11px;font-weight:700;margin-top:6px}
.card2{background:#fff;border-radius:22px;padding:16px 16px 8px;box-shadow:0 6px 18px rgba(40,40,60,.05);margin-bottom:2px}
.crow .ci.mono{font-weight:800;font-size:16px;color:var(--red);background:var(--cream)}
.barrow2 .brtop .bname{flex:1;min-width:0;font-weight:600}
.barrow2 .brsub{font-size:11px;color:var(--muted);margin-top:4px}
.hlcard{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#33291f,#171210);color:#fff;border-radius:20px;padding:14px 15px;margin-bottom:16px;cursor:pointer;box-shadow:0 10px 24px rgba(0,0,0,.16)}
.hlcard .hli{width:38px;height:38px;border-radius:12px;background:#ffffff1a;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.hlcard .hlmain{flex:1;min-width:0}
.hlcard .hlh{font-weight:700;font-size:14px;line-height:1.25}
.hlcard .hls{font-size:11.5px;opacity:.72;margin-top:2px}
.hlcard .hlgo{font-size:11px;font-weight:800;color:var(--orange-l);flex-shrink:0}
.botnav .fab{flex:0 0 auto;width:56px;height:56px;border-radius:50%;background:linear-gradient(145deg,var(--orange),var(--red));color:#fff;font-size:30px;font-weight:300;line-height:1;box-shadow:0 10px 22px rgba(213,62,15,.5);margin-top:-26px;display:flex;align-items:center;justify-content:center;padding:0;border:4px solid var(--bg)}
.botnav .fab:active{transform:scale(.95)}
/* ---- login ---- */
.loginbg{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;background:#FBEEE7 linear-gradient(160deg,#FFF4EC 0%,#FCE0CF 55%,#F6D6C6 100%)}
.loginbox{background:#fff;border-radius:24px;padding:26px 24px;width:100%;max-width:384px;box-shadow:0 24px 60px rgba(126,3,6,.20)}
.loginbox .lgo{width:76px;height:76px;object-fit:contain;margin:0 auto 2px;display:block}
.loginbox .lword{height:34px;object-fit:contain;display:block;margin:0 auto 8px}
.loginbox h2{text-align:center;font-size:21px;font-weight:800;margin-bottom:2px}
.loginbox .lsub{text-align:center;color:var(--muted);font-size:12px;margin-bottom:18px}
.ltabs{display:flex;background:var(--bg);border-radius:12px;padding:4px;margin-bottom:16px}
.ltabs button{flex:1;border:none;background:none;padding:9px;border-radius:9px;font-weight:700;font-size:13px;color:var(--muted);cursor:pointer}
.ltabs button.on{background:#fff;color:var(--red);box-shadow:0 2px 8px rgba(0,0,0,.07)}
.lfield{margin-bottom:12px}
.lfield label{display:block;font-size:12px;color:var(--muted);margin-bottom:4px;font-weight:600}
.lerr{background:#fbe7e5;color:var(--red);font-size:12px;padding:9px 11px;border-radius:9px;margin-bottom:12px;display:none}
.lremember{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--muted);margin:2px 0 12px;cursor:pointer}
.lremember input{width:auto;min-width:0;margin:0;flex-shrink:0}
@media(max-width:820px){
  body{display:block}
  .sidebar{display:none}
  .content{min-height:100vh}
  .topbar{position:sticky;top:0;background:rgba(255,244,236,.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:15;padding:18px 18px 8px;align-items:center}
  .topbar .t-title{font-size:22px;font-weight:800;color:var(--ink)}
  .topbar .t-right{display:none}
  .t-word-img{display:block;height:25px;object-fit:contain;margin-left:auto}
  main{padding:6px 16px 120px}
  h2.title{display:none}
  .desc{margin:-2px 0 16px}
  .cards{gap:12px;margin-bottom:16px}
  .card{border-radius:22px;padding:20px}
  .card .val{font-size:26px}.card .val.sm{font-size:19px}
  .card.accent{grid-column:1/-1}
  .panel{border-radius:22px;padding:18px}
  .flexbtns .btn{flex:1}
  .botnav{display:flex;position:fixed;left:14px;right:14px;bottom:calc(12px + env(safe-area-inset-bottom));z-index:40;background:#fff;border:none;border-radius:24px;padding:8px 6px;box-shadow:0 14px 36px rgba(120,20,10,.18);gap:2px}
  .botnav button{flex:1;min-width:0;background:none;border:none;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:9.5px;color:var(--muted);font-weight:700;cursor:pointer;padding:8px 2px;border-radius:16px;transition:.15s}
  .botnav button .bic{font-size:19px;line-height:1}
  .botnav button.active{color:var(--red);background:var(--cream)}
}
.view{display:none}.view.active{display:block}
h2.title{font-size:20px;margin-bottom:4px}
.desc{color:var(--muted);margin-bottom:18px;font-size:13px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px}
.card{background:var(--card);border:none;border-radius:18px;padding:18px;box-shadow:0 6px 20px rgba(40,40,60,.05)}
.card .lbl{font-size:12px;color:var(--muted);margin-bottom:6px}
.card .val{font-size:22px;font-weight:700}.card .val.sm{font-size:17px}
.card.accent{background:linear-gradient(135deg,var(--red),var(--red-d));color:#fff;border:none}
.card.accent .lbl{color:#fff;opacity:.85}
.card.green .val{color:var(--green)}.card.amber .val{color:var(--amber)}
.panel{background:var(--card);border:none;border-radius:18px;padding:20px;margin-bottom:20px;box-shadow:0 6px 20px rgba(40,40,60,.05);overflow-x:auto}
.panel h3{font-size:15px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:8px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line)}
th{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);font-weight:600}
tbody tr:hover{background:#fbfafa}
td.num,th.num{text-align:right}
.btn{background:var(--red);color:#fff;border:none;padding:9px 16px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer}
.btn:hover{background:var(--red-d)}
.btn.ghost{background:#fff;color:var(--red);border:1.5px solid var(--red)}
.btn.sm{padding:5px 11px;font-size:12px}
.btn.gray{background:#eee;color:#555}.btn.gray:hover{background:#e0e0e0}
.btn.dark{background:#333}.btn.dark:hover{background:#111}
input,select,textarea{font-family:inherit;font-size:13px;padding:8px 10px;border:1px solid #d8d8d8;border-radius:8px;width:100%;min-width:0;max-width:100%;background:#fff}
/* iOS/Safari: date/time input abaikan width:100% & melebar sesuai isi → normalkan */
input[type="date"],input[type="time"],input[type="datetime-local"]{-webkit-appearance:none;appearance:none}
input:focus,select:focus{outline:none;border-color:var(--red)}
label.f{display:block;font-size:12px;color:var(--muted);margin-bottom:4px;font-weight:500}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.pill.lunas{background:var(--green-bg);color:var(--green)}
.pill.belum{background:#fbe7e5;color:var(--red)}
.pill.sebagian{background:var(--amber-bg);color:var(--amber)}
.bar{height:9px;border-radius:6px;background:#eee;overflow:hidden}
.bar>span{display:block;height:100%;background:var(--red)}
.barrow{display:grid;grid-template-columns:150px 1fr 90px;gap:10px;align-items:center;margin-bottom:9px;font-size:12px}
.right{text-align:right}.muted{color:var(--muted)}.mini{font-size:12px;color:var(--muted)}
.up{color:var(--red);font-weight:700}.down{color:var(--green);font-weight:700}.flat{color:var(--muted)}
.empty{text-align:center;color:var(--muted);padding:26px;font-size:13px}
.modal-bg{position:fixed;inset:0;background:#0007;display:none;align-items:flex-start;justify-content:center;z-index:50;padding:30px 14px;overflow:auto}
.modal-bg.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:22px;width:100%;max-width:640px;box-shadow:0 20px 60px #0004}
.modal h3{font-size:17px;margin-bottom:16px}
.modal .close{float:right;cursor:pointer;color:var(--muted);font-size:20px;line-height:1;border:none;background:none}
@media(max-width:820px){.modal-bg{padding:0;align-items:flex-end}.modal{max-width:100%;border-radius:20px 20px 0 0;max-height:92vh;overflow:auto}}
.dynrow{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:6px;margin-bottom:6px;align-items:center}
.dynrow.ops,.dynrow.out{grid-template-columns:2fr 1fr auto}
/* combobox bisa diketik */
.combo{position:relative}
.combo-in{width:100%}
.combo-menu{display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:60;background:#fff;border:1px solid #e2e2e2;border-radius:12px;box-shadow:0 14px 34px rgba(0,0,0,.16);max-height:230px;overflow:auto}
.combo.open .combo-menu{display:block}
.combo-opt{padding:11px 12px;font-size:13px;cursor:pointer;border-bottom:1px solid var(--line)}
.combo-opt:last-child{border-bottom:none}
.combo-opt:active,.combo-opt:hover{background:var(--bg)}
.combo-empty{padding:12px;font-size:12px;color:var(--muted);text-align:center}
/* kartu baris input (produk/qty/harga) — enak di mobile */
.itemrow{background:var(--bg);border-radius:14px;padding:10px 10px 9px;margin-bottom:9px}
.itemrow .irtop{display:flex;gap:8px;align-items:center}
.itemrow .irtop .combo{flex:1;min-width:0}
.itemrow .irbot{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
.itemrow .irbot.one{grid-template-columns:1fr}
.itemrow>input,.itemrow>select{margin-top:8px}
.itemrow .irsub{text-align:right;font-size:12px;margin-top:7px;color:var(--muted);font-weight:600}
.x{background:#f2dede;color:var(--red);border:none;border-radius:7px;width:30px;height:32px;cursor:pointer;font-weight:700}
.sumbox{background:var(--bg);border-radius:10px;padding:12px 14px;margin-top:10px;font-size:13px}
.sumbox div{display:flex;justify-content:space-between;padding:2px 0}
.sumbox .big{font-weight:700;font-size:15px;border-top:1px solid var(--line);margin-top:5px;padding-top:7px}
.tag-cat{font-size:10px;padding:2px 7px;border-radius:6px;background:#f0f0f0;color:#666;margin-left:6px}
.pill-mini{display:inline-block;font-size:9px;font-weight:700;padding:1px 6px;border-radius:5px;background:var(--amber-bg);color:var(--amber);vertical-align:middle;letter-spacing:.02em}
.pill-mini.endorse{background:var(--blue-bg);color:var(--blue)}
.pill-mini.tester{background:#eef0f2;color:#5a6672}
.itemcell{line-height:1.4;min-width:150px}
.itemcell .li{display:flex;justify-content:space-between;gap:12px;align-items:baseline;padding:1.5px 0}
.itemcell .li .q{white-space:nowrap;font-weight:700}
.itemcell .free{margin-top:6px;padding-top:6px;border-top:1px dashed var(--line)}
.flexbtns{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.acts{display:flex;gap:6px;justify-content:flex-end;flex-wrap:nowrap;white-space:nowrap}
.btn.del{background:#fbe7e5;color:var(--red);padding:5px 9px;font-size:13px;line-height:1}
.btn.del:hover{background:#f6d3ce}
.spark{display:flex;align-items:flex-end;gap:2px;height:26px}
.spark i{display:block;width:6px;background:var(--blue);border-radius:2px 2px 0 0;opacity:.7}
#toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;opacity:0;transition:.3s;pointer-events:none;z-index:99}
#toast.show{opacity:1}
@media(max-width:640px){.grid2{grid-template-columns:1fr}.dynrow,.dynrow.ops,.dynrow.out{grid-template-columns:1fr 1fr auto}.barrow{grid-template-columns:110px 1fr 70px}}
/* ---- POS / Kasir ---- */
.pos-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.pos-head .pt{font-size:20px;font-weight:800}
.pos-today{background:linear-gradient(150deg,var(--orange),var(--red));color:#fff;border-radius:16px;padding:10px 16px;font-weight:700;font-size:13px;box-shadow:0 8px 20px rgba(213,62,15,.28)}
.pos-today b{font-size:16px;display:block;margin-top:1px}
.possearch{margin-bottom:14px}
.posgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:11px;padding-bottom:96px}
.pcard{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:13px;cursor:pointer;text-align:left;position:relative;transition:transform .12s ease,box-shadow .12s ease;box-shadow:0 4px 12px rgba(40,40,60,.05)}
.pcard:hover{box-shadow:0 10px 22px rgba(40,40,60,.1)}
.pcard:active{transform:scale(.97)}
.pcard.out{opacity:.5;pointer-events:none}
.pcard .pn{font-weight:700;font-size:13.5px;line-height:1.25;margin-bottom:8px;min-height:34px;color:var(--ink)}
.pcard .pp{font-weight:800;color:var(--red);font-size:15px}
.pcard .ps{font-size:11px;color:var(--muted);margin-top:2px}
.pcard .qbadge{position:absolute;top:-7px;right:-7px;background:var(--red);color:#fff;font-size:12px;font-weight:800;min-width:24px;height:24px;border-radius:12px;display:grid;place-items:center;padding:0 6px;box-shadow:0 4px 10px rgba(213,62,15,.4)}
.posbar{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(88px + env(safe-area-inset-bottom));width:min(560px,calc(100% - 28px));z-index:30;background:linear-gradient(150deg,var(--orange),var(--red));color:#fff;border-radius:18px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 14px 34px rgba(213,62,15,.42);cursor:pointer}
.posbar .pc{font-size:12.5px;opacity:.92;font-weight:600}
.posbar .pv{font-size:19px;font-weight:800}
.posbar .go{background:#ffffff2b;padding:9px 16px;border-radius:12px;font-weight:800;font-size:14px}
@media(min-width:821px){.posbar{left:auto;right:34px;transform:none;bottom:24px}}
/* keranjang di modal checkout */
.crt{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line)}
.crt .cn{flex:1;min-width:0}
.crt .cn b{font-weight:700;font-size:14px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.crt .cn span{font-size:12px;color:var(--muted)}
.crt .stp{display:flex;align-items:center;gap:0;border:1px solid var(--line);border-radius:10px;overflow:hidden}
.crt .stp button{width:32px;height:32px;border:none;background:var(--bg);color:var(--red);font-size:17px;font-weight:800;cursor:pointer}
.crt .stp span{min-width:30px;text-align:center;font-weight:800;font-size:14px}
.crt .lt{font-weight:800;font-size:14px;min-width:74px;text-align:right}
.posseg{display:flex;background:var(--bg);border-radius:12px;padding:4px;gap:4px;margin:4px 0 12px}
.posseg button{flex:1;border:none;background:none;padding:9px;border-radius:9px;font-weight:700;font-size:13px;color:var(--muted);cursor:pointer}
.posseg button.on{background:var(--red);color:#fff}
.poskembali{background:var(--green-bg);color:var(--green);border-radius:12px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;font-weight:700;margin-top:6px}
.poskembali b{font-size:19px}
.pos-done{text-align:center;padding:8px 0}
.pos-done .ok{width:72px;height:72px;border-radius:50%;background:var(--green-bg);color:var(--green);font-size:38px;display:grid;place-items:center;margin:4px auto 14px}
/* struk */
.preceipt{width:74mm;margin:0 auto;color:#000;font-family:-apple-system,'Segoe UI',monospace;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.preceipt .rlogo{width:46px;height:46px;object-fit:contain;display:block;margin:0 auto 4px}
.preceipt h4{text-align:center;font-size:15px;margin-bottom:2px}
.preceipt .rc{text-align:center;font-size:10px;color:#333;line-height:1.4;margin-bottom:8px}
.preceipt .rmeta{font-size:10.5px;color:#333;border-top:1px dashed #999;border-bottom:1px dashed #999;padding:5px 0;margin-bottom:6px}
.preceipt .rrow{display:flex;justify-content:space-between;font-size:11.5px;gap:8px;margin:3px 0}
.preceipt .rrow .ri{flex:1}
.preceipt .rtot{border-top:1px dashed #999;margin-top:6px;padding-top:6px}
.preceipt .rtot .rrow{font-weight:700}
.preceipt .rthx{text-align:center;font-size:11px;margin-top:10px;border-top:1px dashed #999;padding-top:8px}
/* ---- cetak laporan (A4) ---- */
#printArea{display:none}
.preport{color:#000;font-size:12px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.preport h1{font-size:19px;color:var(--red);text-align:center;margin-bottom:3px;letter-spacing:.02em}
.preport .psub{text-align:center;color:#555;font-size:11px;margin-bottom:16px}
.preport .psec{background:var(--red);color:#fff;font-weight:700;padding:6px 11px;border-radius:5px;margin:16px 0 8px;font-size:12.5px}
.preport table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:6px}
.preport th,.preport td{border:1px solid #dcdcdc;padding:6px 9px;text-align:left;vertical-align:top}
.preport th{background:#f6f6f6;font-weight:700}
.preport td.num,.preport th.num{text-align:right;white-space:nowrap}
.preport tr.tot td{font-weight:700;background:#faf1ee}
.preport i{color:var(--red);font-style:normal;font-size:11px}
.preport .phead{display:flex;align-items:center;gap:14px;border-bottom:2px solid var(--red);padding-bottom:10px;margin-bottom:14px}
.preport .plogo{width:58px;height:58px;object-fit:contain;flex-shrink:0}
.preport .pbiz{font-size:16px;font-weight:800;color:#111;letter-spacing:.01em}
.preport .pcontact{font-size:10.5px;color:#555;margin-top:3px;line-height:1.45}
.preport .phead+h1{margin-top:6px}
@page{size:A4 portrait;margin:14mm}
@media print{
  html,body{background:#fff!important;display:block!important;margin:0;min-height:0!important}
  .sidebar,.content,.topbar,main,.modal-bg,.sheet-bg,.botnav,#toast,.loginbg{display:none!important}
  #printArea{display:block!important;min-height:0!important}
  .preport{width:182mm;margin:0 auto}
  .preport table,.preport tr,.preport .psec{page-break-inside:avoid}
}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="brand"><div class="logo"><img id="sideLogo" src="icons/logo-bowl.png" alt=""></div><img class="brand-word" src="icons/logo-word-white.png" alt="Racikin"></div>
  <nav id="nav">
    <button data-v="dashboard" data-label="Dashboard" class="active"><span class="ic">📊</span> Dashboard</button>
    <button data-v="pos" data-label="Kasir (POS)"><span class="ic">🛒</span> Kasir</button>
    <button data-v="produksi" data-label="Produksi"><span class="ic">🏭</span> Produksi</button>
    <button data-v="distribusi" data-label="Distribusi"><span class="ic">🚚</span> Distribusi</button>
    <button data-v="pembayaran" data-label="Pembayaran"><span class="ic">💰</span> Pembayaran</button>
    <button data-v="keuangan" data-label="Keuangan"><span class="ic">💹</span> Keuangan</button>
    <button data-v="bahan" data-label="Bahan Baku"><span class="ic">🧂</span> Bahan Baku</button>
    <button data-v="produk" data-label="Produk"><span class="ic">🫙</span> Produk</button>
    <button data-v="toko" data-label="Toko"><span class="ic">🏪</span> Toko</button>
    <button data-v="profile" data-label="Profil Usaha"><span class="ic">🏷️</span> Profil Usaha</button>
    <button data-v="backup" data-label="Backup"><span class="ic">💾</span> Backup</button>
  </nav>
  <button class="logout" onclick="doLogout()"><span>⏻</span> Logout</button>
  <div class="role">ANNA Snack &amp; Kitchen</div>
</aside>
<div class="content">
  <div class="topbar"><div class="t-title" id="topTitle">Dashboard</div><img class="t-word-img" src="icons/logo-word.png" alt="Racikin"><div class="t-right">👋 Halo, <b>ANNA</b></div></div>
  <main>
    <section id="v-dashboard" class="view active"><div class="empty">Memuat data…</div></section>
    <section id="v-pos" class="view"></section>
    <section id="v-produksi" class="view"></section>
    <section id="v-distribusi" class="view"></section>
    <section id="v-pembayaran" class="view"></section>
    <section id="v-keuangan" class="view"></section>
    <section id="v-bahan" class="view"></section>
    <section id="v-produk" class="view"></section>
    <section id="v-toko" class="view"></section>
    <section id="v-profile" class="view"></section>
    <section id="v-backup" class="view"></section>
  </main>
</div>
<div class="modal-bg" id="modalBg"><div class="modal" id="modal"></div></div>
<div id="toast"></div>
<div id="printArea"></div>
<div class="loginbg" id="loginScreen" style="display:flex"><div class="loginbox" id="loginBox"></div></div>
<nav class="botnav" id="botnav">
  <button data-v="dashboard" onclick="go('dashboard')"><span class="bic">📊</span>Beranda</button>
  <button data-v="distribusi" onclick="go('distribusi')"><span class="bic">🚚</span>Distribusi</button>
  <button class="fab" onclick="fabAction()" aria-label="Tambah">+</button>
  <button data-v="produksi" onclick="go('produksi')"><span class="bic">🏭</span>Produksi</button>
  <button id="btnMore" onclick="openMore()"><span class="bic">☰</span>Lainnya</button>
</nav>
<div class="sheet-bg" id="sheetBg" onclick="closeMore(event)">
  <div class="sheet">
    <div class="sheet-h">Menu Lainnya</div>
    <button onclick="go('pos')"><span>🛒</span> Kasir (POS)</button>
    <button onclick="go('keuangan')"><span>💹</span> Keuangan</button>
    <button onclick="go('pembayaran')"><span>💰</span> Pembayaran</button>
    <button onclick="go('bahan')"><span>🧂</span> Bahan Baku</button>
    <button onclick="go('produk')"><span>🫙</span> Produk</button>
    <button onclick="go('toko')"><span>🏪</span> Toko</button>
    <button onclick="go('profile')"><span>🏷️</span> Profil Usaha</button>
    <button onclick="go('backup')"><span>💾</span> Backup</button>
    <button onclick="closeMore();doLogout()" style="color:var(--red)"><span>⏻</span> Keluar</button>
  </div>
</div>

<script>
"use strict";
const rp=n=>"Rp"+Math.round(n||0).toLocaleString("id-ID");
const uid=()=>Date.now().toString(36)+Math.random().toString(36).slice(2,6);
const today=()=>new Date().toISOString().slice(0,10);
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
function comboDraw(cid,filter){const c=COMBO[cid];if(!c)return;const m=document.querySelector("#"+cid+" .combo-menu");if(!m)return;const f=(filter||"").toLowerCase().trim();const list=c.opts.filter(o=>!f||o.label.toLowerCase().includes(f));m.innerHTML=list.length?list.slice(0,60).map(o=>`<div class="combo-opt" onmousedown="comboPick('${cid}','${o.id}')">${esc(o.label)}</div>`).join(""):'<div class="combo-empty">Tak ada yang cocok</div>';}
function comboType(cid,v){const el=document.getElementById(cid);if(el)el.classList.add("open");comboDraw(cid,v);}
function comboBlur(cid){setTimeout(()=>{const el=document.getElementById(cid);if(el)el.classList.remove("open");},160);}
function comboPick(cid,id){const c=COMBO[cid];if(!c)return;const o=c.opts.find(x=>x.id===id);const el=document.getElementById(cid);if(el){const inp=el.querySelector(".combo-in");if(inp)inp.value=o?o.label:"";el.classList.remove("open");}if(c.cb)c.cb(id,o?o.label:"");}

// ---------- API ----------
async function api(action,payload={},opts={}){
  const r=await fetch("api.php",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"fetch"},body:JSON.stringify({action,...payload})});
  const j=await r.json();
  if(j&&j.error){if(!opts.silent)toast("⚠️ "+j.error);throw new Error(j.error);}
  return j;
}
let BIZ={};
async function reload(){S=await api("bootstrap");_stockCache=null;if(S.biz)BIZ=S.biz;updateBizUI();}
function updateBizUI(){const r=document.querySelector(".sidebar .role");if(r)r.textContent=BIZ.name||"";const t=document.querySelector(".topbar .t-right");if(t)t.innerHTML=`👋 Halo, <b>${esc(BIZ.user||"")}</b>`;applyLogos();}
// logo usaha (kalau sudah upload di Profil) menggantikan logo Racikin; kalau belum → default bowl
function applyLogos(){
  const lg=(S.profile&&S.profile.logo)||"";
  const side=document.getElementById("sideLogo");
  if(side)side.src=lg||"icons/logo-bowl.png";
}

// ========== KEUANGAN: filter bulan, laba rugi, kas ==========
const curMonth=()=>{const d=new Date();return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0");};
let FILTER_MONTH=curMonth(), KEU_TAB="lr";   // default: bulan berjalan
const cashOut=()=>S.cashOut||[];
const MONF=["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
const monthLabelFull=k=>k==="all"?"Semua Bulan":`${MONF[(+String(k).slice(5,7))-1]} ${String(k).slice(0,4)}`;
const inMonth=d=>FILTER_MONTH==="all"||((d||"").slice(0,7)===FILTER_MONTH);
function monthKeys(){const set=new Set([curMonth()]);S.notas.forEach(n=>{if(n.date)set.add(n.date.slice(0,7));(n.payments||[]).forEach(p=>{if(p.date)set.add(p.date.slice(0,7));});});cashOut().forEach(c=>{if(c.date)set.add(c.date.slice(0,7));});S.batches.forEach(b=>{if(b.date)set.add(b.date.slice(0,7));});return[...set].sort().reverse();}
function setMonth(v){FILTER_MONTH=v;renderCur();}
function monthBar(){const ks=monthKeys();if(FILTER_MONTH!=="all"&&!ks.includes(FILTER_MONTH))FILTER_MONTH="all";return `<select class="monthsel" onchange="setMonth(this.value)"><option value="all" ${FILTER_MONTH==="all"?"selected":""}>📅 Semua Bulan</option>${ks.map(k=>`<option value="${k}" ${FILTER_MONTH===k?"selected":""}>${monthLabelFull(k)}</option>`).join("")}</select>`;}
function keuCalc(){
  const notas=S.notas.filter(n=>inMonth(n.date));
  const pendapatan=notas.reduce((a,n)=>a+notaTotal(n),0);
  const isJual=it=>(it.kind||"jual")==="jual";
  const hpp=notas.reduce((a,n)=>a+notaItems(n).filter(isJual).reduce((x,it)=>x+(+it.qty||0)*(+it.hpp||0),0),0);
  const promo=notas.reduce((a,n)=>a+notaItems(n).filter(it=>!isJual(it)).reduce((x,it)=>x+(+it.qty||0)*(+it.hpp||0),0),0);
  const labaKotor=pendapatan-hpp;
  const co=cashOut().filter(c=>inMonth(c.date));
  const byCat=k=>co.filter(c=>c.category===k).reduce((a,c)=>a+(+c.amount||0),0);
  const biayaOp=byCat("operasional")+byCat("lain");
  const labaBersih=labaKotor-promo-biayaOp;
  const kasMasukP=S.notas.reduce((a,n)=>a+(n.payments||[]).filter(p=>inMonth(p.date)).reduce((x,p)=>x+(+p.amount||0),0),0);
  const kasKeluarP=co.reduce((a,c)=>a+(+c.amount||0),0);
  const totalMasuk=S.notas.reduce((a,n)=>a+notaPaid(n),0);
  const totalKeluar=cashOut().reduce((a,c)=>a+(+c.amount||0),0);
  return {notas,pendapatan,hpp,promo,labaKotor,biayaOp,labaBersih,byCat,co,kasMasukP,kasKeluarP,saldo:totalMasuk-totalKeluar};
}
const CASH_CATS=[["prive","Kas Diambil Pemilik"],["operasional","Biaya Operasional"],["modal","Beli Bahan / Modal"],["lain","Lainnya"]];
const cashCatLabel=k=>({prive:"Kas Diambil Pemilik",operasional:"Biaya Operasional",modal:"Beli Bahan / Modal",lain:"Lainnya"}[k]||k);
const cashCatIcon=k=>({prive:"👤",operasional:"⚙️",modal:"📦",lain:"•"}[k]||"•");
function rKeuangan(){
  const k=keuCalc();
  const tabs=`<div class="ltabs" style="max-width:320px;margin-bottom:14px"><button class="${KEU_TAB==='lr'?'on':''}" onclick="KEU_TAB='lr';rKeuangan()">Laba Rugi</button><button class="${KEU_TAB==='kas'?'on':''}" onclick="KEU_TAB='kas';rKeuangan()">Kas</button></div>`;
  let body;
  if(KEU_TAB==='lr'){
    body=`<div class="panel"><h3>Laba Rugi <span class="mini" style="font-weight:400">${monthLabelFull(FILTER_MONTH)}</span> <button class="btn sm" onclick="printLR()">🖨 Cetak</button></h3>
      <div class="lrrow"><span>Pendapatan (Omzet)</span><b>${rp(k.pendapatan)}</b></div>
      <div class="lrrow"><span>Harga Pokok Penjualan (HPP)</span><span style="color:var(--red)">(${rp(k.hpp)})</span></div>
      <div class="lrrow tot"><span>Laba Kotor</span><b>${rp(k.labaKotor)}</b></div>
      ${k.promo>0?`<div class="lrrow"><span>Biaya Barang Gratis (bonus/endorse/tester)</span><span style="color:var(--red)">(${rp(k.promo)})</span></div>`:""}
      <div class="lrrow"><span>Biaya Operasional Lain <span style="font-weight:400;color:var(--muted)">(non-produksi)</span></span><span style="color:var(--red)">(${rp(k.biayaOp)})</span></div>
      <div class="lrrow tot big"><span>Laba Bersih</span><b style="color:${k.labaBersih>=0?'var(--green)':'var(--red)'}">${rp(k.labaBersih)}</b></div></div>
      <p class="mini" style="padding:0 4px">HPP sudah termasuk bahan + operasional produksi. Kas diambil pemilik (prive) tidak masuk laba rugi — lihat tab <b>Kas</b>.</p>`;
  } else {
    const rows=k.co.map(c=>`<div class="crow"><div class="ci">${cashCatIcon(c.category)}</div><div class="cmain"><div class="ctitle">${cashCatLabel(c.category)}</div><div class="csub">${fmtDate(c.date)}${c.note?" · "+esc(c.note):""}</div></div><div class="cright"><div class="camt" style="color:var(--red)">−${rp(c.amount)}</div></div><button class="crow-del" onclick="delCash('${c.id}')">✕</button></div>`).join("");
    body=`<div class="cards"><div class="card accent"><div class="lbl">Saldo Kas (total)</div><div class="val">${rp(k.saldo)}</div></div></div>
    <div class="tiles">
      <div class="tile"><div class="tl2">⬇️ Kas Masuk</div><div class="tv" style="color:var(--green)">${rp(k.kasMasukP)}</div><div class="tc">${monthLabelFull(FILTER_MONTH)}</div></div>
      <div class="tile"><div class="tl2">⬆️ Kas Keluar</div><div class="tv" style="color:var(--red)">${rp(k.kasKeluarP)}</div><div class="tc">prive ${rp(k.byCat('prive'))}</div></div>
    </div>
    <div class="flexbtns"><button class="btn" onclick="editCash()">+ Kas Keluar / Prive</button></div>
    <div class="panel"><h3>Rincian Kas Keluar</h3>${k.co.length===0?'<div class="empty">Belum ada kas keluar.</div>':`<div class="clist">${rows}</div>`}</div>`;
  }
  document.getElementById("v-keuangan").innerHTML=`<h2 class="title">Keuangan</h2><div class="desc">Laba rugi & arus kas usaha.</div>${monthBar()}${tabs}${body}`;
}
function editCash(id){
  const c=id?JSON.parse(JSON.stringify(cashOut().find(x=>x.id===id))):{id:null,date:today(),category:"prive",amount:"",note:""};
  window._c=c;
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${c.id?"Edit":"Tambah"} Kas Keluar</h3>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal</label><input type="date" value="${c.date}" oninput="_c.date=this.value"></div><div><label class="f">Kategori</label><select oninput="_c.category=this.value">${CASH_CATS.map(([v,t])=>`<option value="${v}" ${c.category===v?"selected":""}>${t}</option>`).join("")}</select></div></div>
  <div style="margin-bottom:12px"><label class="f">Jumlah (Rp)</label><input inputmode="numeric" value="${c.amount?grp(c.amount):""}" oninput="this.value=grp(this.value);_c.amount=digits(this.value)" placeholder="0"></div>
  <label class="f">Catatan</label><input value="${esc(c.note||"")}" oninput="_c.note=this.value" placeholder="mis. ambil untuk keperluan pribadi">
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveCash()">Simpan</button></div>`);
}
async function saveCash(){const c=window._c;if(!(+c.amount>0)){toast("Isi jumlah.");return;}await api("saveCashOut",{cash:c});await reload();closeModal();toast("Kas keluar dicatat ✓");rKeuangan();}
async function delCash(id){if(confirm("Hapus catatan kas keluar ini?")){await api("deleteCashOut",{id});await reload();rKeuangan();}}
function printLR(){
  const k=keuCalc();
  document.getElementById("printArea").innerHTML=`<div class="preport">
    ${printHead("LAPORAN LABA RUGI","Periode: "+monthLabelFull(FILTER_MONTH)+" • Dicetak "+fmtDate(today()))}
    <div class="psec">Laba Rugi</div>
    <table><tbody>
      <tr><td>Pendapatan (Omzet)</td><td class="num">${rp(k.pendapatan)}</td></tr>
      <tr><td>Harga Pokok Penjualan (HPP)</td><td class="num">(${rp(k.hpp)})</td></tr>
      <tr class="tot"><td>Laba Kotor</td><td class="num">${rp(k.labaKotor)}</td></tr>
      ${k.promo>0?`<tr><td>Biaya Barang Gratis (bonus/endorse/tester)</td><td class="num">(${rp(k.promo)})</td></tr>`:""}
      <tr><td>Biaya Operasional Lain (non-produksi)</td><td class="num">(${rp(k.biayaOp)})</td></tr>
      <tr class="tot"><td><b>Laba Bersih</b></td><td class="num"><b>${rp(k.labaBersih)}</b></td></tr>
    </tbody></table>
    <div class="psec">Arus Kas — ${monthLabelFull(FILTER_MONTH)}</div>
    <table><tbody>
      <tr><td>Kas Masuk (pembayaran diterima)</td><td class="num">${rp(k.kasMasukP)}</td></tr>
      <tr><td>Kas Keluar</td><td class="num">(${rp(k.kasKeluarP)})</td></tr>
      <tr><td>&nbsp;&nbsp;• termasuk Kas Diambil Pemilik</td><td class="num">(${rp(k.byCat('prive'))})</td></tr>
      <tr class="tot"><td><b>Saldo Kas (total keseluruhan)</b></td><td class="num"><b>${rp(k.saldo)}</b></td></tr>
    </tbody></table>
    <div class="psub" style="text-align:left;margin-top:16px">Racikin • ${esc(BIZ.name||"")}</div>
  </div>`;
  window.print();
}
function toast(m){const t=document.getElementById("toast");t.textContent=m;t.classList.add("show");clearTimeout(window._tt);window._tt=setTimeout(()=>t.classList.remove("show"),2200);}

// ---------- derived ----------
const prod=id=>S.products.find(p=>p.id===id);
const store=id=>S.stores.find(s=>s.id===id);
const mat=id=>S.materials.find(m=>m.id===id);
const notaItems=n=>n.items||[];
let _stockCache=null;
function stock(pid){
  if(!_stockCache){const m={};S.batches.forEach(b=>(b.outputs||[]).forEach(o=>{m[o.productId]=(m[o.productId]||0)+(+o.qty||0);}));S.notas.forEach(n=>notaItems(n).forEach(it=>{m[it.productId]=(m[it.productId]||0)-(+it.qty||0);}));_stockCache=m;}
  return _stockCache[pid]||0;
}
// ringkasan per NOTA (gabungan semua item)
const notaTotal=n=>notaItems(n).reduce((a,it)=>a+(+it.qty||0)*(+it.harga||0),0);
const notaProfit=n=>notaItems(n).reduce((a,it)=>a+(+it.qty||0)*((+it.harga||0)-(+it.hpp||0)),0);
const notaQty=n=>notaItems(n).reduce((a,it)=>a+(+it.qty||0),0);
const notaPaid=n=>(n.payments||[]).reduce((a,p)=>a+(+p.amount||0),0);
const notaDue=n=>notaTotal(n)-notaPaid(n);
function notaStatus(n){const t=notaTotal(n),p=notaPaid(n);if(t<=0)return"lunas";if(p<=0)return"belum";if(p>=t)return"lunas";return"sebagian";}
// nomor nota default: NOTA-YYYYMMDD-NNN (urut per hari)
function nextNotaNo(dateStr){
  const ymd=(dateStr||today()).replace(/-/g,"");
  const n=S.notas.filter(x=>(x.date||"").replace(/-/g,"")===ymd).length+1;
  return `${(BIZ.alias||"nota").toUpperCase()}-${ymd}-${String(n).padStart(3,"0")}`;
}
function batchCalc(b){
  const mat=(b.materials||[]).reduce((a,x)=>a+(+x.qty||0)*(+x.price||0),0);
  const ops=(b.ops||[]).reduce((a,o)=>a+(+o.amount||0),0);
  const total=mat+ops;
  const validOut=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0);
  const outIds=new Set(validOut.map(o=>o.productId));
  const bottles=validOut.reduce((a,o)=>a+(+o.qty||0),0);
  // qty & bobot per produk (gabung baris duplikat)
  const qtyByProd={},wByProd={};
  validOut.forEach(o=>{const p=prod(o.productId);const g=(p&&p.gram)||1;qtyByProd[o.productId]=(qtyByProd[o.productId]||0)+(+o.qty||0);wByProd[o.productId]=(wByProd[o.productId]||0)+(+o.qty||0)*g;});
  const wsum=Object.values(wByProd).reduce((a,v)=>a+v,0);
  // bahan: dialokasikan khusus ke satu produk vs dibagi rata (pool)
  let sharedMat=0;const assigned={};
  (b.materials||[]).forEach(x=>{const cost=(+x.qty||0)*(+x.price||0);const t=x.targetProduct;if(t&&outIds.has(t))assigned[t]=(assigned[t]||0)+cost;else sharedMat+=cost;});
  const pool=sharedMat+ops;
  const per={};
  Object.keys(qtyByProd).forEach(pid=>{const q=qtyByProd[pid];const fromPool=wsum>0?pool*wByProd[pid]/wsum/q:(bottles>0?pool/bottles:0);const fromAssigned=q>0?(assigned[pid]||0)/q:0;per[pid]=fromPool+fromAssigned;});
  return{mat,ops,total,bottles,wsum,perProduct:per,avg:bottles>0?total/bottles:0};
}
function srcLabel(s){return {seed:"awal",manual:"manual",batch:"dari batch"}[s]||esc(s||"-");}
function chgBadge(frac,nullText){
  if(frac==null)return nullText;
  if(frac>0)return `<span class="up">▲ ${(frac*100).toFixed(1)}%</span>`;
  if(frac<0)return `<span class="down">▼ ${(Math.abs(frac)*100).toFixed(1)}%</span>`;
  return '<span class="flat">0%</span>';
}
function matStat(m){
  const h=(m.history||[]).slice().sort((a,b)=>(a.date||"").localeCompare(b.date||"")||a.id-b.id);
  const cur=m.price,prev=h.length>1?h[h.length-2].price:null;
  const chg=prev!=null&&prev!==0?(cur-prev)/prev:null;
  return{h,cur,prev,chg,updates:h.length,last:h.length?h[h.length-1].date:null};
}

// ---------- nav ----------
document.querySelectorAll("#nav button").forEach(btn=>btn.onclick=()=>{
  document.querySelectorAll("#nav button").forEach(b=>b.classList.remove("active"));
  btn.classList.add("active");
  document.querySelectorAll(".view").forEach(v=>v.classList.remove("active"));
  document.getElementById("v-"+btn.dataset.v).classList.add("active");
  const tt=document.getElementById("topTitle");if(tt)tt.textContent=btn.dataset.label||"";
  const bv=btn.dataset.v;
  document.querySelectorAll(".botnav button[data-v]").forEach(b=>b.classList.toggle("active",b.dataset.v===bv));
  const more=document.getElementById("btnMore");if(more)more.classList.toggle("active",!["dashboard","distribusi","produksi"].includes(bv));
  curView=bv;try{localStorage.setItem("anna_view",curView);}catch(e){}renderCur();
});
function go(v){const b=document.querySelector('#nav button[data-v="'+v+'"]');if(b)b.click();closeMore();window.scrollTo(0,0);}
function openMore(){document.getElementById("sheetBg").classList.add("open");}
function closeMore(e){if(e&&e.target!==e.currentTarget)return;document.getElementById("sheetBg").classList.remove("open");}
function isMobile(){return window.matchMedia("(max-width:820px)").matches;}
try{window.matchMedia("(max-width:820px)").addEventListener("change",()=>{try{renderCur();}catch(e){}});}catch(e){}
function fabAction(){const map={dashboard:"editNota",distribusi:"editNota",pembayaran:"editNota",keuangan:"editCash",produksi:"editBatch",bahan:"editMat",produk:"editProd",toko:"editStore"};const fn=map[curView];if(fn&&typeof window[fn]==="function")window[fn]();else toast("Tak ada aksi tambah di halaman ini.");}
const MON=["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
const monthLabel=k=>MON[(+String(k).slice(5,7))-1]||k;
function donut(segs,cl){
  const tot=segs.reduce((a,s)=>a+s.v,0);const R=54,C=2*Math.PI*R,gap=2.5;let off=0;
  const arcs=tot<=0?"":segs.filter(s=>s.v>0).map(s=>{const len=s.v/tot*C;const d=Math.max(len-gap,.5);const el=`<circle cx="70" cy="70" r="${R}" fill="none" stroke="${s.color}" stroke-width="15" stroke-dasharray="${d} ${C-d}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"/>`;off+=len;return el;}).join("");
  return `<svg viewBox="0 0 140 140" width="128" height="128" style="flex-shrink:0"><circle cx="70" cy="70" r="${R}" fill="none" stroke="#f1eeed" stroke-width="15"/>${arcs}${cl?`<text x="70" y="74" text-anchor="middle" font-size="12" font-weight="600" fill="#8a8f99">${cl}</text>`:""}</svg>`;
}
function crow(o){return `<div class="crow"${o.onclick?` onclick="${o.onclick}"`:""}>`
  +`<div class="ci">${o.icon}</div>`
  +`<div class="cmain"><div class="ctitle">${o.title}</div>${o.sub?`<div class="csub">${o.sub}</div>`:""}${o.badges||""}</div>`
  +`<div class="cright">${o.amt!=null&&o.amt!==""?`<div class="camt"${o.amtColor?` style="color:${o.amtColor}"`:""}>${o.amt}</div>`:""}${o.status?`<span class="pill ${o.status}">${o.status.toUpperCase()}</span>`:""}${o.acts||""}</div>`
  +`${o.del?`<button class="crow-del" onclick="event.stopPropagation();${o.del}">✕</button>`:""}`
  +`</div>`;}
function renderCur(){({dashboard:rDash,pos:rPOS,produksi:rProduksi,distribusi:rDistribusi,pembayaran:rPembayaran,keuangan:rKeuangan,bahan:rBahan,produk:rProduk,toko:rToko,profile:rProfile,backup:rBackup}[curView])();}

// ---------- modal ----------
const mBg=document.getElementById("modalBg"),mEl=document.getElementById("modal");
function openModal(h){mEl.innerHTML=h;mBg.classList.add("open");}
function closeModal(){mBg.classList.remove("open");mEl.innerHTML="";}
mBg.onclick=e=>{if(e.target===mBg)closeModal();};

// ================= DASHBOARD =================
// --- helper waktu & delta ---
function monthOffset(base,delta){const d=new Date(base+"-01T00:00");const x=new Date(d.getFullYear(),d.getMonth()+delta,1);return x.getFullYear()+"-"+String(x.getMonth()+1).padStart(2,"0");}
function lastMonths(n){const base=FILTER_MONTH==="all"?curMonth():FILTER_MONTH;const arr=[];for(let i=n-1;i>=0;i--)arr.push(monthOffset(base,-i));return arr;}
function daysSince(dateStr){if(!dateStr)return 0;const a=new Date(dateStr+"T00:00"),b=new Date(today()+"T00:00");return Math.max(0,Math.round((b-a)/86400000));}
function momDelta(byMonthObj){if(FILTER_MONTH==="all")return null;const pk=monthOffset(FILTER_MONTH,-1);const pv=byMonthObj[pk]||0,cv=byMonthObj[FILTER_MONTH]||0;return pv===0?null:(cv-pv)/pv;}
function momBadge(frac){if(frac==null)return '<span style="opacity:.75">belum ada pembanding</span>';const up=frac>=0;return `<span style="color:${up?'#c8f2d4':'#ffd3c7'};font-weight:700">${up?'▲':'▼'} ${Math.abs(frac*100).toFixed(0)}% vs bln lalu</span>`;}
function deltaTag(frac){if(frac==null)return '<span class="mini flat">baru</span>';const up=frac>=0;return `<span class="mini" style="color:${up?'var(--green)':'var(--red)'};font-weight:700">${up?'▲':'▼'} ${Math.abs(frac*100).toFixed(0)}% vs bln lalu</span>`;}

// --- kalkulasi terpusat dashboard (dipakai versi mobile & desktop) ---
function computeDash(){
  const fnotas=S.notas.filter(n=>inMonth(n.date));
  const omzet=fnotas.reduce((a,n)=>a+notaTotal(n),0);
  const laba=fnotas.reduce((a,n)=>a+notaProfit(n),0);
  const margin=omzet>0?laba/omzet*100:0;
  const byOmz={},byLab={};S.notas.forEach(n=>{const k=(n.date||"").slice(0,7);if(!k)return;byOmz[k]=(byOmz[k]||0)+notaTotal(n);byLab[k]=(byLab[k]||0)+notaProfit(n);});
  const dOmz=momDelta(byOmz),dLab=momDelta(byLab);
  // posisi keuangan (saldo/piutang/stok = kondisi SEKARANG, lintas semua bulan)
  const saldoKas=keuCalc().saldo;
  const piutang=S.notas.reduce((a,n)=>a+Math.max(0,notaDue(n)),0);
  const nilaiStok=S.products.reduce((a,p)=>a+Math.max(0,stock(p.id))*(+p.hpp||0),0);
  // tren 6 bulan (omzet + laba)
  const trend=lastMonths(6).map(m=>({m,omzet:byOmz[m]||0,laba:byLab[m]||0}));
  // produk paling menguntungkan (bulan terpilih)
  const labaByP={},omzByP={};
  fnotas.forEach(n=>notaItems(n).forEach(it=>{const p=it.productId;labaByP[p]=(labaByP[p]||0)+(+it.qty||0)*((+it.harga||0)-(+it.hpp||0));omzByP[p]=(omzByP[p]||0)+(+it.qty||0)*(+it.harga||0);}));
  const prodRank=S.products.map(p=>({n:p.name,laba:labaByP[p.id]||0,omzet:omzByP[p.id]||0})).filter(x=>x.omzet!==0||x.laba!==0).sort((a,b)=>b.laba-a.laba);
  // top penunggak (lintas waktu) + umur piutang
  const dueBy={};S.notas.forEach(n=>{const due=notaDue(n);if(due>0&&notaStatus(n)!=="lunas"){const sid=n.storeId;if(!dueBy[sid])dueBy[sid]={due:0,oldest:n.date||""};dueBy[sid].due+=due;if((n.date||"")&&(n.date<dueBy[sid].oldest||!dueBy[sid].oldest))dueBy[sid].oldest=n.date;}});
  const penunggak=Object.entries(dueBy).map(([sid,v])=>({n:(store(sid)||{}).name||"?",due:v.due,days:daysSince(v.oldest)})).sort((a,b)=>b.due-a.due);
  // pusat aksi
  const low=S.products.filter(p=>stock(p.id)<20).map(p=>({n:p.name,s:stock(p.id)})).sort((a,b)=>a.s-b.s);
  const overdue=S.notas.filter(n=>notaStatus(n)!=="lunas"&&daysSince(n.date)>14);
  const priceUp=S.materials.map(m=>{const st=matStat(m);return{n:m.name,chg:st.chg,cur:st.cur??m.price};}).filter(x=>x.chg!=null&&x.chg>0.005).sort((a,b)=>b.chg-a.chg);
  const recent=[...fnotas].slice(0,6);
  return {fnotas,omzet,laba,margin,dOmz,dLab,saldoKas,piutang,nilaiStok,trend,prodRank,penunggak,low,overdue,priceUp,recent};
}
// --- grafik garis tren 6 bulan (Omzet oranye + Laba biru, satu sumbu Rupiah) ---
function trendChart(trend){
  const W=560,H=172,padL=10,padR=10,padT=16,padB=26,n=trend.length;
  const vals=trend.flatMap(t=>[t.omzet,t.laba]);const mx=Math.max(1,...vals);
  const X=i=>padL+(W-padL-padR)*(n<=1?0.5:i/(n-1));
  const Y=v=>H-padB-(H-padT-padB)*(v/mx);
  const path=key=>trend.map((t,i)=>(i?"L":"M")+X(i).toFixed(1)+" "+Y(t[key]).toFixed(1)).join(" ");
  const dots=(key,color)=>trend.map((t,i)=>`<circle cx="${X(i).toFixed(1)}" cy="${Y(t[key]).toFixed(1)}" r="3" fill="${color}"><title>${monthLabelFull(t.m)} — ${key==="omzet"?"Omzet":"Laba"} ${rp(t[key])}</title></circle>`).join("");
  const labels=trend.map((t,i)=>`<text x="${X(i).toFixed(1)}" y="${H-8}" font-size="10" fill="#8a8f99" text-anchor="middle">${monthLabel(t.m)}</text>`).join("");
  if(vals.every(v=>v===0))return '<div class="empty">Belum ada data penjualan.</div>';
  const areaO=`M${X(0).toFixed(1)} ${(H-padB).toFixed(1)} `+trend.map((t,i)=>`L${X(i).toFixed(1)} ${Y(t.omzet).toFixed(1)}`).join(" ")+` L${X(n-1).toFixed(1)} ${(H-padB).toFixed(1)} Z`;
  return `<div class="pchart"><svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="xMidYMid meet" style="min-width:340px">
    <defs><linearGradient id="tg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--orange)" stop-opacity=".28"/><stop offset="1" stop-color="var(--orange)" stop-opacity="0"/></linearGradient></defs>
    <line x1="${padL}" y1="${H-padB}" x2="${W-padR}" y2="${H-padB}" stroke="#eee"/>
    <path d="${areaO}" fill="url(#tg)"/>
    <path d="${path("omzet")}" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    <path d="${path("laba")}" fill="none" stroke="#2471a3" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${dots("omzet","var(--red)")}${dots("laba","#2471a3")}${labels}
  </svg></div><div class="legend2"><span><i style="background:var(--red)"></i>Omzet</span><span><i style="background:#2471a3"></i>Laba</span></div>`;
}
// --- bar produk berdasarkan LABA (hijau untung, merah rugi) ---
const prodShort=n=>String(n||"").replace(/\s*\([^)]*\)\s*$/,"").trim();   // buang ukuran "(150gr)" utk label ringkas
function prodProfitBars(prodRank){
  const rank=prodRank.slice(0,6);
  if(!rank.length)return '<div class="empty">Belum ada penjualan.</div>';
  const mx=Math.max(1,...rank.map(x=>Math.abs(x.laba)));
  return rank.map(x=>{const m=x.omzet>0?x.laba/x.omzet*100:0;const neg=x.laba<0;return `<div class="barrow2"><div class="brtop"><span class="bname">${esc(prodShort(x.n))}</span><b style="color:${neg?"var(--red)":"var(--green)"}">${rp(x.laba)}</b></div><div class="brbar"><span style="width:${Math.abs(x.laba)/mx*100}%;background:${neg?"var(--red)":"var(--green)"}"></span></div><div class="brsub">margin ${m.toFixed(0)}%${x.omzet>0?` · omzet ${rp(x.omzet)}`:""}</div></div>`;}).join("");
}
// --- daftar penunggak + umur piutang ---
function penunggakList(penunggak){
  if(!penunggak.length)return '<div class="empty">Tidak ada piutang. 🎉</div>';
  const mx=Math.max(1,...penunggak.map(x=>x.due));
  return penunggak.slice(0,6).map(s=>`<div class="barrow2"><div class="brtop"><span>${esc(s.n)} <span class="mini">${s.days} hari</span></span><b style="color:var(--amber)">${rp(s.due)}</b></div><div class="brbar"><span style="width:${s.due/mx*100}%;background:linear-gradient(90deg,var(--orange-l),var(--amber))"></span></div></div>`).join("");
}
// --- pusat aksi: sinyal yang perlu ditindak ---
function actionCenter(d){
  const items=[];
  d.low.slice(0,5).forEach(x=>items.push(`<div class="actrow"><span class="actdot" style="background:var(--red)"></span><div><b>Stok menipis:</b> ${esc(x.n)} — ${x.s} botol</div></div>`));
  if(d.overdue.length)items.push(`<div class="actrow"><span class="actdot" style="background:var(--amber)"></span><div><b>${d.overdue.length} nota</b> lewat 14 hari belum lunas</div></div>`);
  d.priceUp.slice(0,4).forEach(x=>items.push(`<div class="actrow"><span class="actdot" style="background:#b8560f"></span><div><b>Harga bahan naik:</b> ${esc(x.n)} +${(x.chg*100).toFixed(0)}% → ${rp(x.cur)}</div></div>`));
  return items.length?items.join(""):'<div class="empty">Semua aman 👍 tak ada yang perlu ditindak.</div>';
}
function heroPill(frac){
  if(frac==null)return '<span class="hpill">bulan pertama</span>';
  const up=frac>=0;return `<span class="hpill ${up?"up":"dn"}">${up?"▲":"▼"} ${Math.abs(frac*100).toFixed(0)}% vs bln lalu</span>`;
}
// kartu sorotan otomatis — pilih kabar paling penting (mirip kartu "Sales revenue increased" di referensi)
function dashHighlight(d){
  let icon="📈",head="",sub="",link="Lihat",dest="keuangan";
  if(d.dOmz!=null&&d.dOmz>0.001){icon="🎉";head=`Omzet naik ${(d.dOmz*100).toFixed(0)}% dari bulan lalu`;sub="Pertahankan momentum penjualan";dest="keuangan";link="Laba rugi";}
  else if(d.dOmz!=null&&d.dOmz<-0.001){icon="📉";head=`Omzet turun ${Math.abs(d.dOmz*100).toFixed(0)}% dari bulan lalu`;sub="Cek produk & pelanggan yang melambat";dest="keuangan";link="Laba rugi";}
  else if(d.low.length){icon="📦";head=`${d.low.length} produk stok menipis`;sub="Segera produksi biar tak kehabisan";dest="produksi";link="Buat batch";}
  else if(d.piutang>0){icon="💸";head=`${rp(d.piutang)} piutang belum tertagih`;sub=`Dari ${d.penunggak.length} toko`;dest="pembayaran";link="Tagih";}
  else if(d.prodRank.length){icon="⭐";head=`${esc(prodShort(d.prodRank[0].n))} paling cuan`;sub=`Laba ${rp(d.prodRank[0].laba)} bulan ini`;dest="produk";link="Produk";}
  else {icon="👋";head="Selamat datang di Racikin";sub="Mulai catat produksi & penjualanmu";dest="produksi";link="Mulai";}
  return `<div class="hlcard" onclick="go('${dest}')"><div class="hli">${icon}</div><div class="hlmain"><div class="hlh">${head}</div><div class="hls">${sub}</div></div><div class="hlgo">${link} ›</div></div>`;
}
// daftar penunggak gaya baris (lingkaran inisial + nilai kanan) — mirip referensi
function penunggakRows(penunggak){
  if(!penunggak.length)return '<div class="empty">Tidak ada piutang. 🎉</div>';
  return `<div class="clist">${penunggak.slice(0,5).map(s=>`<div class="crow" onclick="go('pembayaran')"><div class="ci mono">${esc((s.n[0]||"?").toUpperCase())}</div><div class="cmain"><div class="ctitle">${esc(s.n)}</div><div class="csub">piutang · ${s.days} hari</div></div><div class="cright"><div class="camt" style="color:var(--amber)">${rp(s.due)}</div></div></div>`).join("")}</div>`;
}
function rDashM(){
  const d=computeDash();
  const uname=BIZ.user||"Racikin";
  document.getElementById("v-dashboard").innerHTML=`
  <div class="dgreet"><div><h2>Halo, ${esc(uname)} 👋</h2><p>Ringkasan ${esc(BIZ.name||"usaha")}</p></div></div>
  ${monthBar()}
  <div class="hero2">
    <div class="hl">💰 Omzet · ${monthLabelFull(FILTER_MONTH)}</div>
    <div class="hv">${rp(d.omzet)}</div>
    <div class="hpills">${heroPill(d.dOmz)}<span class="hpill">Laba ${rp(d.laba)} · ${d.margin.toFixed(0)}%</span></div>
    <div class="hact">
      <button onclick="go('pos')"><span class="hai">🛒</span>Kasir</button>
      <button onclick="editNota()"><span class="hai">🧾</span>Nota</button>
      <button onclick="go('pembayaran')"><span class="hai">💰</span>Bayar</button>
    </div>
  </div>
  ${dashHighlight(d)}
  <div class="tiles">
    <div class="mcard"><div class="mi" style="background:var(--green-bg);color:var(--green)">🏦</div><div class="ml">Saldo Kas</div><div class="mv" style="color:${d.saldoKas>=0?"var(--green)":"var(--red)"}">${rp(d.saldoKas)}</div><div class="ms muted">posisi sekarang</div></div>
    <div class="mcard"><div class="mi" style="background:var(--amber-bg);color:var(--amber)">⏳</div><div class="ml">Piutang</div><div class="mv" style="color:var(--amber)">${rp(d.piutang)}</div><div class="ms muted">belum tertagih</div></div>
    <div class="mcard"><div class="mi" style="background:var(--green-bg);color:var(--green)">💵</div><div class="ml">Laba Kotor</div><div class="mv">${rp(d.laba)}</div><div class="ms">${deltaTag(d.dLab)}</div></div>
    <div class="mcard"><div class="mi" style="background:var(--blue-bg);color:var(--blue)">📦</div><div class="ml">Nilai Stok</div><div class="mv">${rp(d.nilaiStok)}</div><div class="ms muted">HPP di gudang</div></div>
  </div>
  <div class="dsec"><h3>Tren 6 Bulan</h3></div>
  <div class="card2">${trendChart(d.trend)}</div>
  <div class="dsec"><h3>Produk Terlaris</h3><a onclick="go('produk')">Semua</a></div>
  <div class="card2">${prodProfitBars(d.prodRank)}</div>
  <div class="dsec"><h3>💸 Top Penunggak</h3><a onclick="go('pembayaran')">Tagih</a></div>
  ${penunggakRows(d.penunggak)}
  <div class="dsec"><h3>⚡ Perlu Tindakan</h3></div>
  <div class="card2">${actionCenter(d)}</div>
  <div class="dsec"><h3>Nota Terbaru</h3><a onclick="go('distribusi')">Semua</a></div>
  ${d.recent.length===0?'<div class="empty">Belum ada nota.</div>':`<div class="clist">${d.recent.map(n=>crow({icon:"🧾",title:esc(n.notaNo||"-"),sub:`${esc((store(n.storeId)||{}).name||"?")} · ${fmtDate(n.date)}`,amt:rp(notaTotal(n)),status:notaStatus(n),onclick:"go('distribusi')"})).join("")}</div>`}`;
}
function rDash(){
  if(isMobile())return rDashM();
  const d=computeDash();
  document.getElementById("v-dashboard").innerHTML=`
  <h2 class="title">Dashboard</h2><div class="desc">Kokpit bisnis — pilih periode di kanan; posisi kas selalu kondisi terkini.</div>
  ${monthBar()}
  <div class="cards">
    <div class="card accent"><div class="lbl">Omzet · ${monthLabelFull(FILTER_MONTH)}</div><div class="val">${rp(d.omzet)}</div><div class="lbl" style="margin:8px 0 0">${d.dOmz==null?"belum ada pembanding":`${d.dOmz>=0?"▲":"▼"} ${Math.abs(d.dOmz*100).toFixed(0)}% vs bln lalu`}</div></div>
    <div class="card green"><div class="lbl">Laba Kotor</div><div class="val">${rp(d.laba)}</div><div class="lbl" style="margin:8px 0 0">margin ${d.margin.toFixed(0)}% · ${deltaTag(d.dLab)}</div></div>
    <div class="card"><div class="lbl">Margin</div><div class="val">${d.margin.toFixed(0)}%</div><div class="lbl" style="margin:8px 0 0">laba per rupiah omzet</div></div>
  </div>
  <div class="cards">
    <div class="card"><div class="lbl">🏦 Saldo Kas <span class="mini">(sekarang)</span></div><div class="val sm" style="color:${d.saldoKas>=0?"var(--green)":"var(--red)"}">${rp(d.saldoKas)}</div></div>
    <div class="card amber"><div class="lbl">⏳ Piutang <span class="mini">(belum tertagih)</span></div><div class="val sm">${rp(d.piutang)}</div></div>
    <div class="card"><div class="lbl">📦 Nilai Stok <span class="mini">(HPP di gudang)</span></div><div class="val sm">${rp(d.nilaiStok)}</div></div>
  </div>
  <div class="grid2">
    <div class="panel"><h3>Tren 6 Bulan — Omzet &amp; Laba</h3>${trendChart(d.trend)}</div>
    <div class="panel"><h3>Produk Paling Menguntungkan</h3>${prodProfitBars(d.prodRank)}</div>
  </div>
  <div class="grid2">
    <div class="panel"><h3>💸 Top Penunggak <span class="mini" style="font-weight:400">(umur piutang)</span></h3>${penunggakList(d.penunggak)}</div>
    <div class="panel"><h3>⚡ Perlu Tindakan</h3>${actionCenter(d)}</div>
  </div>
  <div class="panel"><h3>Nota Terbaru</h3>${d.recent.length===0?'<div class="empty">Belum ada nota.</div>':`<table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th class="num">Item</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>${d.recent.map(n=>`<tr><td>${fmtDate(n.date)}</td><td>${esc(n.notaNo||"-")}</td><td>${esc((store(n.storeId)||{}).name||"?")}</td><td class="num">${notaItems(n).length}</td><td class="num">${rp(notaTotal(n))}</td><td><span class="pill ${notaStatus(n)}">${notaStatus(n).toUpperCase()}</span></td></tr>`).join("")}</tbody></table>`}</div>`;
}

// ===== QR ENCODER + QRIS (di-embed, offline) =====
// Encoder QR minimal (byte mode, versi 1-10, ECC level M) — cukup untuk QRIS (~140 byte).
// Berbasis spesifikasi QR (ISO/IEC 18004) gaya Nayuki. Return matriks boolean.
(function(global){
  var ECC_CW = { // ecc codewords per block, [level][ver1..20]
    L:[7,10,15,20,26,18,20,24,30,18,20,24,26,30,22,24,28,30,28,28],
    M:[10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26],
    Q:[13,22,18,26,18,24,18,22,20,24,28,26,24,20,30,24,28,28,26,30],
    H:[17,28,22,16,22,28,26,26,24,28,24,28,22,24,24,30,28,28,26,28]
  };
  var NUM_BLK = {
    L:[1,1,1,1,1,2,2,2,2,4,4,4,4,4,6,6,6,6,7,8],
    M:[1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16],
    Q:[1,1,2,2,4,4,6,6,8,8,8,10,12,16,12,17,16,18,21,20],
    H:[1,1,2,4,4,4,5,6,8,8,11,11,16,16,18,16,19,21,25,25]
  };
  var TOTAL_CW=[26,44,70,100,134,172,196,242,292,346,404,466,532,581,655,733,815,901,991,1085];
  var ALIGN=[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50],
    [6,30,54],[6,32,58],[6,34,62],[6,26,46,66],[6,26,48,70],[6,26,50,74],[6,30,54,78],[6,30,56,82],[6,30,58,86],[6,34,62,90]];
  var ECL_BITS={L:1,M:0,Q:3,H:2}; // 2-bit indicator dlm format info

  // GF(256)
  var EXP=new Array(512),LOG=new Array(256);
  (function(){var x=1;for(var i=0;i<255;i++){EXP[i]=x;LOG[x]=i;x<<=1;if(x&0x100)x^=0x11d;}for(var i2=255;i2<512;i2++)EXP[i2]=EXP[i2-255];})();
  function gmul(a,b){return (a===0||b===0)?0:EXP[LOG[a]+LOG[b]];}
  function rsGenPoly(deg){var p=[1];for(var i=0;i<deg;i++){var np=new Array(p.length+1).fill(0);for(var j=0;j<p.length;j++){np[j]^=gmul(p[j],1);np[j+1]^=gmul(p[j],EXP[i]);}p=np;}return p;}
  function rsEnc(data,ecLen){var gen=rsGenPoly(ecLen);var res=new Array(ecLen).fill(0);for(var i=0;i<data.length;i++){var f=data[i]^res[0];res.shift();res.push(0);if(f!==0)for(var j=0;j<ecLen;j++)res[j]^=gmul(gen[j+1],f);}return res;}

  function pickVersion(len,ecl){ // byte mode: mode(4)+cc(8 atau 16)+8*len bits
    for(var v=1;v<=20;v++){
      var ccBits=v<10?8:16;
      var dataCw=TOTAL_CW[v-1]-ECC_CW[ecl][v-1]*NUM_BLK[ecl][v-1];
      var need=Math.ceil((4+ccBits+8*len)/8);
      if(need<=dataCw)return v;
    }
    return -1;
  }

  function encode(text,ecl){
    ecl=ecl||'M';
    var bytes=[];for(var i=0;i<text.length;i++){var c=text.charCodeAt(i); if(c<128)bytes.push(c); else {var e=unescape(encodeURIComponent(text.charAt(i)));for(var k=0;k<e.length;k++)bytes.push(e.charCodeAt(k));}}
    var v=pickVersion(bytes.length,ecl);
    if(v<0)throw new Error("Data terlalu panjang untuk QR (maks ~270 byte).");
    var ccBits=v<10?8:16;
    var totalCw=TOTAL_CW[v-1], ecPerBlk=ECC_CW[ecl][v-1], numBlk=NUM_BLK[ecl][v-1];
    var dataCw=totalCw-ecPerBlk*numBlk;
    // --- bitstream ---
    var bits=[];
    function put(val,n){for(var b=n-1;b>=0;b--)bits.push((val>>b)&1);}
    put(4,4); // byte mode
    put(bytes.length,ccBits);
    for(var i2=0;i2<bytes.length;i2++)put(bytes[i2],8);
    // terminator
    var cap=dataCw*8;
    for(var t=0;t<4&&bits.length<cap;t++)bits.push(0);
    while(bits.length%8!==0)bits.push(0);
    var dcw=[];for(var p=0;p<bits.length;p+=8){var by=0;for(var q=0;q<8;q++)by=(by<<1)|bits[p+q];dcw.push(by);}
    var pad=[0xEC,0x11],pi=0;while(dcw.length<dataCw){dcw.push(pad[pi&1]);pi++;}
    // --- blok data + ecc ---
    var base=Math.floor(dataCw/numBlk),rem=dataCw%numBlk;
    var blocks=[],ecblocks=[],pos=0;
    for(var b2=0;b2<numBlk;b2++){var sz=base+(b2>=numBlk-rem?1:0);var d=dcw.slice(pos,pos+sz);pos+=sz;blocks.push(d);ecblocks.push(rsEnc(d,ecPerBlk));}
    // interleave
    var out=[];var maxD=0;for(var b3=0;b3<numBlk;b3++)maxD=Math.max(maxD,blocks[b3].length);
    for(var i3=0;i3<maxD;i3++)for(var b4=0;b4<numBlk;b4++)if(i3<blocks[b4].length)out.push(blocks[b4][i3]);
    for(var i4=0;i4<ecPerBlk;i4++)for(var b5=0;b5<numBlk;b5++)out.push(ecblocks[b5][i4]);
    // codewords -> bit array
    var allbits=[];for(var o=0;o<out.length;o++)for(var bb=7;bb>=0;bb--)allbits.push((out[o]>>bb)&1);

    // --- matriks ---
    var size=17+4*v;
    var m=[],reserved=[];
    for(var r=0;r<size;r++){m.push(new Array(size).fill(null));reserved.push(new Array(size).fill(false));}
    function setF(r,c,val){m[r][c]=val?1:0;reserved[r][c]=true;}
    function finder(r,c){for(var dr=-1;dr<=7;dr++)for(var dc=-1;dc<=7;dc++){var rr=r+dr,cc=c+dc;if(rr<0||rr>=size||cc<0||cc>=size)continue;var dark=(dr>=0&&dr<=6&&(dc===0||dc===6))||(dc>=0&&dc<=6&&(dr===0||dr===6))||(dr>=2&&dr<=4&&dc>=2&&dc<=4);setF(rr,cc,dark);}}
    finder(0,0);finder(0,size-7);finder(size-7,0);
    // timing
    for(var ti=8;ti<size-8;ti++){if(!reserved[6][ti])setF(6,ti,ti%2===0);if(!reserved[ti][6])setF(ti,6,ti%2===0);}
    // dark module
    setF(size-8,8,true);
    // alignment
    var ap=ALIGN[v-1],apn=ap.length;
    for(var a1=0;a1<apn;a1++)for(var a2=0;a2<apn;a2++){
      if((a1===0&&a2===0)||(a1===0&&a2===apn-1)||(a1===apn-1&&a2===0))continue; // 3 sudut = tumpang tindih finder, dilewati
      var ar=ap[a1],ac=ap[a2];
      for(var dr2=-2;dr2<=2;dr2++)for(var dc2=-2;dc2<=2;dc2++){var dark2=Math.max(Math.abs(dr2),Math.abs(dc2))!==1;setF(ar+dr2,ac+dc2,dark2);}}
    // reservasi area format (belum diisi)
    for(var f1=0;f1<9;f1++){if(!reserved[8][f1]){reserved[8][f1]=true;}if(!reserved[f1][8]){reserved[f1][8]=true;}}
    for(var f2=0;f2<8;f2++){reserved[8][size-1-f2]=true;reserved[size-1-f2][8]=true;}
    // reservasi version info (v>=7)
    if(v>=7){for(var vr=0;vr<6;vr++)for(var vc=0;vc<3;vc++){reserved[vr][size-11+vc]=true;reserved[size-11+vc][vr]=true;}}

    // --- taruh data (zigzag) ---
    var di=0,dir=-1,col=size-1;
    while(col>0){if(col===6)col--;
      for(var rr2=0;rr2<size;rr2++){var row=dir<0?size-1-rr2:rr2;
        for(var cc2=0;cc2<2;cc2++){var cx=col-cc2;if(reserved[row][cx])continue;var bit=di<allbits.length?allbits[di]:0;di++;m[row][cx]=bit;}
      }
      dir=-dir;col-=2;
    }

    // --- masking: pilih penalti terkecil ---
    function maskFn(k,r,c){switch(k){case 0:return (r+c)%2===0;case 1:return r%2===0;case 2:return c%3===0;case 3:return (r+c)%3===0;case 4:return (Math.floor(r/2)+Math.floor(c/3))%2===0;case 5:return ((r*c)%2)+((r*c)%3)===0;case 6:return (((r*c)%2)+((r*c)%3))%2===0;case 7:return (((r+c)%2)+((r*c)%3))%2===0;}}
    function fmtBits(mask){var data=(ECL_BITS[ecl]<<3)|mask;var rem=data;for(var i5=0;i5<10;i5++)rem=(rem<<1)^(((rem>>9)&1)?0x537:0);var bch=((data<<10)|rem)^0x5412;var arr=[];for(var b6=14;b6>=0;b6--)arr.push((bch>>b6)&1);return arr;}
    function placeFmt(mask){var f=fmtBits(mask);
      // sekitar kiri-atas
      for(var i6=0;i6<=5;i6++)m[8][i6]=f[i6];
      m[8][7]=f[6];m[8][8]=f[7];m[7][8]=f[8];
      for(var i7=9;i7<15;i7++)m[14-i7][8]=f[i7];
      // duplikat
      for(var i8=0;i8<7;i8++)m[size-1-i8][8]=f[i8];
      for(var i9=7;i9<15;i9++)m[8][size-15+i9]=f[i9];
    }
    function placeVer(){if(v<7)return;var data=v;var rem=data;for(var i10=0;i10<12;i10++)rem=(rem<<1)^(((rem>>11)&1)?0x1f25:0);var bch=(data<<12)|rem;var arr=[];for(var b7=17;b7>=0;b7--)arr.push((bch>>b7)&1);
      for(var i11=0;i11<18;i11++){var r3=Math.floor(i11/3),c3=i11%3;m[r3][size-11+c3]=arr[17-i11];m[size-11+c3][r3]=arr[17-i11];}}
    function penalty(mm){var pen=0,n=size;
      for(var r4=0;r4<n;r4++){var rc=1,cc3=1;for(var c4=1;c4<n;c4++){if(mm[r4][c4]===mm[r4][c4-1])rc++;else{if(rc>=5)pen+=3+(rc-5);rc=1;}if(mm[c4][r4]===mm[c4-1][r4])cc3++;else{if(cc3>=5)pen+=3+(cc3-5);cc3=1;}}if(rc>=5)pen+=3+(rc-5);if(cc3>=5)pen+=3+(cc3-5);}
      for(var r5=0;r5<n-1;r5++)for(var c5=0;c5<n-1;c5++){var vv=mm[r5][c5];if(vv===mm[r5][c5+1]&&vv===mm[r5+1][c5]&&vv===mm[r5+1][c5+1])pen+=3;}
      var dark=0;for(var r6=0;r6<n;r6++)for(var c6=0;c6<n;c6++)dark+=mm[r6][c6];var ratio=dark*100/(n*n);pen+=Math.floor(Math.abs(ratio-50)/5)*10;
      return pen;
    }
    var best=null,bestPen=1e9;
    for(var mk=0;mk<8;mk++){
      var mm=[];for(var r7=0;r7<size;r7++)mm.push(m[r7].slice());
      for(var r8=0;r8<size;r8++)for(var c8=0;c8<size;c8++)if(!reserved[r8][c8]&&maskFn(mk,r8,c8))mm[r8][c8]^=1;
      // pasang format/version di salinan (butuh fungsi yg tulis ke mm)
      (function(target){var f=fmtBits(mk);
        for(var i=0;i<=5;i++)target[8][i]=f[i];target[8][7]=f[6];target[8][8]=f[7];target[7][8]=f[8];for(var i2=9;i2<15;i2++)target[14-i2][8]=f[i2];
        for(var i3=0;i3<7;i3++)target[size-1-i3][8]=f[i3];for(var i4=7;i4<15;i4++)target[8][size-15+i4]=f[i4];
        if(v>=7){var data=v,rem=data;for(var i5=0;i5<12;i5++)rem=(rem<<1)^(((rem>>11)&1)?0x1f25:0);var bch=(data<<12)|rem;var arr=[];for(var b=17;b>=0;b--)arr.push((bch>>b)&1);for(var i6=0;i6<18;i6++){var r=Math.floor(i6/3),c=i6%3;target[r][size-11+c]=arr[17-i6];target[size-11+c][r]=arr[17-i6];}}
      })(mm);
      var pen=penalty(mm);
      if(pen<bestPen){bestPen=pen;best=mm;}
    }
    return {size:size,version:v,modules:best};
  }
  global.QR={encode:encode};
})(typeof window!=='undefined'?window:global);

// ---- QRIS: bangun kode dinamis (sisip nominal) + CRC16 + render QR ----
function crc16qris(s){var c=0xFFFF;for(var i=0;i<s.length;i++){c^=(s.charCodeAt(i)<<8);for(var j=0;j<8;j++){c=(c&0x8000)?((c<<1)^0x1021):(c<<1);c&=0xFFFF;}}return c.toString(16).toUpperCase().padStart(4,"0");}
function qrisValid(s){s=(s||"").trim();if(!/^0002/.test(s)||s.length<30)return false;var body=s.slice(0,-4);return crc16qris(body)===s.slice(-4).toUpperCase();}
function qrisDynamic(staticStr,amount){
  var s=(staticStr||"").trim();if(!/^0002/.test(s))return null;
  var i=0,fields=[];
  while(i<s.length){var tag=s.slice(i,i+2);var len=parseInt(s.slice(i+2,i+4),10);if(isNaN(len)||i+4+len>s.length)break;var val=s.slice(i+4,i+4+len);i+=4+len;if(tag==="63"||tag==="54")continue;if(tag==="01")fields.push(["01","12"]);else fields.push([tag,val]);}
  var amt=String(Math.round(amount));var f54=["54",amt];
  var res=[],ins=false;fields.forEach(function(f){if(f[0]==="58"&&!ins){res.push(f54);ins=true;}res.push(f);});if(!ins)res.push(f54);
  var base=res.map(function(f){return f[0]+String(f[1].length).padStart(2,"0")+f[1];}).join("");
  var withTag=base+"6304";return withTag+crc16qris(withTag);
}
function renderQR(canvas,text){
  var q=QR.encode(text,'L');var n=q.size,m=q.modules;
  var quiet=4,total=n+quiet*2;var scale=Math.max(2,Math.floor(280/total));var px=total*scale;
  canvas.width=px;canvas.height=px;var ctx=canvas.getContext("2d");
  ctx.fillStyle="#fff";ctx.fillRect(0,0,px,px);ctx.fillStyle="#000";
  for(var r=0;r<n;r++)for(var c=0;c<n;c++)if(m[r][c])ctx.fillRect((c+quiet)*scale,(r+quiet)*scale,scale,scale);
}

// ================= POS / KASIR =================
const POS_NAME="Umum (Kasir)";
let POS={cart:{},bayar:"",method:"Tunai",customer:"",q:""};
function posStore(){return S.stores.find(s=>s.name===POS_NAME);}
function posTotal(){return Object.entries(POS.cart).reduce((a,[pid,q])=>a+q*((prod(pid)||{}).harga||0),0);}
function posCount(){return Object.values(POS.cart).reduce((a,q)=>a+q,0);}
function rPOS(){
  const t0=today();
  const todaySales=S.notas.filter(n=>n.date===t0).reduce((a,n)=>a+notaTotal(n),0);
  const todayCount=S.notas.filter(n=>n.date===t0).length;
  const grid=S.products.map(p=>{const st=stock(p.id);const q=POS.cart[p.id]||0;
    return `<button class="pcard ${st<=0?"out":""}" data-n="${esc(p.name.toLowerCase())}" onclick="posAdd('${p.id}')">${q>0?`<span class="qbadge">${q}</span>`:""}<div class="pn">${esc(p.name)}</div><div class="pp">${rp(p.harga)}</div><div class="ps">stok ${st}</div></button>`;}).join("");
  const cnt=posCount(),tot=posTotal();
  document.getElementById("v-pos").innerHTML=`
    <div class="pos-head"><div class="pt">🛒 Kasir</div><div class="pos-today">Penjualan hari ini<b>${rp(todaySales)} · ${todayCount} nota</b></div></div>
    <input class="possearch" id="posSearch" placeholder="🔎 Cari produk…" oninput="posFilterGrid(this.value)" value="${esc(POS.q)}">
    ${S.products.length===0?'<div class="empty">Belum ada produk. Tambah dulu di menu Produk.</div>':`<div class="posgrid" id="posGrid">${grid}</div>`}
    ${cnt>0?`<div class="posbar" onclick="posCheckoutModal()"><div><div class="pc">${cnt} item</div><div class="pv">${rp(tot)}</div></div><div class="go">Bayar →</div></div>`:""}`;
  if(POS.q)posFilterGrid(POS.q);
}
function posFilterGrid(q){POS.q=q;q=(q||"").toLowerCase();document.querySelectorAll("#posGrid .pcard").forEach(c=>{c.style.display=c.dataset.n.includes(q)?"":"none";});}
function posAdd(pid){const p=prod(pid);if(!p)return;const st=stock(pid);const q=POS.cart[pid]||0;if(q>=st){toast("Stok "+p.name+" tinggal "+st);return;}POS.cart[pid]=q+1;rPOS();}
function posInc(pid){const st=stock(pid);const q=POS.cart[pid]||0;if(q>=st){toast("Stok tinggal "+st);return;}POS.cart[pid]=q+1;posCheckoutModal();}
function posDec(pid){const q=POS.cart[pid]||0;if(q<=1)delete POS.cart[pid];else POS.cart[pid]=q-1;if(posCount()===0){closeModal();rPOS();}else posCheckoutModal();}
function posCheckoutModal(){
  const tot=posTotal();if(tot<=0){toast("Keranjang kosong.");return;}
  const lines=Object.entries(POS.cart).map(([pid,q])=>{const p=prod(pid)||{};const lt=q*(+p.harga||0);
    return `<div class="crt"><div class="cn"><b>${esc(p.name||"?")}</b><span>${rp(p.harga)} / botol</span></div><div class="stp"><button onclick="posDec('${pid}')">−</button><span>${q}</span><button onclick="posInc('${pid}')">+</button></div><div class="lt">${rp(lt)}</div></div>`;}).join("");
  const isCash=POS.method==="Tunai";
  const bayar=isCash?(+digits(POS.bayar)||0):tot;
  const kembali=Math.max(0,bayar-tot);
  comboReset();
  const custCombo=combo(S.stores.filter(s=>s.name!==POS_NAME).map(s=>({id:s.id,label:s.name})),POS.customer,(id)=>{POS.customer=id;},"Umum / pelanggan langsung");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>Pembayaran</h3>
    <div style="max-height:32vh;overflow:auto;margin-bottom:8px">${lines}</div>
    <div class="crt" style="border:none;font-weight:800;font-size:16px"><div class="cn">Total</div><div>${rp(tot)}</div></div>
    <label class="f" style="margin-top:10px">Pelanggan <span class="mini">(opsional)</span></label>${custCombo}
    <label class="f" style="margin-top:12px">Metode Bayar</label>
    <div class="posseg">${["Tunai","Transfer","QRIS"].map(x=>`<button class="${POS.method===x?"on":""}" onclick="posSetMethod('${x}')">${x}</button>`).join("")}</div>
    ${isCash?`<label class="f">Uang Diterima</label><input id="posBayar" inputmode="numeric" placeholder="${grp(tot)}" value="${POS.bayar?grp(POS.bayar):""}" oninput="posBayarInput(this)">
    <div class="poskembali"><span>Kembalian</span><b id="posKembali">${rp(kembali)}</b></div>`:`<p class="mini" style="margin-top:6px">Pembayaran ${esc(POS.method)} dianggap pas — ${rp(tot)}.</p>`}
    <div style="margin-top:16px;display:flex;gap:8px"><button class="btn gray" onclick="closeModal()">Batal</button><button class="btn" style="flex:1" onclick="posCheckout()">✓ Selesaikan · ${rp(tot)}</button></div>`);
}
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
      <div style="font-size:27px;font-weight:800;color:var(--red);margin-bottom:12px">${rp(total)}</div>
      <div style="display:inline-block;background:#fff;padding:12px;border-radius:16px;box-shadow:0 8px 22px rgba(0,0,0,.12)"><canvas id="qrCanvas" style="display:block;width:260px;height:260px;image-rendering:pixelated"></canvas></div>
      <div class="mini" style="margin:12px auto 2px;max-width:280px">Pelanggan scan pakai <b>GoPay / OVO / Dana / ShopeePay / m-banking</b> — nominal sudah otomatis terisi.</div>
    </div>
    <div style="margin-top:16px;display:flex;gap:8px"><button class="btn gray" onclick="posCheckoutModal()">Kembali</button><button class="btn" style="flex:1" onclick="posFinalize(${total},0,'QRIS')">✓ Sudah Dibayar</button></div>`);
  renderQR(document.getElementById("qrCanvas"),dyn);
}
async function posFinalize(bayar,kembali,method){
  const tot=posTotal();if(tot<=0){toast("Keranjang kosong.");return;}
  let storeId=POS.customer||await ensurePosStore();
  if(!storeId){toast("Gagal menyiapkan pelanggan.");return;}
  const items=Object.entries(POS.cart).map(([pid,q])=>{const p=prod(pid)||{};return {productId:pid,qty:q,harga:+p.harga||0,hpp:+p.hpp||0,kind:"jual"};});
  const nota={id:null,date:today(),storeId,notaNo:nextNotaNo(today()),items};
  try{
    const res=await api("saveNota",{nota});
    await api("addPayment",{notaId:res.id,amount:tot,date:today(),note:"POS "+method});
    POS.cart={};POS.bayar="";POS.customer="";
    await reload();
    posSuccess(res.id,tot,bayar,kembali,method);
  }catch(e){/* api sudah menampilkan toast error (mis. stok kurang) */}
}
function posSuccess(id,total,bayar,kembali,method){
  openModal(`<div class="pos-done"><div class="ok">✓</div><h3 style="margin-bottom:6px">Transaksi Berhasil</h3>
    <div class="mini" style="margin-bottom:14px">Total ${rp(total)} · ${esc(method)}</div>
    ${method==="Tunai"?`<div class="poskembali" style="justify-content:center;gap:14px;margin-bottom:16px">Kembalian <b>${rp(kembali)}</b></div>`:""}
    <div style="display:flex;gap:8px"><button class="btn ghost" style="flex:1" onclick="printReceipt('${id}',${bayar},${kembali},'${esc(method)}')">🖨 Struk</button><button class="btn" style="flex:1" onclick="closeModal();rPOS()">+ Transaksi Baru</button></div></div>`);
}
function printReceipt(id,bayar,kembali,method){
  const n=S.notas.find(x=>x.id===id);if(!n){toast("Nota tak ditemukan.");return;}
  const p=S.profile||{};
  const contacts=[p.phone?"Telp "+p.phone:"",p.whatsapp?"WA "+p.whatsapp:""].filter(Boolean).map(esc).join(" · ");
  const items=notaItems(n).map(it=>{const pr=prod(it.productId)||{};const sub=(+it.qty||0)*(+it.harga||0);return `<div class="rrow"><span class="ri">${esc(pr.name||"?")}<br><span style="color:#555">${it.qty} × ${rp(it.harga)}</span></span><span>${rp(sub)}</span></div>`;}).join("");
  const total=notaTotal(n);
  document.getElementById("printArea").innerHTML=`<div class="preceipt">
    <img class="rlogo" src="${p.logo||"icons/logo-bowl.png"}" alt="">
    <h4>${esc(BIZ.name||"Racikin")}</h4>
    ${(p.address||contacts)?`<div class="rc">${p.address?esc(p.address):""}${p.address&&contacts?"<br>":""}${contacts}</div>`:""}
    <div class="rmeta">${esc(n.notaNo||"")}<br>${fmtDate(n.date)} · Kasir</div>
    ${items}
    <div class="rtot"><div class="rrow"><span>TOTAL</span><span>${rp(total)}</span></div>${bayar!=null?`<div class="rrow"><span>Bayar (${esc(method||"Tunai")})</span><span>${rp(bayar)}</span></div><div class="rrow"><span>Kembalian</span><span>${rp(kembali||0)}</span></div>`:""}</div>
    <div class="rthx">Terima kasih 🙏<br>— ${esc(BIZ.name||"Racikin")} —</div>
  </div>`;
  window.print();
}

// ================= PRODUKSI =================
function rProduksi(){
  if(isMobile()){
    const rows=S.batches.map(b=>{const c=batchCalc(b);
      const prods=(b.outputs||[]).map(o=>esc((prod(o.productId)||{}).name||"?")).join(", ");
      const acts=`<div class="cacts"><button class="btn sm" onclick="event.stopPropagation();printBatch('${b.id}')">🖨 Cetak</button></div>`;
      return crow({icon:"🏭",title:(b.note?esc(b.note):prods)||"Batch",sub:`${fmtDate(b.date)} · ${c.bottles} botol · HPP ${rp(c.avg)}`,amt:rp(c.total),acts,onclick:`editBatch('${b.id}')`,del:`delBatch('${b.id}')`});}).join("");
    document.getElementById("v-produksi").innerHTML=`<div class="desc">Racik bahan + biaya → HPP per batch otomatis.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editBatch()">+ Batch Baru</button>${S.batches.length===0?'<div class="empty">Belum ada batch.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.batches.map(b=>{const c=batchCalc(b);const o=(b.outputs||[]).map(x=>`<div class="li"><span>${esc((prod(x.productId)||{}).name||"?")}</span><span class="q">×${x.qty}</span></div>`).join("")||'<span class="muted">—</span>';
    return `<tr><td>${fmtDate(b.date)}${b.note?`<div class="mini">${esc(b.note)}</div>`:""}</td><td><div class="itemcell">${o}</div></td><td class="num">${c.bottles}</td><td class="num">${rp(c.mat)}</td><td class="num">${rp(c.ops)}</td><td class="num"><b>${rp(c.total)}</b></td><td class="num">${rp(c.avg)}</td><td class="num"><div class="acts"><button class="btn sm" onclick="printBatch('${b.id}')">🖨 Cetak</button><button class="btn sm gray" onclick="editBatch('${b.id}')">Edit</button><button class="btn sm del" onclick="delBatch('${b.id}')">✕</button></div></td></tr>`;}).join("");
  document.getElementById("v-produksi").innerHTML=`
  <h2 class="title">Produksi &amp; HPP per Batch</h2>
  <div class="desc">Pilih bahan baku (harga otomatis dari master) + biaya → tentukan produk jadi &amp; qty → HPP per batch dihitung otomatis.</div>
  <div class="flexbtns"><button class="btn" onclick="editBatch()">+ Batch Baru</button></div>
  <div class="panel"><h3>Riwayat Batch</h3>${S.batches.length===0?'<div class="empty">Belum ada batch.</div>':`<table><thead><tr><th>Tanggal</th><th>Produk Jadi</th><th class="num">Botol</th><th class="num">Bahan</th><th class="num">Ops</th><th class="num">Total Modal</th><th class="num">HPP rata/botol</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
}
function printBatch(id){
  const b=S.batches.find(x=>x.id===id);if(!b)return;
  const c=batchCalc(b);
  const matRows=(b.materials||[]).map(m=>{const sub=(+m.qty||0)*(+m.price||0);
    const tgt=(m.targetProduct)?` <i>→ ${esc((prod(m.targetProduct)||{}).name||"?")}</i>`:"";
    return `<tr><td>${esc(m.name||"?")}${tgt}</td><td class="num">${(+m.qty||0)} ${esc(m.unit||"")}</td><td class="num">${rp(m.price)}</td><td class="num">${rp(sub)}</td></tr>`;}).join("");
  const opsRows=(b.ops||[]).map(o=>`<tr><td>${esc(o.name||"?")}</td><td class="num">${rp(o.amount)}</td></tr>`).join("");
  const outRows=(b.outputs||[]).filter(o=>o.productId&&+o.qty>0).map(o=>{const p=prod(o.productId)||{};
    const hpp=Math.round(c.perProduct[o.productId]||0),harga=+p.harga||0,untung=harga-hpp,margin=hpp>0?untung/hpp*100:0;
    return `<tr><td>${esc(p.name||"?")}</td><td class="num">${o.qty}</td><td class="num">${rp(harga)}</td><td class="num">${rp(hpp)}</td><td class="num">${rp(untung)}</td><td class="num">${margin.toFixed(0)}%</td></tr>`;}).join("");
  document.getElementById("printArea").innerHTML=`<div class="preport">
    ${printHead("LAPORAN HPP"+(b.note?" — "+esc(b.note):" — Batch Produksi"),"Produksi • "+fmtDate(b.date)+" • "+c.bottles+" botol")}
    <div class="psec">1. Biaya Bahan Baku</div>
    <table><thead><tr><th>Bahan</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
      <tbody>${matRows||'<tr><td colspan="4">—</td></tr>'}<tr class="tot"><td colspan="3">TOTAL BAHAN BAKU</td><td class="num">${rp(c.mat)}</td></tr></tbody></table>
    <div class="psec">2. Biaya Operasional</div>
    <table><thead><tr><th>Item</th><th class="num">Subtotal</th></tr></thead>
      <tbody>${opsRows||'<tr><td colspan="2">—</td></tr>'}<tr class="tot"><td>TOTAL OPERASIONAL</td><td class="num">${rp(c.ops)}</td></tr></tbody></table>
    <div class="psec">3. HPP &amp; Harga Jual per Botol</div>
    <table><thead><tr><th>Produk</th><th class="num">Botol</th><th class="num">Harga Jual</th><th class="num">HPP</th><th class="num">Untung/botol</th><th class="num">Margin</th></tr></thead>
      <tbody>${outRows||'<tr><td colspan="6">—</td></tr>'}</tbody></table>
    <div class="psec">4. Ringkasan Batch</div>
    <table><tbody>
      <tr><td>Total Modal (bahan + operasional)</td><td class="num"><b>${rp(c.total)}</b></td></tr>
      <tr><td>Total Botol Jadi</td><td class="num">${c.bottles} botol</td></tr>
      <tr><td>HPP Rata-rata / botol</td><td class="num">${rp(c.avg)}</td></tr>
    </tbody></table>
    <div class="psub" style="margin-top:18px;text-align:left">Dicetak ${fmtDate(today())} — Racikin</div>
  </div>`;
  window.print();
}
function matDefault(){return {materialId:"",name:"",unit:"kg",qty:"",price:"",targetProduct:"",custom:false};}
// ---- draft batch (disimpan di browser, tidak menyentuh database) ----
const DRAFT_KEY="anna_batch_draft";
function hasDraft(){return !!localStorage.getItem(DRAFT_KEY);}
function persistDraft(){if(window._b&&!window._b.id){try{localStorage.setItem(DRAFT_KEY,JSON.stringify(window._b));}catch(e){}}}
function clearDraft(){try{localStorage.removeItem(DRAFT_KEY);}catch(e){}}
function saveDraft(){persistDraft();closeModal();toast("Draft batch disimpan 💾");}
function discardDraft(){if(confirm("Buang draft batch ini?")){clearDraft();closeModal();toast("Draft dibuang.");rProduksi();}}
function editBatch(id){
  let b=null;
  if(id) b=JSON.parse(JSON.stringify(S.batches.find(x=>x.id===id)));
  else if(hasDraft()){try{b=JSON.parse(localStorage.getItem(DRAFT_KEY));toast("Draft dipulihkan 📝");}catch(e){b=null;}}
  if(!b) b={id:null,date:today(),note:"",materials:[matDefault()],ops:[{name:"Gas",amount:""},{name:"Tenaga kerja",amount:""}],outputs:[{productId:"",qty:""}]};
  window._b=b;openModal(batchForm());
}
function batchForm(){
  const b=window._b;comboReset();
  // produk jadi berbeda dalam batch ini — alokasi bahan hanya relevan kalau ada ≥2 produk
  const outProds=[...new Set((b.outputs||[]).filter(o=>o.productId).map(o=>o.productId))];
  const showAlloc=outProds.length>=2;
  const matOpts=[...S.materials.map(x=>({id:x.id,label:x.name+" ("+rp(x.price)+"/"+x.unit+")"})),{id:"__c",label:"— lainnya (manual) —"}];
  const matRows=b.materials.map((m,i)=>{
    const isCustom=m.custom||(!m.materialId&&!!m.name);
    const nameInput=isCustom?`<input placeholder="Nama bahan" value="${esc(m.name)}" oninput="_b.materials[${i}].name=this.value">`:"";
    const allocSel=showAlloc?`<select onchange="_b.materials[${i}].targetProduct=this.value;bSum()" style="font-size:12px" title="Alokasi biaya bahan ini">
        <option value="" ${!m.targetProduct?"selected":""}>↔ Dibagi rata ke semua produk</option>
        ${outProds.map(pid=>`<option value="${pid}" ${m.targetProduct===pid?"selected":""}>→ khusus ${esc((prod(pid)||{}).name||"?")}</option>`).join("")}
      </select>`:"";
    return `<div class="itemrow">
      <div class="irtop">${combo(matOpts,isCustom?"__c":m.materialId,(id)=>pickMat(i,id),"— cari / pilih bahan —")}<button class="x" onclick="_b.materials.splice(${i},1);remo(batchForm())">×</button></div>
      ${nameInput}
      <div class="irbot"><input type="number" inputmode="decimal" placeholder="Qty" value="${m.qty}" oninput="_b.materials[${i}].qty=this.value;bSum()"><input inputmode="numeric" placeholder="Harga" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value);_b.materials[${i}].price=digits(this.value);bSum()"></div>
      ${allocSel}
      <div class="irsub" id="matsub${i}">${rp((+m.qty||0)*(+m.price||0))}</div></div>`;}).join("");
  const opsRows=b.ops.map((o,i)=>`<div class="dynrow ops"><input placeholder="Nama biaya" value="${esc(o.name)}" oninput="_b.ops[${i}].name=this.value"><input inputmode="numeric" placeholder="Rp" value="${o.amount?grp(o.amount):""}" oninput="this.value=grp(this.value);_b.ops[${i}].amount=digits(this.value);bSum()"><button class="x" onclick="_b.ops.splice(${i},1);remo(batchForm())">×</button></div>`).join("");
  const outRows=b.outputs.map((o,i)=>`<div class="itemrow"><div class="irtop">${combo(S.products.map(p=>({id:p.id,label:p.name})),o.productId,(id)=>{window._b.outputs[i].productId=id;remo(batchForm());},"— cari / pilih produk —")}<button class="x" onclick="_b.outputs.splice(${i},1);remo(batchForm())">×</button></div><div class="irbot one"><input type="number" inputmode="numeric" placeholder="Qty botol" value="${o.qty}" oninput="_b.outputs[${i}].qty=this.value;bSum()"></div></div>`).join("");
  return `<button class="close" onclick="closeModal()">×</button><h3>${b.id?"Edit":"Tambah"} Batch Produksi</h3>
  ${!b.id?`<div class="mini" style="background:var(--amber-bg);color:var(--amber);padding:7px 10px;border-radius:8px;margin-bottom:12px">📝 Batch ini belum tersimpan ke database. Klik <b>Simpan Draft</b> untuk menyimpan sementara di browser &amp; lanjut nanti (tetap ada walau modal ditutup / halaman di-refresh).</div>`:""}
  <div class="grid2" style="margin-bottom:14px"><div><label class="f">Tanggal</label><input type="date" value="${b.date}" oninput="_b.date=this.value"></div><div><label class="f">Catatan</label><input value="${esc(b.note)}" oninput="_b.note=this.value" placeholder="opsional"></div></div>
  <label class="f">Bahan Baku <span class="mini">(pilih dari master; harga bisa disesuaikan — subtotal per baris otomatis)</span></label>${matRows}
  ${showAlloc?`<p class="mini" style="margin:2px 0 4px">💡 Ada ${outProds.length} produk jadi — pakai dropdown <b>alokasi</b> tiap bahan: <b>khusus</b> satu produk (mis. daging→Premium, tulang→Campur) atau <b>dibagi rata</b>. Operasional selalu dibagi rata per bobot.</p>`:""}
  <button class="btn sm ghost" onclick="_b.materials.push(matDefault());remo(batchForm())">+ bahan</button>
  <label class="f" style="margin-top:14px">Biaya Operasional</label>${opsRows}
  <button class="btn sm ghost" onclick="_b.ops.push({name:'',amount:''});remo(batchForm())">+ biaya</button>
  <label class="f" style="margin-top:14px">Produk Jadi &amp; Qty</label>${outRows}
  <button class="btn sm ghost" onclick="_b.outputs.push({productId:'',qty:''});remo(batchForm())">+ produk</button>
  <div class="sumbox" id="bSum"></div>
  <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
    ${!b.id&&hasDraft()?`<button class="btn gray" onclick="discardDraft()">🗑 Buang Draft</button>`:""}
    <button class="btn gray" onclick="closeModal()">Tutup</button>
    ${!b.id?`<button class="btn ghost" onclick="saveDraft()">💾 Simpan Draft</button>`:""}
    <button class="btn" onclick="saveBatch()">Simpan Batch</button>
  </div>`;
}
function pickMat(i,val){const m=window._b.materials[i];
  if(val==="__c"){m.custom=true;m.materialId="";}
  else if(val===""){m.custom=false;m.materialId="";m.name="";m.price="";m.unit="kg";}
  else{const x=mat(val);m.custom=false;m.materialId=x.id;m.name=x.name;m.unit=x.unit;m.price=x.price;}
  remo(batchForm());}
function remo(h){mEl.innerHTML=h;bSum();}
function bSum(){const c=batchCalc(window._b),box=document.getElementById("bSum");
  (window._b.materials||[]).forEach((m,i)=>{const el=document.getElementById("matsub"+i);if(el)el.textContent=rp((+m.qty||0)*(+m.price||0));});
  persistDraft();
  if(!box)return;
  box.innerHTML=`<div><span>Total bahan baku</span><span>${rp(c.mat)}</span></div><div><span>Total operasional</span><span>${rp(c.ops)}</span></div><div class="big"><span>Total modal batch</span><span>${rp(c.total)}</span></div><div style="margin-top:6px"><span>Total botol jadi</span><span>${c.bottles} botol</span></div><div><span>HPP rata-rata / botol</span><span><b>${rp(c.avg)}</b></span></div>${(window._b.outputs||[]).filter(o=>o.productId&&o.qty).map(o=>`<div class="mini" style="display:flex;justify-content:space-between"><span>↳ HPP ${esc((prod(o.productId)||{}).name||"?")}</span><span>${rp(c.perProduct[o.productId])}</span></div>`).join("")}`;
}
async function saveBatch(){
  const b=window._b;
  b.materials=b.materials.filter(m=>(m.materialId||m.name)&&(+m.qty||+m.price));
  b.ops=b.ops.filter(o=>o.name&&+o.amount);
  b.outputs=b.outputs.filter(o=>o.productId&&+o.qty>0);
  if(b.outputs.length===0){toast("Isi minimal 1 produk jadi + qty.");return;}
  await api("saveBatch",{batch:b});clearDraft();await reload();closeModal();toast("Batch tersimpan ✓");rProduksi();
}
async function delBatch(id){if(confirm("Hapus batch ini? Stok berkurang.")){await api("deleteBatch",{id});await reload();rProduksi();}}

// ================= DISTRIBUSI (per NOTA) =================
function rDistribusi(){
  if(isMobile()){
    const list=S.notas.filter(n=>inMonth(n.date));
    const rows=list.map(n=>{const items=notaItems(n);const free=items.filter(it=>(it.kind||"jual")!=="jual");
      const badges=free.length?`<div style="margin-top:4px">${free.map(it=>`<span class="pill-mini ${it.kind}">${kindLabel(it.kind)}</span>`).join(" ")}</div>`:"";
      return crow({icon:"🧾",title:esc(n.notaNo||"-"),sub:`${esc((store(n.storeId)||{}).name||"?")} · ${items.length} item · ${fmtDate(n.date)}`,badges,amt:rp(notaTotal(n)),status:notaStatus(n),onclick:`editNota('${n.id}')`,del:`delNota('${n.id}')`});}).join("");
    document.getElementById("v-distribusi").innerHTML=`<div class="desc">Tiap pengiriman = 1 nota berisi banyak produk.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editNota()">+ Nota Baru</button>${monthBar()}${list.length===0?'<div class="empty">Tak ada nota pada periode ini.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const nm=it=>esc((prod(it.productId)||{}).name||"?");
  const list=S.notas.filter(n=>inMonth(n.date));
  const rows=list.map(n=>{
    const jualIt=notaItems(n).filter(it=>(it.kind||"jual")==="jual");
    const freeIt=notaItems(n).filter(it=>(it.kind||"jual")!=="jual");
    const li=(inner,q)=>`<div class="li"><span>${inner}</span><span class="q">×${q}</span></div>`;
    const jualTxt=jualIt.map(it=>li(nm(it),it.qty)).join("")||'<div class="li muted">—</div>';
    const freeTxt=freeIt.length?`<div class="free">${freeIt.map(it=>li(`<span class="pill-mini ${it.kind}">${kindLabel(it.kind)}</span> ${nm(it)}`,it.qty)).join("")}</div>`:"";
    const itemsCell=`<div class="itemcell">${jualTxt}${freeTxt}</div>`;
    return `<tr><td>${fmtDate(n.date)}</td><td><b>${esc(n.notaNo||"-")}</b></td><td>${esc((store(n.storeId)||{}).name||"?")}</td><td>${itemsCell}</td><td class="num"><b>${rp(notaTotal(n))}</b></td><td class="num" style="color:var(--green)">${rp(notaProfit(n))}</td><td><span class="pill ${notaStatus(n)}">${notaStatus(n).toUpperCase()}</span></td><td class="num"><div class="acts"><button class="btn sm gray" onclick="editNota('${n.id}')">Edit</button><button class="btn sm del" onclick="delNota('${n.id}')">✕</button></div></td></tr>`;
  }).join("");
  document.getElementById("v-distribusi").innerHTML=`<h2 class="title">Distribusi ke Toko</h2><div class="desc">Tiap pengiriman = 1 nota/faktur berisi banyak produk. Stok berkurang otomatis &amp; jadi tagihan (per nota) di menu Pembayaran.</div>
  <div class="flexbtns"><button class="btn" onclick="editNota()">+ Nota Baru</button>${monthBar()}${S.stores.length===0?'<span class="mini" style="align-self:center">⚠️ Tambah toko dulu.</span>':""}</div>
  <div class="panel"><h3>Riwayat Nota</h3>${list.length===0?'<div class="empty">Tak ada nota pada periode ini.</div>':`<table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th>Item</th><th class="num">Total</th><th class="num">Laba</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
}
const NOTA_FREE_KINDS=[["bonus","Bonus"],["endorse","Endorse"],["tester","Tester"]];
const kindLabel=k=>({jual:"",bonus:"BONUS",endorse:"ENDORSE",tester:"TESTER"}[k]||"");
function notaItemDefault(){return {productId:"",qty:"",harga:"",hpp:0,kind:"jual"};}
function notaFreeDefault(){return {productId:"",qty:"",harga:0,hpp:0,kind:"bonus"};}
function editNota(id){
  if(S.stores.length===0){toast("Tambah toko dulu di menu Toko.");return;}
  if(S.products.length===0){toast("Tambah produk dulu di menu Produk.");return;}
  const n=id?JSON.parse(JSON.stringify(S.notas.find(x=>x.id===id))):{id:null,date:today(),storeId:"",notaNo:nextNotaNo(today()),items:[notaItemDefault()],payments:[]};
  window._n=n;openModal(notaForm());
}
function notaProdOpts(sel){return `<option value="" ${!sel?"selected":""}>— pilih produk —</option>`+S.products.map(p=>`<option value="${p.id}" ${p.id===sel?"selected":""}>${esc(p.name)} — stok ${stock(p.id)}</option>`).join("");}
function prodCombo(curId,cb){return combo(S.products.map(p=>({id:p.id,label:p.name+" · stok "+stock(p.id)})),curId,cb,"— cari / pilih produk —");}
function notaForm(){const n=window._n;comboReset();
  const idx=n.items.map((it,i)=>({it,i}));
  // baris JUAL: produk (combo) | qty + harga | subtotal
  const jualRows=idx.filter(x=>(x.it.kind||"jual")==="jual").map(({it,i})=>`<div class="itemrow">
    <div class="irtop">${prodCombo(it.productId,(id)=>notaPickProd(i,id))}<button class="x" onclick="_n.items.splice(${i},1);remoN()">×</button></div>
    <div class="irbot"><input type="number" inputmode="numeric" placeholder="Qty botol" value="${it.qty}" oninput="_n.items[${i}].qty=this.value;nSum()"><input inputmode="numeric" placeholder="Harga/botol" value="${it.harga?grp(it.harga):""}" oninput="this.value=grp(this.value);_n.items[${i}].harga=digits(this.value);nSum()"></div>
    <div class="irsub" id="nsub${i}">${rp((+it.qty||0)*(+it.harga||0))}</div></div>`).join("");
  // baris GRATIS: produk (combo) | qty + jenis
  const freeRows=idx.filter(x=>(x.it.kind||"jual")!=="jual").map(({it,i})=>`<div class="itemrow">
    <div class="irtop">${prodCombo(it.productId,(id)=>notaPickProd(i,id))}<button class="x" onclick="_n.items.splice(${i},1);remoN()">×</button></div>
    <div class="irbot"><input type="number" inputmode="numeric" placeholder="Qty botol" value="${it.qty}" oninput="_n.items[${i}].qty=this.value;nSum()"><select onchange="notaSetKind(${i},this.value)" title="Jenis pemberian gratis">${NOTA_FREE_KINDS.map(([v,t])=>`<option value="${v}" ${it.kind===v?"selected":""}>${t}</option>`).join("")}</select></div></div>`).join("");
  return `<button class="close" onclick="closeModal()">×</button><h3>${n.id?"Edit":"Tambah"} Nota / Faktur</h3>
  <div class="grid2" style="margin-bottom:12px"><div><label class="f">Tanggal</label><input type="date" value="${n.date}" oninput="_n.date=this.value"></div><div><label class="f">Toko / Penerima</label>${combo(S.stores.map(s=>({id:s.id,label:s.name})),n.storeId,(id)=>{window._n.storeId=id;},"— cari / pilih toko —")}</div></div>
  <div style="margin-bottom:12px"><label class="f">No. Nota / Faktur <span class="mini">(otomatis, bisa diubah)</span></label><input value="${esc(n.notaNo||"")}" oninput="_n.notaNo=this.value" placeholder="mis. NOTA-20260704-001"></div>
  <label class="f">Produk Dijual <span class="mini">(ditagih ke toko)</span></label>${jualRows||'<div class="mini" style="margin-bottom:6px">Belum ada produk jual.</div>'}
  <button class="btn sm ghost" onclick="_n.items.push(notaItemDefault());remoN()">+ item jual</button>
  <div style="border-top:1px dashed var(--line);margin:16px 0 10px"></div>
  <label class="f">Bonus / Endorse / Tester <span class="mini">(GRATIS — tak ditagih, tapi tetap potong stok &amp; tercatat)</span></label>${freeRows||'<div class="mini" style="margin-bottom:6px">Tidak ada. Klik di bawah kalau mau tambah produk gratis.</div>'}
  <button class="btn sm ghost" onclick="_n.items.push(notaFreeDefault());remoN()">+ bonus / endorse / tester</button>
  <div class="sumbox" id="nSum"></div>
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveNota()">Simpan Nota</button></div>`;
}
function notaPickProd(i,val){const it=window._n.items[i];const p=prod(val);it.productId=val;if(p){it.hpp=p.hpp;if((it.kind||"jual")==="jual")it.harga=p.harga;}remoN();}
function notaSetKind(i,val){const it=window._n.items[i];it.kind=val;
  if(val!=="jual"){it.harga=0;}else{const p=prod(it.productId);if(p&&!(+it.harga>0))it.harga=p.harga;}
  remoN();}
function remoN(){mEl.innerHTML=notaForm();nSum();}
function nSum(){const n=window._n;
  n.items.forEach((it,i)=>{const el=document.getElementById("nsub"+i);if(el){const free=(it.kind||"jual")!=="jual";el.innerHTML=free?'<span style="color:var(--amber)">GRATIS</span>':rp((+it.qty||0)*(+it.harga||0));}});
  const box=document.getElementById("nSum");if(!box)return;
  const valid=n.items.filter(it=>it.productId&&+it.qty>0);
  const freeQty=valid.filter(it=>(it.kind||"jual")!=="jual").reduce((a,it)=>a+(+it.qty||0),0);
  box.innerHTML=`<div><span>Jumlah item</span><span>${valid.length} baris · ${notaQty(n)} botol${freeQty?` (${freeQty} gratis)`:""}</span></div><div class="big"><span>Total tagihan</span><span>${rp(notaTotal(n))}</span></div><div><span>Estimasi laba</span><span style="color:var(--green)"><b>${rp(notaProfit(n))}</b></span></div>`;
}
async function saveNota(){const n=window._n;if(!n.storeId){toast("Pilih toko/penerima dulu.");return;}if(n.items.filter(it=>it.productId&&+it.qty>0).length===0){toast("Isi minimal 1 item + qty.");return;}await api("saveNota",{nota:n});await reload();closeModal();toast("Nota tersimpan ✓");rDistribusi();}
async function delNota(id){if(confirm("Hapus nota ini beserta pembayarannya? Stok kembali.")){await api("deleteNota",{id});await reload();rDistribusi();}}

// ================= PEMBAYARAN (per NOTA) =================
function rPembayaran(){
  if(isMobile()){
    const lst=[...S.notas].filter(n=>inMonth(n.date)).sort((a,b)=>{const o={belum:0,sebagian:1,lunas:2};return o[notaStatus(a)]-o[notaStatus(b)]||(b.date||"").localeCompare(a.date||"");});
    const totalP=lst.reduce((a,n)=>a+notaDue(n),0);
    const rows=lst.map(n=>{const due=notaStatus(n)!=="lunas";
      const acts=`<div class="cacts">${due?`<button class="btn sm" onclick="event.stopPropagation();payModal('${n.id}')">Bayar</button>`:""}<button class="btn sm gray" onclick="event.stopPropagation();payHist('${n.id}')">🕘</button></div>`;
      return crow({icon:"💰",title:esc(n.notaNo||"-"),sub:`${esc((store(n.storeId)||{}).name||"?")} · ${fmtDate(n.date)}`,amt:rp(notaDue(n)),amtColor:"var(--amber)",status:notaStatus(n),onclick:due?`payModal('${n.id}')`:`payHist('${n.id}')`,acts});}).join("");
    document.getElementById("v-pembayaran").innerHTML=`<div class="card accent" style="margin-bottom:16px"><div class="lbl">Total Piutang (${monthLabelFull(FILTER_MONTH)})</div><div class="val">${rp(totalP)}</div></div>${monthBar()}${lst.length===0?'<div class="empty">Tak ada tagihan pada periode ini.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const list=[...S.notas].filter(n=>inMonth(n.date)).sort((a,b)=>{const o={belum:0,sebagian:1,lunas:2};return o[notaStatus(a)]-o[notaStatus(b)]||(b.date||"").localeCompare(a.date||"");});
  const totalPiutang=list.reduce((a,n)=>a+notaDue(n),0);
  const rows=list.map(n=>`<tr><td>${fmtDate(n.date)}</td><td>${esc(n.notaNo||"-")}</td><td>${esc((store(n.storeId)||{}).name||"?")}</td><td class="num">${notaItems(n).length} item</td><td class="num">${rp(notaTotal(n))}</td><td class="num" style="color:var(--green)">${rp(notaPaid(n))}</td><td class="num" style="color:var(--amber)"><b>${rp(notaDue(n))}</b></td><td><span class="pill ${notaStatus(n)}">${notaStatus(n).toUpperCase()}</span></td><td class="num"><div class="acts">${notaStatus(n)!=="lunas"?`<button class="btn sm" onclick="payModal('${n.id}')">Bayar</button>`:""}<button class="btn sm gray" onclick="payHist('${n.id}')">Riwayat</button></div></td></tr>`).join("");
  document.getElementById("v-pembayaran").innerHTML=`<h2 class="title">Pembayaran &amp; Piutang</h2><div class="desc">Tagihan dilacak per nota. Bisa cicil; sisa piutang update otomatis.</div>
  ${monthBar()}
  <div class="cards"><div class="card amber"><div class="lbl">Total Piutang (${monthLabelFull(FILTER_MONTH)})</div><div class="val">${rp(totalPiutang)}</div></div></div>
  <div class="panel"><h3>Daftar Tagihan (per Nota)</h3>${list.length===0?'<div class="empty">Tak ada tagihan pada periode ini.</div>':`<table><thead><tr><th>Tgl</th><th>No. Nota</th><th>Toko</th><th class="num">Item</th><th class="num">Tagihan</th><th class="num">Terbayar</th><th class="num">Sisa</th><th>Status</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
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

// ================= BAHAN BAKU =================
function rBahan(){
  if(isMobile()){
    const rows=S.materials.map(m=>{const s=matStat(m);
      const chg=s.chg==null?"":(s.chg>0?`<span class="up">▲ ${(s.chg*100).toFixed(0)}%</span>`:s.chg<0?`<span class="down">▼ ${(Math.abs(s.chg)*100).toFixed(0)}%</span>`:"");
      const acts=`<div class="cacts"><button class="btn sm gray" onclick="event.stopPropagation();editMat('${m.id}')">✏️</button></div>`;
      return crow({icon:"🧂",title:esc(m.name),sub:`per ${esc(m.unit)} · ${s.updates}× update · 📈 lihat tren`,badges:chg?`<div style="margin-top:2px">${chg}</div>`:"",amt:rp(m.cur??m.price),acts,onclick:`matHist('${m.id}')`,del:`delMat('${m.id}')`});}).join("");
    document.getElementById("v-bahan").innerHTML=`<div class="desc">Ketuk bahan untuk lihat grafik tren harga &amp; riwayat; tombol ✏️ untuk ubah harga.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editMat()">+ Bahan Baru</button>${S.materials.length===0?'<div class="empty">Belum ada bahan.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.materials.map(m=>{const s=matStat(m);
    const chg=chgBadge(s.chg,'<span class="flat">—</span>');
    return `<tr><td><a href="#" onclick="matHist('${m.id}');return false" style="color:var(--red);font-weight:600;text-decoration:none">${esc(m.name)}</a></td><td>${esc(m.unit)}</td><td class="num"><b>${rp(m.cur??m.price)}</b></td><td class="num">${s.prev!=null?rp(s.prev):"-"}</td><td class="num">${chg}</td><td class="num muted">${s.updates}×</td><td class="num"><div class="acts"><button class="btn sm" onclick="editMat('${m.id}')">Ubah Harga</button><button class="btn sm gray" onclick="matHist('${m.id}')">📈 Grafik</button><button class="btn sm del" onclick="delMat('${m.id}')">✕</button></div></td></tr>`;}).join("");
  document.getElementById("v-bahan").innerHTML=`<h2 class="title">Master Bahan Baku &amp; Tren Harga</h2>
  <div class="desc">Klik nama bahan untuk lihat grafik tren harga &amp; riwayat. Tiap harga berubah, klik "Ubah Harga" — riwayat tersimpan otomatis.</div>
  <div class="flexbtns"><button class="btn" onclick="editMat()">+ Bahan Baru</button></div>
  <div class="panel">${S.materials.length===0?'<div class="empty">Belum ada bahan.</div>':`<table><thead><tr><th>Bahan</th><th>Satuan</th><th class="num">Harga Sekarang</th><th class="num">Harga Lalu</th><th class="num">Perubahan</th><th class="num">Update</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
}
function editMat(id){const m=id?S.materials.find(x=>x.id===id):{id:null,name:"",unit:"kg",price:""};
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${id?"Ubah Harga":"Tambah"} Bahan</h3>
  <label class="f">Nama bahan</label><input id="mn" value="${esc(m.name)}">
  <div class="grid2" style="margin:12px 0"><div><label class="f">Satuan</label><input id="mu" value="${esc(m.unit||"kg")}"></div><div><label class="f">Harga / satuan</label><input id="mp" inputmode="numeric" value="${m.price?grp(m.price):""}" oninput="this.value=grp(this.value)"></div></div>
  <div><label class="f">Tanggal berlaku</label><input id="md" type="date" value="${today()}"></div>
  <p class="mini" style="margin-top:8px">Kalau harga berubah, perubahan otomatis tercatat di riwayat.</p>
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveMat('${id||""}')">Simpan</button></div>`);}
async function saveMat(id){const o={id:id||null,name:document.getElementById("mn").value.trim(),unit:document.getElementById("mu").value.trim()||"kg",price:+digits(document.getElementById("mp").value)||0};
  if(!o.name){toast("Nama wajib.");return;}await api("saveMaterial",{material:o,date:document.getElementById("md").value});await reload();closeModal();toast("Bahan tersimpan ✓");rBahan();}
async function delMat(id){if(confirm("Hapus bahan ini beserta riwayat harganya?")){await api("deleteMaterial",{id});await reload();rBahan();}}
// nama batch yang enak dibaca: catatan → produk yang dihasilkan → tanggal
function batchName(b){
  if(b.note)return b.note;
  const ps=[...new Set((b.outputs||[]).map(o=>(prod(o.productId)||{}).name).filter(Boolean))];
  return ps.length?ps.join(", "):fmtDate(b.date);
}
// sumber satu titik harga — kalau dari batch, tulis batch mana
function priceSrcLabel(h){
  if(h.source==="batch"){
    const b=h.ref?S.batches.find(x=>x.id===h.ref):null;
    if(b)return "dari batch: "+esc(batchName(b));
    return "dari batch ("+fmtDate(h.date)+")";
  }
  return srcLabel(h.source);
}
// grafik garis tren harga satu bahan
function priceTrend(h){
  if(h.length<2)return '<div class="empty" style="padding:16px">Belum cukup data untuk grafik (minimal 2 titik harga).</div>';
  const W=520,H=150,padL=10,padR=10,padT=22,padB=24,n=h.length;
  const ps=h.map(x=>x.price);const mn=Math.min(...ps),mx=Math.max(...ps),span=mx-mn||1;
  const X=i=>padL+(W-padL-padR)*(i/(n-1));
  const Y=p=>H-padB-(H-padT-padB)*((p-mn)/span);
  const pts=h.map((x,i)=>[X(i),Y(x.price)]);
  const line=pts.map((p,i)=>(i?"L":"M")+p[0].toFixed(1)+" "+p[1].toFixed(1)).join(" ");
  const area=`M${pts[0][0].toFixed(1)} ${H-padB} `+pts.map(p=>`L${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(" ")+` L${pts[n-1][0].toFixed(1)} ${H-padB} Z`;
  const dots=h.map((x,i)=>`<circle cx="${X(i).toFixed(1)}" cy="${Y(x.price).toFixed(1)}" r="3.5" fill="#fff" stroke="var(--red)" stroke-width="2"><title>${fmtDate(x.date)}: ${rp(x.price)}</title></circle>`).join("");
  return `<div class="pchart"><svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" preserveAspectRatio="xMidYMid meet" style="min-width:300px">
    <defs><linearGradient id="ptg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--orange)" stop-opacity=".30"/><stop offset="1" stop-color="var(--orange)" stop-opacity="0"/></linearGradient></defs>
    <path d="${area}" fill="url(#ptg)"/>
    <path d="${line}" fill="none" stroke="var(--red)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${dots}
    <text x="${padL}" y="13" font-size="10.5" fill="#8a8f99">Tertinggi ${rp(mx)}</text>
    <text x="${W-padR}" y="13" font-size="10.5" fill="#8a8f99" text-anchor="end">Terendah ${rp(mn)}</text>
  </svg></div>`;
}
function matHist(id){const m=S.materials.find(x=>x.id===id);if(!m){toast("Bahan tak ditemukan.");return;}
  const s=matStat(m);
  const rows=s.h.slice().reverse().map((h,idx,arr)=>{const older=arr[idx+1];const c=older?(h.price-older.price)/older.price:null;
    const cc=chgBadge(c,"");
    return `<tr><td>${fmtDate(h.date)}</td><td class="num">${rp(h.price)}</td><td class="num">${cc}</td><td class="mini">${priceSrcLabel(h)}</td><td class="num"><button class="btn sm gray" onclick="delPrice(${h.id},'${id}')">×</button></td></tr>`;}).join("");
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>📈 ${esc(m.name)} — Tren Harga</h3>
    <div class="mini" style="margin:-8px 0 12px">Harga sekarang <b style="color:var(--ink);font-size:14px">${rp(s.cur??m.price)}</b> / ${esc(m.unit)} · ${s.updates}× update${s.chg!=null?" · "+chgBadge(s.chg,""):""}</div>
    ${priceTrend(s.h)}
    <div style="margin-top:16px"><div class="mini" style="font-weight:700;margin-bottom:6px;color:var(--ink)">Riwayat Harga</div>
    ${s.h.length===0?'<div class="empty">Belum ada riwayat.</div>':`<table><thead><tr><th>Tanggal</th><th class="num">Harga</th><th class="num">vs sblm</th><th>Sumber</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>
    <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Tutup</button> <button class="btn" onclick="editMat('${id}')">Ubah Harga</button></div>`);}
async function delPrice(pid,id){if(confirm("Hapus titik harga ini?")){await api("deletePricePoint",{id:pid});await reload();matHist(id);}}

// ================= PRODUK =================
function rProduk(){
  if(isMobile()){
    const rows=S.products.map(p=>crow({icon:"🫙",title:esc(p.name),sub:`${esc(p.cat)} · ${p.gram}gr · stok ${stock(p.id)}`,badges:`<div class="csub">HPP ${rp(p.hpp)} · margin ${rp(p.harga-p.hpp)}</div>`,amt:rp(p.harga),onclick:`editProd('${p.id}')`,del:`delProd('${p.id}')`})).join("");
    document.getElementById("v-produk").innerHTML=`<div class="desc">HPP terisi otomatis dari batch; bisa diubah manual.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editProd()">+ Produk Baru</button>${S.products.length===0?'<div class="empty">Belum ada produk.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.products.map(p=>`<tr><td>${esc(p.name)}</td><td>${esc(p.cat)}</td><td class="num">${p.gram} gr</td><td class="num">${rp(p.hpp)}</td><td class="num">${rp(p.harga)}</td><td class="num" style="color:${p.harga-p.hpp>=0?'var(--green)':'var(--red)'}">${rp(p.harga-p.hpp)}</td><td class="num">${stock(p.id)}</td><td class="num"><div class="acts"><button class="btn sm gray" onclick="editProd('${p.id}')">Edit</button><button class="btn sm del" onclick="delProd('${p.id}')">✕</button></div></td></tr>`).join("");
  document.getElementById("v-produk").innerHTML=`<h2 class="title">Master Produk</h2><div class="desc">HPP terisi otomatis dari batch terakhir, tapi bisa diubah manual.</div>
  <div class="flexbtns"><button class="btn" onclick="editProd()">+ Produk Baru</button></div>
  <div class="panel"><table><thead><tr><th>Nama</th><th>Kategori</th><th class="num">Ukuran</th><th class="num">HPP</th><th class="num">Harga</th><th class="num">Margin</th><th class="num">Stok</th><th></th></tr></thead><tbody>${rows}</tbody></table></div>`;
}
function editProd(id){const p=id?S.products.find(x=>x.id===id):{id:null,name:"",cat:"",gram:"",hpp:"",harga:""};
  openModal(`<button class="close" onclick="closeModal()">×</button><h3>${id?"Edit":"Tambah"} Produk</h3>
  <label class="f">Nama produk</label><input id="pn" value="${esc(p.name)}">
  <div class="grid2" style="margin:12px 0"><div><label class="f">Kategori</label><input id="pc" value="${esc(p.cat)}"></div><div><label class="f">Ukuran (gram)</label><input id="pg" type="number" value="${p.gram}"></div></div>
  <div class="grid2"><div><label class="f">HPP / botol</label><input id="ph" inputmode="numeric" value="${p.hpp?grp(p.hpp):""}" oninput="this.value=grp(this.value)"></div><div><label class="f">Harga jual / botol</label><input id="phj" inputmode="numeric" value="${p.harga?grp(p.harga):""}" oninput="this.value=grp(this.value)"></div></div>
  <p class="mini" style="margin-top:8px">Ukuran (gram) dipakai membagi HPP antar produk dalam 1 batch (per bobot).</p>
  <div style="margin-top:16px;text-align:right"><button class="btn gray" onclick="closeModal()">Batal</button> <button class="btn" onclick="saveProd('${id||""}')">Simpan</button></div>`);}
async function saveProd(id){const o={id:id||null,name:document.getElementById("pn").value.trim(),cat:document.getElementById("pc").value.trim()||"Umum",gram:+document.getElementById("pg").value||1,hpp:+digits(document.getElementById("ph").value)||0,harga:+digits(document.getElementById("phj").value)||0};
  if(!o.name){toast("Nama wajib.");return;}await api("saveProduct",{product:o});await reload();closeModal();toast("Produk tersimpan ✓");rProduk();}
async function delProd(id){const used=S.batches.some(b=>(b.outputs||[]).some(o=>o.productId===id))||S.notas.some(n=>notaItems(n).some(it=>it.productId===id));if(used){toast("Produk dipakai di batch/nota.");return;}if(confirm("Hapus produk ini?")){await api("deleteProduct",{id});await reload();rProduk();}}

// ================= TOKO =================
function rToko(){
  if(isMobile()){
    const rows=S.stores.map(s=>{const ns=S.notas.filter(n=>n.storeId===s.id);const omz=ns.reduce((a,n)=>a+notaTotal(n),0),due=ns.reduce((a,n)=>a+notaDue(n),0);
      return crow({icon:"🏪",title:esc(s.name),sub:`${esc(s.contact||"tanpa kontak")}${due>0?` · piutang ${rp(due)}`:""}`,amt:rp(omz),onclick:`editStore('${s.id}')`,del:`delStore('${s.id}')`});}).join("");
    document.getElementById("v-toko").innerHTML=`<div class="desc">Daftar toko/warung tempat titip atau jual.</div><button class="btn" style="width:100%;margin-bottom:14px" onclick="editStore()">+ Toko Baru</button>${S.stores.length===0?'<div class="empty">Belum ada toko.</div>':`<div class="clist">${rows}</div>`}`;
    return;
  }
  const rows=S.stores.map(s=>{const ns=S.notas.filter(n=>n.storeId===s.id);const omz=ns.reduce((a,n)=>a+notaTotal(n),0),due=ns.reduce((a,n)=>a+notaDue(n),0);
    return `<tr><td>${esc(s.name)}</td><td>${esc(s.contact||"")}</td><td>${esc(s.address||"")}</td><td class="num">${rp(omz)}</td><td class="num" style="color:var(--amber)">${rp(due)}</td><td class="num"><div class="acts"><button class="btn sm gray" onclick="editStore('${s.id}')">Edit</button><button class="btn sm del" onclick="delStore('${s.id}')">✕</button></div></td></tr>`;}).join("");
  document.getElementById("v-toko").innerHTML=`<h2 class="title">Master Toko</h2><div class="desc">Daftar toko/warung tempat titip atau jual produk.</div>
  <div class="flexbtns"><button class="btn" onclick="editStore()">+ Toko Baru</button></div>
  <div class="panel">${S.stores.length===0?'<div class="empty">Belum ada toko.</div>':`<table><thead><tr><th>Nama</th><th>Kontak</th><th>Alamat</th><th class="num">Omzet</th><th class="num">Piutang</th><th></th></tr></thead><tbody>${rows}</tbody></table>`}</div>`;
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

// ================= PROFIL USAHA =================
let _prof={};
function rProfile(){
  _prof=JSON.parse(JSON.stringify(S.profile||{}));
  const logo=_prof.logo||"";
  document.getElementById("v-profile").innerHTML=`<h2 class="title">Profil Usaha</h2>
  <div class="desc">Logo &amp; kontak ini muncul di kop laporan yang kamu cetak (HPP batch &amp; laba rugi).</div>
  <div class="panel" style="max-width:600px">
    <div style="display:flex;gap:18px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
      <div id="logoPrev" class="logobox">${logo?`<img src="${logo}" alt="logo">`:`<span>🍲</span>`}</div>
      <div style="flex:1;min-width:190px">
        <div style="font-weight:800;font-size:16px;margin-bottom:2px">${esc(BIZ.name||"Usaha")}</div>
        <div class="mini" style="margin-bottom:10px">Logo untuk kop laporan — disarankan gambar persegi (maks 2MB, otomatis diperkecil).</div>
        <div class="flexbtns" style="margin-bottom:0"><button class="btn sm ghost" onclick="document.getElementById('logoInp').click()">⬆ Unggah Logo</button><button class="btn sm gray" id="logoDel" onclick="removeLogo()" ${logo?"":'style="display:none"'}>Hapus Logo</button></div>
        <input type="file" id="logoInp" accept="image/*" style="display:none" onchange="pickLogo(this)">
      </div>
    </div>
    <label class="f">Alamat</label><textarea id="pfAddr" rows="2" style="margin-bottom:14px" placeholder="mis. Jl. Melati No. 10, Bandung">${esc(_prof.address||"")}</textarea>
    <div class="grid2" style="margin-bottom:14px"><div><label class="f">No. Telepon</label><input id="pfPhone" value="${esc(_prof.phone||"")}" placeholder="0812xxxx"></div><div><label class="f">WhatsApp</label><input id="pfWa" value="${esc(_prof.whatsapp||"")}" placeholder="0812xxxx"></div></div>
    <div class="grid2" style="margin-bottom:14px"><div><label class="f">Instagram</label><input id="pfIg" value="${esc(_prof.instagram||"")}" placeholder="@usaha"></div><div><label class="f">Facebook</label><input id="pfFb" value="${esc(_prof.facebook||"")}" placeholder="nama halaman"></div></div>
    <label class="f">TikTok / sosmed lain</label><input id="pfTt" value="${esc(_prof.tiktok||"")}" placeholder="@usaha" style="margin-bottom:18px">
    <div style="border-top:1px solid var(--line);padding-top:16px">
      <label class="f">Kode QRIS <span class="mini">(untuk pembayaran di Kasir)</span></label>
      <textarea id="pfQris" rows="3" placeholder="Tempel string QRIS dari hasil scan sticker usahamu (mulai 0002...)" oninput="qrisCheck(this.value)" style="font-size:12px;font-family:monospace">${esc(_prof.qris||"")}</textarea>
      <div id="qrisMsg" class="mini" style="margin-top:6px">${_prof.qris?(qrisValid(_prof.qris)?'<span style="color:var(--green)">✓ QRIS valid — nanti muncul di Kasir saat pilih QRIS.</span>':'<span style="color:var(--red)">⚠ Kode QRIS belum valid.</span>'):'Scan sticker QRIS-mu pakai app pembaca QR, salin teksnya ke sini. Kosongkan kalau belum ada.'}</div>
    </div>
    <div style="text-align:right;margin-top:16px"><button class="btn" onclick="saveProfile()">💾 Simpan Profil</button></div>
  </div>
  <div class="panel" style="max-width:600px"><h3>🔒 Keamanan</h3>
    <div class="grid2" style="margin-bottom:12px"><div><label class="f">Password Lama</label><input id="cpOld" type="password" placeholder="password sekarang"></div><div><label class="f">Password Baru (min 6)</label><input id="cpNew" type="password" placeholder="password baru"></div></div>
    <div style="text-align:right"><button class="btn ghost" onclick="doChangePassword()">Ubah Password</button></div>
  </div>`;
}
async function doChangePassword(){
  const o=document.getElementById("cpOld").value,n=document.getElementById("cpNew").value;
  if(!o||n.length<6){toast("Isi password lama & password baru (min 6).");return;}
  try{await api("authChangePassword",{oldPassword:o,newPassword:n});toast("Password diubah ✓");document.getElementById("cpOld").value="";document.getElementById("cpNew").value="";}catch(e){}
}
function qrisCheck(v){v=(v||"").replace(/[\r\n\t]+/g,"").trim();const el=document.getElementById("qrisMsg");if(!el)return;
  if(!v){el.innerHTML="Kosongkan kalau belum punya QRIS.";return;}
  el.innerHTML=qrisValid(v)?'<span style="color:var(--green)">✓ QRIS valid — siap dipakai di Kasir.</span>':'<span style="color:var(--red)">⚠ Kode belum valid (cek lagi salin-tempelnya).</span>';}
function pickLogo(inp){const f=inp.files[0];if(!f)return;
  if(!/^image\//.test(f.type)){toast("File harus berupa gambar.");return;}
  if(f.size>2*1024*1024){toast("Ukuran maksimal 2MB.");return;}
  const rd=new FileReader();
  rd.onload=e=>{const img=new Image();img.onload=()=>{
    const max=512;let w=img.width,h=img.height;
    if(w>h&&w>max){h=Math.round(h*max/w);w=max;}else if(h>=w&&h>max){w=Math.round(w*max/h);h=max;}
    const cv=document.createElement("canvas");cv.width=w;cv.height=h;cv.getContext("2d").drawImage(img,0,0,w,h);
    const url=cv.toDataURL("image/png");_prof.logo=url;
    document.getElementById("logoPrev").innerHTML=`<img src="${url}" alt="logo">`;
    const db=document.getElementById("logoDel");if(db)db.style.display="";
  };img.onerror=()=>toast("Gambar tak bisa dibaca.");img.src=e.target.result;};
  rd.readAsDataURL(f);}
function removeLogo(){_prof.logo="";document.getElementById("logoPrev").innerHTML=`<span>🍲</span>`;const db=document.getElementById("logoDel");if(db)db.style.display="none";}
async function saveProfile(){
  const g=id=>document.getElementById(id).value.trim();
  const qris=document.getElementById("pfQris").value.replace(/[\r\n\t]+/g,"").trim();
  if(qris&&!qrisValid(qris)){toast("Kode QRIS belum valid — cek lagi.");return;}
  const prof={address:g("pfAddr"),phone:g("pfPhone"),whatsapp:g("pfWa"),instagram:g("pfIg"),facebook:g("pfFb"),tiktok:g("pfTt"),logo:_prof.logo||"",qris};
  await api("saveProfile",{profile:prof});await reload();toast("Profil tersimpan ✓");rProfile();
}
// kop laporan untuk cetak (logo + nama usaha + kontak/sosmed)
function printHead(title,sub){
  const p=S.profile||{};
  const contacts=[p.address,p.phone?"Telp "+p.phone:"",p.whatsapp?"WA "+p.whatsapp:"",p.instagram?"IG "+p.instagram:"",p.facebook?"FB "+p.facebook:"",p.tiktok?"TikTok "+p.tiktok:""].filter(Boolean).map(esc).join(" · ");
  return `<div class="phead">${p.logo?`<img class="plogo" src="${p.logo}" alt="logo">`:""}<div><div class="pbiz">${esc(BIZ.name||"Usaha")}</div>${contacts?`<div class="pcontact">${contacts}</div>`:""}</div></div>
  <h1>${title}</h1>${sub?`<div class="psub">${sub}</div>`:""}`;
}

// ================= BACKUP =================
function rBackup(){
  document.getElementById("v-backup").innerHTML=`<h2 class="title">Backup &amp; Data</h2><div class="desc">Data tersimpan di database MySQL (XAMPP). Backup berkala tetap disarankan.</div>
  <div class="panel"><h3>Backup / Restore</h3><p class="mini" style="margin-bottom:14px">Simpan seluruh data ke file .json, atau muat kembali (menimpa data sekarang).</p>
  <div class="flexbtns"><button class="btn" onclick="exportJSON()">⬇ Download Backup (.json)</button><button class="btn ghost" onclick="document.getElementById('imp').click()">⬆ Restore dari file</button><input type="file" id="imp" accept=".json" style="display:none" onchange="importJSON(this)"></div></div>
  <div class="panel"><h3>Export Excel/CSV</h3><div class="flexbtns"><button class="btn dark" onclick="exportCSV('batches')">Batch Produksi</button><button class="btn dark" onclick="exportCSV('distributions')">Distribusi</button><button class="btn dark" onclick="exportCSV('payments')">Pembayaran</button><button class="btn dark" onclick="exportCSV('materials')">Harga Bahan</button></div></div>
  <div class="panel"><h3>Harga Bahan</h3><p class="mini" style="margin-bottom:14px">Selaraskan harga master &amp; riwayat bahan dari harga yang dipakai di batch (harga batch tanggal terbaru jadi harga terkini).</p><button class="btn ghost" onclick="resyncPrices()">🔄 Sinkron Ulang Harga Bahan</button></div>
  <div class="panel"><h3>Reset</h3><p class="mini" style="margin-bottom:14px">Hapus semua data &amp; kembali ke contoh awal.</p><button class="btn gray" onclick="resetAll()">Reset Semua Data</button></div>`;
}
function exportJSON(){dl("anna-backup-"+today()+".json",JSON.stringify(S,null,2),"application/json");}
async function importJSON(inp){const f=inp.files[0];if(!f)return;const r=new FileReader();
  r.onload=async e=>{try{const d=JSON.parse(e.target.result);if(!d.products)throw 0;await api("importAll",{data:d});await reload();toast("Data dimuat ✓");document.querySelector('#nav button').click();}catch(err){toast("File tidak valid.");}};r.readAsText(f);}
function csvCell(v){v=v==null?"":String(v);return /[",\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v;}
function exportCSV(type){let rows=[];
  if(type==="batches"){rows.push(["Tanggal","Catatan","Produk Jadi","Botol","Bahan","Ops","Total Modal","HPP rata/botol"]);S.batches.forEach(b=>{const c=batchCalc(b);rows.push([b.date,b.note,(b.outputs||[]).map(o=>`${(prod(o.productId)||{}).name} x${o.qty}`).join("; "),c.bottles,c.mat,c.ops,c.total,Math.round(c.avg)]);});}
  else if(type==="distributions"){rows.push(["Tanggal","No. Nota","Toko","Produk","Jenis","Qty","Harga","Total","HPP","Laba","Status Nota"]);S.notas.forEach(n=>notaItems(n).forEach(it=>rows.push([n.date,n.notaNo,(store(n.storeId)||{}).name,(prod(it.productId)||{}).name,(it.kind||"jual"),it.qty,it.harga,(+it.qty||0)*(+it.harga||0),it.hpp,(+it.qty||0)*((+it.harga||0)-(+it.hpp||0)),notaStatus(n)])));}
  else if(type==="materials"){rows.push(["Bahan","Satuan","Tanggal","Harga","Sumber"]);S.materials.forEach(m=>(m.history||[]).forEach(h=>rows.push([m.name,m.unit,h.date,h.price,h.source])));}
  else{rows.push(["Tanggal Bayar","No. Nota","Toko","Jumlah","Catatan"]);S.notas.forEach(n=>(n.payments||[]).forEach(p=>rows.push([p.date,n.notaNo,(store(n.storeId)||{}).name,p.amount,p.note])));}
  dl(type+"-"+today()+".csv","﻿"+rows.map(r=>r.map(csvCell).join(",")).join("\n"),"text/csv");}
function dl(name,content,mime){const b=new Blob([content],{type:mime}),u=URL.createObjectURL(b),a=document.createElement("a");a.href=u;a.download=name;a.click();URL.revokeObjectURL(u);}
async function resetAll(){if(confirm("Yakin hapus SEMUA data? Tidak bisa dibatalkan.")){await api("reset");await reload();toast("Data direset.");document.querySelector('#nav button').click();}}
async function resyncPrices(){const r=await api("resyncPrices");await reload();toast(`Harga ${r.materials||0} bahan disinkron ✓`);rBackup();}

// ---------- boot ----------
// ---------- login ----------
const llink='style="color:var(--red);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none"';
function renderLogin(mode){
  const box=document.getElementById("loginBox");
  let form;
  if(mode==='forgot'){
    form=`<div class="lerr" id="lerr"></div>
      <h3 style="text-align:center;margin-bottom:4px">Lupa Password</h3>
      <p class="lsub" style="margin-bottom:16px">Isi kode usaha &amp; email. Link reset akan dikirim ke email itu.</p>
      <div class="lfield"><label>Kode Usaha</label><input id="fCode" placeholder="mis. anna" autocapitalize="none"></div>
      <div class="lfield"><label>Email</label><input id="fEmail" type="email" placeholder="email terdaftar" autocapitalize="none"></div>
      <button class="btn" style="width:100%;margin-top:6px" onclick="doForgot()">Kirim Link Reset</button>
      <div style="text-align:center;margin-top:14px"><a ${llink} onclick="renderLogin('login')">← Kembali masuk</a></div>`;
  } else if(mode==='reset'){
    form=`<div class="lerr" id="lerr"></div>
      <h3 style="text-align:center;margin-bottom:4px">Buat Password Baru</h3>
      <p class="lsub" style="margin-bottom:16px">Masukkan password baru untuk akunmu.</p>
      <div class="lfield"><label>Password Baru (min 6)</label><input id="rpNew" type="password" placeholder="password baru"></div>
      <div class="lfield"><label>Ulangi Password</label><input id="rpNew2" type="password" placeholder="ulangi password"></div>
      <button class="btn" style="width:100%;margin-top:6px" onclick="doResetConfirm()">Simpan Password</button>`;
  } else {
    const tab=`<div class="ltabs"><button class="${mode!=='reg'?'on':''}" onclick="renderLogin('login')">Masuk</button><button class="${mode==='reg'?'on':''}" onclick="renderLogin('reg')">Daftar Usaha</button></div><div class="lerr" id="lerr"></div>`;
    form=mode==='reg'
    ?`${tab}<div class="lfield"><label>Nama Usaha</label><input id="rName" placeholder="mis. ANNA Snack & Kitchen"></div>
      <div class="lfield"><label>Kode Usaha <span style="font-weight:400">(huruf kecil/angka, tanpa spasi)</span></label><input id="rCode" placeholder="mis. anna" autocapitalize="none"></div>
      <div class="lfield"><label>Nama Kamu</label><input id="rUser" placeholder="mis. Anna"></div>
      <div class="lfield"><label>Email</label><input id="rEmail" type="email" placeholder="email@usaha.com" autocapitalize="none"></div>
      <div class="lfield"><label>Password (min 6)</label><input id="rPass" type="password" placeholder="buat password"></div>
      <label class="lremember"><input type="checkbox" id="rRemember" checked> Ingat saya (tetap masuk 30 hari)</label>
      <button class="btn" style="width:100%;margin-top:6px" onclick="doRegister()">Daftar &amp; Masuk</button>`
    :`${tab}<div class="lfield"><label>Kode Usaha</label><input id="lCode" placeholder="mis. anna" autocapitalize="none"></div>
      <div class="lfield"><label>Email</label><input id="lEmail" type="email" placeholder="email kamu" autocapitalize="none"></div>
      <div class="lfield"><label>Password</label><input id="lPass" type="password" placeholder="password"></div>
      <label class="lremember"><input type="checkbox" id="lRemember" checked> Ingat saya (tetap masuk 30 hari)</label>
      <button class="btn" style="width:100%;margin-top:6px" onclick="doLogin()">Masuk</button>
      <div style="text-align:center;margin-top:14px"><a ${llink} onclick="renderLogin('forgot')">Lupa password?</a></div>`;
  }
  box.innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div class="lsub">Kelola produksi, HPP &amp; penjualan UMKM</div>${form}`;
}
async function doForgot(){
  const code=document.getElementById("fCode").value.trim().toLowerCase(),email=document.getElementById("fEmail").value.trim().toLowerCase();
  if(!code||!email){loginErr("Isi kode usaha & email.");return;}
  try{await api("authResetRequest",{code,email},{silent:true});}catch(e){}
  document.getElementById("loginBox").innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div style="text-align:center;padding:6px 0"><div style="font-size:42px">📧</div><h3 style="margin:8px 0 6px">Cek Email Kamu</h3><p class="lsub">Kalau email terdaftar, link reset password sudah dikirim (berlaku 1 jam). Cek juga folder spam ya.</p><button class="btn ghost" style="width:100%;margin-top:16px" onclick="renderLogin('login')">Kembali ke Masuk</button></div>`;
}
async function doResetConfirm(){
  const p1=document.getElementById("rpNew").value,p2=document.getElementById("rpNew2").value;
  if(p1.length<6){loginErr("Password minimal 6 karakter.");return;}
  if(p1!==p2){loginErr("Password tidak sama.");return;}
  try{
    await api("authResetConfirm",{token:window._resetToken,password:p1},{silent:true});
    try{history.replaceState({},"",location.pathname);}catch(e){}
    document.getElementById("loginBox").innerHTML=`<img class="lgo" src="icons/logo-bowl.png" alt=""><img class="lword" src="icons/logo-word.png" alt="Racikin"><div style="text-align:center;padding:6px 0"><div style="font-size:42px">✅</div><h3 style="margin:8px 0 6px">Password Diubah</h3><p class="lsub">Silakan masuk dengan password baru.</p><button class="btn" style="width:100%;margin-top:16px" onclick="renderLogin('login')">Masuk Sekarang</button></div>`;
  }catch(e){loginErr(e.message||"Link reset tidak valid atau kadaluarsa.");}
}
function loginErr(m){const e=document.getElementById("lerr");if(e){e.textContent=m;e.style.display="block";}}
async function doLogin(){const code=document.getElementById("lCode").value.trim().toLowerCase(),email=document.getElementById("lEmail").value.trim().toLowerCase(),password=document.getElementById("lPass").value,remember=document.getElementById("lRemember").checked;if(!code||!email||!password){loginErr("Isi kode usaha, email & password.");return;}try{BIZ=await api("authLogin",{code,email,password,remember},{silent:true});document.getElementById("loginScreen").style.display="none";await bootApp();}catch(e){loginErr(e.message||"Gagal masuk.");}}
async function doRegister(){const name=document.getElementById("rName").value.trim(),code=document.getElementById("rCode").value.trim().toLowerCase(),user=document.getElementById("rUser").value.trim(),email=document.getElementById("rEmail").value.trim().toLowerCase(),password=document.getElementById("rPass").value,remember=document.getElementById("rRemember").checked;if(!name||!code||!user||!email||!password){loginErr("Lengkapi semua kolom.");return;}try{BIZ=await api("authRegister",{name,code,user,email,password,remember},{silent:true});document.getElementById("loginScreen").style.display="none";await bootApp();}catch(e){loginErr(e.message||"Gagal daftar.");}}
async function doLogout(){if(!confirm("Keluar dari "+(BIZ.name||"usaha")+"?"))return;try{await api("authLogout");}catch(e){}location.reload();}
async function bootApp(){
  try{await reload();
    const v=(()=>{try{return localStorage.getItem("anna_view");}catch(e){return null;}})()||"dashboard";
    (document.querySelector(`#nav button[data-v="${v}"]`)||document.querySelector("#nav button")).click();
  }catch(e){document.getElementById("loginScreen").style.display="none";document.getElementById("v-dashboard").innerHTML='<div class="panel"><h3>Gagal terhubung ke database</h3><p class="mini">Pastikan Apache &amp; MySQL di XAMPP sudah <b>Start</b>, lalu refresh.</p></div>';}
}
(async()=>{
  // link reset password dari email: ?reset=selector.token
  const rt=new URLSearchParams(location.search).get("reset");
  if(rt){window._resetToken=rt;document.getElementById("loginScreen").style.display="flex";renderLogin("reset");return;}
  let st;try{st=await api("authStatus");}catch(e){st={loggedIn:false};}
  if(st&&st.loggedIn){BIZ=st;document.getElementById("loginScreen").style.display="none";await bootApp();}
  else{renderLogin("login");}
})();
// ---- PWA: daftarkan service worker ----
if("serviceWorker" in navigator){window.addEventListener("load",()=>navigator.serviceWorker.register("sw.js").catch(()=>{}));}
</script>
</body>
</html>
