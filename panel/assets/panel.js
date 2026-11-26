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
