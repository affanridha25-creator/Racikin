"use strict";
// qris.js — encoder QR + QRIS dinamis
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

