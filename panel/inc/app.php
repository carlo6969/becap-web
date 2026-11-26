<?php
// Núcleo del panel de control BeCap (APARECE · Plan Esencial)
declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');
mb_internal_encoding('UTF-8');

const PANEL_VERSION = '1.2';
const SCHEMA_VERSION = 3;

/* ---------- configuración (fuera del repositorio) ---------- */
function config(): array {
  static $c = null;
  if ($c !== null) return $c;
  $candidatos = [];
  if (!empty($_SERVER['DOCUMENT_ROOT'])) $candidatos[] = dirname($_SERVER['DOCUMENT_ROOT']) . '/becap-panel-config.php';
  $candidatos[] = __DIR__ . '/../_privado/config.php';
  foreach ($candidatos as $f) {
    if (is_file($f)) { $c = require $f; return $c; }
  }
  http_response_code(503);
  exit('El panel aún no está configurado.');
}

/* ---------- base de datos ---------- */
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = config();
  $dsn = 'mysql:host=' . ($c['db_host'] ?? 'localhost') . ';dbname=' . $c['db_name'] . ';charset=utf8mb4';
  try {
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  } catch (PDOException $e) {
    error_log('BeCap panel DB: ' . $e->getMessage());
    http_response_code(503);
    exit('No se pudo conectar a la base de datos. Intenta en unos minutos.');
  }
  $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
  instalar_esquema($pdo);
  return $pdo;
}

function instalar_esquema(PDO $pdo): void {
  try { if ((int)$pdo->query("SELECT valor FROM ajustes WHERE clave='schema'")->fetchColumn() >= SCHEMA_VERSION) return; }
  catch (PDOException $e) { /* primera vez: se crean las tablas */ }
  // Todas las sentencias son "IF NOT EXISTS": correrlas de nuevo agrega solo lo nuevo
  $sql = file_get_contents(__DIR__ . '/schema.sql');
  foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
  $pdo->prepare("UPDATE ajustes SET valor=? WHERE clave='schema'")->execute([(string)SCHEMA_VERSION]);
}

function q(string $sql, array $p = []): PDOStatement {
  $st = db()->prepare($sql);
  $st->execute($p);
  return $st;
}
function q1(string $sql, array $p = []) { return q($sql, $p)->fetch(); }
function qv(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
function ahora(): string { return date('Y-m-d H:i:s'); }

/* ---------- utilidades ---------- */
function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function money($n): string { return '$' . number_format((float)$n, 2, '.', ','); }
function num($v): float { return round((float)str_replace([',', '$', ' '], '', (string)$v), 2); }
function txt($v, int $max = 500): string { return mb_substr(trim((string)($v ?? '')), 0, $max); }
function folio(string $pref, $id): string { return $pref . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT); }
function fecha_mx($f, bool $hora = false): string {
  if (!$f) return '—';
  $t = strtotime((string)$f);
  $m = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'][(int)date('n', $t) - 1];
  return date('j', $t) . ' ' . $m . ' ' . date('Y', $t) . ($hora ? ' · ' . date('g:i a', $t) : '');
}
function tel_wa(?string $t): string {
  $d = preg_replace('/\D/', '', (string)$t);
  if (strlen($d) === 10) $d = '52' . $d;
  return $d;
}
function url(string $p, array $extra = []): string {
  return 'index.php?' . http_build_query(array_merge(['p' => $p], $extra));
}
function redirect(string $p, array $extra = []): void { header('Location: ' . url($p, $extra)); exit; }
function flash(?string $msg = null, string $tipo = 'ok') {
  if ($msg !== null) { $_SESSION['flash'] = [$msg, $tipo]; return null; }
  $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }
