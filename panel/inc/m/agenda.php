<?php
$d = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['d'] ?? '')) ? $_GET['d'] : date('Y-m-d');
$lunes = date('Y-m-d', strtotime($d . ' -' . ((int)date('N', strtotime($d)) - 1) . ' days'));
$id = (int)($_GET['id'] ?? 0);

if (es_post()) {
  $acc = $_POST['accion'] ?? '';
  $cid = (int)($_POST['cita_id'] ?? 0);
  if ($acc === 'guardar') {
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['fecha'] ?? '')) ? $_POST['fecha'] : null;
    $hora = preg_match('/^\d{2}:\d{2}$/', (string)($_POST['hora'] ?? '')) ? $_POST['hora'] . ':00' : null;
    $cli = cliente_de_form();
    if (!$fecha || !$hora) { flash('Elige el día y la hora de la cita.', 'err'); redirect('agenda', ['d' => $d]); }
    $estado = array_key_exists($_POST['estado'] ?? '', ESTADOS_CITA) ? $_POST['estado'] : 'confirmada';
    $motivo = txt($_POST['motivo'] ?? '', 1000) ?: null;
    if ($cid) q('UPDATE citas SET cliente_id=?, fecha=?, hora=?, motivo=?, estado=? WHERE id=?', [$cli, $fecha, $hora, $motivo, $estado, $cid]);
    else q('INSERT INTO citas (cliente_id, fecha, hora, motivo, estado, origen, usuario_id, creado) VALUES (?,?,?,?,?,?,?,?)', [$cli, $fecha, $hora, $motivo, $estado, 'manual', usuario()['id'], ahora()]);
    flash('Cita guardada.');
    redirect('agenda', ['d' => $fecha]);
  }
  if ($acc === 'estado' && $cid && array_key_exists($_POST['estado'] ?? '', ESTADOS_CITA)) {
    q('UPDATE citas SET estado=? WHERE id=?', [$_POST['estado'], $cid]);
    flash('Cita: ' . ESTADOS_CITA[$_POST['estado']] . '.');
  }
  if ($acc === 'eliminar' && $cid) { q('DELETE FROM citas WHERE id=?', [$cid]); flash('Cita eliminada.'); }
  redirect('agenda', ['d' => $d]);
}

$edit = $id ? q1('SELECT * FROM citas WHERE id=?', [$id]) : null;
$fin = date('Y-m-d', strtotime($lunes . ' +6 days'));
$rows = q('SELECT ci.*, c.nombre cliente, c.telefono FROM citas ci LEFT JOIN clientes c ON c.id=ci.cliente_id WHERE ci.fecha BETWEEN ? AND ? ORDER BY ci.fecha, ci.hora', [$lunes, $fin])->fetchAll();
$por_dia = [];
foreach ($rows as $r) $por_dia[$r['fecha']][] = $r;
$pend = q("SELECT ci.*, c.nombre cliente, c.telefono FROM citas ci LEFT JOIN clientes c ON c.id=ci.cliente_id WHERE ci.estado='pendiente' AND ci.fecha >= CURDATE() ORDER BY ci.fecha, ci.hora")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

layout_inicio('Agenda y citas', 'agenda');
?>
<div class="head"><div><h1>Agenda y citas</h1><p class="muted">Visitas a la tienda para probarse gorras. Las que agendan desde la web llegan aquí como “por confirmar”.</p></div>
  <div class="acts"><a class="btn" href="#nueva" data-open="citaForm">+ Nueva cita</a></div></div>

