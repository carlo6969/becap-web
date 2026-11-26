(function(){
'use strict';
const P = window.__P;
const WA = '527712884753';
const $ = (s, el=document) => el.querySelector(s);
const $$ = (s, el=document) => [...el.querySelectorAll(s)];
const money = n => '$' + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 0, maximumFractionDigits: 0});
const img = (p, k=0) => `img/p/${p.id}-${Math.min(k, p.n-1)}.webp`;
const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const store = { get(k, d){ try{ return JSON.parse(localStorage.getItem(k)) ?? d }catch(e){ return d } }, set(k, v){ try{ localStorage.setItem(k, JSON.stringify(v)) }catch(e){} } };

const CATS = [
  {k:'originales', t:'Originales', s:'New Era · ’47 · Puma', img:69},
  {k:'disenador', t:'Diseñador', s:'Kiblo · 31 Hats · Barbas', img:65},
  {k:'vintage', t:'Vintage & Flex', s:'Algodón, acid wash, flex', img:52},
  {k:'bucket', t:'Bucket & Brimless', s:'Pana, corduroy, docker', img:40},
  {k:'ropa', t:'Ropa', s:'Hoodies, playeras, shorts', img:19},
  {k:'complementos', t:'Complementos', s:'Calcetas, lentes, cuidado', img:3},
];
const catName = k => (CATS.find(c => c.k === k) || {}).t || 'Todo';
const BRANDS = [...new Set(P.map(p => p.b))].sort((a,b) => (a==='Be Cap Selección') - (b==='Be Cap Selección') || P.filter(p=>p.b===b).length - P.filter(p=>p.b===a).length);
const FITS = ['Snapback','Strapback','Ajustable','Flex / cerrada'];

/* ---------- announcement & brands ---------- */
const ann = ['Envío gratis a partir de $1,000 MXN','Envíos a todo México','Tienda en Av. Revolución 503, Pachuca','Nuevo: diseña tu gorra en 3D en Be Cap Studio','4.9 ★ en Google'];
$('#annTrack').innerHTML = [...ann, ...ann, ...ann, ...ann].map(t => `<span>${t}</span>`).join('');
const br = ['New Era','Kiblo Caps','’47','31 Hats','Barbas Hats','Innedit','Puma','The North Face','Carhartt','Vans','Essentials'];
$('#brandTrack').innerHTML = [...br, ...br].map(t => `<span>${t}</span>`).join('');

/* ---------- product card ---------- */
function priceHtml(p){
  const from = p.v.length && new Set(p.v.map(v=>v[1])).size > 1 ? '<small style="font-weight:600;color:var(--muted);font-size:12px">desde</small> ' : '';
  return `<div class="price ${p.cp?'red':''}">${from}<span class="now">${money(p.p)}</span>${p.cp?`<s>${money(p.cp)}</s>`:''}</div>`;
}
function tagsHtml(p){
  let t = '';
  if(!p.a) t += '<span class="tag out">Agotado</span>';
  else if(p.cp) t += `<span class="tag sale">-${Math.round((1-p.p/p.cp)*100)}%</span>`;
  if(p.bs && p.a) t += '<span class="tag best">Más vendido</span>';
  if(p.new && p.a) t += '<span class="tag new">Nuevo</span>';
  return `<div class="tags">${t}</div>`;
}
function optSummary(p){
  if(!p.o.length) return '';
  const vals = p.o.map((o,i) => [...new Set(p.v.map(v => v[0].split(' / ')[i]))]);
  return `<div class="sw">${p.o.map((o,i)=>`<i>${vals[i].length} ${o.toLowerCase()==='color'?'colores':o.toLowerCase()==='talla'?'tallas':'opciones'}</i>`).join('')}</div>`;
}
function card(p){
  return `<article class="card" data-id="${p.id}">
    <div class="ph">${tagsHtml(p)}<img class="a" src="${img(p,0)}" alt="${esc(p.t)}" loading="lazy" decoding="async">${p.n>1?`<img class="b" src="${img(p,1)}" alt="" loading="lazy" decoding="async">`:''}
      ${p.a?`<button class="qa" data-qa="${p.id}" aria-label="Agregar ${esc(p.t)}"><svg class="ic" style="width:18px;height:18px"><use href="#i-plus"/></svg><span>${p.o.length?'Elegir':'Agregar'}</span></button>`:''}</div>
    <div class="meta"><div class="brand">${esc(p.b)}</div><h3>${esc(p.t)}</h3>${priceHtml(p)}${optSummary(p)}</div>
  </article>`;
}

