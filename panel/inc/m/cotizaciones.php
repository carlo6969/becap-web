<?php
if ($p === 'cotizacion') {
  $id = (int)($_GET['id'] ?? 0);
  $c = $id ? q1('SELECT * FROM cotizaciones WHERE id=?', [$id]) : null;
  if ($id && !$c) { flash('Esa cotización no existe.', 'err'); redirect('cotizaciones'); }

  if (es_post()) {
    $acc = $_POST['accion'] ?? '';
    if ($acc === 'guardar') {
      $items = leer_partidas();
      if (!$items) { flash('Agrega al menos un producto.', 'err'); redirect('cotizacion', $id ? ['id' => $id] : ['nueva' => 1]); }
      $cli = cliente_de_form();
      $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['fecha'] ?? '')) ? $_POST['fecha'] : date('Y-m-d');
      $vig = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['vigencia'] ?? '')) ? $_POST['vigencia'] : null;
      $estado = array_key_exists($_POST['estado'] ?? '', ESTADOS_COT) ? $_POST['estado'] : 'borrador';
      $desc = max(0, num($_POST['descuento'] ?? 0)); $env = max(0, num($_POST['envio'] ?? 0));
      $campos = [$cli, $fecha, $vig, $estado, $desc, $env, txt($_POST['notas'] ?? '', 2000) ?: null];
      if (!$id) {
        q('INSERT INTO cotizaciones (cliente_id, fecha, vigencia, estado, descuento, envio, notas, usuario_id, creado, actualizado) VALUES (?,?,?,?,?,?,?,?,?,?)', array_merge($campos, [usuario()['id'], ahora(), ahora()]));
        $id = (int)db()->lastInsertId();
      } else {
        q('UPDATE cotizaciones SET cliente_id=?, fecha=?, vigencia=?, estado=?, descuento=?, envio=?, notas=?, actualizado=? WHERE id=?', array_merge($campos, [ahora(), $id]));
      }
      $sub = guardar_partidas('cotizacion', $id, $items);
      q('UPDATE cotizaciones SET subtotal=?, total=? WHERE id=?', [$sub, max(0, round($sub - $desc + $env, 2)), $id]);
      flash('Cotización guardada.');
    } elseif ($id && $acc === 'estado' && array_key_exists($_POST['estado'] ?? '', ESTADOS_COT)) {
      q('UPDATE cotizaciones SET estado=?, actualizado=? WHERE id=?', [$_POST['estado'], ahora(), $id]);
      flash('Cotización marcada como ' . mb_strtolower(ESTADOS_COT[$_POST['estado']]) . '.');
    } elseif ($id && $acc === 'eliminar' && es_admin()) {
      q("DELETE FROM partidas WHERE doc='cotizacion' AND doc_id=?", [$id]);
      q('DELETE FROM cotizaciones WHERE id=?', [$id]);
      flash('Cotización eliminada.');
      redirect('cotizaciones');
    }
    redirect('cotizacion', ['id' => $id]);
  }

  $items = $c ? partidas('cotizacion', $id) : [];
  $cli = $c && $c['cliente_id'] ? q1('SELECT * FROM clientes WHERE id=?', [$c['cliente_id']]) : null;

  /* ---- hoja para imprimir o guardar en PDF ---- */
  if ($c && isset($_GET['imprimir'])) {
    ?><!doctype html><html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cotización <?= e(folio('COT', $id)) ?> · Be Cap</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Manrope:wght@400..800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/panel.css?v=<?= PANEL_VERSION ?>"></head>
    <body class="hoja"><div class="sheet">
      <div class="sh-top"><img src="../img/logo.webp" alt="Be Cap"><div><h1>Cotización</h1><b><?= e(folio('COT', $id)) ?></b><br><span>Fecha: <?= e(fecha_mx($c['fecha'])) ?></span><?php if ($c['vigencia']): ?><br><span>Válida hasta: <?= e(fecha_mx($c['vigencia'])) ?></span><?php endif; ?></div></div>
      <div class="sh-cli"><small>Para</small><b><?= e($cli['nombre'] ?? 'Cliente') ?></b><?= $cli && $cli['telefono'] ? '<br>' . e($cli['telefono']) : '' ?></div>
      <table class="tbl"><thead><tr><th>Descripción</th><th class="r">Cant.</th><th class="r">Precio</th><th class="r">Importe</th></tr></thead><tbody>
        <?php foreach ($items as $it): ?><tr><td><?= e($it['descripcion']) ?></td><td class="r"><?= e((float)$it['cantidad']) ?></td><td class="r"><?= money($it['precio']) ?></td><td class="r"><?= money($it['importe']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
      <div class="sh-tot"><span>Subtotal <b><?= money($c['subtotal']) ?></b></span><?php if ((float)$c['descuento'] > 0): ?><span>Descuento <b>−<?= money($c['descuento']) ?></b></span><?php endif; ?><?php if ((float)$c['envio'] > 0): ?><span>Envío <b><?= money($c['envio']) ?></b></span><?php endif; ?><span class="big">Total <b><?= money($c['total']) ?> MXN</b></span></div>
      <?php if ($c['notas']): ?><p class="sh-notas"><?= nl2br(e($c['notas'])) ?></p><?php endif; ?>
      <p class="sh-pie">Be Cap Gorras &amp; Accesorios · Av. Revolución 503, Local B, Pachuca, Hgo. · WhatsApp 771 288 4753</p>
      <button class="btn noprint" onclick="window.print()">Imprimir o guardar como PDF</button>
    </div></body></html><?php
    return;
  }

  $titulo = $c ? 'Cotización ' . folio('COT', $id) : 'Nueva cotización';
  layout_inicio($titulo, 'cotizaciones');
  ?>
  <div class="head">
    <div><a class="back" href="<?= url('cotizaciones') ?>">← Cotizaciones</a><h1><?= e($titulo) ?></h1><?php if ($c): ?><p><?= chip($c['estado'], ESTADOS_COT) ?> <?php if ($c['venta_id']): ?><a href="<?= url('venta', ['id' => $c['venta_id']]) ?>" class="sm">Venta <?= e(folio('V', $c['venta_id'])) ?> →</a><?php endif; ?></p><?php endif; ?></div>
    <?php if ($c): ?><div class="acts">
      <a class="btn ghost" target="_blank" href="<?= url('cotizacion', ['id' => $id, 'imprimir' => 1]) ?>">Ver / PDF</a>
      <?php if ($cli && $cli['telefono']):
        $lineas = implode("\n", array_map(fn($it) => '• ' . (float)$it['cantidad'] . ' × ' . $it['descripcion'] . ' – ' . money($it['importe']), $items));
        $msg = "Hola {$cli['nombre']}, te comparto tu cotización " . folio('COT', $id) . " de Be Cap:\n\n$lineas\n\nTotal: " . money($c['total']) . " MXN" . ($c['vigencia'] ? "\nVálida hasta: " . fecha_mx($c['vigencia']) : '');
      ?><a class="btn wa" target="_blank" rel="noopener" href="https://wa.me/<?= e(tel_wa($cli['telefono'])) ?>?text=<?= rawurlencode($msg) ?>">Enviar por WhatsApp</a><?php endif; ?>
      <?php if (!$c['venta_id']): ?><a class="btn" href="<?= url('venta', ['nueva' => 1, 'de_cotizacion' => $id]) ?>">Convertir en venta</a><?php endif; ?>
    </div><?php endif; ?>
  </div>
  <form method="post" class="stack" id="docForm">
    <?= csrf_field() ?><input type="hidden" name="accion" value="guardar">
    <div class="card">
      <h3>Datos de la cotización</h3>
      <?php selector_cliente($c && $c['cliente_id'] ? (int)$c['cliente_id'] : null); ?>
      <div class="grid3">
        <label>Fecha <input name="fecha" type="date" value="<?= e($c['fecha'] ?? date('Y-m-d')) ?>"></label>
        <label>Válida hasta <input name="vigencia" type="date" value="<?= e($c['vigencia'] ?? date('Y-m-d', strtotime('+15 days'))) ?>"></label>
        <label>Estado <select name="estado"><?= select_opts(ESTADOS_COT, $c['estado'] ?? 'borrador') ?></select></label>
      </div>
      <label>Notas para el cliente <textarea name="notas" rows="2" placeholder="Ej. Bordado personalizado, tiempo de entrega 5 días…"><?= e($c['notas'] ?? '') ?></textarea></label>
    </div>
    <?php editor_partidas($items, (float)($c['descuento'] ?? 0), (float)($c['envio'] ?? 0)); ?>
    <div class="acts end"><button class="btn big">Guardar cotización</button></div>
  </form>
  <?php if ($c && es_admin()): ?>
  <div class="card danger"><h3>Más opciones</h3><form method="post" data-confirm="¿Eliminar esta cotización para siempre?"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><button class="btn red">Eliminar cotización</button></form></div>
  <?php endif;
  layout_fin();
  return;
}

