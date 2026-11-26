<?php
function venta_recalcular(int $id): void {
  $pag = (float)qv('SELECT COALESCE(SUM(monto),0) FROM cobros WHERE venta_id=?', [$id]);
  q('UPDATE ventas SET pagado=?, actualizado=? WHERE id=?', [$pag, ahora(), $id]);
}

/* ======================= DETALLE / EDICIÓN ======================= */
if ($p === 'venta') {
  $id = (int)($_GET['id'] ?? 0);
  $v = $id ? q1('SELECT * FROM ventas WHERE id=?', [$id]) : null;
  if ($id && !$v) { flash('Esa venta no existe.', 'err'); redirect('ventas'); }

  if (es_post()) {
    $acc = $_POST['accion'] ?? '';
    if ($acc === 'guardar') {
      $items = leer_partidas();
      if (!$items) { flash('Agrega al menos un producto.', 'err'); redirect('venta', $id ? ['id' => $id] : ['nueva' => 1]); }
      $cli = cliente_de_form();
      $fecha = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)($_POST['fecha'] ?? '')) ? str_replace('T', ' ', $_POST['fecha']) . ':00' : ahora();
      $origen = array_key_exists($_POST['origen'] ?? '', ORIGENES) ? $_POST['origen'] : 'tienda';
      $entrega = ($_POST['entrega'] ?? '') === 'envio' ? 'envio' : 'tienda';
      $desc = max(0, num($_POST['descuento'] ?? 0)); $env = max(0, num($_POST['envio'] ?? 0));
      $campos = [$cli, $fecha, $origen, $entrega, txt($_POST['direccion'] ?? '', 600) ?: null, txt($_POST['guia'] ?? '', 80) ?: null, $desc, $env, txt($_POST['notas'] ?? '', 2000) ?: null];
      if (!$id) {
        $estado = ($_POST['estado'] ?? '') === 'por_confirmar' ? 'por_confirmar' : 'confirmada';
        q('INSERT INTO ventas (cliente_id, fecha, origen, entrega, direccion, guia, descuento, envio, notas, estado, cotizacion_id, usuario_id, creado, actualizado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          array_merge($campos, [$estado, ((int)($_POST['cotizacion_id'] ?? 0)) ?: null, usuario()['id'], ahora(), ahora()]));
        $id = (int)db()->lastInsertId();
      } else {
        q('UPDATE ventas SET cliente_id=?, fecha=?, origen=?, entrega=?, direccion=?, guia=?, descuento=?, envio=?, notas=?, actualizado=? WHERE id=?', array_merge($campos, [ahora(), $id]));
      }
      $sub = guardar_partidas('venta', $id, $items);
      q('UPDATE ventas SET subtotal=?, total=? WHERE id=?', [$sub, max(0, round($sub - $desc + $env, 2)), $id]);
      if ($cid = (int)($_POST['cotizacion_id'] ?? 0)) q("UPDATE cotizaciones SET estado='aceptada', venta_id=?, actualizado=? WHERE id=?", [$id, ahora(), $cid]);
      // cobro inicial opcional
      $m = num($_POST['cobro_inicial'] ?? 0);
      if ($m > 0) { q('INSERT INTO cobros (venta_id, fecha, monto, metodo, usuario_id, creado) VALUES (?,?,?,?,?,?)', [$id, ahora(), $m, array_key_exists($_POST['metodo_inicial'] ?? '', METODOS) ? $_POST['metodo_inicial'] : 'efectivo', usuario()['id'], ahora()]); venta_recalcular($id); }
      flash('Venta guardada.');
    } elseif ($id && $acc === 'estado' && array_key_exists($_POST['estado'] ?? '', ESTADOS_VENTA)) {
      q('UPDATE ventas SET estado=?, actualizado=? WHERE id=?', [$_POST['estado'], ahora(), $id]);
      flash('Estado actualizado: ' . ESTADOS_VENTA[$_POST['estado']] . '.');
    } elseif ($id && $acc === 'entregada') {
      q('UPDATE ventas SET entregada=1-entregada, actualizado=? WHERE id=?', [ahora(), $id]);
      flash('Entrega actualizada.');
    } elseif ($id && $acc === 'cobro') {
      $m = num($_POST['monto'] ?? 0);
      if ($m <= 0) flash('Escribe el monto del pago.', 'err');
      else {
        $f = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['fecha_cobro'] ?? '')) ? $_POST['fecha_cobro'] . date(' H:i:s') : ahora();
        q('INSERT INTO cobros (venta_id, fecha, monto, metodo, referencia, usuario_id, creado) VALUES (?,?,?,?,?,?,?)',
          [$id, $f, $m, array_key_exists($_POST['metodo'] ?? '', METODOS) ? $_POST['metodo'] : 'efectivo', txt($_POST['referencia'] ?? '', 80) ?: null, usuario()['id'], ahora()]);
        venta_recalcular($id);
        flash('Pago registrado: ' . money($m) . '.');
      }
    } elseif ($id && $acc === 'borrar_cobro' && es_admin()) {
      q('DELETE FROM cobros WHERE id=? AND venta_id=?', [(int)($_POST['cobro_id'] ?? 0), $id]);
      venta_recalcular($id);
      flash('Pago eliminado.');
    } elseif ($id && $acc === 'eliminar' && es_admin()) {
      q('DELETE FROM partidas WHERE doc=\'venta\' AND doc_id=?', [$id]);
      q('DELETE FROM cobros WHERE venta_id=?', [$id]);
      q('UPDATE cotizaciones SET venta_id=NULL WHERE venta_id=?', [$id]);
      q('DELETE FROM ventas WHERE id=?', [$id]);
      flash('Venta eliminada.');
      redirect('ventas');
    }
    redirect('venta', ['id' => $id]);
  }

  // Datos para el formulario (nueva venta, o a partir de una cotización)
  $items = $v ? partidas('venta', $id) : [];
  $cot = null;
  if (!$v && ($cid = (int)($_GET['de_cotizacion'] ?? 0))) {
    $cot = q1('SELECT * FROM cotizaciones WHERE id=?', [$cid]);
    if ($cot) $items = partidas('cotizacion', $cid);
  }
  $cli = $v ? ($v['cliente_id'] ? q1('SELECT * FROM clientes WHERE id=?', [$v['cliente_id']]) : null) : null;
  $cobros = $v ? q('SELECT c.*, u.nombre usr FROM cobros c LEFT JOIN usuarios u ON u.id=c.usuario_id WHERE venta_id=? ORDER BY fecha', [$id])->fetchAll() : [];
  $titulo = $v ? 'Venta ' . folio('V', $id) : 'Nueva venta';
  layout_inicio($titulo, 'ventas');
  ?>
  <div class="head">
    <div><a class="back" href="<?= url('ventas') ?>">← Ventas</a><h1><?= e($titulo) ?></h1>
      <?php if ($v): ?><p><?= chip($v['estado'], ESTADOS_VENTA) ?> <?= chip_pago($v) ?> <?= $v['entregada'] ? '<span class="chip chip-entregada">Entregada</span>' : '' ?> <span class="muted sm"><?= e(ORIGENES[$v['origen']]) ?></span></p><?php endif; ?>
    </div>
    <?php if ($v): ?><div class="acts">
      <?php if ($cli && $cli['telefono']):
        $msg = "Hola {$cli['nombre']}, te escribimos de Be Cap sobre tu pedido " . folio('V', $id) . " por " . money($v['total']) . ".";
      ?><a class="btn wa" target="_blank" rel="noopener" href="https://wa.me/<?= e(tel_wa($cli['telefono'])) ?>?text=<?= rawurlencode($msg) ?>">WhatsApp al cliente</a><?php endif; ?>
      <?php if ($v['estado'] === 'por_confirmar'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="estado" value="confirmada"><button class="btn">Confirmar pedido</button></form><?php endif; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="entregada"><button class="btn ghost"><?= $v['entregada'] ? 'Marcar como no entregada' : 'Marcar como entregada' ?></button></form>
    </div><?php endif; ?>
  </div>

  <?php if ($v && $v['estado'] !== 'cancelada'): $saldo = (float)$v['total'] - (float)$v['pagado']; ?>
  <div class="kpis k3">
    <div class="kpi"><small>Total</small><b><?= money($v['total']) ?></b></div>
    <div class="kpi"><small>Pagado</small><b><?= money($v['pagado']) ?></b></div>
    <div class="kpi <?= $saldo > 0.009 ? 'warn' : 'good' ?>"><small>Saldo</small><b><?= money(max(0, $saldo)) ?></b></div>
  </div>
  <?php endif; ?>

  <?php if ($v): ?>
  <div class="card">
    <h3>Pagos (cobros)</h3>
    <?php if ($cobros): ?><table class="tbl"><thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th class="r">Monto</th><th></th></tr></thead><tbody>
      <?php foreach ($cobros as $c): ?><tr><td><?= e(fecha_mx($c['fecha'])) ?></td><td><?= e(METODOS[$c['metodo']]) ?></td><td class="muted"><?= e(implode(' · ', array_filter([$c['referencia'], $c['usr']]))) ?></td><td class="r"><?= money($c['monto']) ?></td>
        <td class="r"><?php if (es_admin()): ?><form method="post" data-confirm="¿Eliminar este pago?"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar_cobro"><input type="hidden" name="cobro_id" value="<?= $c['id'] ?>"><button class="x" aria-label="Eliminar pago">×</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table><?php else: ?><p class="muted">Aún no hay pagos registrados.</p><?php endif; ?>
    <?php if ($v['estado'] !== 'cancelada'): ?>
    <form method="post" class="row-form"><?= csrf_field() ?><input type="hidden" name="accion" value="cobro">
      <label>Monto <input name="monto" type="number" step="0.01" min="0.01" required value="<?= e(max(0, round((float)$v['total'] - (float)$v['pagado'], 2)) ?: '') ?>"></label>
      <label>Método <select name="metodo"><?= select_opts(METODOS, 'efectivo') ?></select></label>
      <label>Fecha <input name="fecha_cobro" type="date" value="<?= date('Y-m-d') ?>"></label>
      <label>Referencia <input name="referencia" maxlength="80" placeholder="Opcional"></label>
      <button class="btn">Registrar pago</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <form method="post" class="stack" id="docForm">
    <?= csrf_field() ?><input type="hidden" name="accion" value="guardar">
    <?php if ($cot): ?><input type="hidden" name="cotizacion_id" value="<?= (int)$cot['id'] ?>"><div class="flash">Venta a partir de la cotización <?= e(folio('COT', $cot['id'])) ?>.</div><?php endif; ?>
    <div class="card">
      <h3>Datos de la venta</h3>
      <?php selector_cliente($v ? ($v['cliente_id'] ? (int)$v['cliente_id'] : null) : ($cot && $cot['cliente_id'] ? (int)$cot['cliente_id'] : null)); ?>
      <div class="grid3">
        <label>Fecha <input name="fecha" type="datetime-local" value="<?= e(date('Y-m-d\TH:i', strtotime($v['fecha'] ?? 'now'))) ?>"></label>
        <label>¿Por dónde llegó? <select name="origen"><?= select_opts(ORIGENES, $v['origen'] ?? 'tienda') ?></select></label>
        <label>Entrega <select name="entrega" id="entregaSel"><option value="tienda">Recoge en tienda</option><option value="envio"<?= ($v['entrega'] ?? '') === 'envio' ? ' selected' : '' ?>>Envío a domicilio</option></select></label>
      </div>
      <div class="grid2 envio-box">
        <label>Dirección de envío <textarea name="direccion" rows="3"><?= e($v['direccion'] ?? '') ?></textarea></label>
        <label>Número de guía <input name="guia" maxlength="80" value="<?= e($v['guia'] ?? '') ?>"></label>
      </div>
      <label>Notas <textarea name="notas" rows="2"><?= e($v['notas'] ?? ($cot['notas'] ?? '')) ?></textarea></label>
    </div>
    <?php editor_partidas($items, (float)($v['descuento'] ?? ($cot['descuento'] ?? 0)), (float)($v['envio'] ?? ($cot['envio'] ?? 0))); ?>
    <?php if (!$v): ?>
    <div class="card"><h3>¿Ya pagó?</h3>
      <div class="grid3">
        <label>Pago recibido ahora <input name="cobro_inicial" type="number" min="0" step="0.01" placeholder="0.00"></label>
        <label>Método <select name="metodo_inicial"><?= select_opts(METODOS, 'efectivo') ?></select></label>
        <label>Estado <select name="estado"><option value="confirmada">Confirmada</option><option value="por_confirmar">Por confirmar</option></select></label>
      </div>
    </div>
    <?php endif; ?>
    <div class="acts end"><button class="btn big">Guardar venta</button></div>
  </form>

  <?php if ($v): ?>
  <div class="card danger">
    <h3>Más opciones</h3>
    <div class="acts">
      <?php if ($v['estado'] !== 'cancelada'): ?><form method="post" data-confirm="¿Cancelar esta venta? No contará en los reportes."><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="estado" value="cancelada"><button class="btn ghost">Cancelar venta</button></form>
      <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="estado" value="confirmada"><button class="btn ghost">Reactivar venta</button></form><?php endif; ?>
      <?php if (es_admin()): ?><form method="post" data-confirm="¿Eliminar la venta para siempre? Esto no se puede deshacer."><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><button class="btn red">Eliminar</button></form><?php endif; ?>
    </div>
  </div>
  <?php endif;
  layout_fin();
  return;
}

