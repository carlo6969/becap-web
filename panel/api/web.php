<?php
// Recibe los pedidos y las citas que los clientes mandan por WhatsApp desde la tienda en línea.
// Quedan en el panel como "por confirmar" para que el equipo los revise.
require __DIR__ . '/../inc/app.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function responder(int $code, array $data): void { http_response_code($code); echo json_encode($data); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false]);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
  $oh = parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
  if (strcasecmp($oh, (string)($_SERVER['HTTP_HOST'] ?? '')) !== 0) responder(403, ['ok' => false]);
}
$raw = file_get_contents('php://input', false, null, 0, 20001);
if ($raw === false || strlen($raw) > 20000) responder(413, ['ok' => false]);
$d = json_decode($raw, true);
if (!is_array($d)) responder(400, ['ok' => false]);

db();
if (intentos('web', 60) >= 20) responder(429, ['ok' => false]);
registrar_intento('web');

$tipo = $d['tipo'] ?? '';
$nombre = txt($d['nombre'] ?? '', 120);
if ($nombre === '') responder(422, ['ok' => false]);
$tel = txt(preg_replace('/[^\d+ ()-]/', '', (string)($d['telefono'] ?? '')), 30) ?: null;

if ($tipo === 'pedido') {
  $cat = catalogo();
  $items = [];
  foreach (array_slice((array)($d['items'] ?? []), 0, 30) as $it) {
    $pid = (int)($it['id'] ?? -1);
    $vt = txt($it['vt'] ?? '', 80);
    $cant = max(1, min(99, (int)($it['q'] ?? 1)));
    if (!isset($cat[$pid])) continue;                // solo productos que existen en el catálogo
    $p = $cat[$pid]; $precio = $p['precio'];
    foreach ($p['variantes'] as $v) if ($v['nombre'] === $vt) $precio = $v['precio'];
    $items[] = ['producto_web_id' => $pid, 'descripcion' => mb_substr($p['nombre'] . ($vt && $vt !== 'Default Title' ? " ($vt)" : ''), 0, 200), 'cantidad' => $cant, 'precio' => $precio, 'importe' => round($cant * $precio, 2)];
  }
  if (!$items) responder(422, ['ok' => false]);
  $entrega = ($d['entrega'] ?? '') === 'envio' ? 'envio' : 'tienda';
  $dir = $entrega === 'envio' ? (txt($d['direccion'] ?? '', 600) ?: null) : null;
  $cli = cliente_buscar_o_crear($nombre, $tel, 'web', $dir);
  q("INSERT INTO ventas (cliente_id, fecha, origen, estado, entrega, direccion, notas, creado, actualizado) VALUES (?,?,'web','por_confirmar',?,?,?,?,?)",
    [$cli, ahora(), $entrega, $dir, txt($d['notas'] ?? '', 1000) ?: null, ahora(), ahora()]);
  $id = (int)db()->lastInsertId();
  $sub = guardar_partidas('venta', $id, $items);
  q('UPDATE ventas SET subtotal=?, total=? WHERE id=?', [$sub, $sub, $id]);
  responder(200, ['ok' => true]);
}

if ($tipo === 'cita') {
  $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['fecha'] ?? '')) ? $d['fecha'] : null;
  $hora = preg_match('/^\d{1,2}:\d{2}$/', (string)($d['hora'] ?? '')) ? $d['hora'] . ':00' : null;
  if (!$fecha || !$hora || $fecha < date('Y-m-d') || $fecha > date('Y-m-d', strtotime('+120 days'))) responder(422, ['ok' => false]);
  $cli = cliente_buscar_o_crear($nombre, $tel, 'web');
  q("INSERT INTO citas (cliente_id, fecha, hora, motivo, estado, origen, creado) VALUES (?,?,?,?,'pendiente','web',?)", [$cli, $fecha, $hora, txt($d['motivo'] ?? '', 1000) ?: null, ahora()]);
  responder(200, ['ok' => true]);
}

responder(400, ['ok' => false]);