/* ======================= LISTADO ======================= */
$f_estado = array_key_exists($_GET['estado'] ?? '', ESTADOS_COT) ? $_GET['estado'] : '';
$f_q = txt($_GET['q'] ?? '', 60);
$where = ['1=1']; $par = [];
if ($f_estado) { $where[] = 'co.estado=?'; $par[] = $f_estado; }
if ($f_q !== '') { $where[] = '(c.nombre LIKE ? OR co.id=?)'; array_push($par, "%$f_q%", (int)preg_replace('/\D/', '', $f_q)); }
$rows = q('SELECT co.*, c.nombre cliente FROM cotizaciones co LEFT JOIN clientes c ON c.id=co.cliente_id WHERE ' . implode(' AND ', $where) . ' ORDER BY co.fecha DESC, co.id DESC LIMIT 200', $par)->fetchAll();
layout_inicio('Cotizaciones', 'cotizaciones');
?>
<div class="head"><div><h1>Cotizaciones</h1><p class="muted">Para pedidos de mayoreo, gorras personalizadas o equipos.</p></div><div class="acts"><a class="btn" href="<?= url('cotizacion', ['nueva' => 1]) ?>">+ Nueva cotización</a></div></div>
<form class="filters" method="get"><input type="hidden" name="p" value="cotizaciones">
  <input name="q" placeholder="Buscar cliente o folio" value="<?= e($f_q) ?>">
  <select name="estado"><option value="">Todos los estados</option><?= select_opts(ESTADOS_COT, $f_estado) ?></select>
  <button class="btn ghost">Filtrar</button>
</form>
<div class="card flush">
<?php if (!$rows): ?><p class="muted pad">Aún no hay cotizaciones.</p><?php else: ?>
<table class="tbl click"><thead><tr><th>Folio</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Vigencia</th><th class="r">Total</th></tr></thead><tbody>
<?php foreach ($rows as $c): $vencida = $c['vigencia'] && $c['vigencia'] < date('Y-m-d') && in_array($c['estado'], ['borrador', 'enviada'], true); ?>
<tr data-href="<?= url('cotizacion', ['id' => $c['id']]) ?>"><td><a href="<?= url('cotizacion', ['id' => $c['id']]) ?>"><b><?= e(folio('COT', $c['id'])) ?></b></a></td><td><?= e(fecha_mx($c['fecha'])) ?></td><td><?= e($c['cliente'] ?: '—') ?></td><td><?= chip($c['estado'], ESTADOS_COT) ?></td><td class="<?= $vencida ? 'red' : 'muted' ?>"><?= e(fecha_mx($c['vigencia'])) ?><?= $vencida ? ' · vencida' : '' ?></td><td class="r"><?= money($c['total']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
<?php layout_fin();
