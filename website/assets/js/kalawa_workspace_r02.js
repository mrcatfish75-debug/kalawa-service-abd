/* Kalawa Workspace R02: interaksi visual saja; tidak menyentuh transaksi atau hak akses. */
(function () {
  'use strict';
  const sidebar = document.querySelector('#ks-sidebar');
  const openers = [...document.querySelectorAll('[data-ks-menu]')];
  const opener = openers[0];
  const bottomNav = document.querySelector('.ks-bottom-nav');
  const backdrop = document.querySelector('.ks-menu-backdrop');
  const appArea = document.querySelector('.ks-app-area');
  if (sidebar && opener && backdrop && appArea) {
    const desktop = window.matchMedia('(min-width: 1100px)');
    let lastFocus = null;
    const open = () => {
      if (desktop.matches) return;
      lastFocus = document.activeElement;
      sidebar.inert = false;
      sidebar.removeAttribute('aria-hidden');
      sidebar.setAttribute('role', 'dialog');
      sidebar.setAttribute('aria-modal', 'true');
      document.body.classList.add('ks-menu-open');
      backdrop.hidden = false;
      openers.forEach(el=>el.setAttribute('aria-expanded', 'true'));
      if(bottomNav) bottomNav.inert=true;
      (sidebar.querySelector('[data-ks-close-menu]') || sidebar.querySelector('a'))?.focus();
      appArea.inert = true;
    };
    const close = (returnFocus = true) => {
      const wasOpen = document.body.classList.contains('ks-menu-open');
      appArea.inert = false;
      if(bottomNav) bottomNav.inert=false;
      if (wasOpen && returnFocus && !desktop.matches) {
        const target = lastFocus && document.contains(lastFocus) ? lastFocus : opener;
        target.focus();
      }
      document.body.classList.remove('ks-menu-open');
      backdrop.hidden = true;
      openers.forEach(el=>el.setAttribute('aria-expanded', 'false'));
      if(bottomNav) bottomNav.inert=false;
      sidebar.removeAttribute('role');
      sidebar.removeAttribute('aria-modal');
      sidebar.inert = !desktop.matches;
      if (desktop.matches) sidebar.removeAttribute('aria-hidden');
      else sidebar.setAttribute('aria-hidden', 'true');
    };
    openers.forEach(el=>el.addEventListener('click', () => document.body.classList.contains('ks-menu-open') ? close() : open()));
    document.querySelectorAll('[data-ks-close-menu]').forEach(el => el.addEventListener('click', () => close()));
    sidebar.querySelectorAll('a').forEach(el => el.addEventListener('click', () => close()));
    document.addEventListener('keydown', ev => {
      if (!document.body.classList.contains('ks-menu-open')) return;
      if (ev.key === 'Escape') { ev.preventDefault(); close(); }
      if (ev.key !== 'Tab') return;
      const list = Array.from(sidebar.querySelectorAll('a,button:not(:disabled),input:not([type="hidden"])'))
        .filter(el => el.getClientRects().length);
      if (!list.length) return;
      if (ev.shiftKey && document.activeElement === list[0]) { ev.preventDefault(); list[list.length - 1].focus(); }
      else if (!ev.shiftKey && document.activeElement === list[list.length - 1]) { ev.preventDefault(); list[0].focus(); }
    });
    const handleResize = () => {
      // A link focused on desktop must not become trapped in the hidden drawer.
      if (!desktop.matches && sidebar.contains(document.activeElement)) opener.focus();
      close(false);
    };
    if (desktop.addEventListener) desktop.addEventListener('change', handleResize);
    else if (desktop.addListener) desktop.addListener(handleResize);
    handleResize();
  }

  const passwordBtn = document.querySelector('[data-ks-password-toggle]');
  if (passwordBtn) passwordBtn.addEventListener('click', () => {
    const input = document.getElementById('password'); if (!input) return;
    const shown = input.type === 'password'; input.type = shown ? 'text' : 'password';
    passwordBtn.textContent = shown ? 'Sembunyikan' : 'Tampilkan';
    passwordBtn.setAttribute('aria-pressed', shown ? 'true' : 'false');
  });

  // Hanya daftar WO R01A. Kartu dibuat dari data tabel yang benar-benar dikirim PHP.
  // Tidak memakai record palsu atau fetch baru ke database.
  const holder = document.querySelector('.ks-wo-table');
  if (holder && holder.querySelector('table tbody')) {
    const tbody = holder.querySelector('tbody');
    const cards = document.createElement('div');
    cards.className = 'ks-wo-mobile-cards';
    cards.setAttribute('aria-label', 'Work Order dalam tampilan ponsel');
    for (const tr of Array.from(tbody.querySelectorAll('tr'))) {
      const td = tr.querySelectorAll('td');
      if (td.length !== 6) continue; // pesan kosong tetap memakai tabel asli
      const card = document.createElement('article');
      card.className = 'ks-wo-mobile-card';
      const heading = document.createElement('div'); heading.className = 'ks-wo-mobile-top';
      const code = document.createElement('strong'); code.textContent = td[0].querySelector('strong')?.textContent?.trim() || td[0].textContent.trim();
      heading.appendChild(code);
      const badge = td[4].querySelector('.ks-wo-status'); if (badge) heading.appendChild(badge.cloneNode(true));
      card.appendChild(heading);
      const desc = document.createElement('p'); desc.className = 'ks-wo-mobile-meta';
      const plate = td[2].querySelector('small')?.textContent?.trim() || '';
      const date = td[0].querySelector('small')?.textContent?.trim() || '';
      desc.textContent = [td[2].querySelector('strong')?.textContent?.trim() || '', plate, date].filter(Boolean).join(' · ');
      card.appendChild(desc);
      const facts = document.createElement('dl'); facts.className = 'ks-wo-mobile-facts';
      const mechanicLabel = document.createElement('dt'); mechanicLabel.textContent = 'Mekanik';
      const mechanic = document.createElement('dd'); mechanic.textContent = td[3].textContent.trim();
      facts.append(mechanicLabel, mechanic);
      // A missing marker is not evidence of an unpaid WO: copy only the server's actual text.
      const paidOriginal = td[4].querySelector('small');
      if (paidOriginal) {
        const paidLabel = document.createElement('dt'); paidLabel.textContent = 'Pembayaran';
        const paid = document.createElement('dd'); paid.className = 'ks-wo-mobile-paid';
        paid.textContent = paidOriginal.textContent.trim(); facts.append(paidLabel, paid);
      }
      card.appendChild(facts);
      const bottom = document.createElement('div'); bottom.className = 'ks-wo-mobile-bottom';
      const branch = document.createElement('span'); branch.textContent = td[1].textContent.trim(); bottom.appendChild(branch);
      const linkOriginal = td[5].querySelector('a');
      if (linkOriginal) {
        const link = document.createElement('a'); link.textContent = 'Lihat detail'; link.href = linkOriginal.href;
        link.className = 'ks-wo-mobile-link'; bottom.appendChild(link);
      }
      card.appendChild(bottom); cards.appendChild(card);
    }
    if (cards.children.length) { holder.appendChild(cards); document.body.classList.add('ks-cards-ready'); }
  }
})();

