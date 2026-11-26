<?php
// Punto de venta para el local: cobra rápido, descuenta inventario y registra la venta y el pago

/* ---------- cobrar ---------- */
if (es_post() && ($_POST['accion'] ?? '') === 'cobrar') {
  $carrito = json_decode((string)($_POST['carrito'] ?? '[]'), true);
  $items = [];
  foreach (array_slice(is_array($carrito) ? $carrito : [], 0, 60) as $c) {
    $inv = q1('SELECT i.*, p.eliminado FROM inventario i JOIN productos p ON p.id=i.producto_web_id WHERE i.id=? AND i.activo=1', [(int)($c['inv'] ?? 0)]);
    $cant = max(1, min(999, (int)($c['q'] ?? 1)));
    if (!$inv || $inv['eliminado']) continue;
    $items[] = ['producto_web_id' => (int)$inv['producto_web_id'], 'inventario_id' => (int)$inv['id'], 'descripcion' => mb_substr($inv['descripcion'], 0, 200), 'cantidad' => $cant, 'precio' => (float)$inv['precio'], 'importe' => round($cant * (float)$inv['precio'], 2)];
  }
  if (!$items) { flash('El ticket está vacío.', 'err'); redirect('pos'); }
  $metodo = in_array($_POST['metodo'] ?? '', ['efectivo', 'tarjeta', 'transferencia'], true) ? $_POST['metodo'] : 'efectivo';
  $sub = array_sum(array_column($items, 'importe'));
  $desc = min($sub, max(0, num($_POST['descuento'] ?? 0)));
  $total = round($sub - $desc, 2);
  $cli = (int)($_POST['cliente_id'] ?? 0) ?: null;
  if ($cli && !qv('SELECT id FROM clientes WHERE id=?', [$cli])) $cli = null;
  $recibido = $metodo === 'efectivo' ? max($total, num($_POST['recibido'] ?? 0)) : $total;
  $pdo = db(); $pdo->beginTransaction();
  q("INSERT INTO ventas (cliente_id, fecha, origen, estado, entrega, entregada, descuento, notas, usuario_id, creado, actualizado) VALUES (?,?,'tienda','confirmada','tienda',1,?,?,?,?,?)",
    [$cli, ahora(), $desc, 'Punto de venta' . ($metodo === 'efectivo' && $recibido > $total ? ' · recibió ' . money($recibido) . ', cambio ' . money($recibido - $total) : ''), usuario()['id'], ahora(), ahora()]);
  $id = (int)$pdo->lastInsertId();
  guardar_partidas('venta', $id, $items);
  q('UPDATE ventas SET subtotal=?, total=?, pagado=? WHERE id=?', [$sub, $total, $total, $id]);
  if ($total > 0) q('INSERT INTO cobros (venta_id, fecha, monto, metodo, referencia, usuario_id, creado) VALUES (?,?,?,?,?,?,?)', [$id, ahora(), $total, $metodo, 'Punto de venta', usuario()['id'], ahora()]);
  $neg = venta_sincronizar_stock($id);
  $pdo->commit();
  if ($neg) flash('Venta cobrada. Ojo: quedó inventario en negativo de ' . implode(', ', $neg) . '.', 'err');
  redirect('pos', ['ticket' => $id, 'r' => $metodo === 'efectivo' ? $recibido : null]);
}