/* ---------- hero card, cats, best, mega ---------- */
const heroP = P[69];
$('#heroCard').innerHTML = `<img src="${img(heroP)}" alt=""><div><small>Destacado · New Era</small><h4>${esc(heroP.t)}</h4><b>${money(heroP.p)}</b></div><span class="plus"><svg class="ic"><use href="#i-plus"/></svg></span>`;
$('#heroCard').onclick = () => openProduct(heroP.id);

$('#cats').innerHTML = CATS.map(c => `<button class="cat" data-cat="${c.k}"><img src="${img(P[c.img])}" alt="" loading="lazy"><span class="arr"><svg class="ic" style="width:16px"><use href="#i-arrow"/></svg></span><div><h3>${c.t}</h3><small>${P.filter(p=>p.c===c.k).length} productos · ${c.s}</small></div></button>`).join('');

const best = P.filter(p => p.bs).sort((a,b)=>b.a-a.a).concat([P[69],P[74],P[65],P[30]]);
$('#bestScroller').innerHTML = best.map(card).join('');
const sc = $('#bestScroller');
$('#bsPrev').onclick = () => sc.scrollBy({left: -sc.clientWidth*.8, behavior:'smooth'});
$('#bsNext').onclick = () => sc.scrollBy({left: sc.clientWidth*.8, behavior:'smooth'});

$('#megaCats').innerHTML = `<li><button data-cat="all">Ver todo <small>${P.length}</small></button></li>` + CATS.map(c => `<li><button data-cat="${c.k}">${c.t} <small>${P.filter(p=>p.c===c.k).length}</small></button></li>`).join('');
$('#megaBrands').innerHTML = BRANDS.filter(b=>b!=='Be Cap Selección').slice(0,8).map(b => `<li><button data-brand="${esc(b)}">${esc(b)} <small>${P.filter(p=>p.b===b).length}</small></button></li>`).join('');
const fp = P[61];
$('#megaFeat').innerHTML = `<img src="${img(fp)}" alt=""><div>Gorras de diseñador<br><span style="color:var(--muted);font-weight:700">Kiblo Caps desde ${money(499)}</span></div>`;
$('#megaFeat').onclick = () => { setCat('disenador'); closeMega(); };
$('#fCatsFoot').innerHTML = CATS.map(c => `<li><button data-cat="${c.k}">${c.t}</button></li>`).join('');
$('#mShopSub').innerHTML = `<button data-cat="all">Ver todo</button>` + CATS.map(c => `<button data-cat="${c.k}">${c.t}</button>`).join('');
$('#mShopSub').style.display = 'none';
$('#mShopT').onclick = () => { const s = $('#mShopSub'); s.style.display = s.style.display === 'none' ? 'block' : 'none'; };

const mw = $('#megaWrap'); let mt;
const openMega = () => { clearTimeout(mt); mw.classList.add('open'); $('#megaBtn').setAttribute('aria-expanded','true'); };
const closeMega = () => { mw.classList.remove('open'); $('#megaBtn').setAttribute('aria-expanded','false'); };
mw.addEventListener('mouseenter', openMega);
mw.addEventListener('mouseleave', () => { mt = setTimeout(closeMega, 160); });
$('#megaBtn').onclick = () => mw.classList.contains('open') ? closeMega() : openMega();

const look = [P[19], P[20], P[11], P[2]];
$('#lookMinis').innerHTML = look.map(p => `<div class="mini" data-id="${p.id}"><img src="${img(p)}" alt=""><div><b>${esc(p.t)}</b><small>${money(p.p)}</small></div></div>`).join('');

