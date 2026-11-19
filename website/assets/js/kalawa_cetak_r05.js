/* Refresh print time from server time plus elapsed time. No requests or writes. */
(() => {
  'use strict';
  const header=document.querySelector('[data-ks-print-epoch]');
  if(!header)return;
  const epoch=Number(header.dataset.ksPrintEpoch),start=performance.now();
  if(!Number.isFinite(epoch))return;
  const formatter=new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'});
  const update=()=>{
    const now=new Date(epoch+performance.now()-start);
    header.querySelectorAll('[data-ks-print-time]').forEach(el=>{el.textContent=formatter.format(now)+' WIB';el.dateTime=now.toISOString();});
  };
  window.addEventListener('beforeprint',update);
})();
