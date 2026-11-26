<?php
// Crea al primer administrador. Solo funciona mientras no exista ningún usuario.
if ((int)qv('SELECT COUNT(*) FROM usuarios') > 0) redirect('entrar');
// Sin clave de instalación configurada, el primer acceso queda cerrado por completo
if (strlen((string)(config()['setup_key'] ?? '')) < 16) {
  layout_inicio('Panel en preparación');
  ?><div class="login card"><img src="../img/logo.webp" alt="Be Cap" class="lg"><h1>Panel en preparación</h1><p class="muted">El acceso del equipo de Be Cap se activa durante la capacitación con APARECE.</p></div><?php
  layout_fin(); exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $clave_inst = (string)(config()['setup_key'] ?? '');
  if (!csrf_ok()) $error = 'La página expiró. Vuelve a intentarlo.';
  elseif (intentos('instalar', 60) >= 5) $error = 'Demasiados intentos. Espera una hora.';
  elseif (strlen($clave_inst) < 16 || !hash_equals($clave_inst, (string)($_POST['setup'] ?? ''))) { registrar_intento('instalar'); $error = 'La clave de instalación no es correcta.'; }
  else {
    $nombre = txt($_POST['nombre'] ?? '', 80);
    $usr = mb_strtolower(txt($_POST['usuario'] ?? '', 40));
    $pw = (string)($_POST['clave'] ?? '');
    if ($nombre === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $usr)) $error = 'Escribe un nombre y un usuario de 3 a 40 letras o números, sin espacios.';
    elseif (strlen($pw) < 10) $error = 'La contraseña debe tener al menos 10 caracteres.';
    elseif ($pw !== ($_POST['clave2'] ?? '')) $error = 'Las contraseñas no coinciden.';
    else {
      q('INSERT INTO usuarios (nombre, usuario, pass_hash, rol, creado) VALUES (?,?,?,?,?)', [$nombre, $usr, password_hash($pw, PASSWORD_DEFAULT), 'admin', ahora()]);
      session_regenerate_id(true);
      $_SESSION['uid'] = (int)db()->lastInsertId();
      flash('¡Listo! Tu usuario de administrador quedó creado.');
      redirect('resumen');
    }
  }
}
layout_inicio('Primer acceso');
?>
<form method="post" class="login card">
  <img src="../img/logo.webp" alt="Be Cap" class="lg">
  <h1>Primer acceso</h1>
  <p class="muted">Crea el usuario del administrador de Be Cap. Necesitas la clave de instalación que te dio APARECE.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Clave de instalación <input name="setup" type="password" required autocomplete="off"></label>
  <label>Tu nombre <input name="nombre" required maxlength="80" value="<?= e($_POST['nombre'] ?? '') ?>"></label>
  <label>Usuario <input name="usuario" required maxlength="40" autocapitalize="none" placeholder="ej. luis" value="<?= e($_POST['usuario'] ?? '') ?>"></label>
  <label>Contraseña (mínimo 10 caracteres) <input name="clave" type="password" required minlength="10" autocomplete="new-password"></label>
  <label>Repite la contraseña <input name="clave2" type="password" required minlength="10" autocomplete="new-password"></label>
  <button class="btn">Crear administrador</button>
</form>
<?php layout_fin();
