<?php
require __DIR__ . '/inc/app.php';
header('X-Content-Type-Options: nosniff');
iniciar_sesion();
db();

$p = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'resumen'));
$publicas = ['entrar', 'instalar'];
$modulos = ['pos', 'productos', 'producto', 'resumen', 'ventas', 'venta', 'cotizaciones', 'cotizacion', 'agenda', 'clientes', 'cliente', 'inventario', 'reportes', 'usuarios', 'cuenta', 'salir'];

$sin_usuarios = (int)qv('SELECT COUNT(*) FROM usuarios') === 0;
if ($sin_usuarios && $p !== 'instalar') redirect('instalar');
if (!in_array($p, $publicas, true) && !usuario()) redirect('entrar');
if (!in_array($p, array_merge($publicas, $modulos), true)) $p = 'resumen';

require __DIR__ . '/inc/m/' . ($p === 'venta' ? 'ventas' : ($p === 'cotizacion' ? 'cotizaciones' : ($p === 'cliente' ? 'clientes' : ($p === 'producto' ? 'productos' : $p)))) . '.php';
