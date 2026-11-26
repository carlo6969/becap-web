<?php
if ($p === 'cliente') {
  $id = (int)($_GET['id'] ?? 0);
  $c = $id ? q1('SELECT * FROM clientes WHERE id=?', [$id]) : null;
  if ($id && !$c) { flash('Ese cliente no existe.', 'err'); redirect('clientes'); }
  if (es_post()) {
    $acc = $_POST['accion'] ?? '';
    if ($acc === 'guardar') {
      $nombre = txt($_POST['nombre'] ?? '', 120);
      if ($nombre === '') { flash('Escribe el nombre del cliente.', 'err'); redirect('cliente', $id ? ['id' => $id] : ['nuevo' => 1]); }
      $vals = [$nombre, txt($_POST['telefono'] ?? '', 30) ?: null, txt($_POST['email'] ?? '', 120) ?: null, txt($_POST['direccion'] ?? '', 600) ?: null, txt($_POST['notas'] ?? '', 2000) ?: null];
      if ($id) q('UPDATE clientes SET nombre=?, telefono=?, email=?, direccion=?, notas=?, actualizado=? WHERE id=?', array_merge($vals, [ahora(), $id]));
      else { q('INSERT INTO clientes (nombre, telefono, email, direccion, notas, origen, creado, actualizado) VALUES (?,?,?,?,?,?,?,?)', array_merge($vals, ['manual', ahora(), ahora()])); $id = (int)db()->lastInsertId(); }
      flash('Cliente guardado.');
    } elseif ($id && $acc === 'eliminar' && es_admin()) {
      foreach (['ventas', 'cotizaciones', 'citas'] as $t) q("UPDATE $t SET cliente_id=NULL WHERE cliente_id=?", [$id]);
      q('DELETE FROM clientes WHERE id=?', [$id]);
      flash('Cliente eliminado. Sus ventas se conservan sin nombre.');
      redirect('clientes');
    }
    redirect('cliente', ['id' => $id]);
  }
  $ventas = $c ? q('SELECT * FROM ventas WHERE cliente_id=? ORDER BY fecha DESC', [$id])->fetchAll() : [];
  $cots = $c ? q('SELECT * FROM cotizaciones WHERE cliente_id=? ORDER BY fecha DESC', [$id])->fetchAll() : [];
  $citas = $c ? q('SELECT * FROM citas WHERE cliente_id=? ORDER BY fecha DESC, hora DESC LIMIT 20', [$id])->fetchAll() : [];
  $comprado = array_sum(array_map(fn($v) => $v['estado'] === 'confirmada' ? (float)$v['total'] : 0, $ventas));
  $debe = array_sum(array_map(fn($v) => $v['estado'] === 'confirmada' ? max(0, (float)$v['total'] - (float)$v['pagado']) : 0, $ventas));
  layout_inicio($c ? $c['nombre'] : 'Nuevo cliente', 'clientes');
  ?>
  <div class="head"><div><a class="back" href="<?= url('clientes') ?>">← Clientes</a><h1><?= e($c['nombre'] ?? 'Nuevo cliente') ?></h1><?php if ($c): ?><p class="muted sm">Cliente desde <?= e(fecha_mx($c['creado'])) ?><?= $c['origen'] === 'web' ? ' · llegó por la página web' : '' ?></p><?php endif; ?></div>
    <?php if ($c): ?><div class="acts"><?php if ($c['telefono']): ?><a class="btn wa" target="_blank" rel="noopener" href="https://wa.me/<?= e(tel_wa($c['telefono'])) ?>">WhatsApp</a><?php endif; ?><a class="btn" href="<?= url('venta', ['nueva' => 1]) ?>">+ Venta</a></div><?php endif; ?></div>
  <?php if ($c): ?><div class="kpis k3">
    <div class="kpi"><small>Compras</small><b><?= count(array_filter($ventas, fn($v) => $v['estado'] === 'confirmada')) ?></b></div>
    <div class="kpi"><small>Total comprado</small><b><?= money($comprado) ?></b></div>
    <div class="kpi <?= $debe > 0 ? 'warn' : '' ?>"><small>Saldo pendiente</small><b><?= money($debe) ?></b></div>
  </div><?php endif; ?>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="accion" value="guardar">
    <h3>Datos</h3>
    <div class="grid3">
      <label>Nombre <input name="nombre" required maxlength="120" value="<?= e($c['nombre'] ?? '') ?>"></label>
      <label>WhatsApp / teléfono <input name="telefono" maxlength="30" inputmode="tel" value="<?= e($c['telefono'] ?? '') ?>"></label>
      <label>Correo <input name="email" type="email" maxlength="120" value="<?= e($c['email'] ?? '') ?>"></label>
    </div>
    <div class="grid2">
      <label>Dirección <textarea name="direccion" rows="3"><?= e($c['direccion'] ?? '') ?></textarea></label>
      <label>Notas (talla, gustos, equipo favorito…) <textarea name="notas" rows="3"><?= e($c['notas'] ?? '') ?></textarea></label>
    </div>
    <div class="acts end"><button class="btn">Guardar cliente</button></div>
  </form>
  <?php if ($c): ?>
  <div class="card flush"><h3 class="pad">Ventas</h3>
    <?php if (!$ventas): ?><p class="muted pad">Sin ventas.</p><?php else: ?><table class="tbl click"><tbody>
    <?php foreach ($ventas as $v): ?><tr data-href="<?= url('venta', ['id' => $v['id']]) ?>"><td><a href="<?= url('venta', ['id' => $v['id']]) ?>"><b><?= e(folio('V', $v['id'])) ?></b></a></td><td><?= e(fecha_mx($v['fecha'])) ?></td><td><?= chip($v['estado'], ESTADOS_VENTA) ?> <?= chip_pago($v) ?></td><td class="r"><?= money($v['total']) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>
  <?php if ($cots || $citas): ?><div class="grid2">
    <div class="card"><h3>Cotizaciones</h3><?php if (!$cots): ?><p class="muted">Sin cotizaciones.</p><?php else: ?><ul class="list"><?php foreach ($cots as $co): ?><li><a href="<?= url('cotizacion', ['id' => $co['id']]) ?>"><b><?= e(folio('COT', $co['id'])) ?></b><span><?= e(fecha_mx($co['fecha'])) ?></span><em><?= money($co['total']) ?></em></a></li><?php endforeach; ?></ul><?php endif; ?></div>
    <div class="card"><h3>Citas</h3><?php if (!$citas): ?><p class="muted">Sin citas.</p><?php else: ?><ul class="list"><?php foreach ($citas as $ci): ?><li><a href="<?= url('agenda', ['d' => $ci['fecha'], 'id' => $ci['id']]) ?>"><b><?= e(fecha_mx($ci['fecha'])) ?></b><span><?= e(date('g:i a', strtotime($ci['hora']))) ?></span><?= chip($ci['estado'], ESTADOS_CITA) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
  </div><?php endif; ?>
  <?php if (es_admin()): ?><div class="card danger"><h3>Más opciones</h3><form method="post" data-confirm="¿Eliminar este cliente? Sus ventas se conservan."><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><button class="btn red">Eliminar cliente</button></form></div><?php endif; ?>
  <?php endif;
  layout_fin();
  return;
}

