<?php
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['desde'] ?? '')) ? $_GET['desde'] : date('Y-m-01');
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['hasta'] ?? '')) ? $_GET['hasta'] : date('Y-m-d');
if ($hasta < $desde) [$desde, $hasta] = [$hasta, $desde];
$rango = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];

/* ---------- descargas a Excel (CSV con acentos compatibles) ---------- */
$exp = $_GET['exportar'] ?? '';
if ($exp) {
  $sets = [
    'ventas' => ['ventas', ['Folio', 'Fecha', 'Cliente', 'Teléfono', 'Origen', 'Estado', 'Entrega', 'Subtotal', 'Descuento', 'Envío', 'Total', 'Pagado', 'Saldo', 'Productos'], function () use ($rango) {
      foreach (q("SELECT v.*, c.nombre cliente, c.telefono, (SELECT GROUP_CONCAT(CONCAT(FORMAT(pa.cantidad,0),' x ',pa.descripcion) SEPARATOR ' | ') FROM partidas pa WHERE pa.doc='venta' AND pa.doc_id=v.id) prods FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.fecha BETWEEN ? AND ? ORDER BY v.fecha", $rango) as $v)
        yield [folio('V', $v['id']), $v['fecha'], $v['cliente'], $v['telefono'], ORIGENES[$v['origen']], ESTADOS_VENTA[$v['estado']], $v['entrega'] === 'envio' ? 'Envío' : 'Tienda', $v['subtotal'], $v['descuento'], $v['envio'], $v['total'], $v['pagado'], max(0, $v['total'] - $v['pagado']), $v['prods']];
    }],
    'productos' => ['productos-vendidos', ['Producto', 'Piezas', 'Importe'], function () use ($rango) {
      foreach (q("SELECT pa.descripcion, SUM(pa.cantidad) piezas, SUM(pa.importe) importe FROM partidas pa JOIN ventas v ON v.id=pa.doc_id AND pa.doc='venta' WHERE v.estado='confirmada' AND v.fecha BETWEEN ? AND ? GROUP BY pa.descripcion ORDER BY importe DESC", $rango) as $r)
        yield [$r['descripcion'], (float)$r['piezas'], $r['importe']];
    }],
    'cobros' => ['cobros', ['Fecha', 'Venta', 'Cliente', 'Método', 'Referencia', 'Monto', 'Registró'], function () use ($rango) {
      foreach (q("SELECT co.*, c.nombre cliente, u.nombre usr FROM cobros co JOIN ventas v ON v.id=co.venta_id LEFT JOIN clientes c ON c.id=v.cliente_id LEFT JOIN usuarios u ON u.id=co.usuario_id WHERE v.estado<>'cancelada' AND co.fecha BETWEEN ? AND ? ORDER BY co.fecha", $rango) as $r)
        yield [$r['fecha'], folio('V', $r['venta_id']), $r['cliente'], METODOS[$r['metodo']], $r['referencia'], $r['monto'], $r['usr']];
    }],
    'cotizaciones' => ['cotizaciones', ['Folio', 'Fecha', 'Vigencia', 'Cliente', 'Estado', 'Total', 'Venta'], function () use ($desde, $hasta) {
      foreach (q('SELECT co.*, c.nombre cliente FROM cotizaciones co LEFT JOIN clientes c ON c.id=co.cliente_id WHERE co.fecha BETWEEN ? AND ? ORDER BY co.fecha', [$desde, $hasta]) as $r)
        yield [folio('COT', $r['id']), $r['fecha'], $r['vigencia'], $r['cliente'], ESTADOS_COT[$r['estado']], $r['total'], $r['venta_id'] ? folio('V', $r['venta_id']) : ''];
    }],
    'clientes' => ['clientes', ['Nombre', 'Teléfono', 'Correo', 'Dirección', 'Notas', 'Origen', 'Alta', 'Compras', 'Total comprado'], function () {
      foreach (q("SELECT c.*, COUNT(v.id) n, COALESCE(SUM(v.total),0) t FROM clientes c LEFT JOIN ventas v ON v.cliente_id=c.id AND v.estado='confirmada' GROUP BY c.id ORDER BY c.nombre") as $r)
        yield [$r['nombre'], $r['telefono'], $r['email'], $r['direccion'], $r['notas'], $r['origen'], $r['creado'], $r['n'], $r['t']];
    }],
  ];
  if (!isset($sets[$exp])) redirect('reportes');
  [$nombre, $cab, $gen] = $sets[$exp];
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="becap-' . $nombre . ($exp === 'clientes' ? '' : "-$desde-a-$hasta") . '.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  $limpia = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; // evita fórmulas inyectadas
  fputcsv($out, $cab);
  foreach ($gen() as $fila) fputcsv($out, array_map($limpia, $fila));
  fclose($out);
  exit;
}

