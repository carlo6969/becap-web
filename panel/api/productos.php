<?php
// Catálogo para la página web, en el mismo formato que usaba productos.js (window.__P).
// La posición de cada producto en la lista es su id; los ocultos o eliminados van como {id, x:1}.
require __DIR__ . '/../inc/app.php';
header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, max-age=0');

$out = [];
foreach (catalogo() as $id => $p) {
  if (!$p['publicado']) continue;
  $v = []; $algo = false;
  foreach ($p['variantes'] as $i) { $d = disponible($i); $algo = $algo || $d; if ($i['variante'] !== null) $v[] = [$i['variante'], (float)$i['precio'], $d ? 1 : 0]; }
  $out[$id] = ['id' => $id, 'h' => $p['slug'], 't' => $p['nombre'], 'c' => $p['categoria'], 'b' => $p['marca'], 'f' => $p['ajuste'], 'p' => (float)$p['precio'], 'cp' => (float)$p['precio_antes'],
    'o' => $p['opts'], 'v' => $v, 'a' => $algo ? 1 : 0, 'n' => count($p['imgs']), 'im' => $p['imgs'], 'd' => (string)$p['descripcion'], 'bs' => (int)$p['mas_vendido'], 'new' => (int)$p['nuevo']];
}
$max = (int)qv('SELECT COALESCE(MAX(id),-1) FROM productos');
$lista = [];
for ($i = 0; $i <= $max; $i++) $lista[] = $out[$i] ?? ['id' => $i, 'x' => 1, 't' => '', 'p' => 0, 'o' => [], 'v' => [], 'a' => 0, 'n' => 0, 'im' => [], 'b' => '', 'c' => '', 'f' => '', 'cp' => 0, 'd' => '', 'bs' => 0, 'new' => 0];
echo 'window.__P=' . json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';';