/* ---------- ticket ---------- */
if (isset($_GET['ticket'])) {
  $v = q1('SELECT v.*, c.nombre cliente, u.nombre cajero FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id LEFT JOIN usuarios u ON u.id=v.usuario_id WHERE v.id=?', [(int)$_GET['ticket']]);
  if (!$v) redirect('pos');
  $its = partidas('venta', (int)$v['id']);
  $pago = q1('SELECT metodo FROM cobros WHERE venta_id=? ORDER BY id LIMIT 1', [$v['id']]);
  $rec = isset($_GET['r']) ? num($_GET['r']) : 0;
  $hoja = function () use ($v, $its, $pago, $rec) { ?>
    <div class="tk" id="ticket">
      <img src="../img/logo.webp" alt="Be Cap">
      <p class="c">Be Cap Gorras &amp; Accesorios<br>Av. Revolución 503, Local B, Pachuca, Hgo.<br>WhatsApp 771 288 4753</p>
      <hr>
      <p>Ticket <?= e(folio('V', $v['id'])) ?><br><?= e(date('d/m/Y g:i a', strtotime($v['fecha']))) ?><br>Atendió: <?= e($v['cajero'] ?: '—') ?><?= $v['cliente'] ? '<br>Cliente: ' . e($v['cliente']) : '' ?></p>
      <hr>
      <table><?php foreach ($its as $it): ?><tr><td colspan="2"><?= e($it['descripcion']) ?></td></tr><tr><td><?= (float)$it['cantidad'] ?> x <?= money($it['precio']) ?></td><td style="text-align:right"><?= money($it['importe']) ?></td></tr><?php endforeach; ?></table>
      <hr>
      <table>
        <?php if ((float)$v['descuento'] > 0): ?><tr><td>Subtotal</td><td style="text-align:right"><?= money($v['subtotal']) ?></td></tr><tr><td>Descuento</td><td style="text-align:right">-<?= money($v['descuento']) ?></td></tr><?php endif; ?>
        <tr><td><b>TOTAL</b></td><td style="text-align:right"><b><?= money($v['total']) ?></b></td></tr>
        <tr><td>Pago</td><td style="text-align:right"><?= e(METODOS[$pago['metodo'] ?? 'efectivo']) ?></td></tr>
        <?php if ($rec > (float)$v['total']): ?><tr><td>Recibido</td><td style="text-align:right"><?= money($rec) ?></td></tr><tr><td><b>Cambio</b></td><td style="text-align:right"><b><?= money($rec - (float)$v['total']) ?></b></td></tr><?php endif; ?>
      </table>
      <hr><p class="c">¡Gracias por tu compra!<br>Síguenos y compra en línea en nuestra página.</p>
    </div>
  <?php };
  if (isset($_GET['imprimir'])) {
    ?><!doctype html><html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ticket <?= e(folio('V', $v['id'])) ?></title><link rel="stylesheet" href="assets/panel.css?v=<?= PANEL_VERSION ?>"></head><body style="background:#fff"><?php $hoja(); ?><script>window.print()</script></body></html><?php
    return;
  }
  layout_inicio('Venta cobrada', 'pos');
  ?>
  <div class="head"><div><a class="back" href="<?= url('pos') ?>">← Punto de venta</a><h1>Venta cobrada</h1><p class="muted">Se registró en Ventas, se descontó del inventario y quedó pagada.</p></div>
    <div class="acts"><a class="btn ghost" target="_blank" href="<?= url('pos', ['ticket' => $v['id'], 'imprimir' => 1, 'r' => $rec ?: null]) ?>">Imprimir ticket</a><a class="btn ghost" href="<?= url('venta', ['id' => $v['id']]) ?>">Ver la venta</a><a class="btn big" href="<?= url('pos') ?>" autofocus>Nueva venta</a></div></div>
  <?php if ($rec > (float)$v['total']): ?><div class="kpis k3"><div class="kpi"><small>Total</small><b><?= money($v['total']) ?></b></div><div class="kpi"><small>Recibido</small><b><?= money($rec) ?></b></div><div class="kpi good"><small>Cambio</small><b><?= money($rec - (float)$v['total']) ?></b></div></div><?php endif; ?>
  <div class="card"><?php $hoja(); ?></div>
  <?php layout_fin();
  return;
}

/* ---------- pantalla de cobro ---------- */
$prods = [];
foreach (catalogo() as $p) {
  $vs = [];
  foreach ($p['variantes'] as $i) $vs[] = ['inv' => (int)$i['id'], 'v' => (string)($i['variante'] ?? ''), 'p' => (float)$i['precio'], 's' => $i['stock'] === null ? null : (int)$i['stock'], 'd' => disponible($i) ? 1 : 0];
  if (!$vs) continue;
  $prods[] = ['id' => (int)$p['id'], 't' => $p['nombre'], 'c' => $p['categoria'], 'b' => $p['marca'], 'img' => $p['imgs'] ? img_panel($p['imgs'][0]) : '../img/logo.webp', 'v' => $vs];
}
$hoy = date('Y-m-d');
$corte = q("SELECT co.metodo, COUNT(*) n, SUM(co.monto) t FROM cobros co JOIN ventas v ON v.id=co.venta_id WHERE v.estado<>'cancelada' AND DATE(co.fecha)=? GROUP BY co.metodo", [$hoy])->fetchAll();
$corte_pos = q1("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE estado='confirmada' AND DATE(fecha)=? AND notas LIKE 'Punto de venta%'", [$hoy]);
$por_usuario = q("SELECT u.nombre, COUNT(*) n, SUM(v.total) t FROM ventas v LEFT JOIN usuarios u ON u.id=v.usuario_id WHERE v.estado='confirmada' AND DATE(v.fecha)=? AND v.notas LIKE 'Punto de venta%' GROUP BY v.usuario_id", [$hoy])->fetchAll();
layout_inicio('Punto de venta', 'pos');
?>
<div class="head"><div><h1>Punto de venta</h1><p class="muted">Toca un producto para agregarlo al ticket. Al cobrar se registra la venta, el pago y se descuenta del inventario.</p></div>
  <div class="acts"><button class="btn ghost" data-open="corteBox">Corte de caja de hoy</button></div></div>