/* ---------- shop filters ---------- */
const F = { cat:'all', brands:new Set(), fits:new Set(), max:1399, avail:false, sale:false, q:'', sort:'feat', shown:12 };
function renderFilterUI(){
  $('#fCats').innerHTML = `<button data-fcat="all" class="${F.cat==='all'?'on':''}">Todo <small>${P.length}</small></button>` + CATS.map(c => `<button data-fcat="${c.k}" class="${F.cat===c.k?'on':''}">${c.t} <small>${P.filter(p=>p.c===c.k).length}</small></button>`).join('');
  $('#fFits').innerHTML = FITS.map(f => `<label class="chk"><input type="checkbox" data-fit="${f}" ${F.fits.has(f)?'checked':''}>${f}<small>${P.filter(p=>p.f===f).length}</small></label>`).join('');
  $('#fBrands').innerHTML = BRANDS.map(b => `<label class="chk"><input type="checkbox" data-fbrand="${esc(b)}" ${F.brands.has(b)?'checked':''}>${esc(b)}<small>${P.filter(p=>p.b===b).length}</small></label>`).join('');
}
const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase();
function filtered(){
  const q = norm(F.q.trim());
  let r = P.filter(p => (F.cat==='all' || p.c===F.cat) && (!F.brands.size || F.brands.has(p.b)) && (!F.fits.size || F.fits.has(p.f)) && p.p <= F.max && (!F.avail || p.a) && (!F.sale || p.cp) && (!q || q.split(/\s+/).every(w => norm(p.t+' '+p.b+' '+catName(p.c)+' '+p.d).includes(w))));
  const s = F.sort;
  if(s==='lo') r.sort((a,b)=>a.p-b.p); else if(s==='hi') r.sort((a,b)=>b.p-a.p); else if(s==='az') r.sort((a,b)=>a.t.localeCompare(b.t)); else if(s==='new') r.sort((a,b)=>a.id-b.id);
  else r.sort((a,b)=> (b.a-a.a) || (b.bs-a.bs) || ((a.c==='ropa'||a.c==='complementos') - (b.c==='ropa'||b.c==='complementos')) || a.id-b.id);
  return r;
}
function renderGrid(){
  const r = filtered();
  $('#count').textContent = `${r.length} producto${r.length===1?'':'s'}${F.cat!=='all'?' en '+catName(F.cat):''}`;
  const show = r.slice(0, F.shown);
  $('#grid').innerHTML = show.length ? show.map(card).join('') : '';
  $('#grid').style.display = show.length ? '' : 'none';
  $('#more').innerHTML = !r.length ? `<div class="empty"><b>No encontramos productos con esos filtros</b>Prueba con otra búsqueda o <button style="text-decoration:underline;font-weight:800" id="resetF">limpia los filtros</button>.</div>`
    : `<small>Viendo ${show.length} de ${r.length}</small><div class="bar"><i style="width:${show.length/r.length*100}%"></i></div>${show.length<r.length?`<button class="btn btn-ghost" id="loadMore">Ver más productos</button>`:''}`;
  const af = [];
  if(F.cat!=='all') af.push(['cat', catName(F.cat)]);
  F.fits.forEach(f => af.push(['fit', f]));
  F.brands.forEach(b => af.push(['brand', b]));
  if(F.max<1399) af.push(['max', 'Hasta '+money(F.max)]);
  if(F.avail) af.push(['avail','Disponibles']);
  if(F.sale) af.push(['sale','En oferta']);
  if(F.q) af.push(['q', '“'+F.q+'”']);
  $('#activeF').innerHTML = af.map(([k,t]) => `<button data-rm="${k}" data-v="${esc(t)}">${esc(t)} <svg class="ic" style="width:14px;height:14px"><use href="#i-x"/></svg></button>`).join('') + (af.length>1?`<button data-rm="all" style="background:none;text-decoration:underline">Limpiar todo</button>`:'');
}
function update(resetShown=true){ if(resetShown) F.shown = 12; renderFilterUI(); renderGrid(); }
function setCat(k, scroll=true){ F.cat = k; F.brands.clear(); F.fits.clear(); F.q=''; $('#q').value=''; update(); if(scroll) document.getElementById('tienda').scrollIntoView({behavior:'smooth'}); }
function setBrand(b){ F.cat='all'; F.brands = new Set([b]); update(); document.getElementById('tienda').scrollIntoView({behavior:'smooth'}); }

document.addEventListener('change', e => {
  const t = e.target;
  if(t.dataset.fit){ t.checked ? F.fits.add(t.dataset.fit) : F.fits.delete(t.dataset.fit); update(); }
  if(t.dataset.fbrand){ t.checked ? F.brands.add(t.dataset.fbrand) : F.brands.delete(t.dataset.fbrand); update(); }
  if(t.id==='fAvail'){ F.avail = t.checked; update(); }
  if(t.id==='fSale'){ F.sale = t.checked; update(); }
  if(t.id==='sort'){ F.sort = t.value; update(); }
});
$('#fPrice').addEventListener('input', e => { F.max = +e.target.value; $('#fPriceV').textContent = money(F.max); update(); });
let qt; $('#q').addEventListener('input', e => { clearTimeout(qt); qt = setTimeout(() => { F.q = e.target.value; update(); }, 160); });
$('#clrFit').onclick = () => { F.fits.clear(); update(); };
$('#clrBrand').onclick = () => { F.brands.clear(); update(); };
function resetAll(){ Object.assign(F,{cat:'all',max:1399,avail:false,sale:false,q:''}); F.brands.clear(); F.fits.clear(); $('#q').value=''; $('#fPrice').value=1399; $('#fPriceV').textContent=money(1399); $('#fAvail').checked=false; $('#fSale').checked=false; update(); }