/* Menu lookup uses only links already authorized and rendered by PHP. */
(() => {
  'use strict';
  const dialog=document.querySelector('.ks-command');
  const input=document.querySelector('#ks-command-query');
  const results=document.querySelector('#ks-command-results');
  const launch=document.querySelector('[data-ks-command]');
  if(!dialog||!input||!results||!launch||typeof dialog.showModal!=='function')return;
  const links=[...document.querySelectorAll('.ks-side-nav a')];
  const empty=dialog.querySelector('.ks-command-empty');
  let returnTo=null;
  const render=()=>{
    const term=input.value.trim().toLocaleLowerCase('id');
    results.replaceChildren();
    links.filter(a=>a.textContent.toLocaleLowerCase('id').includes(term)).forEach(a=>{
      const copy=a.cloneNode(true);
      copy.className='ks-command-link';
      results.append(copy);
    });
    empty.hidden=results.children.length>0;
  };
  const open=()=>{
    if(dialog.open||document.body.classList.contains('ks-menu-open'))return;
    returnTo=document.activeElement;input.value='';render();dialog.showModal();input.focus();
  };
  launch.hidden=false;
  launch.addEventListener('click',open);
  input.addEventListener('input',render);
  dialog.querySelector('[data-ks-command-close]').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('close',()=>{if(returnTo?.isConnected)returnTo.focus();});
  dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
  document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();open();}});
  dialog.addEventListener('keydown',e=>{
    if(e.key==='Escape'){e.preventDefault();dialog.close();return;}
    const list=[...results.querySelectorAll('a')];
    const at=list.indexOf(document.activeElement);
    if(e.key==='ArrowDown'&&list.length){e.preventDefault();list[(at+1)%list.length].focus();}
    if(e.key==='ArrowUp'&&list.length){e.preventDefault();if(at<=0)input.focus();else list[at-1].focus();}
    if(e.key==='Enter'&&e.target===input&&list[0]){e.preventDefault();list[0].click();}
  });
})();

/* Sidebar progress is shown only when its menu actually scrolls. */
(() => {
  const nav=document.querySelector('.ks-side-nav');
  const progress=document.querySelector('.ks-nav-scroll');
  if(!nav||!progress)return;
  const update=()=>{const max=nav.scrollHeight-nav.clientHeight;progress.hidden=max<2;progress.firstElementChild.style.width=(max>0?Math.min(100,100*nav.scrollTop/max):0)+'%';};
  nav.addEventListener('scroll',update,{passive:true});
  window.addEventListener('resize',update);
  if(window.ResizeObserver)new ResizeObserver(update).observe(nav);
  update();
})();

