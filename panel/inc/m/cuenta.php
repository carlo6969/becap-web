<?php
$u = q1('SELECT * FROM usuarios WHERE id=?', [usuario()['id']]);
if (es_post()) {
  $nombre = txt($_POST['nombre'] ?? '', 80);
  if ($nombre !== '') q('UPDATE usuarios SET nombre=? WHERE id=?', [$nombre, $u['id']]);
  $nueva = (string)($_POST['nueva'] ?? '');
  if ($nueva !== '') {
    if (!password_verify((string)($_POST['actual'] ?? ''), $u['pass_hash'])) { flash('Tu contraseña actual no es correcta.', 'err'); redirect('cuenta'); }
    if (strlen($nueva) < 10) { flash('La contraseña nueva debe tener al menos 10 caracteres.', 'err'); redirect('cuenta'); }
    if ($nueva !== ($_POST['nueva2'] ?? '')) { flash('Las contraseñas nuevas no coinciden.', 'err'); redirect('cuenta'); }
    q('UPDATE usuarios SET pass_hash=? WHERE id=?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
    session_regenerate_id(true);
    flash('Contraseña actualizada.');
  } else flash('Datos guardados.');
  redirect('cuenta');
}
layout_inicio('Mi cuenta', 'cuenta');
?>
<div class="head"><div><h1>Mi cuenta</h1><p class="muted">Usuario: <b><?= e($u['usuario']) ?></b> · <?= $u['rol'] === 'admin' ? 'Administrador' : 'Vendedor' ?></p></div></div>
<form method="post" class="card stack narrow"><?= csrf_field() ?>
  <label>Nombre <input name="nombre" maxlength="80" value="<?= e($u['nombre']) ?>"></label>
  <h3 class="mt">Cambiar contraseña</h3>
  <label>Contraseña actual <input name="actual" type="password" autocomplete="current-password"></label>
  <label>Nueva contraseña (mínimo 10 caracteres) <input name="nueva" type="password" minlength="10" autocomplete="new-password"></label>
  <label>Repite la nueva contraseña <input name="nueva2" type="password" minlength="10" autocomplete="new-password"></label>
  <div class="acts end"><button class="btn">Guardar</button></div>
</form>
<?php layout_fin();
