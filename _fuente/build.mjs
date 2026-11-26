import {minify} from 'terser'; import * as csso from 'csso'; import fs from 'fs';
const src='./src/';
const LOCAL=process.argv[2]==='local';
const css=csso.minify(fs.readFileSync(src+'style.css','utf8')).css;
const store=(await minify(fs.readFileSync(src+'store.js','utf8'),{compress:{passes:1},mangle:true,format:{max_line_len:400}})).code;
const cat=fs.readFileSync(src+'catalogo.js','utf8').replace('export const TIPOS','const TIPOS');
const studioSrc=fs.readFileSync(src+'studio.js','utf8').replace('// TIPOS (catálogo','\n'+cat+'\n// TIPOS (catálogo');
const studio=(await minify(studioSrc,{module:true,compress:{passes:1},mangle:true,format:{max_line_len:400}})).code;
let body=fs.readFileSync(src+'body.html','utf8').replace(/\n\s*/g,'\n');
const three=LOCAL?{t:'/node_modules/three/build/three.module.js',a:'/node_modules/three/examples/jsm/'}:{t:'https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js',a:'https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/'};
const old=fs.readFileSync('./orig-index.html','utf8');
const head=old.slice(0,old.indexOf('<style>'));
const html=`${head}<style>
${css}
</style>
<script type="importmap">{"imports":{"three":"${three.t}","three/addons/":"${three.a}"}}</script>
</head>
<body>
${body}
<script src="panel/api/productos.php"></script>
<script>window.__P||document.write('<script src="productos.js"><\\/script>')</script>
<script src="conexion-panel.js" defer></script>
<script>
${store}
</script>
<script type="module">
${studio}
</script>
</body>
</html>`;
fs.writeFileSync(LOCAL?'./dist/index.local.html':'./dist/index.html',html); console.log(html.length);