/* ---------- global clicks ---------- */
document.addEventListener('click', e => {
  const t = e.target.closest('button, .card, .mini, a[data-nav]');
  if(!t) return;
  if(t.dataset.qa){ e.stopPropagation(); const p = P[+t.dataset.qa]; p.o.length ? openProduct(p.id) : addToCart(p.id, '', 1); return; }
  if(t.dataset.fcat){ F.cat = t.dataset.fcat; update(); return; }
  if(t.dataset.cat){ setCat(t.dataset.cat); closeMega(); closeAll(); return; }
  if(t.dataset.brand){ setBrand(t.dataset.brand); closeMega(); return; }
  if(t.dataset.open){ openProduct(+t.dataset.open); return; }
  if(t.dataset.rm){ const k=t.dataset.rm, v=t.dataset.v; if(k==='all') return resetAll(); if(k==='cat')F.cat='all'; if(k==='fit')F.fits.delete(v); if(k==='brand')F.brands.delete(v); if(k==='max'){F.max=1399;$('#fPrice').value=1399;$('#fPriceV').textContent=money(1399);} if(k==='avail'){F.avail=false;$('#fAvail').checked=false;} if(k==='sale'){F.sale=false;$('#fSale').checked=false;} if(k==='q'){F.q='';$('#q').value='';} update(); return; }
  if(t.id==='loadMore'){ F.shown += 12; renderGrid(); return; }
  if(t.id==='resetF'){ resetAll(); return; }
  if(t.matches('a[data-nav]')){ closeAll(); return; }
  if(t.classList.contains('card') || t.classList.contains('mini')){ openProduct(+t.dataset.id); }
});

/* ---------- overlays ---------- */
const scrim = $('#scrim');
function openLayer(el){ closeAll(true); el.classList.add('on'); scrim.classList.add('on'); document.body.style.overflow='hidden'; }
function closeAll(keep){ ['#cart','#mnav','#filters','#pm'].forEach(s => $(s).classList.remove('on')); if(!keep){ scrim.classList.remove('on'); document.body.style.overflow=''; } }
scrim.onclick = () => closeAll();
$$('[data-close]').forEach(b => b.onclick = () => closeAll());
$('#burger').onclick = () => openLayer($('#mnav'));
$('#fOpen').onclick = () => { openLayer($('#filters')); $('#fApply').style.display='flex'; };
$('#fClose').onclick = () => closeAll();
$('#fApply').onclick = () => { closeAll(); document.getElementById('grid').scrollIntoView({behavior:'smooth', block:'start'}); };
document.addEventListener('keydown', e => { if(e.key==='Escape'){ closeAll(); closeSearch(); $('#lb').classList.remove('on'); } });