/* ---------- números del periodo ---------- */
$k = q1("SELECT COUNT(*) n, COALESCE(SUM(total),0) t, COALESCE(SUM(descuento),0) d FROM ventas WHERE estado='confirmada' AND fecha BETWEEN ? AND ?", $rango);
$cobrado = (float)qv("SELECT COALESCE(SUM(co.monto),0) FROM cobros co JOIN ventas v ON v.id=co.venta_id WHERE v.estado<>'cancelada' AND co.fecha BETWEEN ? AND ?", $rango);
$saldo = (float)qv("SELECT COALESCE(SUM(GREATEST(total-pagado,0)),0) FROM ventas WHERE estado='confirmada' AND fecha BETWEEN ? AND ?", $rango);
$canc = (int)qv("SELECT COUNT(*) FROM ventas WHERE estado='cancelada' AND fecha BETWEEN ? AND ?", $rango);
$top = q("SELECT pa.descripcion, SUM(pa.cantidad) piezas, SUM(pa.importe) importe FROM partidas pa JOIN ventas v ON v.id=pa.doc_id AND pa.doc='venta' WHERE v.estado='confirmada' AND v.fecha BETWEEN ? AND ? GROUP BY pa.descripcion ORDER BY importe DESC LIMIT 10", $rango)->fetchAll();
$met = q("SELECT co.metodo, SUM(co.monto) t, COUNT(*) n FROM cobros co JOIN ventas v ON v.id=co.venta_id WHERE v.estado<>'cancelada' AND co.fecha BETWEEN ? AND ? GROUP BY co.metodo ORDER BY t DESC", $rango)->fetchAll();
$ori = q("SELECT origen, COUNT(*) n, SUM(total) t FROM ventas WHERE estado='confirmada' AND fecha BETWEEN ? AND ? GROUP BY origen ORDER BY t DESC", $rango)->fetchAll();
$cot = q1("SELECT COUNT(*) n, SUM(estado='aceptada') a, COALESCE(SUM(total),0) t FROM cotizaciones WHERE fecha BETWEEN ? AND ?", [$desde, $hasta]);
$web = q1("SELECT COUNT(*) n, SUM(estado='confirmada') c FROM ventas WHERE origen='web' AND fecha BETWEEN ? AND ?", $rango);
$dias = q("SELECT DATE(fecha) d, COUNT(*) n, SUM(total) t FROM ventas WHERE estado='confirmada' AND fecha BETWEEN ? AND ? GROUP BY DATE(fecha) ORDER BY d", $rango)->fetchAll();
$maxd = max(1.0, ...array_map(fn($r) => (float)$r['t'], $dias ?: [['t' => 1]]));

$atajos = [
  'Este mes' => [date('Y-m-01'), date('Y-m-d')],
  'Mes pasado' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
  'Últimos 7 días' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
  'Este año' => [date('Y-01-01'), date('Y-m-d')],
];
layout_inicio('Reportes', 'reportes');
$qs = ['desde' => $desde, 'hasta' => $hasta];
?>
<div class="head"><div><h1>Reportes</h1><p class="muted"><?= e(fecha_mx($desde)) ?> al <?= e(fecha_mx($hasta)) ?></p></div></div>
<form class="filters" method="get"><input type="hidden" name="p" value="reportes">
  <label class="inl">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
  <label class="inl">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
  <button class="btn ghost">Ver</button>
  <span class="chips"><?php foreach ($atajos as $lbl => [$a, $b]): ?><a class="<?= $a === $desde && $b === $hasta ? 'on' : '' ?>" href="<?= url('reportes', ['desde' => $a, 'hasta' => $b]) ?>"><?= $lbl ?></a><?php endforeach; ?></span>
