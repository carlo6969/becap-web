<?php
requiere_admin();
$max = (int)(config()['max_usuarios'] ?? 2);
$activos = fn() => (int)qv('SELECT COUNT(*) FROM usuarios WHERE activo=1');
$admins = fn() => (int)qv("SELECT COUNT(*) FROM usuarios WHERE activo=1 AND rol='admin'");

if (es_post()) {
  $acc = $_POST['accion'] ?? '';
  $uid = (int)($_POST['uid'] ?? 0);
  $rol = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'vendedor';
  if ($acc === 'crear') {
    $nombre = txt($_POST['nombre'] ?? '', 80);
    $usr = mb_strtolower(txt($_POST['usuario'] ?? '', 40));
    $pw = (string)($_POST['clave'] ?? '');
    if ($activos() >= $max) flash("Tu plan incluye hasta $max usuarios activos. Desactiva uno o mejora el plan.", 'err');
    elseif ($nombre === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $usr)) flash('Escribe un nombre y un usuario de 3 a 40 letras o números, sin espacios.', 'err');
    elseif (strlen($pw) < 10) flash('La contraseña debe tener al menos 10 caracteres.', 'err');
    elseif (qv('SELECT id FROM usuarios WHERE usuario=?', [$usr])) flash('Ese usuario ya existe.', 'err');
    else { q('INSERT INTO usuarios (nombre, usuario, pass_hash, rol, creado) VALUES (?,?,?,?,?)', [$nombre, $usr, password_hash($pw, PASSWORD_DEFAULT), $rol, ahora()]); flash("Usuario $usr creado."); }
  } elseif ($uid && $acc === 'editar') {
    $u = q1('SELECT * FROM usuarios WHERE id=?', [$uid]);
    $activo = isset($_POST['activo']) ? 1 : 0;
    if (!$u) flash('Ese usuario no existe.', 'err');
    elseif ($uid === (int)usuario()['id'] && (!$activo || $rol !== 'admin')) flash('No puedes quitarte el acceso de administrador a ti mismo.', 'err');
    elseif ($activo && !$u['activo'] && $activos() >= $max) flash("Tu plan incluye hasta $max usuarios activos.", 'err');
    else {
      q('UPDATE usuarios SET nombre=?, rol=?, activo=? WHERE id=?', [txt($_POST['nombre'] ?? $u['nombre'], 80) ?: $u['nombre'], $rol, $activo, $uid]);
      $pw = (string)($_POST['clave'] ?? '');
      if ($pw !== '') { if (strlen($pw) < 10) flash('Datos guardados, pero la contraseña nueva debe tener al menos 10 caracteres.', 'err'); else q('UPDATE usuarios SET pass_hash=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $uid]); }
      if (!flash_pendiente()) flash('Usuario actualizado.');
    }
  } elseif ($uid && $acc === 'eliminar') {
    if ($uid === (int)usuario()['id']) flash('No puedes eliminar tu propio usuario.', 'err');
    else { q('DELETE FROM usuarios WHERE id=?', [$uid]); flash('Usuario eliminado.'); }
  }
  redirect('usuarios');
}
function flash_pendiente(): bool { return !empty($_SESSION['flash']); }

$rows = q('SELECT * FROM usuarios ORDER BY activo DESC, rol, nombre')->fetchAll();
layout_inicio('Usuarios', 'usuarios');
?>
<div class="head"><div><h1>Usuarios</h1><p class="muted"><?= $activos() ?> de <?= $max ?> usuarios activos (plan Esencial). Cada persona entra con su propio usuario.</p></div></div>
<div class="card flush">
<table class="tbl"><thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Último acceso</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?>
<tr class="<?= $u['activo'] ? '' : 'off' ?>"><td><b><?= e($u['nombre']) ?></b><?= $u['activo'] ? '' : ' <span class="tag">inactivo</span>' ?></td><td><?= e($u['usuario']) ?></td><td><?= $u['rol'] === 'admin' ? 'Administrador' : 'Vendedor' ?></td><td class="muted"><?= e(fecha_mx($u['ultimo_acceso'], true)) ?></td>
<td class="r"><button class="link sm" data-open="u<?= $u['id'] ?>">Editar</button></td></tr>
<tr class="hidden editrow" id="u<?= $u['id'] ?>"><td colspan="5">
  <form method="post" class="row-form"><?= csrf_field() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="uid" value="<?= $u['id'] ?>">
    <label>Nombre <input name="nombre" maxlength="80" value="<?= e($u['nombre']) ?>"></label>
    <label>Rol <select name="rol"><option value="vendedor">Vendedor</option><option value="admin"<?= $u['rol'] === 'admin' ? ' selected' : '' ?>>Administrador</option></select></label>
    <label>Nueva contraseña <input name="clave" type="password" minlength="10" placeholder="Déjalo vacío para no cambiarla" autocomplete="new-password"></label>
    <label class="check"><input type="checkbox" name="activo" <?= $u['activo'] ? 'checked' : '' ?>> Activo</label>
    <button class="btn">Guardar</button>
  </form>
  <?php if ($u['id'] != usuario()['id']): ?><form method="post" data-confirm="¿Eliminar a <?= e($u['nombre']) ?>? Ya no podrá entrar."><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><button class="link sm red">Eliminar usuario</button></form><?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php if ($activos() < $max): ?>
<form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="accion" value="crear">
  <h3>Agregar usuario</h3>
  <div class="grid2">
    <label>Nombre <input name="nombre" required maxlength="80" placeholder="Ej. Ana López"></label>
    <label>Usuario para entrar <input name="usuario" required maxlength="40" autocapitalize="none" placeholder="ej. ana"></label>
    <label>Contraseña (mínimo 10 caracteres) <input name="clave" type="password" required minlength="10" autocomplete="new-password"></label>
    <label>Rol <select name="rol"><option value="vendedor">Vendedor: ventas, cobros, cotizaciones, agenda, clientes e inventario</option><option value="admin">Administrador: todo, además de usuarios y eliminar registros</option></select></label>
  </div>
  <div class="acts end"><button class="btn">Crear usuario</button></div>
</form>
<?php else: ?>
<div class="card"><p class="muted">Ya tienes los <?= $max ?> usuarios que incluye el plan Esencial. Para más personas, el plan Negocio incluye hasta 8.</p></div>
<?php endif;
layout_fin();
