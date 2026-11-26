<?php
// Entrega las fotos de productos subidas desde el panel (se guardan fuera de la carpeta pública).
require __DIR__ . '/../inc/app.php';
$f = (string)($_GET['f'] ?? '');
if (!preg_match('/^\d+-[a-f0-9]{10}\.(webp|jpg|png)$/', $f, $m)) { http_response_code(404); exit; }
$ruta = dir_fotos() . '/' . $f;
if (!is_file($ruta)) { http_response_code(404); exit; }
header('Content-Type: ' . ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$m[1]]);
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000, immutable');
readfile($ruta);
