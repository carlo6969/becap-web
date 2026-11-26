import {minify} from 'terser'; import * as csso from 'csso'; import fs from 'fs';
const src='/home/claude/becap/src/';
const css=csso.minify(fs.readFileSync(src+'style.css','utf8')).css;
const store=(await minify(fs.readFileSync(src+'store.js','utf8'),{compress:{passes:1},mangle:true,format:{max_line_len:400}})).code;
const studio=(await minify(fs.readFileSync(src+'studio.js','utf8'),{module:true,compress:{passes:1},mangle:true,format:{max_line_len:400}})).code;
let body=fs.readFileSync(src+'body.html','utf8').replace(/\n\s*/g,'\n');
const P=fs.readFileSync('/home/claude/becap/products.json','utf8');
const html=`<!DOCTYPE html>
<html lang="es-MX">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Be Cap · Gorras, Streetwear y Accesorios en Pachuca | Tienda online</title>
<meta name="description" content="Gorras New Era, de diseñador y vintage, streetwear y accesorios. Tienda en Av. Revolución 503, Pachuca. Envíos a todo México, gratis desde $1,000.">
<meta name="theme-color" content="#ffd24a">
<link rel="icon" href="img/mascota.webp" type="image/webp">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=Manrope:wght@400..800&family=Archivo+Black&family=Graduate&family=Pirata+One&family=Yellowtail&family=Bungee&display=swap" rel="stylesheet">
<style>
${css}
</style>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
</head>
<body>
${body}
<script src="productos.js"></script>
<script>
${store}
</script>
<script type="module">
${studio}
</script>
</body>
</html>`;
fs.writeFileSync('/home/claude/becap/index.html',html); console.log(html.length);
