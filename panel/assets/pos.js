// Punto de venta: catálogo, ticket, cambio y cobro
(function(){
  var $ = function(s){ return document.querySelector(s); };
  var money = function(n){ return '$' + (Math.round(n * 100) / 100).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
  var esc = function(s){ return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); };
  var norm = function(s){ return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var P = []; try { P = JSON.parse($('#posData').textContent); } catch (e) {}
  var INV = {}; P.forEach(function(p){ p.v.forEach(function(v){ INV[v.inv] = {p: p, v: v}; }); });
  var ticket = [], metodo = 'efectivo';

  function stockTxt(v){ return v.s === null ? (v.d ? 'disponible' : 'no disponible') : (v.s > 0 ? v.s + ' en existencia' : 'agotado'); }
  function render(){
    var q = norm($('#posQ').value.trim()), cat = $('#posCat').value;
    var r = P.filter(function(p){ return (!cat || p.c === cat) && (!q || q.split(/\s+/).every(function(w){ return norm(p.t + ' ' + p.b + ' ' + p.v.map(function(v){ return v.v; }).join(' ')).indexOf(w) > -1; })); });
    $('#posGrid').innerHTML = r.slice(0, 120).map(function(p){
      var min = Math.min.apply(null, p.v.map(function(v){ return v.p; }));
      var s = p.v.reduce(function(a, v){ return a + (v.s || 0); }, 0), cap = p.v.some(function(v){ return v.s !== null; });
      var ago = cap && s <= 0;
      return '<button type="button" class="pitem' + (ago ? ' agotado' : '') + '" data-p="' + p.id + '"><img src="' + esc(p.img) + '" alt="" loading="lazy"><b>' + esc(p.t) + '</b><span>' + money(min) + (p.v.length > 1 ? ' · ' + p.v.length + ' opciones' : '') + '</span><span>' + (cap ? (s > 0 ? s + ' pzs' : 'Agotado') : 'Sin capturar') + '</span></button>';
    }).join('') || '<p class="muted">Sin resultados.</p>';
  }
  function add(inv){
    var l = ticket.find(function(x){ return x.inv === inv; });
    if (l) l.q++; else ticket.push({inv: inv, q: 1});
    pintar();
  }
  function pintar(){
    var sub = 0;
    $('#tLines').innerHTML = ticket.length ? ticket.map(function(l, i){
      var it = INV[l.inv], imp = it.v.p * l.q; sub += imp;
      var aviso = it.v.s !== null && l.q > it.v.s ? ' <small style="color:var(--red)">· solo hay ' + Math.max(0, it.v.s) + '</small>' : '';
      return '<div class="tline"><span><b>' + esc(it.p.t) + '</b>' + (it.v.v ? ' <small>' + esc(it.v.v) + '</small>' : '') + aviso + '</span><span class="r">' + money(imp) + '</span><span class="q"><button type="button" data-d="-1" data-i="' + i + '" aria-label="Menos">−</button><b>' + l.q + '</b><button type="button" data-d="1" data-i="' + i + '" aria-label="Más">+</button><small>× ' + money(it.v.p) + '</small></span></div>';
    }).join('') : '<p class="muted sm">Agrega productos tocándolos en la lista.</p>';
    var d = Math.min(sub, parseFloat($('#posDesc').value) || 0), tot = Math.max(0, sub - d);
    $('#posTot').textContent = money(tot);
    var rec = parseFloat($('#posRec').value) || 0;
    $('#posCambio').textContent = money(Math.max(0, rec - tot));
    $('#cobrar').disabled = !ticket.length || (metodo === 'efectivo' && rec > 0 && rec < tot);
    $('#cobrar').textContent = ticket.length ? 'Cobrar ' + money(tot) : 'Cobrar';
    $('#carrito').value = JSON.stringify(ticket);
  }
  $('#posGrid').addEventListener('click', function(e){
    var b = e.target.closest('[data-p]'); if (!b) return;
    var p = P.find(function(x){ return x.id === +b.dataset.p; });
    if (p.v.length === 1) { add(p.v[0].inv); return; }
    $('#vpT').textContent = p.t;
    $('#vpGrid').innerHTML = p.v.map(function(v){ return '<button type="button" data-inv="' + v.inv + '"' + (v.s !== null && v.s <= 0 ? ' disabled' : '') + '><b>' + esc(v.v) + '</b><small>' + money(v.p) + ' · ' + stockTxt(v) + '</small></button>'; }).join('');
    $('#vpick').classList.remove('hidden');
    var f = $('#vpGrid button:not([disabled])'); if (f) f.focus();
  });
  $('#vpGrid').addEventListener('click', function(e){ var b = e.target.closest('[data-inv]'); if (b && !b.disabled){ add(+b.dataset.inv); $('#vpick').classList.add('hidden'); } });
  $('#vpX').onclick = function(){ $('#vpick').classList.add('hidden'); };
  $('#vpick').addEventListener('click', function(e){ if (e.target.id === 'vpick') $('#vpick').classList.add('hidden'); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') $('#vpick').classList.add('hidden'); });
  $('#tLines').addEventListener('click', function(e){
    var b = e.target.closest('[data-i]'); if (!b) return;
    var l = ticket[+b.dataset.i]; l.q += +b.dataset.d;
    if (l.q < 1) ticket.splice(+b.dataset.i, 1);
    pintar();
  });
  $('#vaciar').onclick = function(){ ticket = []; pintar(); };
  document.querySelectorAll('.paybtns button').forEach(function(b){ b.onclick = function(){
    metodo = b.dataset.m; $('#metodo').value = metodo;
    document.querySelectorAll('.paybtns button').forEach(function(x){ x.classList.toggle('on', x === b); });
    $('#efBox').classList.toggle('hidden', metodo !== 'efectivo'); pintar();
  }; });
  ['#posQ', '#posCat'].forEach(function(s){ $(s).addEventListener('input', render); });
  ['#posDesc', '#posRec'].forEach(function(s){ $(s).addEventListener('input', pintar); });
  $('#posQ').addEventListener('keydown', function(e){ if (e.key === 'Enter'){ e.preventDefault(); var f = $('#posGrid [data-p]'); if (f) f.click(); } });
  $('#posForm').addEventListener('submit', function(e){ if (!ticket.length) e.preventDefault(); else $('#cobrar').disabled = true; });
  render(); pintar();
  $('#posQ').focus();
})();