/* ======================= LISTADO ======================= */
$f_estado = array_key_exists($_GET['estado'] ?? '', ESTADOS_VENTA) ? $_GET['estado'] : '';
$f_pago = in_array($_GET['pago'] ?? '', ['pendiente', 'pagada'], true) ? $_GET['pago'] : '';
$f_q = txt($_GET['q'] ?? '', 60);
$where = ['1=1']; $par = [];
if ($f_estado) { $where[] = 'v.estado=?'; $par[] = $f_estado; }
if ($f_pago === 'pendiente') $where[] = "v.estado='confirmada' AND v.total-v.pagado > 0.009";
if ($f_pago === 'pagada') $where[] = 'v.total-v.pagado <= 0.009 AND v.total > 0';
if ($f_q !== '') { $where[] = '(c.nombre LIKE ? OR c.telefono LIKE ? OR v.id=? OR EXISTS (SELECT 1 FROM partidas pa WHERE pa.doc=\'venta\' AND pa.doc_id=v.id AND pa.descripcion LIKE ?))'; array_push($par, "%$f_q%", "%$f_q%", (int)preg_replace('/\D/', '', $f_q), "%$f_q%"); }
$pag = max(1, (int)($_GET['pg'] ?? 1)); $por = 50;
$total = (int)qv('SELECT COUNT(*) FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE ' . implode(' AND ', $where), $par);
$rows = q('SELECT v.*, c.nombre cliente FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE ' . implode(' AND ', $where) . ' ORDER BY v.fecha DESC, v.id DESC LIMIT ' . $por . ' OFFSET ' . (($pag - 1) * $por), $par)->fetchAll();