function es_https(): bool {
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/* ---------- límite de intentos ---------- */
function intentos(string $tipo, int $minutos): int {
  return (int)qv('SELECT COUNT(*) FROM intentos WHERE ip=? AND tipo=? AND creado > ?', [ip(), $tipo, date('Y-m-d H:i:s', time() - $minutos * 60)]);
}
function registrar_intento(string $tipo): void {
  q('INSERT INTO intentos (ip, tipo, creado) VALUES (?,?,?)', [ip(), $tipo, ahora()]);
  if (random_int(1, 50) === 1) q('DELETE FROM intentos WHERE creado < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

/* ---------- sesión y acceso ---------- */
function iniciar_sesion(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name('becap_panel');
  session_set_cookie_params(['lifetime' => 0, 'path' => '/panel/', 'secure' => es_https(), 'httponly' => true, 'samesite' => 'Lax']);
  session_start();
  if (!empty($_SESSION['uid']) && (time() - ($_SESSION['visto'] ?? 0)) > 60 * 60 * 10) { // 10 h sin actividad
    $_SESSION = []; session_regenerate_id(true);
  }
  $_SESSION['visto'] = time();
}
function usuario(): ?array {
  static $u = false;
  if ($u !== false) return $u;
  $u = null;
  if (!empty($_SESSION['uid'])) {
    $u = q1('SELECT id, nombre, usuario, rol FROM usuarios WHERE id=? AND activo=1', [$_SESSION['uid']]) ?: null;
    if (!$u) unset($_SESSION['uid']);
  }
  return $u;
}
function es_admin(): bool { return (usuario()['rol'] ?? '') === 'admin'; }
function requiere_admin(): void {
  if (!es_admin()) { flash('Esa sección es solo para administradores.', 'err'); redirect('resumen'); }
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool {
  return $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''));
}
function es_post(): bool {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
  if (!csrf_ok()) { flash('La sesión expiró. Vuelve a intentarlo.', 'err'); redirect($_GET['p'] ?? 'resumen'); }
  return true;
}

/* ---------- catálogo de productos (fuente: base de datos; la web lo lee de aquí) ---------- */
const CATS = ['vintage' => 'Vintage', 'originales' => 'Originales', 'disenador' => 'Diseñador', 'bucket' => 'Bucket', 'ropa' => 'Ropa', 'complementos' => 'Complementos'];
const AJUSTES = ['' => 'Sin especificar', 'Snapback' => 'Snapback', 'Strapback' => 'Strapback', 'Ajustable' => 'Ajustable', 'Flex / cerrada' => 'Flex / cerrada'];

// Primera vez: importa los productos que ya estaban en la web (productos.js) con sus fotos y variantes
function importar_catalogo_inicial(): void {
  if ((int)qv('SELECT COUNT(*) FROM productos') > 0) return;
  $f = __DIR__ . '/../../productos.js';
  if (!is_file($f)) return;
  $js = file_get_contents($f);
  $data = json_decode(rtrim(substr($js, (int)strpos($js, '[')), "; \n\r\t"), true);
  if (!is_array($data)) return;
  $pdo = db(); $pdo->beginTransaction();
  foreach ($data as $p) {
    $id = (int)$p['id'];
    $imgs = []; for ($k = 0; $k < (int)($p['n'] ?? 0); $k++) $imgs[] = "img/p/$id-$k.webp";
    q('INSERT INTO productos (id, slug, nombre, categoria, marca, ajuste, precio, precio_antes, descripcion, opciones, imagenes, publicado, nuevo, mas_vendido, creado, actualizado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$id, (string)$p['h'], (string)$p['t'], (string)$p['c'], (string)$p['b'], (string)$p['f'], (float)$p['p'], (float)$p['cp'], (string)$p['d'], json_encode($p['o'] ?? [], JSON_UNESCAPED_UNICODE), json_encode($imgs), 1, (int)!empty($p['new']), (int)!empty($p['bs']), ahora(), ahora()]);
    if (!empty($p['v'])) foreach ($p['v'] as $v) inventario_crear($id, (string)$v[0], (string)$p['t'], (string)$p['c'], (float)$v[1], (int)!empty($v[2]));
    else inventario_crear($id, null, (string)$p['t'], (string)$p['c'], (float)$p['p'], (int)!empty($p['a']));
  }
  $pdo->commit();
}
function inventario_crear(int $pid, ?string $variante, string $nombre, string $cat, float $precio, int $disp = 1): int {
  q('INSERT INTO inventario (producto_web_id, variante, descripcion, categoria, precio, disponible, actualizado) VALUES (?,?,?,?,?,?,?)',
    [$pid, $variante, mb_substr($nombre . ($variante ? " ($variante)" : ''), 0, 190), $cat, $precio, $disp, ahora()]);
  return (int)db()->lastInsertId();
}
function imagenes(array $p): array { $a = json_decode((string)($p['imagenes'] ?? '[]'), true); return is_array($a) ? $a : []; }
function opciones(array $p): array { $a = json_decode((string)($p['opciones'] ?? '[]'), true); return is_array($a) ? $a : []; }
function disponible(array $inv): bool { return $inv['stock'] === null ? (bool)$inv['disponible'] : ((int)$inv['stock'] > 0 && (bool)$inv['disponible']); }

// Productos activos con sus variantes (id => producto)
function catalogo(): array {
  static $cat = null;
  if ($cat !== null) return $cat;
  importar_catalogo_inicial();
  $cat = [];
  foreach (q('SELECT * FROM productos WHERE eliminado=0 ORDER BY id')->fetchAll() as $p) {
    $p['variantes'] = []; $p['imgs'] = imagenes($p); $p['opts'] = opciones($p);
    $cat[(int)$p['id']] = $p;
  }
  foreach (q('SELECT * FROM inventario WHERE activo=1 ORDER BY id')->fetchAll() as $i) {
    if (isset($cat[(int)$i['producto_web_id']])) $cat[(int)$i['producto_web_id']]['variantes'][] = $i;
  }
  return $cat;
}
// Lista plana para el buscador de productos en ventas y cotizaciones
function catalogo_opciones(): array {
  $o = [];
  foreach (catalogo() as $p) foreach ($p['variantes'] as $v) $o[] = ['id' => (int)$p['id'], 'inv' => (int)$v['id'], 'd' => $v['descripcion'], 'p' => (float)$v['precio']];
  return $o;
}
function nuevo_producto_id(): int { return (int)qv('SELECT COALESCE(MAX(id),-1)+1 FROM productos'); }
// Carpeta de fotos de productos. Va fuera de la carpeta pública para que una nueva publicación del sitio no las borre.
function dir_fotos(): string {
  $c = config();
  if (!empty($c['dir_fotos'])) return rtrim($c['dir_fotos'], '/');
  if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $d = dirname($_SERVER['DOCUMENT_ROOT']) . '/becap-fotos';
    if (is_dir($d) || @mkdir($d, 0755, true)) return $d;
  }
  return __DIR__ . '/../../subidas/productos';
}
function img_panel(string $src): string { return preg_match('~^https?://~', $src) ? $src : '../' . ltrim($src, '/'); }

/* ---------- inventario (regalo de APARECE: módulo del plan Negocio) ---------- */
function inventario_sync(): void { importar_catalogo_inicial(); }
function inventario_de_partida(array $pa): ?int {
  if (!empty($pa['inventario_id']) && qv('SELECT id FROM inventario WHERE id=?', [$pa['inventario_id']])) return (int)$pa['inventario_id'];
  if ($pa['producto_web_id'] === null) return null;
  $id = qv('SELECT id FROM inventario WHERE producto_web_id=? AND descripcion=?', [$pa['producto_web_id'], mb_substr($pa['descripcion'], 0, 190)]);
  return $id ? (int)$id : null;
}
function mover_stock(int $inv, int $cant, string $tipo, ?string $nota = null, ?int $venta = null): int {
  // Mientras la existencia no se haya capturado, las ventas se registran pero no mueven el número
  if (in_array($tipo, ['venta', 'devolucion'], true) && qv('SELECT stock IS NULL FROM inventario WHERE id=?', [$inv])) {
    q('INSERT INTO movimientos (inventario_id, tipo, cantidad, stock_final, nota, venta_id, usuario_id, creado) VALUES (?,?,?,NULL,?,?,?,?)', [$inv, $tipo, $cant, $nota, $venta, usuario()['id'] ?? null, ahora()]);
    return PHP_INT_MAX;
  }
  q('UPDATE inventario SET stock=COALESCE(stock,0)+?, actualizado=? WHERE id=?', [$cant, ahora(), $inv]);
  $final = (int)qv('SELECT stock FROM inventario WHERE id=?', [$inv]);
  q('INSERT INTO movimientos (inventario_id, tipo, cantidad, stock_final, nota, venta_id, usuario_id, creado) VALUES (?,?,?,?,?,?,?,?)', [$inv, $tipo, $cant, $final, $nota, $venta, usuario()['id'] ?? null, ahora()]);
  return $final;
}
// Deja el inventario de acuerdo con la venta: descuenta si está confirmada y regresa si se cancela o cambia.
// Devuelve los productos que quedaron en negativo.
function venta_sincronizar_stock(int $venta_id): array {
  $v = q1('SELECT estado FROM ventas WHERE id=?', [$venta_id]);
  $quiere = [];
  if ($v && $v['estado'] === 'confirmada') foreach (partidas('venta', $venta_id) as $pa) {
    if ($inv = inventario_de_partida($pa)) $quiere[$inv] = ($quiere[$inv] ?? 0) + (int)round((float)$pa['cantidad']);
  }
  $aplicado = [];
  foreach (q("SELECT inventario_id, -SUM(cantidad) n FROM movimientos WHERE venta_id=? AND tipo IN ('venta','devolucion') GROUP BY inventario_id", [$venta_id])->fetchAll() as $r) $aplicado[(int)$r['inventario_id']] = (int)$r['n'];
  $neg = [];
  foreach (array_unique(array_merge(array_keys($quiere), array_keys($aplicado))) as $inv) {
    $diff = ($quiere[$inv] ?? 0) - ($aplicado[$inv] ?? 0);
    if ($diff === 0) continue;
    $final = mover_stock($inv, -$diff, $diff > 0 ? 'venta' : 'devolucion', folio('V', $venta_id), $venta_id);
    if ($final < 0) $neg[] = qv('SELECT descripcion FROM inventario WHERE id=?', [$inv]);
  }
  return $neg;
}
function faltantes_count(): int {
  return (int)qv('SELECT COUNT(*) FROM inventario i JOIN productos p ON p.id=i.producto_web_id AND p.eliminado=0 WHERE i.activo=1 AND i.stock IS NOT NULL AND i.stock <= i.minimo');
}

/* ---------- partidas (renglones de venta o cotización) ---------- */
function leer_partidas(): array {
  $out = [];
  $d = $_POST['it_d'] ?? []; $c = $_POST['it_c'] ?? []; $pr = $_POST['it_p'] ?? []; $pid = $_POST['it_id'] ?? []; $inv = $_POST['it_inv'] ?? [];
  foreach ((array)$d as $i => $desc) {
    $desc = txt($desc, 200);
    if ($desc === '') continue;
    $cant = max(0.0, num($c[$i] ?? 1)); if ($cant <= 0) $cant = 1;
    $precio = max(0.0, num($pr[$i] ?? 0));
    $id = ($pid[$i] ?? '') !== '' ? (int)$pid[$i] : null;
    $iv = ($inv[$i] ?? '') !== '' ? (int)$inv[$i] : null;
    $out[] = ['producto_web_id' => $id, 'inventario_id' => $iv, 'descripcion' => $desc, 'cantidad' => $cant, 'precio' => $precio, 'importe' => round($cant * $precio, 2)];
  }
  return $out;
}
function guardar_partidas(string $doc, int $id, array $items): float {
  q('DELETE FROM partidas WHERE doc=? AND doc_id=?', [$doc, $id]);
  $sub = 0.0;
  foreach ($items as $it) {
    q('INSERT INTO partidas (doc, doc_id, producto_web_id, inventario_id, descripcion, cantidad, precio, importe) VALUES (?,?,?,?,?,?,?,?)',
      [$doc, $id, $it['producto_web_id'], $it['inventario_id'] ?? null, $it['descripcion'], $it['cantidad'], $it['precio'], $it['importe']]);
    $sub += $it['importe'];
  }
  return round($sub, 2);
}
function partidas(string $doc, int $id): array {
  return q('SELECT * FROM partidas WHERE doc=? AND doc_id=? ORDER BY id', [$doc, $id])->fetchAll();
}

/* ---------- clientes ---------- */
function cliente_buscar_o_crear(string $nombre, ?string $tel, string $origen = 'manual', ?string $dir = null): ?int {
  $nombre = txt($nombre, 120);
  if ($nombre === '') return null;
  $digitos = preg_replace('/\D/', '', (string)$tel);
  if (strlen($digitos) >= 10) {
    $id = qv("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(telefono,' ',''),'-',''),'+','') LIKE ? ORDER BY id LIMIT 1", ['%' . substr($digitos, -10)]);
    if ($id) { if ($dir) q('UPDATE clientes SET direccion=COALESCE(NULLIF(direccion,\'\'),?), actualizado=? WHERE id=?', [$dir, ahora(), $id]); return (int)$id; }
  } else {
    $id = qv('SELECT id FROM clientes WHERE LOWER(nombre)=LOWER(?) ORDER BY id LIMIT 1', [$nombre]);
    if ($id) return (int)$id;
  }
  q('INSERT INTO clientes (nombre, telefono, direccion, origen, creado, actualizado) VALUES (?,?,?,?,?,?)', [$nombre, $tel ?: null, $dir, $origen, ahora(), ahora()]);
  return (int)db()->lastInsertId();
}
function clientes_opciones(): array {
  return q('SELECT id, nombre, telefono FROM clientes ORDER BY nombre')->fetchAll();
}
// Toma el cliente del formulario: uno existente (cliente_id) o uno nuevo (cliente_nuevo)
function cliente_de_form(): ?int {
  $id = (int)($_POST['cliente_id'] ?? 0);
  if ($id > 0 && qv('SELECT id FROM clientes WHERE id=?', [$id])) return $id;
  $nuevo = txt($_POST['cliente_nuevo'] ?? '', 120);
  if ($nuevo !== '') return cliente_buscar_o_crear($nuevo, txt($_POST['cliente_tel'] ?? '', 30) ?: null);
  return null;
}

/* ---------- etiquetas ---------- */
const ESTADOS_VENTA = ['por_confirmar' => 'Por confirmar', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'];
const ESTADOS_COT = ['borrador' => 'Borrador', 'enviada' => 'Enviada', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'];
const ESTADOS_CITA = ['pendiente' => 'Por confirmar', 'confirmada' => 'Confirmada', 'atendida' => 'Atendida', 'cancelada' => 'Cancelada', 'no_asistio' => 'No asistió'];
const ORIGENES = ['web' => 'Página web', 'whatsapp' => 'WhatsApp', 'tienda' => 'Tienda', 'otro' => 'Otro'];
const METODOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta (terminal)', 'deposito' => 'Depósito', 'otro' => 'Otro'];

function chip(string $estado, array $mapa): string {
  return '<span class="chip chip-' . e($estado) . '">' . e($mapa[$estado] ?? $estado) . '</span>';
}
function chip_pago(array $v): string {
  if ($v['estado'] === 'cancelada') return '';
  $saldo = (float)$v['total'] - (float)$v['pagado'];
  if ((float)$v['total'] > 0 && $saldo <= 0.009) return '<span class="chip chip-pagada">Pagada</span>';
  if ((float)$v['pagado'] > 0) return '<span class="chip chip-parcial">Abono</span>';
  return '<span class="chip chip-sinpago">Sin pago</span>';
}
function select_opts(array $mapa, $actual): string {
  $h = '';
  foreach ($mapa as $k => $v) $h .= '<option value="' . e($k) . '"' . ((string)$k === (string)$actual ? ' selected' : '') . '>' . e($v) . '</option>';
  return $h;
}

/* ---------- plantilla ---------- */
function layout_inicio(string $titulo, string $activo = ''): void {
  $u = usuario();
  $pend = $u ? (int)qv("SELECT COUNT(*) FROM ventas WHERE estado='por_confirmar'") : 0;
  $citas = $u ? (int)qv("SELECT COUNT(*) FROM citas WHERE estado='pendiente' AND fecha >= CURDATE()") : 0;
  $menu = [
    'resumen' => ['Resumen', 'M3 13h8V3H3zm0 8h8v-6H3zm10 0h8V11h-8zm0-18v6h8V3z', 0],
    'pos' => ['Punto de venta', 'M4 4h16v2H4zm1 3h14l1 5v2h-1v6H5v-6H4v-2zm2 7v4h6v-4zm8 0v4h2v-4z', 0],
    'ventas' => ['Ventas y cobros', 'M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4M1 2v2h2l3.6 7.6L5.2 14A2 2 0 0 0 7 17h12v-2H7.4l1.1-2h7.5a2 2 0 0 0 1.7-1l3.6-6.5A1 1 0 0 0 20.4 4H5.2l-.9-2zm16 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4', $pend],
    'cotizaciones' => ['Cotizaciones', 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-1 7V3.5L18.5 9zM8 13h8v2H8zm0 4h8v2H8z', 0],
    'agenda' => ['Agenda y citas', 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m0 16H5V9h14z', $citas],
    'clientes' => ['Clientes', 'M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6m0 2c-2.3 0-7 1.2-7 3.5V19h14v-2.5C15 14.2 10.3 13 8 13m8 0c-.3 0-.6 0-1 .1 1.2.8 2 1.9 2 3.4V19h6v-2.5c0-2.3-4.7-3.5-7-3.5', 0],
    'productos' => ['Productos de la web', 'M21 16.5c0 .4-.2.7-.5.9l-7.9 4.4a1 1 0 0 1-1.2 0l-7.9-4.4a1 1 0 0 1-.5-.9v-9c0-.4.2-.7.5-.9l7.9-4.4a1 1 0 0 1 1.2 0l7.9 4.4c.3.2.5.5.5.9zM12 4.2 6 7.5l6 3.3 6-3.3z', 0],
    'inventario' => ['Inventario', 'M20 2H4a2 2 0 0 0-2 2v3a2 2 0 0 0 1 1.7V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.7A2 2 0 0 0 22 7V4a2 2 0 0 0-2-2m-5 12H9v-2h6zm5-7H4V4h16z', $u ? faltantes_count() : 0],
    'reportes' => ['Reportes', 'M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z', 0],
  ];
  if (es_admin()) $menu['usuarios'] = ['Usuarios', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8m0 2c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4', 0];
  ?><!doctype html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · Panel Be Cap</title>
<link rel="icon" href="../img/logo.webp">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Manrope:wght@400..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/panel.css?v=<?= PANEL_VERSION ?>">
</head>
<body class="<?= $u ? 'app' : 'solo' ?>">
<?php if ($u): ?>
<aside class="side" id="side">
  <a class="brand" href="<?= url('resumen') ?>"><img src="../img/logo.webp" alt="Be Cap"><span>Panel</span></a>
  <nav>
    <?php foreach ($menu as $k => [$lbl, $ico, $badge]): ?>
      <a href="<?= url($k) ?>" class="<?= $activo === $k ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="<?= $ico ?>"/></svg><span><?= e($lbl) ?></span><?php if ($badge): ?><b class="badge"><?= $badge ?></b><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="me">
    <a href="<?= url('cuenta') ?>" class="<?= $activo === 'cuenta' ? 'on' : '' ?>"><span class="av"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span><span><b><?= e($u['nombre']) ?></b><small><?= $u['rol'] === 'admin' ? 'Administrador' : 'Vendedor' ?></small></span></a>
    <form method="post" action="<?= url('salir') ?>"><?= csrf_field() ?><button class="link">Cerrar sesión</button></form>
    <a class="toweb" href="../" target="_blank" rel="noopener">Ver la tienda ↗</a>
  </div>
</aside>
<header class="topm"><button id="burger" aria-label="Menú"><svg viewBox="0 0 24 24"><path d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg></button><img src="../img/logo.webp" alt="Be Cap"><span><?= e($titulo) ?></span></header>
<div class="scrim" id="scrim"></div>
<?php endif; ?>
<main class="main">
<?php if ($f = flash()): ?><div class="flash flash-<?= e($f[1]) ?>"><?= e($f[0]) ?></div><?php endif;
}

function layout_fin(): void {
  ?>
</main>
<script src="assets/panel.js?v=<?= PANEL_VERSION ?>"></script>
</body>
</html><?php
}

// Editor de partidas compartido por ventas y cotizaciones
function editor_partidas(array $items, float $descuento = 0, float $envio = 0): void {
  if (!$items) $items = [['producto_web_id' => null, 'inventario_id' => null, 'descripcion' => '', 'cantidad' => 1, 'precio' => 0]];
  ?>
  <div class="card">
    <h3>Productos</h3>
    <div class="items" id="items">
      <div class="it it-h"><span>Producto o servicio</span><span>Cant.</span><span>Precio</span><span>Importe</span><span></span></div>
      <?php foreach ($items as $it): ?>
      <div class="it">
        <input type="hidden" name="it_id[]" value="<?= e($it['producto_web_id']) ?>"><input type="hidden" name="it_inv[]" value="<?= e($it['inventario_id'] ?? '') ?>">
        <input name="it_d[]" list="catalogo" value="<?= e($it['descripcion']) ?>" placeholder="Busca en el catálogo o escribe…" autocomplete="off">
        <input name="it_c[]" type="number" min="1" step="1" value="<?= e((float)$it['cantidad']) ?>" inputmode="numeric">
        <input name="it_p[]" type="number" min="0" step="0.01" value="<?= e((float)$it['precio']) ?>" inputmode="decimal">
        <output>$0.00</output>
        <button type="button" class="x" data-quitar aria-label="Quitar">×</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn ghost sm" id="addItem">+ Agregar renglón</button>
    <div class="totals">
      <label>Descuento <input name="descuento" type="number" min="0" step="0.01" value="<?= e($descuento) ?>"></label>
      <label>Envío <input name="envio" type="number" min="0" step="0.01" value="<?= e($envio) ?>"></label>
      <div class="sum"><span>Subtotal <b id="tSub">$0.00</b></span><span class="big">Total <b id="tTot">$0.00</b></span></div>
    </div>
  </div>
  <datalist id="catalogo"><?php foreach (catalogo_opciones() as $o): ?><option value="<?= e($o['d']) ?>"><?php endforeach; ?></datalist>
  <script type="application/json" id="catData"><?= json_encode(catalogo_opciones(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php
}

// Selector de cliente (existente o nuevo)
function selector_cliente(?int $actual): void {
  ?>
  <div class="grid2">
    <label>Cliente
      <select name="cliente_id" id="cliSel">
        <option value="0">— Cliente nuevo o mostrador —</option>
        <?php foreach (clientes_opciones() as $c): ?><option value="<?= $c['id'] ?>"<?= $actual === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?><?= $c['telefono'] ? ' · ' . e($c['telefono']) : '' ?></option><?php endforeach; ?>
      </select>
    </label>
    <div class="grid2 cli-nuevo" id="cliNuevo">
      <label>Nombre del cliente nuevo <input name="cliente_nuevo" maxlength="120" placeholder="Opcional"></label>
      <label>WhatsApp <input name="cliente_tel" maxlength="30" inputmode="tel" placeholder="771 000 0000"></label>
    </div>
  </div>
  <?php
}