</form>

<div class="kpis">
  <div class="kpi"><small>Vendido</small><b><?= money($k['t']) ?></b><span><?= (int)$k['n'] ?> ventas<?= $canc ? " · $canc canceladas" : '' ?></span></div>
  <div class="kpi"><small>Ticket promedio</small><b><?= money($k['n'] ? $k['t'] / $k['n'] : 0) ?></b><span>por venta</span></div>
  <div class="kpi"><small>Cobrado</small><b><?= money($cobrado) ?></b><span>pagos recibidos</span></div>
  <div class="kpi <?= $saldo > 0 ? 'warn' : '' ?>"><small>Por cobrar</small><b><?= money($saldo) ?></b><span>de ventas del periodo</span></div>
</div>

<div class="card">
  <h3>Ventas por día</h3>
  <?php if (!$dias): ?><p class="muted">Sin ventas en este periodo.</p><?php else: ?>
  <div class="hbars"><?php foreach ($dias as $r): ?><div><span><?= e(fecha_mx($r['d'])) ?></span><i style="width:<?= round($r['t'] / $maxd * 100, 1) ?>%"></i><b><?= money($r['t']) ?></b><small><?= (int)$r['n'] ?></small></div><?php endforeach; ?></div>
  <?php endif; ?>
</div>

<div class="grid2">
  <div class="card"><h3>Productos más vendidos</h3>
    <?php if (!$top): ?><p class="muted">Sin datos.</p><?php else: ?><table class="tbl"><thead><tr><th>Producto</th><th class="r">Piezas</th><th class="r">Importe</th></tr></thead><tbody>
    <?php foreach ($top as $r): ?><tr><td><?= e($r['descripcion']) ?></td><td class="r"><?= (float)$r['piezas'] ?></td><td class="r"><?= money($r['importe']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </div>
  <div class="card"><h3>Cobros por forma de pago</h3>
    <?php if (!$met): ?><p class="muted">Sin cobros.</p><?php else: ?><table class="tbl"><tbody>
    <?php foreach ($met as $r): ?><tr><td><?= e(METODOS[$r['metodo']]) ?></td><td class="r muted"><?= (int)$r['n'] ?> pago<?= $r['n'] == 1 ? '' : 's' ?></td><td class="r"><b><?= money($r['t']) ?></b></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <h3 class="mt">Ventas por canal</h3>
    <?php if (!$ori): ?><p class="muted">Sin datos.</p><?php else: ?><table class="tbl"><tbody>
    <?php foreach ($ori as $r): ?><tr><td><?= e(ORIGENES[$r['origen']]) ?></td><td class="r muted"><?= (int)$r['n'] ?> venta<?= $r['n'] == 1 ? '' : 's' ?></td><td class="r"><b><?= money($r['t']) ?></b></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <p class="sm muted mt">Pedidos desde la web: <?= (int)$web['n'] ?> recibidos, <?= (int)$web['c'] ?> confirmados. Cotizaciones: <?= (int)$cot['n'] ?> hechas, <?= (int)$cot['a'] ?> aceptadas.</p>
  </div>
</div>

<div class="card">
  <h3>Descargar a Excel</h3>
  <p class="muted sm">Se descargan con el periodo elegido arriba y se abren directo en Excel o Google Sheets.</p>
  <div class="acts">
    <a class="btn ghost" href="<?= url('reportes', $qs + ['exportar' => 'ventas']) ?>">Ventas</a>
    <a class="btn ghost" href="<?= url('reportes', $qs + ['exportar' => 'productos']) ?>">Productos vendidos</a>
    <a class="btn ghost" href="<?= url('reportes', $qs + ['exportar' => 'cobros']) ?>">Cobros</a>
    <a class="btn ghost" href="<?= url('reportes', $qs + ['exportar' => 'cotizaciones']) ?>">Cotizaciones</a>
    <a class="btn ghost" href="<?= url('reportes', ['exportar' => 'clientes']) ?>">Clientes (todos)</a>
  </div>
</div>
<?php layout_fin();
