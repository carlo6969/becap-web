<?php
$hoy = date('Y-m-d');
$ini_mes = date('Y-m-01');
$vh = q1("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE estado='confirmada' AND DATE(fecha)=?", [$hoy]);
$vm = q1("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE estado='confirmada' AND fecha >= ?", [$ini_mes]);
$cm = (float)qv('SELECT COALESCE(SUM(c.monto),0) FROM cobros c JOIN ventas v ON v.id=c.venta_id WHERE v.estado<>\'cancelada\' AND c.fecha >= ?', [$ini_mes]);
$por_cobrar = (float)qv("SELECT COALESCE(SUM(total-pagado),0) FROM ventas WHERE estado='confirmada' AND total-pagado > 0.009");
$pend = q("SELECT v.*, c.nombre cliente FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.estado='por_confirmar' ORDER BY v.fecha DESC LIMIT 8")->fetchAll();
$citas = q("SELECT ci.*, c.nombre cliente, c.telefono FROM citas ci LEFT JOIN clientes c ON c.id=ci.cliente_id WHERE ci.fecha BETWEEN ? AND ? AND ci.estado IN ('pendiente','confirmada') ORDER BY ci.fecha, ci.hora LIMIT 8", [$hoy, date('Y-m-d', strtotime('+6 days'))])->fetchAll();
inventario_sync();
$bajos = q('SELECT i.id, i.descripcion, i.stock, i.minimo FROM inventario i JOIN productos p ON p.id=i.producto_web_id AND p.eliminado=0 WHERE i.activo=1 AND i.stock IS NOT NULL AND i.stock <= i.minimo ORDER BY i.stock, i.descripcion LIMIT 8')->fetchAll();
$sin_capturar = (int)qv('SELECT COUNT(*) FROM inventario i JOIN productos p ON p.id=i.producto_web_id AND p.eliminado=0 WHERE i.activo=1 AND i.stock IS NULL');
$cots = (int)qv("SELECT COUNT(*) FROM cotizaciones WHERE estado IN ('borrador','enviada')");

// ventas de los últimos 14 días
$serie = [];
for ($i = 13; $i >= 0; $i--) $serie[date('Y-m-d', strtotime("-$i days"))] = 0.0;
foreach (q("SELECT DATE(fecha) d, SUM(total) t FROM ventas WHERE estado='confirmada' AND fecha >= ? GROUP BY DATE(fecha)", [array_key_first($serie)])->fetchAll() as $r) $serie[$r['d']] = (float)$r['t'];
$max = max(1.0, max($serie));

layout_inicio('Resumen', 'resumen');
$h = (int)date('G');
?>
<div class="head">
  <div><h1><?= $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches') ?>, <?= e(explode(' ', usuario()['nombre'])[0]) ?></h1><p class="muted"><?= e(fecha_mx($hoy)) ?></p></div>
  <div class="acts"><a class="btn" href="<?= url('venta', ['nueva' => 1]) ?>">+ Nueva venta</a><a class="btn ghost" href="<?= url('cotizacion', ['nueva' => 1]) ?>">+ Cotización</a></div>
</div>

<div class="kpis">
  <div class="kpi"><small>Ventas de hoy</small><b><?= money($vh['t']) ?></b><span><?= (int)$vh['n'] ?> venta<?= $vh['n'] == 1 ? '' : 's' ?></span></div>
  <div class="kpi"><small>Ventas del mes</small><b><?= money($vm['t']) ?></b><span><?= (int)$vm['n'] ?> venta<?= $vm['n'] == 1 ? '' : 's' ?></span></div>
  <div class="kpi"><small>Cobrado este mes</small><b><?= money($cm) ?></b><span>pagos registrados</span></div>
  <div class="kpi <?= $por_cobrar > 0 ? 'warn' : '' ?>"><small>Por cobrar</small><b><?= money($por_cobrar) ?></b><span><a href="<?= url('ventas', ['pago' => 'pendiente']) ?>">ver ventas con saldo</a></span></div>
</div>

<div class="card">
  <h3>Ventas de los últimos 14 días</h3>
  <div class="bars">
    <?php foreach ($serie as $d => $t): ?>
      <div class="bar" title="<?= e(fecha_mx($d)) ?>: <?= money($t) ?>"><i style="height:<?= round($t / $max * 100, 1) ?>%"></i><span><?= date('j', strtotime($d)) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid2">
  <div class="card">
    <h3>Pedidos de la web por confirmar <?php if ($pend): ?><b class="badge"><?= count($pend) ?></b><?php endif; ?></h3>
    <?php if (!$pend): ?><p class="muted">No hay pedidos nuevos. Cuando un cliente mande su pedido por WhatsApp desde la tienda en línea, aparecerá aquí.</p>
    <?php else: ?><ul class="list">
      <?php foreach ($pend as $v): ?><li><a href="<?= url('venta', ['id' => $v['id']]) ?>"><b><?= e($v['cliente'] ?: 'Sin nombre') ?></b><span><?= e(folio('V', $v['id'])) ?> · <?= e(fecha_mx($v['fecha'], true)) ?></span><em><?= money($v['total']) ?></em></a></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </div>
  <div class="card">
    <h3>Próximas citas (7 días)</h3>
    <?php if (!$citas): ?><p class="muted">Sin citas en los próximos días.</p>
    <?php else: ?><ul class="list">
      <?php foreach ($citas as $c): ?><li><a href="<?= url('agenda', ['id' => $c['id'], 'd' => $c['fecha']]) ?>"><b><?= e($c['cliente'] ?: 'Sin nombre') ?></b><span><?= e(fecha_mx($c['fecha'])) ?> · <?= e(date('g:i a', strtotime($c['hora']))) ?></span><?= chip($c['estado'], ESTADOS_CITA) ?></a></li><?php endforeach; ?>
    </ul><?php endif; ?>
    <p class="sm"><a href="<?= url('cotizaciones') ?>"><?= $cots ?> <?= $cots == 1 ? 'cotización abierta' : 'cotizaciones abiertas' ?> →</a></p>
  </div>
</div>
<div class="card mt">
  <h3>Aviso de faltantes <?php if ($bajos): ?><b class="badge"><?= faltantes_count() ?></b><?php endif; ?></h3>
  <?php if ($bajos): ?><ul class="list low">
    <?php foreach ($bajos as $b): ?><li><a href="<?= url('inventario', ['ver' => 'faltantes']) ?>"><b><?= e($b['descripcion']) ?></b><span>mínimo <?= (int)$b['minimo'] ?></span><em><?= (int)$b['stock'] <= 0 ? 'Agotado' : 'Quedan ' . (int)$b['stock'] ?></em></a></li><?php endforeach; ?>
  </ul>
  <?php elseif ($sin_capturar): ?><p class="muted">Faltan <?= $sin_capturar ?> productos por capturar en el inventario. <a href="<?= url('inventario', ['ver' => 'sin']) ?>">Capturar existencias →</a></p>
  <?php else: ?><p class="muted">Todo el inventario está arriba del mínimo.</p><?php endif; ?>
</div>
<?php layout_fin();
