/* Be Cap · conexión de la tienda con el panel de control.
   Cuando un cliente manda su pedido o agenda una visita por WhatsApp,
   también se registra en el panel como "por confirmar". No cambia nada de lo que ve el cliente. */
(function(){
  var API = 'panel/api/web.php';
  var get = function(k, d){ try{ var v = JSON.parse(localStorage.getItem(k)); return v == null ? d : v; }catch(e){ return d; } };
  var val = function(id){ var el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; };
  function enviar(data){
    try{
      var body = JSON.stringify(data);
      if(navigator.sendBeacon && navigator.sendBeacon(API, new Blob([body], {type:'application/json'}))) return;
      fetch(API, {method:'POST', body: body, headers:{'Content-Type':'application/json'}, keepalive:true}).catch(function(){});
    }catch(e){}
  }
  function pedido(){
    var on = document.querySelector('[data-del].on');
    var entrega = on ? on.getAttribute('data-del') : 'tienda';
    var ad = get('becap_addr', {}) || {};
    var dir = entrega === 'envio' ? [
      (ad.calle||'') + ' ' + (ad.ext||'') + (ad.int ? ' Int. ' + ad.int : ''),
      'Col. ' + (ad.col||'') + ', C.P. ' + (ad.cp||''),
      (ad.mun||'') + ', ' + (ad.edo||''),
      ad.ref ? 'Referencias: ' + ad.ref : ''
    ].filter(Boolean).join('\n') : '';
    enviar({ tipo:'pedido', nombre: val('coName'), telefono: entrega === 'envio' ? (ad.tel||'') : '', entrega: entrega, direccion: dir, notas: val('coNote'),
      items: (get('becap_cart', []) || []).map(function(c){ return { id:c.id, vt:c.vt||'', q:c.q }; }) });
  }
  function cita(){
    var s = document.querySelector('#slots button.on');
    if(!s) return;
    var h = parseFloat(s.getAttribute('data-h')), hh = Math.floor(h), mm = Math.round((h-hh)*60);
    var motivo = val('aWant');
    if(!motivo && window.__P){ motivo = (get('becap_cart', []) || []).map(function(c){ return (window.__P[c.id]||{}).t; }).filter(Boolean).join(', '); }
    enviar({ tipo:'cita', nombre: val('aName'), fecha: val('aDate'), hora: hh + ':' + (mm < 10 ? '0' : '') + mm, motivo: motivo });
  }
  var abrir = window.open;
  window.open = function(url){
    try{
      if(typeof url === 'string' && url.indexOf('https://wa.me/') === 0){
        var t = decodeURIComponent((url.split('text=')[1] || '').replace(/\+/g, ' '));
        if(t.indexOf('Quiero hacer este pedido') > -1) pedido();
        else if(t.indexOf('Quiero agendar una visita') > -1) cita();
      }
    }catch(e){}
    return abrir.apply(window, arguments);
  };
})();