/* ---------- product modal ---------- */
let cur = null;
function openProduct(id){
  const p = P[id]; cur = {p, sel: p.o.map(() => null), qty:1, k:0};
  if(p.o.length){ const fv = p.v.find(v=>v[2]) || p.v[0]; fv[0].split(' / ').forEach((x,i)=>cur.sel[i]=x); }
  renderPM(); openLayer($('#pm'));
}
function curVariant(){ const p = cur.p; if(!p.o.length) return null; const key = cur.sel.join(' / '); return p.v.find(v => v[0]===key) || null; }
function renderPM(){
  const p = cur.p, v = curVariant(), price = v ? v[1] : p.p, avail = v ? !!v[2] : !!p.a;
  const thumbs = Array.from({length:p.n}, (_,k) => `<button class="${k===cur.k?'on':''}" data-k="${k}"><img src="${img(p,k)}" alt=""></button>`).join('');
  const opts = p.o.map((o,i) => {
    const vals = [...new Set(p.v.map(x => x[0].split(' / ')[i]))];
    return `<div class="ogroup"><h6>${esc(o)}: <span>${esc(cur.sel[i]||'')}</span></h6><div class="ovals">${vals.map(val => { const test = cur.sel.slice(); test[i]=val; const vv = p.v.find(x=>x[0]===test.join(' / ')); return `<button data-oi="${i}" data-ov="${esc(val)}" class="${cur.sel[i]===val?'on':''} ${vv&&!vv[2]?'na':''}">${esc(val)}</button>`; }).join('')}</div></div>`;
  }).join('');
  const waTxt = encodeURIComponent(`Hola Be Cap, quiero apartar para probarme en tienda: ${p.t}${v?' ('+v[0]+')':''} – ${money(price)}. ¿Está disponible?`);
  $('#pmBox').innerHTML = `<button class="mx" data-close aria-label="Cerrar"><svg class="ic"><use href="#i-x"/></svg></button>
  <div class="pm-gal"><div class="pm-main" id="pmMain">${tagsHtml(p)}<img src="${img(p,cur.k)}" alt="${esc(p.t)}" id="pmImg"></div>${p.n>1?`<div class="pm-thumbs">${thumbs}</div>`:''}</div>
  <div class="pm-info">
    <div class="brand">${esc(p.b)} · ${catName(p.c)}</div>
    <h3>${esc(p.t)}</h3>
    <div class="price ${p.cp?'red':''}"><span class="now">${money(price)}</span>${p.cp && !v ? `<s>${money(p.cp)}</s>`:''} <small style="color:var(--muted);font-weight:600">MXN</small></div>
    ${p.d?`<p class="d">${esc(p.d)}${p.d.length>=218?'…':''}</p>`:''}
    ${p.f?`<div style="font-size:13.5px;font-weight:700">Ajuste: <span style="color:var(--muted)">${p.f}</span></div>`:''}
    ${opts}
    <div class="buy"><div class="stepper"><button data-q="-1" aria-label="Menos">−</button><span>${cur.qty}</span><button data-q="1" aria-label="Más">+</button></div>
    <button class="btn ${avail?'btn-ink':'btn-ghost'}" id="pmAdd" ${avail?'':'disabled style="opacity:.5;cursor:not-allowed"'}>${avail?'Agregar al carrito · '+money(price*cur.qty):'Agotado'}</button></div>
    <a class="wa" href="https://wa.me/${WA}?text=${waTxt}" target="_blank" rel="noopener"><svg class="ic"><use href="#i-wa"/></svg> Apártala y pruébatela en tienda</a>
    <div class="perks"><div><svg class="ic"><use href="#i-truck"/></svg>${price*cur.qty>=1000?'Este pedido tiene envío gratis':'Envío gratis a partir de $1,000 MXN'}</div><div><svg class="ic"><use href="#i-store"/></svg>Recoge en tienda: Av. Revolución 503, Pachuca</div></div>
  </div>`;
  $$('[data-close]', $('#pmBox')).forEach(b => b.onclick = () => closeAll());
  $$('.pm-thumbs button').forEach(b => b.onclick = () => { cur.k = +b.dataset.k; $('#pmImg').src = img(p, cur.k); $$('.pm-thumbs button').forEach(x=>x.classList.toggle('on', x===b)); });
  $$('[data-oi]').forEach(b => b.onclick = () => { cur.sel[+b.dataset.oi] = b.dataset.ov; renderPM(); });
  $$('.buy [data-q]').forEach(b => b.onclick = () => { cur.qty = Math.max(1, Math.min(9, cur.qty + +b.dataset.q)); renderPM(); });
  const add = $('#pmAdd'); if(avail) add.onclick = () => { addToCart(p.id, v?v[0]:'', cur.qty); closeAll(); };
  const pm = $('#pmMain');
  pm.onmousemove = e => { const r = pm.getBoundingClientRect(); $('#pmImg').style.transformOrigin = `${(e.clientX-r.left)/r.width*100}% ${(e.clientY-r.top)/r.height*100}%`; $('#pmImg').style.transform='scale(1.8)'; };
  pm.onmouseleave = () => { $('#pmImg').style.transform=''; };
}