$f_q = txt($_GET['q'] ?? '', 60);
$par = []; $w = '1=1';
if ($f_q !== '') { $w = '(c.nombre LIKE ? OR c.telefono LIKE ? OR c.email LIKE ?)'; $par = ["%$f_q%", "%$f_q%", "%$f_q%"]; }
$rows = q("SELECT c.*, COUNT(v.id) compras, COALESCE(SUM(v.total),0) comprado, MAX(v.fecha) ultima, COALESCE(SUM(GREATEST(v.total-v.pagado,0)),0) debe
  FROM clientes c LEFT JOIN ventas v ON v.cliente_id=c.id AND v.estado='confirmada' WHERE $w GROUP BY c.id ORDER BY COALESCE(MAX(v.fecha), c.creado) DESC LIMIT 300", $par)->fetchAll();
layout_inicio('Clientes', 'clientes');
?>
<div class="head"><div><h1>Clientes</h1><p class="muted"><?= (int)qv('SELECT COUNT(*) FROM clientes') ?> clientes registrados.</p></div><div class="acts"><a class="btn ghost" href="<?= url('reportes', ['exportar' => 'clientes']) ?>">Descargar Excel</a><a class="btn" href="<?= url('cliente', ['nuevo' => 1]) ?>">+ Nuevo cliente</a></div></div>
<form class="filters" method="get"><input type="hidden" name="p" value="clientes"><input name="q" placeholder="Buscar por nombre, teléfono o correo" value="<?= e($f_q) ?>"><button class="btn ghost">Buscar</button></form>
<div class="card flush">
<?php if (!$rows): ?><p class="muted pad">No hay clientes<?= $f_q ? ' con esa búsqueda' : ' todavía' ?>.</p><?php else: ?>
<table class="tbl click"><thead><tr><th>Cliente</th><th>WhatsApp</th><th class="r">Compras</th><th class="r">Total comprado</th><th class="r">Saldo</th><th>Última compra</th></tr></thead><tbody>
<?php foreach ($rows as $c): ?><tr data-href="<?= url('cliente', ['id' => $c['id']]) ?>"><td><a href="<?= url('cliente', ['id' => $c['id']]) ?>"><b><?= e($c['nombre']) ?></b></a><?= $c['origen'] === 'web' ? ' <span class="tag">web</span>' : '' ?></td><td class="muted"><?= e($c['telefono']) ?></td><td class="r"><?= (int)$c['compras'] ?></td><td class="r"><?= money($c['comprado']) ?></td><td class="r <?= $c['debe'] > 0 ? 'red' : 'muted' ?>"><?= money($c['debe']) ?></td><td class="muted"><?= e(fecha_mx($c['ultima'])) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
<?php layout_fin();
