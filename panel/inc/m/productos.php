<?php
// Productos de la página web: lo que se publique aquí es lo que ve el cliente en la tienda en línea
importar_catalogo_inicial();
const DIR_FOTOS = __DIR__ . '/../../../subidas/productos';
const MAX_FOTOS = 8;

function slugify(string $s): string {
  $s = strtolower(strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n']));
  return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: 'producto';
}
// Guarda una foto subida en /subidas/productos, la reduce a 1200 px y la convierte a WebP cuando el hosting lo permite
function guardar_foto(array $f, int $pid): ?string {
  if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024 || !is_uploaded_file($f['tmp_name'])) return null;
  $info = @getimagesize($f['tmp_name']);
  $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
  if (!$ext) return null;
  if (!is_dir(DIR_FOTOS) && !@mkdir(DIR_FOTOS, 0755, true)) return null;
  $base = $pid . '-' . bin2hex(random_bytes(5));
  if (function_exists('imagewebp') && ($src = @imagecreatefromstring((string)file_get_contents($f['tmp_name'])))) {
    [$w, $h] = [imagesx($src), imagesy($src)];
    $k = min(1, 1200 / max($w, $h));
    $dst = imagecreatetruecolor((int)round($w * $k), (int)round($h * $k));
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
    $ok = imagewebp($dst, DIR_FOTOS . "/$base.webp", 82);
    imagedestroy($src); imagedestroy($dst);
    return $ok ? "subidas/productos/$base.webp" : null;
  }
  return move_uploaded_file($f['tmp_name'], DIR_FOTOS . "/$base.$ext") ? "subidas/productos/$base.$ext" : null;
}

