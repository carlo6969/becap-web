<?php
// Inventario con aviso de faltantes (módulo del plan Negocio, regalo de APARECE para Be Cap)
inventario_sync();
const TIPOS_MOV = ['entrada' => 'Entrada', 'salida' => 'Salida', 'ajuste' => 'Ajuste', 'venta' => 'Venta', 'devolucion' => 'Regreso por venta'];
const INV_JOIN = 'FROM inventario i JOIN productos p ON p.id=i.producto_web_id AND p.eliminado=0';

if (es_post()) {
  $acc = $_POST['accion'] ?? '';
  if ($acc === 'capturar') {
    // Captura masiva: existencia y mínimo de cada variante visible
    $n = 0;
    foreach ((array)($_POST['st'] ?? []) as $id => $v) {
      $id = (int)$id; $v = trim((string)$v);
      $r = q1('SELECT stock, minimo FROM inventario WHERE id=?', [$id]);
      if (!$r) continue;
      $min = max(0, (int)($_POST['mn'][$id] ?? $r['minimo']));
      if ((int)$r['minimo'] !== $min) q('UPDATE inventario SET minimo=? WHERE id=?', [$min, $id]);
      if ($v === '' || !is_numeric($v)) continue;
      $nuevo = (int)$v;
      if ($r['stock'] === null || (int)$r['stock'] !== $nuevo) {
        mover_stock($id, $nuevo - (int)($r['stock'] ?? 0), 'ajuste', $r['stock'] === null ? 'Inventario inicial' : 'Conteo');
        if ($nuevo > (int)($r['stock'] ?? 0) && $nuevo > 0) q('UPDATE inventario SET disponible=1 WHERE id=?', [$id]); // si llegó mercancía, vuelve a estar a la venta
        $n++;
      }
    }
    flash($n ? "Inventario actualizado ($n producto" . ($n === 1 ? '' : 's') . '). La página web ya muestra los cambios.' : 'Mínimos guardados.');
  } elseif ($acc === 'mover') {
    $id = (int)($_POST['inv'] ?? 0); $c = abs((int)($_POST['cantidad'] ?? 0));
    $tipo = ($_POST['tipo'] ?? '') === 'salida' ? 'salida' : 'entrada';
    if (!$id || !$c || !qv('SELECT id FROM inventario WHERE id=?', [$id])) flash('Elige el producto y la cantidad.', 'err');
    else {
      $final = mover_stock($id, $tipo === 'salida' ? -$c : $c, $tipo, txt($_POST['nota'] ?? '', 200) ?: null);
      if ($tipo === 'entrada' && $final > 0) q('UPDATE inventario SET disponible=1 WHERE id=?', [$id]);
      flash(($tipo === 'entrada' ? "Entrada de $c registrada" : "Salida de $c registrada") . ". Ahora hay $final.");
    }
  }
  redirect('inventario', array_filter(['q' => $_POST['_q'] ?? '', 'cat' => $_POST['_cat'] ?? '', 'ver' => $_POST['_ver'] ?? '']));
}

/* ---------- descarga a Excel ---------- */
if (($_GET['exportar'] ?? '') === 'inventario') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="becap-inventario-' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Producto', 'Categoría', 'Precio', 'Existencia', 'Mínimo', 'Estado', 'Valor a precio de venta', 'Publicado en la web']);
  foreach (q('SELECT i.*, p.publicado, p.categoria pcat ' . INV_JOIN . ' WHERE i.activo=1 ORDER BY p.categoria, i.descripcion') as $r) {
    $est = $r['stock'] === null ? 'Sin capturar' : ((int)$r['stock'] <= 0 ? 'Agotado' : ((int)$r['stock'] <= (int)$r['minimo'] ? 'Bajo' : 'Bien'));
    fputcsv($out, [$r['descripcion'], CATS[$r['pcat']] ?? $r['pcat'], $r['precio'], $r['stock'], $r['minimo'], $est, round((float)$r['precio'] * max(0, (int)$r['stock']), 2), $r['publicado'] ? 'Sí' : 'No']);
  }
  fclose($out); exit;
}

