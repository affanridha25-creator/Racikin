"use strict";
// push.js — notifikasi push (Android)
// ---- Notifikasi push (FCM) — hanya aktif di aplikasi Android ----
async function initPush(){
  try{
    const P=window.Capacitor&&window.Capacitor.Plugins&&window.Capacitor.Plugins.PushNotifications;
    if(!P||!(window.Capacitor.isNativePlatform&&window.Capacitor.isNativePlatform()))return;
    let perm=await P.checkPermissions();
    if(perm.receive!=="granted")perm=await P.requestPermissions();
    if(perm.receive!=="granted")return;
    P.removeAllListeners&&await P.removeAllListeners();
    P.addListener("registration",t=>{try{api("savePushToken",{token:t.value,platform:"android"},{silent:true});}catch(e){}});
    P.addListener("registrationError",()=>{});
    P.addListener("pushNotificationActionPerformed",()=>{});   // tap notif → app terbuka
    await P.register();
  }catch(e){}
}
// (layar kunci & perpanjangan langganan dipindah ke langganan.js)
