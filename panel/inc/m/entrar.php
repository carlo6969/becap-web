<?php
if (usuario()) redirect('resumen');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) {
    $error = 'La página expiró. Vuelve a intentarlo.';
  } elseif (intentos('login', 15) >= 6) {
    $error = 'Demasiados intentos. Espera 15 minutos e intenta de nuevo.';
  } else {
    $usr = mb_strtolower(txt($_POST['usuario'] ?? '', 40));
    $u = q1('SELECT * FROM usuarios WHERE usuario=? AND activo=1', [$usr]);
    if ($u && password_verify((string)($_POST['clave'] ?? ''), $u['pass_hash'])) {
      session_regenerate_id(true);
      $_SESSION['uid'] = (int)$u['id'];
      $_SESSION['csrf'] = bin2hex(random_bytes(32));
      if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) q('UPDATE usuarios SET pass_hash=? WHERE id=?', [password_hash((string)$_POST['clave'], PASSWORD_DEFAULT), $u['id']]);
      q('UPDATE usuarios SET ultimo_acceso=? WHERE id=?', [ahora(), $u['id']]);
      redirect('resumen');
    }
    registrar_intento('login');
    usleep(400000);
    $error = 'Usuario o contraseña incorrectos.';
  }
}
layout_inicio('Entrar');
?>
<form method="post" class="login card" autocomplete="on">
  <img src="../img/logo.webp" alt="Be Cap" class="lg">
  <h1>Panel de control</h1>
  <p class="muted">Acceso solo para el equipo de Be Cap.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Usuario <input name="usuario" required autocomplete="username" autocapitalize="none" value="<?= e($_POST['usuario'] ?? '') ?>"></label>
  <label>Contraseña <input name="clave" type="password" required autocomplete="current-password"></label>
  <button class="btn">Entrar</button>
  <a href="../" class="muted sm">← Volver a la tienda</a>
</form>
<?php layout_fin();