layout_inicio('Ventas y cobros', 'ventas');
?>
<div class="head"><div><h1>Ventas y cobros</h1><p class="muted">Pedidos de la web, WhatsApp y mostrador, con sus pagos.</p></div><div class="acts"><a class="btn" href="<?= url('venta', ['nueva' => 1]) ?>">+ Nueva venta</a></div></div>
<form class="filters" method="get"><input type="hidden" name="p" value="ventas">
  <input name="q" placeholder="Buscar cliente, teléfono, folio o producto" value="<?= e($f_q) ?>">
  <select name="estado"><option value="">Todos los estados</option><?= select_opts(ESTADOS_VENTA, $f_estado) ?></select>
  <select name="pago"><option value="">Pagadas y pendientes</option><option value="pendiente"<?= $f_pago === 'pendiente' ? ' selected' : '' ?>>Con saldo por cobrar</option><option value="pagada"<?= $f_pago === 'pagada' ? ' selected' : '' ?>>Pagadas</option></select>
  <button class="btn ghost">Filtrar</button>
</form>
<div class="card flush">
<?php if (!$rows): ?><p class="muted pad">No hay ventas con esos filtros.</p><?php else: ?>
<table class="tbl click"><thead><tr><th>Folio</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th class="r">Total</th><th class="r">Saldo</th></tr></thead><tbody>
<?php foreach ($rows as $v): $s = (float)$v['total'] - (float)$v['pagado']; ?>
<tr data-href="<?= url('venta', ['id' => $v['id']]) ?>"><td><a href="<?= url('venta', ['id' => $v['id']]) ?>"><b><?= e(folio('V', $v['id'])) ?></b></a><?= $v['origen'] === 'web' ? ' <span class="tag">web</span>' : '' ?></td><td><?= e(fecha_mx($v['fecha'], true)) ?></td><td><?= e($v['cliente'] ?: 'Mostrador') ?></td><td><?= chip($v['estado'], ESTADOS_VENTA) ?> <?= chip_pago($v) ?></td><td class="r"><?= money($v['total']) ?></td><td class="r <?= $s > 0.009 && $v['estado'] === 'confirmada' ? 'red' : 'muted' ?>"><?= $v['estado'] === 'cancelada' ? '—' : money(max(0, $s)) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
<?php if ($total > $por): ?><div class="pager"><?php for ($i = 1; $i <= ceil($total / $por); $i++): ?><a class="<?= $i === $pag ? 'on' : '' ?>" href="<?= url('ventas', ['estado' => $f_estado, 'pago' => $f_pago, 'q' => $f_q, 'pg' => $i]) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif;
layout_fin();