/* ======================= DETALLE / EDICIÓN ======================= */
if ($p === 'producto') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
  $pr = $id !== null ? q1('SELECT * FROM productos WHERE id=? AND eliminado=0', [$id]) : null;
  if ($id !== null && !$pr) { flash('Ese producto no existe.', 'err'); redirect('productos'); }

  if (es_post()) {
    requiere_admin();
    $acc = $_POST['accion'] ?? '';
    if ($acc === 'guardar') {
      $nombre = txt($_POST['nombre'] ?? '', 160);
      if ($nombre === '') { flash('Escribe el nombre del producto.', 'err'); redirect('producto', $pr ? ['id' => $id] : ['nuevo' => 1]); }
      $cat = array_key_exists($_POST['categoria'] ?? '', CATS) ? $_POST['categoria'] : 'vintage';
      $precio = max(0, num($_POST['precio'] ?? 0));
      $opts = array_values(array_filter(array_map(fn($o) => txt($o, 30), [$_POST['op1'] ?? '', $_POST['op2'] ?? ''])));
      $campos = [$nombre, $cat, txt($_POST['marca'] ?? '', 80) ?: 'Be Cap Selección', array_key_exists($_POST['ajuste'] ?? '', AJUSTES) ? $_POST['ajuste'] : '', $precio, max(0, num($_POST['precio_antes'] ?? 0)), txt($_POST['descripcion'] ?? '', 3000), json_encode($opts, JSON_UNESCAPED_UNICODE), isset($_POST['publicado']) ? 1 : 0, isset($_POST['nuevo']) ? 1 : 0, isset($_POST['mas_vendido']) ? 1 : 0];
      if (!$pr) {
        $id = nuevo_producto_id();
        q('INSERT INTO productos (nombre, categoria, marca, ajuste, precio, precio_antes, descripcion, opciones, publicado, nuevo, mas_vendido, id, slug, imagenes, creado, actualizado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($campos, [$id, slugify($nombre) . '-' . $id, '[]', ahora(), ahora()]));
      } else {
        q('UPDATE productos SET nombre=?, categoria=?, marca=?, ajuste=?, precio=?, precio_antes=?, descripcion=?, opciones=?, publicado=?, nuevo=?, mas_vendido=?, actualizado=? WHERE id=?', array_merge($campos, [ahora(), $id]));
      }
      // fotos: quitar, principal y nuevas
      $imgs = imagenes(q1('SELECT imagenes FROM productos WHERE id=?', [$id]));
      $quitar = array_map('intval', (array)($_POST['quitar_foto'] ?? []));
      $imgs = array_values(array_filter($imgs, fn($k) => !in_array($k, $quitar, true), ARRAY_FILTER_USE_KEY));
      $princ = (int)($_POST['principal'] ?? 0);
      if ($princ > 0 && isset($imgs[$princ]) && !in_array($princ, $quitar, true)) { $x = $imgs[$princ]; unset($imgs[$princ]); array_unshift($imgs, $x); $imgs = array_values($imgs); }
      $fallas = 0;
      if (!empty($_FILES['fotos']['name'][0])) foreach ($_FILES['fotos']['name'] as $i => $nm) {
        if (count($imgs) >= MAX_FOTOS) break;
        $ruta = guardar_foto(['name' => $nm, 'tmp_name' => $_FILES['fotos']['tmp_name'][$i], 'error' => $_FILES['fotos']['error'][$i], 'size' => $_FILES['fotos']['size'][$i]], $id);
        if ($ruta) $imgs[] = $ruta; else $fallas++;
      }
      q('UPDATE productos SET imagenes=? WHERE id=?', [json_encode($imgs), $id]);
      // variantes (cada una es un renglón del inventario)
      $vivas = [];
      if ($opts) {
        foreach ((array)($_POST['v_nombre'] ?? []) as $i => $vn) {
          $vn = txt($vn, 120); if ($vn === '') continue;
          $vp = ($_POST['v_precio'][$i] ?? '') === '' ? $precio : max(0, num($_POST['v_precio'][$i]));
          $disp = in_array((string)$i, (array)($_POST['v_disp'] ?? []), true) ? 1 : 0;
          $vid = (int)($_POST['v_id'][$i] ?? 0);
          if ($vid && qv('SELECT id FROM inventario WHERE id=? AND producto_web_id=?', [$vid, $id])) {
            q('UPDATE inventario SET variante=?, descripcion=?, categoria=?, precio=?, disponible=?, activo=1, actualizado=? WHERE id=?', [$vn, mb_substr("$nombre ($vn)", 0, 190), $cat, $vp, $disp, ahora(), $vid]);
          } else {
            $vid = inventario_crear($id, $vn, $nombre, $cat, $vp, $disp);
            $ini = (int)($_POST['v_stock'][$i] ?? 0);
            if (($_POST['v_stock'][$i] ?? '') !== '') mover_stock($vid, $ini, 'ajuste', 'Inventario inicial');
          }
          $vivas[] = $vid;
        }
      } else {
        $vid = (int)qv('SELECT id FROM inventario WHERE producto_web_id=? AND variante IS NULL ORDER BY activo DESC, id LIMIT 1', [$id]);
        $disp = isset($_POST['disponible']) ? 1 : 0;
        if ($vid) q('UPDATE inventario SET descripcion=?, categoria=?, precio=?, disponible=?, activo=1, actualizado=? WHERE id=?', [mb_substr($nombre, 0, 190), $cat, $precio, $disp, ahora(), $vid]);
        else { $vid = inventario_crear($id, null, $nombre, $cat, $precio, $disp); if (($_POST['stock_inicial'] ?? '') !== '') mover_stock($vid, (int)$_POST['stock_inicial'], 'ajuste', 'Inventario inicial'); }
        $vivas[] = $vid;
      }
      // las variantes que ya no están se desactivan (se conserva su historial)
      q('UPDATE inventario SET activo=0 WHERE producto_web_id=? AND id NOT IN (' . implode(',', array_map('intval', $vivas)) . ')', [$id]);
      flash(($pr ? 'Producto guardado.' : 'Producto creado.') . (isset($_POST['publicado']) ? ' Ya se ve en la página web.' : ' Está oculto en la página web.') . ($fallas ? " $fallas foto(s) no se pudieron subir: usa JPG, PNG o WebP de hasta 8 MB." : ''), $fallas ? 'err' : 'ok');
    } elseif ($pr && $acc === 'publicar') {
      q('UPDATE productos SET publicado=1-publicado, actualizado=? WHERE id=?', [ahora(), $id]);
      flash($pr['publicado'] ? 'Producto oculto en la página web.' : 'Producto publicado en la página web.');
    } elseif ($pr && $acc === 'eliminar') {
      q('UPDATE productos SET eliminado=1, publicado=0, actualizado=? WHERE id=?', [ahora(), $id]);
      q('UPDATE inventario SET activo=0 WHERE producto_web_id=?', [$id]);
      flash('Producto eliminado. Sus ventas anteriores se conservan.');
      redirect('productos');
    }
    redirect('producto', ['id' => $id]);
  }

  $vars = $pr ? q('SELECT * FROM inventario WHERE producto_web_id=? AND activo=1 ORDER BY id', [$id])->fetchAll() : [];
  $opts = $pr ? opciones($pr) : [];
  $imgs = $pr ? imagenes($pr) : [];
  $unica = $pr && !$opts ? ($vars[0] ?? null) : null;
  $marcas = q('SELECT DISTINCT marca FROM productos WHERE eliminado=0 ORDER BY marca')->fetchAll(PDO::FETCH_COLUMN);
  $titulo = $pr ? $pr['nombre'] : 'Nuevo producto';
  layout_inicio($titulo, 'productos');
  $ro = !es_admin();
  ?>
  <div class="head">
    <div><a class="back" href="<?= url('productos') ?>">← Productos de la web</a><h1><?= e($titulo) ?></h1>
      <?php if ($pr): ?><p><?= $pr['publicado'] ? '<span class="chip chip-confirmada">Publicado en la web</span>' : '<span class="chip">Oculto en la web</span>' ?> <span class="muted sm">ID <?= (int)$pr['id'] ?></span></p><?php endif; ?></div>
    <?php if ($pr && !$ro): ?><div class="acts">
      <a class="btn ghost" href="../#tienda" target="_blank" rel="noopener">Ver la tienda ↗</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="publicar"><button class="btn <?= $pr['publicado'] ? 'ghost' : '' ?>"><?= $pr['publicado'] ? 'Ocultar de la web' : 'Publicar en la web' ?></button></form>
    </div><?php endif; ?>
  </div>
  <?php if ($ro): ?><div class="flash">Solo un administrador puede crear o cambiar productos. Tú puedes consultarlos.</div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="stack" id="prodForm">
    <?= csrf_field() ?><input type="hidden" name="accion" value="guardar">
    <fieldset class="bare" <?= $ro ? 'disabled' : '' ?>>
    <div class="card">
      <h3>Datos del producto</h3>
      <div class="stack">
        <label>Nombre <input name="nombre" required maxlength="160" value="<?= e($pr['nombre'] ?? '') ?>" placeholder="Ej. NEW ERA YANKEES 9FORTY NEGRA"></label>
        <div class="grid3">
          <label>Categoría <select name="categoria"><?= select_opts(CATS, $pr['categoria'] ?? 'originales') ?></select></label>
          <label>Marca <input name="marca" list="marcas" maxlength="80" value="<?= e($pr['marca'] ?? 'Be Cap Selección') ?>"></label>
          <label>Ajuste (gorras) <select name="ajuste"><?= select_opts(AJUSTES, $pr['ajuste'] ?? '') ?></select></label>
        </div>
        <div class="grid3">
          <label>Precio <input name="precio" type="number" min="0" step="0.01" required value="<?= e((float)($pr['precio'] ?? 0) ?: '') ?>"></label>
          <label>Precio antes (para mostrar descuento) <input name="precio_antes" type="number" min="0" step="0.01" value="<?= e((float)($pr['precio_antes'] ?? 0) ?: '') ?>" placeholder="Opcional"></label>
          <div class="checks">
            <label class="check"><input type="checkbox" name="publicado" <?= ($pr['publicado'] ?? 1) ? 'checked' : '' ?>> Publicado en la web</label>
            <label class="check"><input type="checkbox" name="nuevo" <?= !empty($pr['nuevo']) ? 'checked' : '' ?>> Etiqueta “Nuevo”</label>
            <label class="check"><input type="checkbox" name="mas_vendido" <?= !empty($pr['mas_vendido']) ? 'checked' : '' ?>> Etiqueta “Más vendido”</label>
          </div>
        </div>
        <label>Descripción <textarea name="descripcion" rows="4" maxlength="3000"><?= e($pr['descripcion'] ?? '') ?></textarea></label>
      </div>
      <datalist id="marcas"><?php foreach ($marcas as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist>
    </div>

    <div class="card">
      <h3>Fotos</h3>
      <?php if ($imgs): ?><div class="fotos">
        <?php foreach ($imgs as $k => $src): ?>
        <figure class="<?= $k === 0 ? 'principal' : '' ?>"><img src="<?= e(img_panel($src)) ?>" alt="Foto <?= $k + 1 ?>" loading="lazy">
          <figcaption><?php if ($k === 0): ?><span class="tag">Principal</span><?php else: ?><label class="check sm"><input type="radio" name="principal" value="<?= $k ?>"> Principal</label><?php endif; ?>
          <label class="check sm"><input type="checkbox" name="quitar_foto[]" value="<?= $k ?>"> Quitar</label></figcaption></figure>
        <?php endforeach; ?>
      </div><?php else: ?><p class="muted">Este producto aún no tiene fotos. En la web se mostrará el logo de Be Cap hasta que subas una.</p><?php endif; ?>
      <label class="drop">Subir fotos (JPG, PNG o WebP, hasta <?= MAX_FOTOS ?> por producto) <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple id="fotosIn"></label>
      <div class="fotos" id="fotosPrev"></div>
    </div>

    <div class="card">
      <h3>Tallas, colores y existencias</h3>
      <p class="muted sm">Si el producto viene en varias tallas o colores, escribe el nombre de cada opción. Cada combinación tiene su propio precio, existencia y disponibilidad.</p>
      <div class="grid2">
        <label>Opción 1 <input name="op1" id="op1" maxlength="30" value="<?= e($opts[0] ?? '') ?>" placeholder="Ej. Talla (déjalo vacío si es pieza única)"></label>
        <label>Opción 2 <input name="op2" id="op2" maxlength="30" value="<?= e($opts[1] ?? '') ?>" placeholder="Ej. Color (opcional)"></label>
      </div>
      <div id="sinVar" class="<?= $opts ? 'hidden' : '' ?>">
        <div class="grid3 mt">
          <label class="check"><input type="checkbox" name="disponible" <?= !$unica || $unica['disponible'] ? 'checked' : '' ?>> Disponible para vender</label>
          <?php if (!$unica): ?><label>Existencia inicial <input name="stock_inicial" type="number" step="1" placeholder="Opcional"></label><?php else: ?><p class="muted sm">Existencia actual: <b><?= $unica['stock'] === null ? 'sin capturar' : (int)$unica['stock'] ?></b> · se cambia en <a href="<?= url('inventario', ['q' => $pr['nombre']]) ?>">Inventario</a></p><?php endif; ?>
        </div>
      </div>
      <div id="conVar" class="<?= $opts ? '' : 'hidden' ?>">
        <div class="gen">
          <label>Valores de la opción 1 <input id="gen1" placeholder="Ej. S, M, L, XL"></label>
          <label>Valores de la opción 2 <input id="gen2" placeholder="Ej. Negro, Azul marino"></label>
          <button type="button" class="btn ghost sm" id="genBtn">Generar combinaciones</button>
        </div>
        <div class="vars" id="vars">
          <div class="vr vr-h"><span>Variante (como se ve en la web)</span><span>Precio</span><span>Existencia</span><span>Disponible</span><span></span></div>
          <?php foreach (($opts ? $vars : []) as $i => $v): ?>
          <div class="vr"><input type="hidden" name="v_id[<?= $i ?>]" value="<?= $v['id'] ?>"><input name="v_nombre[<?= $i ?>]" value="<?= e($v['variante']) ?>" required><input name="v_precio[<?= $i ?>]" type="number" min="0" step="0.01" value="<?= e((float)$v['precio']) ?>"><span class="muted sm stk"><?= $v['stock'] === null ? 'sin capturar' : (int)$v['stock'] ?></span><label class="check"><input type="checkbox" name="v_disp[]" value="<?= $i ?>" <?= $v['disponible'] ? 'checked' : '' ?>></label><button type="button" class="x" data-quitar-v aria-label="Quitar variante">×</button></div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn ghost sm" id="addVar">+ Agregar variante</button>
        <p class="muted sm">Las variantes se escriben así: <b>M / Negro</b> (opción 1 / opción 2). La existencia de las variantes que ya existen se cambia en Inventario.</p>
      </div>
    </div>
    </fieldset>
    <?php if (!$ro): ?><div class="acts end sticky-save"><button class="btn big"><?= $pr ? 'Guardar producto' : 'Crear producto' ?></button></div><?php endif; ?>
  </form>
  <?php if ($pr && !$ro): ?>
  <div class="card danger"><h3>Más opciones</h3><form method="post" data-confirm="¿Eliminar este producto? Desaparece de la web y del punto de venta; sus ventas anteriores se conservan."><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><button class="btn red">Eliminar producto</button></form></div>
  <?php endif;
  layout_fin();
  return;
}

/* ======================= LISTADO ======================= */
$f_q = txt($_GET['q'] ?? '', 60);
$f_cat = array_key_exists($_GET['cat'] ?? '', CATS) ? $_GET['cat'] : '';
$f_ver = in_array($_GET['ver'] ?? '', ['publicados', 'ocultos', 'sinfoto'], true) ? $_GET['ver'] : '';
$w = ['p.eliminado=0']; $par = [];
if ($f_q !== '') { $w[] = '(p.nombre LIKE ? OR p.marca LIKE ?)'; array_push($par, "%$f_q%", "%$f_q%"); }
if ($f_cat) { $w[] = 'p.categoria=?'; $par[] = $f_cat; }
if ($f_ver === 'publicados') $w[] = 'p.publicado=1';
if ($f_ver === 'ocultos') $w[] = 'p.publicado=0';
if ($f_ver === 'sinfoto') $w[] = "(p.imagenes IS NULL OR p.imagenes='[]')";
$rows = q('SELECT p.*, COUNT(i.id) nvar, SUM(i.stock) stock, SUM(i.stock IS NULL) sincap FROM productos p LEFT JOIN inventario i ON i.producto_web_id=p.id AND i.activo=1 WHERE ' . implode(' AND ', $w) . ' GROUP BY p.id ORDER BY p.publicado DESC, p.actualizado DESC, p.id DESC', $par)->fetchAll();
$tot = q1('SELECT COUNT(*) n, SUM(publicado) pub FROM productos WHERE eliminado=0');
layout_inicio('Productos de la web', 'productos');
?>
<div class="head"><div><h1>Productos de la web</h1><p class="muted"><?= (int)$tot['pub'] ?> publicados de <?= (int)$tot['n'] ?>. Lo que cambies aquí se ve al instante en la tienda en línea.</p></div>
  <div class="acts"><a class="btn ghost" href="../#tienda" target="_blank" rel="noopener">Ver la tienda ↗</a><?php if (es_admin()): ?><a class="btn" href="<?= url('producto', ['nuevo' => 1]) ?>">+ Nuevo producto</a><?php endif; ?></div></div>
<form class="filters" method="get"><input type="hidden" name="p" value="productos">
  <input name="q" placeholder="Buscar por nombre o marca" value="<?= e($f_q) ?>">
  <select name="cat"><option value="">Todas las categorías</option><?= select_opts(CATS, $f_cat) ?></select>
  <select name="ver"><option value="">Publicados y ocultos</option><option value="publicados"<?= $f_ver === 'publicados' ? ' selected' : '' ?>>Solo publicados</option><option value="ocultos"<?= $f_ver === 'ocultos' ? ' selected' : '' ?>>Solo ocultos</option><option value="sinfoto"<?= $f_ver === 'sinfoto' ? ' selected' : '' ?>>Sin foto</option></select>
  <button class="btn ghost">Filtrar</button>
</form>
<div class="pgrid">
<?php foreach ($rows as $r): $im = imagenes($r); ?>
  <a class="pcard <?= $r['publicado'] ? '' : 'off' ?>" href="<?= url('producto', ['id' => $r['id']]) ?>">
    <div class="ph"><?php if ($im): ?><img src="<?= e(img_panel($im[0])) ?>" alt="" loading="lazy"><?php else: ?><img src="../img/logo.webp" alt="" class="nofoto"><?php endif; ?>
      <?= $r['publicado'] ? '' : '<span class="chip">Oculto</span>' ?></div>
    <b><?= e($r['nombre']) ?></b>
    <span class="muted sm"><?= e(CATS[$r['categoria']] ?? $r['categoria']) ?> · <?= e($r['marca']) ?></span>
    <span class="pfoot"><b><?= money($r['precio']) ?></b><span class="muted sm"><?= (int)$r['nvar'] > 1 ? (int)$r['nvar'] . ' variantes · ' : '' ?><?= (int)$r['sincap'] === (int)$r['nvar'] ? 'sin capturar' : (int)$r['stock'] . ' pzs' ?></span></span>
  </a>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="muted">No hay productos con esos filtros.</p><?php endif; ?>
</div>
<?php layout_fin();