$f_q = txt($_GET['q'] ?? '', 60);
$f_cat = array_key_exists($_GET['cat'] ?? '', CATS) ? $_GET['cat'] : '';
$f_ver = in_array($_GET['ver'] ?? '', ['faltantes', 'sin'], true) ? $_GET['ver'] : '';
$w = ['i.activo=1']; $par = [];
if ($f_q !== '') { $w[] = 'i.descripcion LIKE ?'; $par[] = "%$f_q%"; }
if ($f_cat) { $w[] = 'p.categoria=?'; $par[] = $f_cat; }
if ($f_ver === 'faltantes') $w[] = 'i.stock IS NOT NULL AND i.stock <= i.minimo';
if ($f_ver === 'sin') $w[] = 'i.stock IS NULL';
$rows = q('SELECT i.*, p.categoria pcat, p.publicado, p.id pid ' . INV_JOIN . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY (i.stock IS NOT NULL AND i.stock <= i.minimo) DESC, p.categoria, i.descripcion', $par)->fetchAll();

$k = q1('SELECT COALESCE(SUM(GREATEST(i.stock,0)),0) piezas, COALESCE(SUM(GREATEST(i.stock,0)*i.precio),0) valor, COALESCE(SUM(i.stock IS NOT NULL AND i.stock<=0),0) agotados, COALESCE(SUM(i.stock IS NOT NULL AND i.stock>0 AND i.stock<=i.minimo),0) bajos, COALESCE(SUM(i.stock IS NULL),0) sin, COUNT(*) total ' . INV_JOIN . ' WHERE i.activo=1');
$movs = q('SELECT m.*, i.descripcion, u.nombre usr FROM movimientos m JOIN inventario i ON i.id=m.inventario_id LEFT JOIN usuarios u ON u.id=m.usuario_id ORDER BY m.id DESC LIMIT 40')->fetchAll();
$todos = q('SELECT i.id, i.descripcion, i.stock ' . INV_JOIN . ' WHERE i.activo=1 ORDER BY i.descripcion')->fetchAll();
$keep = '<input type="hidden" name="_q" value="' . e($f_q) . '"><input type="hidden" name="_cat" value="' . e($f_cat) . '"><input type="hidden" name="_ver" value="' . e($f_ver) . '">';

layout_inicio('Inventario', 'inventario');
?>
<div class="head"><div><h1>Inventario</h1><p class="muted">Existencias de cada gorra, talla y color. Las ventas confirmadas y el punto de venta descuentan solos; las canceladas regresan. Lo que se agota aparece como agotado en la página web.</p></div>
  <div class="acts"><a class="btn ghost" href="<?= url('inventario', ['exportar' => 'inventario']) ?>">Descargar Excel</a><?php if (es_admin()): ?><a class="btn ghost" href="<?= url('producto', ['nuevo' => 1]) ?>">+ Producto</a><?php endif; ?><button class="btn" data-open="moverForm">Entrada / salida</button></div></div>

<div class="kpis">
  <div class="kpi"><small>Piezas en existencia</small><b><?= number_format((float)$k['piezas']) ?></b><span>valor <?= money($k['valor']) ?> a precio de venta</span></div>
  <div class="kpi <?= $k['agotados'] ? 'warn' : '' ?>"><small>Agotados</small><b><?= (int)$k['agotados'] ?></b><span><a href="<?= url('inventario', ['ver' => 'faltantes']) ?>">ver faltantes</a></span></div>
  <div class="kpi <?= $k['bajos'] ? 'warn' : '' ?>"><small>Por agotarse</small><b><?= (int)$k['bajos'] ?></b><span>en o debajo del mínimo</span></div>
  <div class="kpi"><small>Sin capturar</small><b><?= (int)$k['sin'] ?></b><span><a href="<?= url('inventario', ['ver' => 'sin']) ?>">capturar existencias</a></span></div>
</div>

<?php if ((int)$k['sin'] > 0 && (int)$k['sin'] === (int)$k['total']): ?>
<div class="flash">Primer paso: escribe cuántas piezas hay de cada producto en la columna “Existencia” y presiona “Guardar inventario”. Los productos de la página web ya están cargados.</div>
<?php endif; ?>

<div class="card hidden" id="moverForm"><h3>Registrar entrada o salida</h3>
  <form method="post" class="row-form"><?= csrf_field() . $keep ?><input type="hidden" name="accion" value="mover">
    <label style="flex:3 1 260px">Producto <select name="inv" required><option value="">Elige…</option><?php foreach ($todos as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['descripcion']) ?> (<?= $t['stock'] === null ? 'sin capturar' : (int)$t['stock'] ?>)</option><?php endforeach; ?></select></label>
    <label>Tipo <select name="tipo"><option value="entrada">Entrada (llegó mercancía)</option><option value="salida">Salida (merma, regalo, muestra)</option></select></label>
    <label>Cantidad <input type="number" name="cantidad" min="1" step="1" required></label>
    <label style="flex:2 1 200px">Nota <input name="nota" maxlength="200" placeholder="Ej. Pedido proveedor New Era"></label>
    <button class="btn">Registrar</button>
  </form>
</div>

<form class="filters" method="get"><input type="hidden" name="p" value="inventario">
  <input name="q" placeholder="Buscar producto" value="<?= e($f_q) ?>">
  <select name="cat"><option value="">Todas las categorías</option><?= select_opts(CATS, $f_cat) ?></select>
  <select name="ver"><option value="">Todos</option><option value="faltantes"<?= $f_ver === 'faltantes' ? ' selected' : '' ?>>Solo faltantes</option><option value="sin"<?= $f_ver === 'sin' ? ' selected' : '' ?>>Sin capturar</option></select>
  <button class="btn ghost">Filtrar</button>
</form>

<form method="post"><?= csrf_field() . $keep ?><input type="hidden" name="accion" value="capturar">
<div class="card flush">
<?php if (!$rows): ?><p class="muted pad">No hay productos con esos filtros.</p><?php else: ?>
<table class="tbl inv"><thead><tr><th>Producto</th><th class="r">Precio</th><th>Estado</th><th class="r">Existencia</th><th class="r">Mínimo</th></tr></thead><tbody>
<?php foreach ($rows as $r):
  $st = $r['stock'];
  [$cls, $lbl] = $st === null ? ['', 'Sin capturar'] : ((int)$st <= 0 ? ['cancelada', 'Agotado'] : ((int)$st <= (int)$r['minimo'] ? ['pendiente', 'Por agotarse'] : ['confirmada', 'Bien']));
?>
<tr><td><a href="<?= url('producto', ['id' => $r['pid']]) ?>"><b><?= e($r['descripcion']) ?></b></a><br><span class="muted sm"><?= e(CATS[$r['pcat']] ?? $r['pcat']) ?><?= $r['publicado'] ? '' : ' · no publicado en la web' ?></span></td>
  <td class="r"><?= money($r['precio']) ?></td>
  <td><span class="chip chip-<?= $cls ?>"><?= $lbl ?></span></td>
  <td class="r"><input class="num" type="number" step="1" name="st[<?= $r['id'] ?>]" value="<?= $st === null ? '' : (int)$st ?>" placeholder="—" aria-label="Existencia de <?= e($r['descripcion']) ?>"></td>
  <td class="r"><input class="num sm" type="number" min="0" step="1" name="mn[<?= $r['id'] ?>]" value="<?= (int)$r['minimo'] ?>" aria-label="Mínimo de <?= e($r['descripcion']) ?>"></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
<?php if ($rows): ?><div class="acts end sticky-save"><span class="muted sm">Cambia las cantidades que necesites y guarda una sola vez.</span><button class="btn big">Guardar inventario</button></div><?php endif; ?>
</form>

<div class="card flush mt"><h3 class="pad">Últimos movimientos</h3>
<?php if (!$movs): ?><p class="muted pad">Aún no hay movimientos.</p><?php else: ?>
<table class="tbl"><thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th class="r">Cantidad</th><th class="r">Quedan</th><th>Quién / nota</th></tr></thead><tbody>
<?php foreach ($movs as $m): ?><tr><td class="muted"><?= e(fecha_mx($m['creado'], true)) ?></td><td><?= e($m['descripcion']) ?></td><td><?= e(TIPOS_MOV[$m['tipo']]) ?></td><td class="r <?= $m['cantidad'] < 0 ? 'red' : '' ?>"><b><?= $m['cantidad'] > 0 ? '+' : '' ?><?= (int)$m['cantidad'] ?></b></td><td class="r"><?= e($m['stock_final']) ?></td>
  <td class="muted sm"><?php if ($m['venta_id']): ?><a href="<?= url('venta', ['id' => $m['venta_id']]) ?>"><?= e(folio('V', $m['venta_id'])) ?></a><?php else: ?><?= e(implode(' · ', array_filter([$m['usr'], $m['nota']]))) ?><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
<?php layout_fin();