<?php if ($pend): ?>
<div class="card hl"><h3>Por confirmar <b class="badge"><?= count($pend) ?></b></h3>
  <?php foreach ($pend as $c): ?><?php cita_fila($c, $d, true); ?><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card <?= $edit ? '' : 'hidden' ?>" id="citaForm">
  <h3><?= $edit ? 'Editar cita' : 'Nueva cita' ?></h3>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="cita_id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <?php selector_cliente($edit && $edit['cliente_id'] ? (int)$edit['cliente_id'] : null); ?>
    <div class="grid3">
      <label>Día <input type="date" name="fecha" required value="<?= e($edit['fecha'] ?? $d) ?>"></label>
      <label>Hora <input type="time" name="hora" required step="900" value="<?= e(substr($edit['hora'] ?? '12:00', 0, 5)) ?>"></label>
      <label>Estado <select name="estado"><?= select_opts(ESTADOS_CITA, $edit['estado'] ?? 'confirmada') ?></select></label>
    </div>
    <label>¿Qué quiere ver o probarse? <textarea name="motivo" rows="2"><?= e($edit['motivo'] ?? '') ?></textarea></label>
    <div class="acts end"><?php if ($edit): ?><a class="btn ghost" href="<?= url('agenda', ['d' => $d]) ?>">Cancelar</a><?php endif; ?><button class="btn">Guardar cita</button></div>
  </form>
</div>

<div class="weeknav">
  <a class="btn ghost sm" href="<?= url('agenda', ['d' => date('Y-m-d', strtotime($lunes . ' -7 days'))]) ?>">← Semana anterior</a>
  <b><?= e(fecha_mx($lunes)) ?> – <?= e(fecha_mx($fin)) ?></b>
  <a class="btn ghost sm" href="<?= url('agenda', ['d' => date('Y-m-d', strtotime($lunes . ' +7 days'))]) ?>">Semana siguiente →</a>
  <?php if ($lunes !== date('Y-m-d', strtotime('monday this week'))): ?><a class="sm" href="<?= url('agenda') ?>">Hoy</a><?php endif; ?>
</div>
<div class="week">
<?php for ($i = 0; $i < 7; $i++): $f = date('Y-m-d', strtotime($lunes . " +$i days")); ?>
  <div class="day <?= $f === date('Y-m-d') ? 'today' : '' ?>">
    <h4><?= $dias[$i] ?> <span><?= date('j', strtotime($f)) ?></span></h4>
    <?php if (empty($por_dia[$f])): ?><p class="muted sm">Sin citas</p><?php else: foreach ($por_dia[$f] as $c) cita_fila($c, $d, false); endif; ?>
  </div>
<?php endfor; ?>
</div>
<?php
layout_fin();

function cita_fila(array $c, string $d, bool $conFecha): void {
  $tel = $c['telefono'] ?? '';
  $msg = 'Hola ' . ($c['cliente'] ?: '') . ', te confirmamos tu visita a Be Cap el ' . fecha_mx($c['fecha']) . ' a las ' . date('g:i a', strtotime($c['hora'])) . '. Te esperamos en Av. Revolución 503, Local B, Pachuca.';
  ?>
  <div class="cita est-<?= e($c['estado']) ?>">
    <div class="ct"><b><?= e(date('g:i a', strtotime($c['hora']))) ?><?= $conFecha ? ' · ' . e(fecha_mx($c['fecha'])) : '' ?></b> <?= chip($c['estado'], ESTADOS_CITA) ?><?= $c['origen'] === 'web' ? ' <span class="tag">web</span>' : '' ?></div>
    <div><?= e($c['cliente'] ?: 'Sin nombre') ?><?= $tel ? ' · <span class="muted">' . e($tel) . '</span>' : '' ?></div>
    <?php if ($c['motivo']): ?><div class="muted sm"><?= e($c['motivo']) ?></div><?php endif; ?>
    <div class="ca">
      <?php foreach (['confirmada' => 'Confirmar', 'atendida' => 'Atendida', 'no_asistio' => 'No vino', 'cancelada' => 'Cancelar'] as $k => $lbl): if ($k === $c['estado']) continue; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="cita_id" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="<?= $k ?>"><button class="link sm"><?= $lbl ?></button></form>
      <?php endforeach; ?>
      <a class="link sm" href="<?= url('agenda', ['d' => $c['fecha'], 'id' => $c['id']]) ?>">Editar</a>
      <?php if ($tel): ?><a class="link sm wa-t" target="_blank" rel="noopener" href="https://wa.me/<?= e(tel_wa($tel)) ?>?text=<?= rawurlencode($msg) ?>">WhatsApp</a><?php endif; ?>
    </div>
  </div>
  <?php
}