<div class="card hidden" id="corteBox">
  <h3>Corte de caja · <?= e(fecha_mx($hoy)) ?></h3>
  <div class="corte">
    <div class="kpi"><small>Ventas en mostrador</small><b><?= money($corte_pos['t']) ?></b><span><?= (int)$corte_pos['n'] ?> tickets</span></div>
    <?php foreach (METODOS as $k => $lbl): $r = array_values(array_filter($corte, fn($c) => $c['metodo'] === $k))[0] ?? null; if (!$r && !in_array($k, ['efectivo', 'tarjeta', 'transferencia'], true)) continue; ?>
      <div class="kpi <?= $k === 'efectivo' ? 'good' : '' ?>"><small><?= e($lbl) ?></small><b><?= money($r['t'] ?? 0) ?></b><span><?= (int)($r['n'] ?? 0) ?> pagos (todos los canales)</span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($por_usuario): ?><p class="sm muted mt">Por persona: <?= e(implode(' · ', array_map(fn($u) => ($u['nombre'] ?: '—') . ' ' . money($u['t']) . ' (' . $u['n'] . ')', $por_usuario))) ?></p><?php endif; ?>
  <p class="sm muted">El efectivo es lo que debe haber en caja por las ventas de hoy, sin contar el fondo inicial.</p>
</div>

<div class="pos">
  <section class="pos-cat">
    <div class="pos-bar">
      <input id="posQ" type="search" placeholder="Buscar producto, marca o talla…" autocomplete="off" aria-label="Buscar producto">
      <select id="posCat" aria-label="Categoría"><option value="">Todas</option><?= select_opts(CATS, '') ?></select>
    </div>
    <div class="pos-grid" id="posGrid"></div>
  </section>

  <form method="post" class="ticket" id="posForm">
    <?= csrf_field() ?><input type="hidden" name="accion" value="cobrar"><input type="hidden" name="carrito" id="carrito"><input type="hidden" name="metodo" id="metodo" value="efectivo">
    <h3>Ticket <button type="button" class="link sm" id="vaciar">Vaciar</button></h3>
    <div class="tlines" id="tLines"><p class="muted sm" id="tVacio">Agrega productos tocándolos en la lista.</p></div>
    <label>Cliente (opcional)
      <select name="cliente_id"><option value="0">Público en general</option><?php foreach (clientes_opciones() as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?></select>
    </label>
    <label>Descuento en pesos <input name="descuento" id="posDesc" type="number" min="0" step="1" value="0" inputmode="decimal"></label>
    <div class="ttot"><span>Total</span><span id="posTot">$0.00</span></div>
    <div class="paybtns" role="group" aria-label="Forma de pago"><button type="button" data-m="efectivo" class="on">Efectivo</button><button type="button" data-m="tarjeta">Tarjeta</button><button type="button" data-m="transferencia">Transferencia</button></div>
    <div id="efBox">
      <label>Recibido <input name="recibido" id="posRec" type="number" min="0" step="1" inputmode="decimal" placeholder="¿Con cuánto paga?"></label>
      <div class="cambio mt"><span>Cambio</span><span id="posCambio">$0.00</span></div>
    </div>
    <button class="btn big" id="cobrar" disabled>Cobrar</button>
  </form>
</div>

<div class="vpick hidden" id="vpick" role="dialog" aria-modal="true" aria-labelledby="vpT"><div class="box"><h3 id="vpT">Elige la variante</h3><div class="vgrid" id="vpGrid"></div><button type="button" class="btn ghost" id="vpX">Cancelar</button></div></div>
<script type="application/json" id="posData"><?= json_encode($prods, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="assets/pos.js?v=<?= PANEL_VERSION ?>"></script>
<?php layout_fin();