/* ---------- cart ---------- */
let cart = store.get('becap_cart', []);
let coMode = false, delivery = 'tienda';
function saveCart(){ store.set('becap_cart', cart); renderCart(); }
function cartCount(){ return cart.reduce((a,c)=>a+c.q,0); }
function cartTotal(){ return cart.reduce((a,c)=>a+c.pr*c.q,0); }
function addToCart(id, vt, q){
  const p = P[id]; const v = vt ? p.v.find(x=>x[0]===vt) : null; const pr = v ? v[1] : p.p;
  const ex = cart.find(c => c.id===id && c.vt===vt);
  ex ? ex.q = Math.min(9, ex.q+q) : cart.push({id, vt, q, pr});
  saveCart(); toast(p, vt);
}
function renderCart(){
  const n = cartCount();
  [$('#cartBadge'), $('#cartBadge2')].forEach(b => { b.textContent = n; b.classList.toggle('on', n>0); });
  const tot = cartTotal(), left = Math.max(0, 1000-tot);
  if(!cart.length){
    coMode = false;
    $('#cartTitle').textContent = 'Tu carrito';
    $('#cartBody').innerHTML = `<div class="cempty"><img src="img/mascota.webp" alt=""><b>Tu carrito está vacío</b>Encuentra la gorra que va contigo.</div>`;
    $('#cartFoot').innerHTML = `<button class="btn btn-ink" data-cat="all">Ir a la tienda</button>`;
    return;
  }
  if(!coMode){
    $('#cartTitle').textContent = `Tu carrito (${n})`;
    $('#cartBody').innerHTML = `<div class="ship ${left?'':'ok'}">${left?`Te faltan <b>${money(left)}</b> para envío gratis`:'¡Tu pedido tiene envío gratis! 🎉'}<div class="bar"><i style="width:${Math.min(100,tot/10)}%"></i></div></div>` +
      cart.map((c,i) => { const p = P[c.id]; return `<div class="citem"><img src="${img(p)}" alt=""><div><h5>${esc(p.t)}</h5><small>${c.vt?esc(c.vt)+' · ':''}${money(c.pr)}</small><div class="stepper"><button data-cq="${i}" data-d="-1" aria-label="Menos">−</button><span>${c.q}</span><button data-cq="${i}" data-d="1" aria-label="Más">+</button></div></div><div><b>${money(c.pr*c.q)}</b><button class="rm" data-crm="${i}">Quitar</button></div></div>`; }).join('');
    $('#cartFoot').innerHTML = `<div class="tot"><span>Subtotal</span><span>${money(tot)} MXN</span></div><button class="btn btn-ink" id="goCO">Finalizar pedido <svg class="ic"><use href="#i-arrow"/></svg></button><button class="btn btn-ghost btn-sm" data-close-cart>Seguir comprando</button>`;
  } else {
    $('#cartTitle').textContent = 'Datos de tu pedido';
    $('#cartBody').innerHTML = `<div class="co">
      <div class="f"><label for="coName">Nombre</label><input id="coName" placeholder="Nombre y apellido" value="${esc(store.get('becap_name',''))}"></div>
      <div class="f"><label>¿Cómo lo quieres recibir?</label><div class="seg"><button data-del="tienda" class="${delivery==='tienda'?'on':''}">Recoger en tienda<small>Av. Revolución 503, Pachuca</small></button><button data-del="envio" class="${delivery==='envio'?'on':''}">Envío a domicilio<small>${tot>=1000?'Gratis en este pedido':'Gratis desde $1,000'}</small></button></div></div>
      ${delivery==='envio'?`<div class="f"><label for="coAddr">Ciudad y código postal</label><input id="coAddr" placeholder="Ej. Querétaro, 76000"></div>`:''}
      <div class="f"><label for="coNote">Notas (opcional)</label><textarea id="coNote" placeholder="Talla, color alterno, horario para recoger…"></textarea></div>
      <div class="ship"><b>Resumen</b><br>${cart.map(c=>`${c.q} × ${esc(P[c.id].t)}${c.vt?' ('+esc(c.vt)+')':''}`).join('<br>')}</div>
    </div>`;
    $('#cartFoot').innerHTML = `<div class="tot"><span>Total productos</span><span>${money(tot)} MXN</span></div><button class="btn" style="background:#25d366;color:#fff" id="sendWA"><svg class="ic"><use href="#i-wa"/></svg> Enviar pedido por WhatsApp</button><button class="btn btn-ghost btn-sm" id="backCart">Volver al carrito</button><p class="demo-note">Te confirmamos disponibilidad y forma de pago por WhatsApp. En la versión final se puede activar el pago en línea con tarjeta.</p>`;
  }
}
$('#cart').addEventListener('click', e => {
  const b = e.target.closest('button'); if(!b) return;
  if(b.dataset.cq){ const c = cart[+b.dataset.cq]; c.q += +b.dataset.d; if(c.q<1) cart.splice(+b.dataset.cq,1); else c.q = Math.min(9,c.q); saveCart(); }
  if(b.dataset.crm){ cart.splice(+b.dataset.crm,1); saveCart(); }
  if(b.id==='goCO'){ coMode = true; renderCart(); }
  if(b.id==='backCart'){ coMode = false; renderCart(); }
  if(b.dataset.del){ const nm = $('#coName')?.value; if(nm) store.set('becap_name', nm); delivery = b.dataset.del; renderCart(); }
  if(b.hasAttribute('data-close-cart')) closeAll();
  if(b.id==='sendWA'){
    const nm = $('#coName').value.trim(); if(!nm){ $('#coName').focus(); $('#coName').style.borderColor='var(--red)'; return; }
    store.set('becap_name', nm);
    const addr = $('#coAddr')?.value.trim() || '', note = $('#coNote').value.trim(), tot = cartTotal();
    const msg = `¡Hola Be Cap! 👋 Quiero hacer este pedido desde su página web:\n\n${cart.map(c=>`• ${c.q} × ${P[c.id].t}${c.vt?' ('+c.vt+')':''} – ${money(c.pr*c.q)}`).join('\n')}\n\nTotal productos: ${money(tot)} MXN\nEntrega: ${delivery==='tienda'?'Recoger en tienda':'Envío a domicilio'+(addr?' – '+addr:'')}${delivery==='envio'?(tot>=1000?' (envío gratis)':''):''}\nNombre: ${nm}${note?'\nNotas: '+note:''}\n\n¿Me confirman disponibilidad y forma de pago?`;
    window.open(`https://wa.me/${WA}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener');
  }
});
$('#openCart').onclick = () => { coMode=false; renderCart(); openLayer($('#cart')); };
$('#mCart').onclick = () => { coMode=false; renderCart(); openLayer($('#cart')); };

let tt;
function toast(p, vt){
  const t = $('#toast');
  t.innerHTML = `<img src="${img(p)}" alt=""><div><b>Agregado al carrito</b><small>${esc(p.t.length>34?p.t.slice(0,32)+'…':p.t)}${vt?' · '+esc(vt):''}</small></div><button id="toastGo">Ver carrito</button>`;
  t.classList.add('on'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('on'), 3200);
  $('#toastGo').onclick = () => { t.classList.remove('on'); $('#openCart').click(); };
}

/* ---------- search overlay ---------- */
const so = $('#so');
function openSearch(){ so.classList.add('on'); document.body.style.overflow='hidden'; setTimeout(()=>$('#soQ').focus(), 50); renderSO(); }
function closeSearch(){ so.classList.remove('on'); if(!$('.drawer.on, .modal.on')) document.body.style.overflow=''; }
$('#openSearch').onclick = openSearch; $('#soClose').onclick = closeSearch;
$('#soSug').innerHTML = ['Yankees','Dodgers','Kiblo','New Era','Woodstock','Hoodie','Calcetas','Gafas'].map(s=>`<button data-s="${s}">${s}</button>`).join('');
$('#soSug').onclick = e => { const b = e.target.closest('[data-s]'); if(b){ $('#soQ').value = b.dataset.s; renderSO(); } };
function renderSO(){
  const q = norm($('#soQ').value.trim());
  const r = q ? P.filter(p => q.split(/\s+/).every(w => norm(p.t+' '+p.b+' '+catName(p.c)).includes(w))) : best.slice(0,8);
  $('#soRes').innerHTML = (q?'':`<p style="grid-column:1/-1;font-weight:800;color:var(--muted)">Populares</p>`) + (r.length ? r.slice(0,12).map(card).join('') : `<p style="grid-column:1/-1;color:var(--muted)">Sin resultados para “${esc($('#soQ').value)}”.</p>`);
}
$('#soQ').addEventListener('input', renderSO);
$('#soQ').addEventListener('keydown', e => { if(e.key==='Enter'){ F.q = $('#soQ').value; $('#q').value = F.q; F.cat='all'; update(); closeSearch(); document.getElementById('tienda').scrollIntoView({behavior:'smooth'}); } });
$('#soRes').addEventListener('click', e => { const c = e.target.closest('.card'); if(c){ closeSearch(); } }, true);

/* ---------- hours / open now ---------- */
const H = [[10.5,19.5],[12,19.5],[12,19.5],[12,19.5],[12,19.5],[12,19.5],[10.5,19.5]]; // dom..sab
const DN = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
const fmt = h => { const hh = Math.floor(h), mm = Math.round((h-hh)*60); const ap = hh>=12?'p.m.':'a.m.'; const h12 = hh>12?hh-12:hh; return `${h12}:${String(mm).padStart(2,'0')} ${ap}`; };
function mxNow(){ const s = new Date().toLocaleString('en-US',{timeZone:'America/Mexico_City'}); return new Date(s); }
function renderHours(){
  const now = mxNow(), d = now.getDay(), h = now.getHours()+now.getMinutes()/60;
  const open = h>=H[d][0] && h<H[d][1];
  $('#hours').innerHTML = [1,2,3,4,5,6,0].map(i => `<tr class="${i===d?'today':''}"><td>${DN[i]}</td><td>${fmt(H[i][0])} – ${fmt(H[i][1])}</td></tr>`).join('');
  let nx = ''; if(!open){ if(h < H[d][0]) nx = `Abrimos hoy a las ${fmt(H[d][0])}`; else { const nd=(d+1)%7; nx = `Abrimos mañana a las ${fmt(H[nd][0])}`; } }
  $('#openNow').innerHTML = open ? `<span style="color:var(--green)">● Abierto ahora</span> · cierra a las ${fmt(H[d][1])}` : `<span style="color:var(--red)">● Cerrado</span> · ${nx}`;
  $('#openPill').innerHTML = `<span class="dot ${open?'':'off'}"></span><span>${open?'Tienda abierta · cierra '+fmt(H[d][1]):'Cerrado · '+nx.replace('Abrimos','abrimos')}</span>`;
}
renderHours(); setInterval(renderHours, 60000);

/* ---------- agenda ---------- */
let slot = null;
const aDate = $('#aDate');
const today = mxNow(); const iso = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
aDate.min = iso(today); aDate.value = iso(today);
function renderSlots(){
  const [y,m,dd] = aDate.value.split('-').map(Number); const dt = new Date(y, m-1, dd); const d = dt.getDay();
  const now = mxNow(); const isToday = iso(now)===aDate.value; const hNow = now.getHours()+now.getMinutes()/60;
  const s = []; for(let h=H[d][0]; h<=H[d][1]-1; h+=1) s.push(h);
  $('#slots').innerHTML = s.map(h => `<button type="button" data-h="${h}" ${isToday && h<hNow+0.5?'disabled':''} class="${slot===h?'on':''}">${fmt(h).replace(':00','').replace(' p.m.','pm').replace(' a.m.','am').replace(':30',':30')}</button>`).join('');
}
aDate.addEventListener('change', () => { slot=null; renderSlots(); });
$('#slots').onclick = e => { const b = e.target.closest('[data-h]'); if(b && !b.disabled){ slot = +b.dataset.h; renderSlots(); } };
renderSlots();
$('#aSend').onclick = () => {
  const nm = $('#aName').value.trim(); if(!nm){ $('#aName').focus(); return; }
  if(slot===null){ $('#slots').animate([{transform:'translateX(-6px)'},{transform:'translateX(6px)'},{transform:'none'}],{duration:300}); return; }
  const [y,m,dd] = aDate.value.split('-').map(Number); const dt = new Date(y,m-1,dd);
  const want = $('#aWant').value.trim() || cart.map(c=>P[c.id].t).join(', ');
  const msg = `¡Hola Be Cap! Quiero agendar una visita a la tienda:\n\nNombre: ${nm}\nDía: ${DN[dt.getDay()]} ${dd}/${m}/${y}\nHora: ${fmt(slot)}\n${want?'Quiero probarme: '+want:''}`;
  window.open(`https://wa.me/${WA}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener');
};

/* ---------- gallery & reviews ---------- */
const G = [['tienda.webp','Fachada de la tienda Be Cap en Pachuca','h2'],['hero.webp','Muro de gorras New Era','w2'],['equipo.webp','Equipo Be Cap en la tienda',''],['ilustracion.webp','Ilustración de la tienda Be Cap',''],['hoodies.webp','Hoodies The North Face','h2'],['p/78-0.webp','New Era Yankees Brown Edition',''],['p/66-0.webp','Kiblo Caps The Fourth Act','w2'],['p/52-0.webp','Snoopy & Woodstock acid wash','']];
$('#gal').innerHTML = G.map(g => `<button class="${g[2]}" data-lb="img/${g[0]}"><img src="img/${g[0]}" alt="${g[1]}" loading="lazy"></button>`).join('');
$('#gal').onclick = e => { const b = e.target.closest('[data-lb]'); if(b){ $('#lbImg').src = b.dataset.lb; $('#lb').classList.add('on'); } };
$('#lbX').onclick = () => $('#lb').classList.remove('on');
$('#lb').onclick = e => { if(e.target.id==='lb') $('#lb').classList.remove('on'); };

const R = [
  ['Karla G.','hace 4 meses','Son muy atentos, te orientan a elegir la gorra que mejor te queda, además tienen bastante variedad de marcas de gorras y sudaderas 😃','#c8202f'],
  ['Betsaida D.','Local Guide · hace 9 meses','La atención es super buena y la chica que me atendió es super agradable, los productos que venden son de muy buena calidad y vale la pena cien por ciento ✨','#1f4a32'],
  ['Dulce D.','hace 3 semanas','La atención es increíble, son muy atentos y te brindan ayuda en caso de que sea necesaria, he comprado muchísimas gorras para mi chico.','#2742a8'],
  ['Brayan G.','hace 9 meses','Excelentes productos y gran variedad. La vendedora Vanesa me brindó super buena atención, muy paciente y servicial.','#5a3b27'],
  ['Erick T.','hace 3 meses','Muy buena atención, la chica que me atendió es muy amable y atenta. Muy recomendable por si quieres gorras o lentes.','#6d1f2a'],
  ['Eduardo A.','hace 4 meses','Gran compra y el servicio excelente, recomendando venir a adquirir sus gorras aquí!','#0f0e0c'],
];
$('#revs').innerHTML = R.map(r => `<div class="rev"><div class="stars">★★★★★</div><p>“${r[2]}”</p><div class="who"><span class="av" style="background:${r[3]}">${r[0][0]}</span><div><b>${r[0]}</b><small>${r[1]} · Google</small></div><svg class="ic" style="margin-left:auto"><use href="#i-google"/></svg></div></div>`).join('');

/* ---------- reveal ---------- */
const io = new IntersectionObserver(es => es.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } }), {rootMargin:'0px 0px -8% 0px'});
$$('.reveal').forEach(el => io.observe(el));

update(); renderCart();
window.__becap = { addToCart, openProduct };
})();
