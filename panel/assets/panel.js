(function(){
  var $ = function(s, c){ return (c || document).querySelector(s); };
  var $$ = function(s, c){ return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var money = function(n){ return '$' + (Math.round(n * 100) / 100).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

  // menú en celular
  var side = $('#side'), scrim = $('#scrim');
  if ($('#burger')) $('#burger').onclick = function(){ side.classList.add('on'); scrim.classList.add('on'); };
  if (scrim) scrim.onclick = function(){ side.classList.remove('on'); scrim.classList.remove('on'); };

  // filas que abren el detalle
  $$('tr[data-href]').forEach(function(tr){ tr.addEventListener('click', function(e){ if (!e.target.closest('a,button,form,input')) location.href = tr.dataset.href; }); });

  // confirmaciones
  $$('form[data-confirm]').forEach(function(f){ f.addEventListener('submit', function(e){ if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });

  // mostrar / ocultar bloques
  $$('[data-open]').forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); var t = document.getElementById(b.dataset.open); if (t){ t.classList.toggle('hidden'); var i = t.querySelector('input:not([type=hidden]),select'); if (i && !t.classList.contains('hidden')) i.focus(); } }); });

  // cliente nuevo solo si no se eligió uno existente
  var cs = $('#cliSel'), cn = $('#cliNuevo');
  if (cs && cn){ var t = function(){ cn.classList.toggle('hidden', cs.value !== '0'); }; cs.addEventListener('change', t); t(); }

  // dirección de envío solo si aplica
  var es = $('#entregaSel'), eb = $('.envio-box');
  if (es && eb){ var te = function(){ eb.classList.toggle('hidden', es.value !== 'envio'); }; es.addEventListener('change', te); te(); }

  // editor de producto: variantes y fotos
  var pf = $('#prodForm');
  if (pf){
    var op1 = $('#op1'), op2 = $('#op2'), vars = $('#vars'), n = 1000;
    var tog = function(){ var con = (op1.value.trim() || op2.value.trim()) !== ''; $('#conVar').classList.toggle('hidden', !con); $('#sinVar').classList.toggle('hidden', con); };
    op1.addEventListener('input', tog); op2.addEventListener('input', tog);
    var fila = function(nombre){
      var i = n++, d = document.createElement('div'); d.className = 'vr';
      d.innerHTML = '<input type="hidden" name="v_id[' + i + ']" value=""><input name="v_nombre[' + i + ']" required><input name="v_precio[' + i + ']" type="number" min="0" step="0.01" placeholder="Igual al producto"><input name="v_stock[' + i + ']" type="number" step="1" placeholder="Inicial"><label class="check"><input type="checkbox" name="v_disp[]" value="' + i + '" checked></label><button type="button" class="x" data-quitar-v aria-label="Quitar variante">×</button>';
      d.querySelector('[name^=v_nombre]').value = nombre || '';
      vars.appendChild(d); return d;
    };
    $('#addVar').onclick = function(){ fila('').querySelector('[name^=v_nombre]').focus(); };
    $('#genBtn').onclick = function(){
      var a = $('#gen1').value.split(',').map(function(x){ return x.trim(); }).filter(Boolean);
      var b = $('#gen2').value.split(',').map(function(x){ return x.trim(); }).filter(Boolean);
      var ya = $$('[name^=v_nombre]', vars).map(function(i){ return i.value; });
      (a.length ? a : ['']).forEach(function(x){ (b.length ? b : ['']).forEach(function(y){ var nm = [x, y].filter(Boolean).join(' / '); if (nm && ya.indexOf(nm) < 0) fila(nm); }); });
    };
    vars.addEventListener('click', function(e){ if (e.target.matches('[data-quitar-v]')) vars.removeChild(e.target.closest('.vr')); });
    var fin = $('#fotosIn'), prev = $('#fotosPrev');
    if (fin) fin.addEventListener('change', function(){
      prev.innerHTML = '';
      Array.prototype.forEach.call(fin.files, function(f){ var fig = document.createElement('figure'), im = document.createElement('img'); im.src = URL.createObjectURL(f); im.alt = ''; fig.appendChild(im); var c = document.createElement('figcaption'); c.textContent = 'Nueva'; fig.appendChild(c); prev.appendChild(fig); });
    });
  }

  // editor de partidas
  var items = $('#items');
  if (!items) return;
  var cat = []; try { cat = JSON.parse($('#catData').textContent); } catch (e) {}
  var porDesc = {}; cat.forEach(function(o){ porDesc[o.d] = o; });
  function calc(){
    var sub = 0;
    $$('.it:not(.it-h)', items).forEach(function(r){
      var c = parseFloat(r.querySelector('[name="it_c[]"]').value) || 0, p = parseFloat(r.querySelector('[name="it_p[]"]').value) || 0;
      r.querySelector('output').textContent = money(c * p); sub += c * p;
    });
    var d = parseFloat(($('[name=descuento]') || {}).value) || 0, en = parseFloat(($('[name=envio]') || {}).value) || 0;
    $('#tSub').textContent = money(sub); $('#tTot').textContent = money(Math.max(0, sub - d + en));
  }
  items.addEventListener('input', function(e){
    if (e.target.name === 'it_d[]'){
      var r = e.target.closest('.it'), o = porDesc[e.target.value];
      r.querySelector('[name="it_id[]"]').value = o ? o.id : '';
      r.querySelector('[name="it_inv[]"]').value = o ? o.inv : '';
      if (o) r.querySelector('[name="it_p[]"]').value = o.p;
    }
    calc();
  });
  items.addEventListener('click', function(e){
    if (!e.target.matches('[data-quitar]')) return;
    var filas = $$('.it:not(.it-h)', items);
    var r = e.target.closest('.it');
    if (filas.length > 1) r.remove(); else $$('input', r).forEach(function(i){ i.value = i.name === 'it_c[]' ? 1 : (i.name === 'it_p[]' ? 0 : ''); });
    calc();
  });
  $('#addItem').onclick = function(){
    var filas = $$('.it:not(.it-h)', items), n = filas[filas.length - 1].cloneNode(true);
    $$('input', n).forEach(function(i){ i.value = i.name === 'it_c[]' ? 1 : (i.name === 'it_p[]' ? 0 : ''); });
    items.appendChild(n); n.querySelector('[name="it_d[]"]').focus(); calc();
  };
  $$('[name=descuento],[name=envio]').forEach(function(i){ i.addEventListener('input', calc); });
  calc();
})();