/* bfcache must not leave a payment form disabled after navigating back. */
window.addEventListener('pageshow',event=>{
  if(!event.persisted)return;
  document.querySelectorAll('form[data-sent="1"]').forEach(form=>{
    delete form.dataset.sent;
    form.querySelectorAll('button[type="submit"]').forEach(button=>{button.disabled=false;});
  });
});

/* Explain scrollable tables only while additional columns are outside the viewport. */
(() => {
  'use strict';
  document.querySelectorAll('main .tablewrap,main .ks-table,main .ks-pay-tablewrap').forEach((region, index) => {
    if (!region.querySelector('table')) return;
    const hint = document.createElement('p');
    hint.className = 'ks-ui-scroll-hint'; hint.id = 'ks-table-scroll-' + index;
    hint.textContent = 'Geser tabel ke samping untuk melihat kolom lainnya.'; hint.hidden = true;
    region.before(hint);
    const initial = {tabindex: region.getAttribute('tabindex'), role: region.getAttribute('role'), label: region.getAttribute('aria-label'), description: region.getAttribute('aria-describedby')};
    const restore = (name, value) => value === null ? region.removeAttribute(name) : region.setAttribute(name, value);
    const update = () => {
      const overflow = region.scrollWidth > region.clientWidth + 1;
      hint.hidden = !overflow;
      if (overflow) {
        region.setAttribute('tabindex', initial.tabindex ?? '0');
        region.setAttribute('role', initial.role ?? 'region');
        region.setAttribute('aria-label', initial.label ?? (region.closest('section')?.querySelector('h2')?.textContent.trim() || 'Tabel data'));
        region.setAttribute('aria-describedby', [initial.description, hint.id].filter(Boolean).join(' '));
      } else {
        restore('tabindex', initial.tabindex); restore('role', initial.role);
        restore('aria-label', initial.label); restore('aria-describedby', initial.description);
      }
    };
    if (window.ResizeObserver) new ResizeObserver(update).observe(region);
    else window.addEventListener('resize', update);
    update();
  });
})();

/* Workspace R02.1: progressive enhancement murni UI, tidak mengubah nama/isi field atau form action. */
(function () {
  'use strict';
  const main=document.querySelector('main.ks-content');
  if (!main) return;
  const filename=window.location.pathname.split('/').pop().toLowerCase();
  if (filename==='pelanggan.php') {
    const sections=[...main.querySelectorAll('section.panel')];
    const formSection=sections.find(el=>/^\s*(Tambah|Ubah) pelanggan\s*$/i.test(el.querySelector('h2')?.textContent||''));
    const listSection=sections.find(el=>/^\s*Daftar pelanggan\s*$/i.test(el.querySelector('h2')?.textContent||''));
    const oldIntro=[...main.querySelectorAll('p')].find(el=>(el.textContent||'').includes('Modul CRUD tahap awal'));
    if (oldIntro) oldIntro.textContent='Kelola data pelanggan untuk pencatatan dan pelayanan servis.';
    if (formSection && listSection) {
      const isEditing=new URLSearchParams(window.location.search).has('ubah');
      const hasError=!!main.querySelector('.alert.bad,[role="alert"]');
      const explicitlyNew=new URLSearchParams(window.location.search).get('tambah')==='1';
      const shouldOpen=isEditing||hasError||explicitlyNew;
      // Reorder actual DOM; browser still submits the original form including CSRF token.
      listSection.insertAdjacentElement('afterend',formSection);
      if (!shouldOpen) formSection.hidden=true;
      const heading=listSection.querySelector('.row.space')||listSection.querySelector('h2')?.parentElement;
      if (heading) {
        const add=document.createElement('button');
        add.type='button';add.className='ks-r02-add-customer';
        add.textContent=shouldOpen ? 'Tutup formulir' : '+ Tambah pelanggan';
        add.setAttribute('aria-expanded',shouldOpen?'true':'false');
        formSection.id='ks-pelanggan-form';add.setAttribute('aria-controls',formSection.id);
        add.addEventListener('click',()=>{
          const willOpen=formSection.hidden;
          formSection.hidden=!willOpen;
          add.setAttribute('aria-expanded',willOpen?'true':'false');
          add.textContent=willOpen?'Tutup formulir':'+ Tambah pelanggan';
          if (willOpen) {
            formSection.scrollIntoView({behavior:'auto',block:'start'});
            formSection.querySelector('input:not([type="hidden"]):not([readonly]),textarea,select')?.focus();
          }
        });
        heading.appendChild(add);
      }
    }
  }
  if (filename==='stok.php') {
    const count=main.querySelector('.ks-stock-count');
    if (count && /data persediaan\.?/.test(count.textContent||'')) {
      count.textContent=count.textContent.replace('data persediaan','kombinasi barang dan cabang');
    }
  }
})();
