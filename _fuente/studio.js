import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DecalGeometry } from 'three/addons/geometries/DecalGeometry.js';
// TIPOS (catálogo del PDF "ORIGINAL COLORES BECAP") se inyecta aquí al construir: ver catalogo.js

const $ = s => document.querySelector(s);
const $$ = s => [...document.querySelectorAll(s)];
const WA = '527712884753';
const ASSETS = 'studio/';
const canvas = $('#cap3d'), stage = $('#stStage');

/* ---------------- estado ---------------- */
// Paleta de hilos para bordado / parche
const PAL = [
  ['Negro','#141414'],['Blanco','#f3f1ec'],['Crema','#eee2c8'],['Arena','#d6c19c'],['Caqui','#ad9466'],['Café','#5b3c28'],['Chocolate','#3a2419'],['Vino','#6b1d29'],
  ['Rojo','#c41f2e'],['Naranja','#e5652a'],['Amarillo Be Cap','#ffd24a'],['Verde botella','#1e4a33'],['Olivo','#67663a'],['Azul marino','#1a2340'],['Azul rey','#2541a6'],['Celeste','#9bc2e5'],
  ['Lila','#b6a2d8'],['Rosa','#efa6bf'],['Gris','#8a8c90'],['Gris oxford','#3a3c40'],['Dorado','#c9a24a'],['Plata','#c9ccd1']
];
const nameOf = hex => (PAL.find(p => p[1].toLowerCase() === hex.toLowerCase()) || ['Personalizado'])[0];
const defDecal = (o={}) => Object.assign({mode:'text', t1:'', t2:'', font:'Archivo Black', color:'#ffd24a', tech:'puff', patch:'#f3f1ec', size:80, y:0, img:null, tint:true, imgName:''}, o);
const NEGRO = Math.max(0, TIPOS[0].colors.findIndex(c => c.n === 'Negro'));
function defaults(){
  return {
    tipo: 0, color: NEGRO,
    // solo el frente trae diseño de inicio; las demás zonas se activan cuando el cliente las elige (cada una suma a la cotización)
    decals:{front:defDecal({t1:'BE CAP', size:90}), left:defDecal({mode:'none', t1:'JUAN', size:75, tech:'flat', color:'#f3f1ec', font:'Yellowtail'}), right:defDecal({mode:'none', t1:'PACHUCA', size:75, tech:'flat', color:'#f3f1ec'}), back:defDecal({mode:'none', t1:'771', size:65, tech:'flat', color:'#f3f1ec'})},
    qty:'1'
  };
}
let S = defaults();
const T = () => TIPOS[S.tipo];
const CUR = () => T().colors[S.color];

/* ---------------- renderer ---------------- */
let renderer;
try { renderer = new THREE.WebGLRenderer({canvas, antialias:true, alpha:true, preserveDrawingBuffer:true}); }
catch(e){ $('#stLoad').textContent = 'Tu navegador no soporta 3D. Prueba desde otro navegador o equipo.'; throw e; }
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.05;
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
const scene = new THREE.Scene();
const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
scene.environmentIntensity = 0.55;
const camera = new THREE.PerspectiveCamera(30, 1, 0.01, 10);
const controls = new OrbitControls(camera, canvas);
controls.target.set(0, 0.065, 0);
controls.enableDamping = true; controls.dampingFactor = 0.08;
controls.minDistance = 0.32; controls.maxDistance = 1.2; controls.enablePan = false;
controls.maxPolarAngle = Math.PI * 0.62;
const key = new THREE.DirectionalLight(0xfff4e6, 2.2);
key.position.set(0.5, 0.9, 0.6); key.castShadow = true;
key.shadow.mapSize.set(2048, 2048);
key.shadow.camera.left = key.shadow.camera.bottom = -0.25;
key.shadow.camera.right = key.shadow.camera.top = 0.25;
key.shadow.radius = 6; key.shadow.bias = -0.0004; key.shadow.normalBias = 0.002;
scene.add(key);
const fill = new THREE.DirectionalLight(0xe8eeff, 0.6); fill.position.set(-0.7, 0.3, -0.4); scene.add(fill);
scene.add(new THREE.HemisphereLight(0xffffff, 0xd9cfbf, 0.5));
const ground = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), new THREE.ShadowMaterial({ opacity: 0.18 }));
ground.rotation.x = -Math.PI/2; ground.receiveShadow = true; scene.add(ground);

/* ---------------- texturas de tela ---------------- */
const tl = new THREE.TextureLoader();
const aniso = renderer.capabilities.getMaxAnisotropy();
function tex(url, size, srgb = true){
  const t = tl.load(ASSETS + url); t.wrapS = t.wrapT = THREE.RepeatWrapping;
  t.repeat.set(1/size, 1/size); t.anisotropy = aniso; if(srgb) t.colorSpace = THREE.SRGBColorSpace; return t;
}
// las UV del modelo están en metros: el segundo valor es el tamaño del mosaico
const TX = {
  twill: tex('tex/twill_n.jpg', 0.018, false), cotton: tex('tex/cotton.jpg', 0.05), washed: tex('tex/washed.jpg', 0.13),
  camo: { beige: tex('tex/camo_beige.jpg', 0.2), gris: tex('tex/camo_gris.jpg', 0.2), verde: tex('tex/camo_verde.jpg', 0.2) },
};
const fabric = () => new THREE.MeshPhysicalMaterial({ color:0xffffff, roughness:0.92, metalness:0, normalMap:TX.twill, normalScale:new THREE.Vector2(0.55, 0.55), sheen:0.4, sheenRoughness:0.85, side:THREE.DoubleSide });
const M = { front:fabric(), back:fabric(), visor:fabric(), inner:fabric(), button:fabric(), strap:fabric(),
  stitch:new THREE.MeshStandardMaterial({roughness:0.8}), stitchV:new THREE.MeshStandardMaterial({roughness:0.8}) };
const PART = { cap_front:'front', cap_back:'back', visor:'visor', soport:'button', label:'inner', border:'inner', stitch:'stitch', stitch_visor:'stitchV', brooch:'strap', 'brooch-back':'strap' };

function setFabric(mat, hex, kind, camo){
  mat.map = camo ? TX.camo[camo] : (kind === 'washed' ? TX.washed : TX.cotton);
  mat.color.set(camo ? '#ffffff' : hex);
  mat.sheenColor.set(camo ? '#8a8070' : hex).lerp(new THREE.Color(1,1,1), kind === 'washed' ? 0.4 : 0.35);
  mat.sheen = kind === 'washed' ? 0.45 : 0.25;
  mat.roughness = kind === 'washed' ? 0.97 : 0.9;
  mat.normalScale.setScalar(kind === 'washed' ? 0.75 : 0.5);
  mat.needsUpdate = true;
}
const tone = (hex, f) => { const c = new THREE.Color(hex); return f >= 0 ? c.lerp(new THREE.Color(1,1,1), f) : c.multiplyScalar(1 + f); };
function recolor(){
  const t = T(), c = CUR(), kind = t.fabric;
  const crown = c.c || '#777', visor = c.v || crown, front = c.f || crown;
  setFabric(M.front, front, kind, c.camo); setFabric(M.back, crown, kind, c.camo); setFabric(M.visor, visor, kind, c.camo);
  setFabric(M.inner, crown, kind, c.camo); M.inner.color.multiplyScalar(0.85);
  setFabric(M.strap, crown, kind, c.camo);
  setFabric(M.button, t.id === 'algodon-2' ? visor : crown, kind, c.camo);   // en algodón 2 tonos el botón va del color de la visera
  const sc = c.camo ? '#5b5446' : crown, sv = c.camo ? '#5b5446' : visor;
  M.stitch.color.copy(kind === 'washed' ? tone(sc, 0.22) : tone(sc, -0.12));   // deslavado: costuras más claras
  M.stitchV.color.copy(kind === 'washed' ? tone(sv, 0.22) : tone(sv, -0.12));
  updateSummary();
}

/* ---------------- bordados (texto / logo · bordado 3D, 2D, parche, DTF) ---------------- */
// Medidas reales de colocación (las que usa Be Cap en producción)
const CM = 0.0115;              // 1 cm real en unidades del modelo (el modelo 3D es ~15% más grande que una gorra real)
const GAP_FRONT_CM = 2.5;       // el bordado frontal va a 2.5 cm de la visera
const GAP_SIDE_CM = 1.5;        // los nombres laterales van a 1.5 cm del borde inferior
const SEAM_FRONT = 0.0425;      // altura de la unión visera-corona al centro del frente
const EDGE_SIDE = 0.008;        // altura del borde inferior de la corona en los laterales
// zonas: w×h del lienzo (misma proporción que la caja), caja en el modelo [ancho, alto] y punto base de proyección
const ZONES = {
  front: {w:1024, h:540, ray:[[0, 0.1, 0.5],[0,0,-1]], mesh:'cap_front', box:[0.11, 0.058], keep:'x', tilt:0.45, anchor:'bottom', base:SEAM_FRONT + GAP_FRONT_CM*CM, gapCm:GAP_FRONT_CM},
  left:  {w:1024, h:360, ray:[[0.5, 0.04, -0.07],[-1,0,0]], mesh:'cap_back', box:[0.085, 0.03], keep:null, anchor:'bottom', base:EDGE_SIDE + GAP_SIDE_CM*CM, gapCm:GAP_SIDE_CM},
  right: {w:1024, h:360, ray:[[-0.5, 0.04, -0.07],[1,0,0]], mesh:'cap_back', box:[0.085, 0.03], keep:null, anchor:'bottom', base:EDGE_SIDE + GAP_SIDE_CM*CM, gapCm:GAP_SIDE_CM},
  back:  {w:1024, h:400, ray:[[0, 0.088, -0.6],[0,0,1]], mesh:'cap_back', box:[0.075, 0.029], keep:'x', anchor:'center'},
};
const ZL = {front:'Frente', left:'Lateral izquierdo', right:'Lateral derecho', back:'Atrás'};
const TECH = {puff:'Bordado 3D', flat:'Bordado 2D', patch:'Parche', dtf:'Estampado DTF'};
const WIDE = ['Archivo Black', 'Graduate', 'Bungee'];      // tipografías anchas: las únicas que se pueden hacer en bordado 3D
const FONTN = {'Archivo Black':'Bloque urbano', 'Graduate':'Varsity', 'Pirata One':'Gótica', 'Yellowtail':'Script / firma', 'Bungee':'Redondeada'};
const decalCanvas = {}, decalBump = {}, decalTex = {}, decalBumpTex = {}, decalMat = {}, decalMesh = {};
for(const z in ZONES){
  const {w, h} = ZONES[z];
  decalCanvas[z] = Object.assign(document.createElement('canvas'), {width:w, height:h});
  decalBump[z] = Object.assign(document.createElement('canvas'), {width:w, height:h});
  decalTex[z] = new THREE.CanvasTexture(decalCanvas[z]); decalTex[z].colorSpace = THREE.SRGBColorSpace; decalTex[z].anisotropy = 8;
  decalBumpTex[z] = new THREE.CanvasTexture(decalBump[z]);
  decalMat[z] = new THREE.MeshStandardMaterial({ map:decalTex[z], transparent:true, roughness:0.5, metalness:0, bumpMap:decalBumpTex[z], polygonOffset:true, polygonOffsetFactor:-4, depthWrite:false });
}
const mk = (w, h) => { const c = document.createElement('canvas'); c.width = w; c.height = h; return c; };
function shade(hex, amt){ const c = new THREE.Color(hex); const hsl = {}; c.getHSL(hsl); c.setHSL(hsl.h, hsl.s, Math.max(0, Math.min(1, hsl.l + amt))); return '#' + c.getHexString(); }
function hatch(g, w, h, a){ g.save(); g.globalCompositeOperation = 'source-atop'; g.lineWidth = 2; for(let i=-h; i<w; i+=5){ g.strokeStyle = `rgba(255,255,255,${a})`; g.beginPath(); g.moveTo(i,h); g.lineTo(i+h*0.55,0); g.stroke(); g.strokeStyle=`rgba(0,0,0,${a*1.2})`; g.beginPath(); g.moveTo(i+2.5,h); g.lineTo(i+2.5+h*0.55,0); g.stroke(); } g.restore(); }
// silueta de un lienzo en un solo color
function solid(src, color){ const o = mk(src.width, src.height), g = o.getContext('2d'); g.drawImage(src, 0, 0); g.globalCompositeOperation = 'source-in'; g.fillStyle = color; g.fillRect(0, 0, o.width, o.height); return o; }
// engrosa la silueta r px en todas direcciones (contorno del parche)
function dilate(src, r, color){
  const s = solid(src, color), o = mk(src.width, src.height), g = o.getContext('2d');
  const steps = Math.max(16, Math.round(r * 0.9));
  for(const k of [1, 0.66, 0.33]) for(let i=0; i<steps; i++){ const a = i/steps*Math.PI*2; g.drawImage(s, Math.cos(a)*r*k, Math.sin(a)*r*k); }
  g.drawImage(s, 0, 0); return o;
}
// quita el fondo liso de un logo sin transparencia (blanco, negro, etc.) para que el parche siga la forma del diseño
function cleanLogo(im){
  const sc = Math.min(1, 1200 / Math.max(im.width, im.height));
  const W = Math.max(1, Math.round(im.width*sc)), H = Math.max(1, Math.round(im.height*sc));
  const c = mk(W, H), g = c.getContext('2d', {willReadFrequently:true}); g.drawImage(im, 0, 0, W, H);
  let data; try { data = g.getImageData(0, 0, W, H); } catch(e){ return c; }
  const p = data.data, at = (x, y) => (y*W + x)*4;
  const corners = [at(0,0), at(W-1,0), at(0,H-1), at(W-1,H-1)];
  if(corners.some(i => p[i+3] < 200)) return c;                         // ya trae transparencia
  const bg = [0,1,2].map(k => corners.reduce((s, i) => s + p[i+k], 0)/4);
  const dist = i => Math.abs(p[i]-bg[0]) + Math.abs(p[i+1]-bg[1]) + Math.abs(p[i+2]-bg[2]);
  if(corners.some(i => dist(i) > 60)) return c;                         // el fondo no es liso: se deja igual
  const TOL = 70, seen = new Uint8Array(W*H), q = new Int32Array(W*H); let qh = 0, qt = 0;
  const push = (x, y) => { const j = y*W + x; if(seen[j]) return; seen[j] = 1; if(dist(j*4) <= TOL) q[qt++] = j; };
  for(let x=0; x<W; x++){ push(x, 0); push(x, H-1); }
  for(let y=0; y<H; y++){ push(0, y); push(W-1, y); }
  while(qh < qt){ const j = q[qh++], x = j % W, y = (j - x)/W; p[j*4+3] = 0; if(x>0) push(x-1, y); if(x<W-1) push(x+1, y); if(y>0) push(x, y-1); if(y<H-1) push(x, y+1); }
  // borde suave: los pixeles vecinos al fondo con color parecido quedan semitransparentes
  for(let j=0; j<W*H; j++){ if(p[j*4+3] === 0) continue; const x = j % W, y = (j - x)/W;
    const nb = (x>0 && !p[(j-1)*4+3]) || (x<W-1 && !p[(j+1)*4+3]) || (y>0 && !p[(j-W)*4+3]) || (y<H-1 && !p[(j+W)*4+3]);
    if(nb){ const dd = dist(j*4); if(dd < TOL*2) p[j*4+3] = Math.round(255 * (dd - TOL) / TOL) | 0; } }
  g.putImageData(data, 0, 0);
  // recorta al contenido para que el tamaño y el centrado sean los del diseño, no los del archivo
  let x0 = W, y0 = H, x1 = -1, y1 = -1;
  for(let y=0; y<H; y++) for(let x=0; x<W; x++) if(p[at(x,y)+3] > 24){ if(x<x0) x0=x; if(x>x1) x1=x; if(y<y0) y0=y; if(y>y1) y1=y; }
  if(x1 < 0) return c;
  const o = mk(x1-x0+1, y1-y0+1); o.getContext('2d').drawImage(c, -x0, -y0); return o;
}
const hasContent = d => d.mode==='text' ? !!(d.t1.trim() || d.t2.trim()) : d.mode==='logo' ? !!d.img : false;
const fullColor = d => d.mode==='logo' && (d.tech==='patch' || d.tech==='dtf' || !d.tint);
function drawDecal(z){
  const d = S.decals[z], Z = ZONES[z], {w, h} = Z;
  const g = decalCanvas[z].getContext('2d'), gb = decalBump[z].getContext('2d');
  g.clearRect(0,0,w,h); gb.fillStyle = '#000'; gb.fillRect(0,0,w,h);
  d._info = null;
  if(hasContent(d)){
    const pxPerM = h / Z.box[1], pxPerCm = pxPerM * CM;
    const patch = d.tech==='patch', dtf = d.tech==='dtf', emb = d.tech==='puff' || d.tech==='flat';
    const R = patch ? Math.round((d.mode==='text' ? 0.42 : 0.3) * pxPerCm) : 0;       // ancho del borde del parche
    const m = Math.round(h*0.03) + R;                                                       // margen del lienzo
    const sz = d.size/100, availW = w - 2*m, availH = h - 2*m;
    const C = mk(w, h), c = C.getContext('2d');
    let cw, ch;
    if(d.mode==='text'){
      const lines = [d.t1.trim(), d.t2.trim()].filter(Boolean), ratio = lines.length===2 ? [1, 0.62] : [1];
      const f0 = 100; c.textAlign = 'center'; c.textBaseline = 'alphabetic';
      const ms = lines.map((tx, i) => { c.font = `${f0*ratio[i]}px "${d.font}"`; const mm = c.measureText(tx); return {w:mm.width, a:mm.actualBoundingBoxAscent, d:mm.actualBoundingBoxDescent}; });
      const gap = f0*0.2, totH = ms.reduce((s, x) => s + x.a + x.d, 0) + gap*(lines.length-1), maxW = Math.max(...ms.map(x => x.w));
      const k = Math.min(availW*sz / maxW, availH*sz / totH);
      cw = maxW*k; ch = totH*k;
      const off = (d.y/100) * (availH - ch) * (Z.anchor==='bottom' ? 1 : 0.5);
      let top = Z.anchor==='bottom' ? h - m - ch - off : h/2 - ch/2 - off;
      lines.forEach((tx, i) => { c.font = `${f0*ratio[i]*k}px "${d.font}"`; c.fillStyle = d.color; c.fillText(tx, w/2, top + ms[i].a*k); top += (ms[i].a + ms[i].d)*k + gap*k; });
      d._off = off;
    } else {
      const im = d.img, k = Math.min(availW*sz/im.width, availH*sz/im.height);
      cw = im.width*k; ch = im.height*k;
      const off = (d.y/100) * (availH - ch) * (Z.anchor==='bottom' ? 1 : 0.5);
      const top = Z.anchor==='bottom' ? h - m - ch - off : h/2 - ch/2 - off;
      c.drawImage(im, w/2-cw/2, top, cw, ch);
      if(!fullColor(d)){ c.globalCompositeOperation = 'source-in'; c.fillStyle = d.color; c.fillRect(0,0,w,h); c.globalCompositeOperation = 'source-over'; }
      d._off = off;
    }
    if(emb) hatch(c, w, h, d.tech==='puff' ? 0.22 : 0.14);
    const L = mk(w, h), l = L.getContext('2d');
    let shape = null;
    if(patch){
      // parche recortado siguiendo el contorno del diseño, con borde blanco o negro
      const border = d.patch, edge = shade(border, border === '#141414' ? 0.1 : -0.1);
      shape = dilate(C, R, border);
      l.save(); l.shadowColor = 'rgba(0,0,0,.4)'; l.shadowBlur = 8; l.shadowOffsetY = 4; l.drawImage(shape, 0, 0); l.restore();
      const ring = mk(w, h), rg = ring.getContext('2d'); rg.drawImage(solid(shape, edge), 0, 0); rg.globalCompositeOperation = 'destination-out'; rg.drawImage(dilate(C, R*0.62, '#000'), 0, 0);
      hatch(rg, w, h, 0.25); l.drawImage(ring, 0, 0);
      l.save(); l.shadowColor = 'rgba(0,0,0,.25)'; l.shadowBlur = 2; l.shadowOffsetY = 1; l.drawImage(C, 0, 0); l.restore();
    } else if(dtf){
      l.save(); l.shadowColor = 'rgba(0,0,0,.18)'; l.shadowBlur = 1.5; l.shadowOffsetY = 1; l.drawImage(C, 0, 0); l.restore();
    } else if(d.tech==='puff'){ l.save(); l.shadowColor = 'rgba(0,0,0,.45)'; l.shadowBlur = 10; l.shadowOffsetY = 6; l.drawImage(C, 0, 0); l.restore(); }
    else { l.save(); l.shadowColor = 'rgba(0,0,0,.25)'; l.shadowBlur = 3; l.shadowOffsetY = 2; l.drawImage(C, 0, 0); l.restore(); }
    g.drawImage(L, 0, 0);
    // relieve
    if(shape) gb.drawImage(solid(shape, '#5a5a5a'), 0, 0);
    const B2 = solid(C, d.tech==='puff' ? '#fff' : dtf ? '#333' : '#bbb'); if(emb || patch) hatch(B2.getContext('2d'), w, h, 0.35);
    gb.filter = d.tech==='puff' ? 'blur(5px)' : 'blur(1.5px)'; gb.drawImage(B2, 0, 0); gb.filter = 'none';
    // medidas reales para la orden de producción
    const tw = cw + 2*R, th = ch + 2*R;
    d._info = { w: tw/pxPerCm, h: th/pxPerCm, gap: Z.anchor==='bottom' ? Z.gapCm + d._off/pxPerCm : null };
  }
  decalTex[z].needsUpdate = true; decalBumpTex[z].needsUpdate = true;
  const mt = decalMat[z];
  mt.roughness = d.tech==='dtf' ? 0.38 : d.tech==='flat' ? 0.55 : 0.5;
  mt.bumpScale = d.tech==='puff' ? 6 : d.tech==='patch' ? 3 : d.tech==='dtf' ? 0.4 : 1.6;
  if(decalMesh[z]) decalMesh[z].visible = d.mode !== 'none';
}
// proyecta cada zona sobre la superficie real del modelo (una sola vez).
// Las zonas con "base" se acomodan para que el borde inferior del bordado quede a la medida real (2.5 cm de la visera / 1.5 cm del borde).
function buildDecals(meshes){
  const up = new THREE.Vector3(0, 1, 0), mtx = new THREE.Matrix4();
  const make = (Z, target, y) => {
    const o = new THREE.Vector3(...Z.ray[0]); o.y = y;
    const ray = new THREE.Raycaster(o, new THREE.Vector3(...Z.ray[1]));
    const hit = ray.intersectObject(target, false)[0]; if(!hit) return null;
    const n = hit.face.normal.clone().transformDirection(target.matrixWorld);
    if(n.dot(ray.ray.direction) > 0) n.negate();
    if(Z.keep === 'x') n.x = 0;
    if(Z.tilt != null) n.y *= Z.tilt;      // proyección más horizontal: el borde inferior queda parejo en la curva del frente
    n.normalize();
    mtx.lookAt(n, new THREE.Vector3(), up);
    const rot = new THREE.Euler().setFromRotationMatrix(mtx);
    return new DecalGeometry(target, hit.point, rot, new THREE.Vector3(Z.box[0], Z.box[1], 0.05));
  };
  // altura del borde inferior de la caja al centro del diseño (donde va el texto o logo)
  const bottomY = geo => { const p = geo.attributes.position, uv = geo.attributes.uv; let m = 9; for(let i=0; i<p.count; i++) if(Math.abs(uv.getX(i) - 0.5) < 0.12) m = Math.min(m, p.getY(i)); return m; };
  for(const z in ZONES){
    const Z = ZONES[z], target = meshes[Z.mesh]; if(!target) continue;
    let y = Z.ray[0][1], geo = make(Z, target, y);
    if(Z.base && geo){
      for(let i=0; i<4; i++){
        const err = bottomY(geo) - (Z.base - Z.box[1]*0.03);   // el diseño deja 3% de margen abajo
        if(Math.abs(err) < 0.0003) break;
        const g2 = make(Z, target, y - err); if(!g2) break; geo.dispose(); geo = g2; y -= err;
      }
    }
    if(!geo) continue;
    decalMesh[z] = new THREE.Mesh(geo, decalMat[z]); decalMesh[z].renderOrder = 2;
    scene.add(decalMesh[z]);
    drawDecal(z);
  }
}

/* ---------------- modelo 3D del cliente ---------------- */
const gltfLoader = new GLTFLoader();
async function loadModel(){
  let buf;
  const r = await fetch(ASSETS + 'becap-gorra.glb').catch(() => null);
  if(r && r.ok && (r.headers.get('content-type') || '').indexOf('html') < 0) buf = await r.arrayBuffer();
  if(!buf || new TextDecoder().decode(new Uint8Array(buf, 0, 4)) !== 'glTF'){
    // respaldo: el mismo modelo en base64 (para vistas previas que no sirven .glb)
    const t = await (await fetch(ASSETS + 'becap-gorra.txt')).text();
    const bin = atob(t.trim()), u = new Uint8Array(bin.length);
    for(let i=0; i<bin.length; i++) u[i] = bin.charCodeAt(i);
    buf = u.buffer;
  }
  return new Promise((ok, ko) => gltfLoader.parse(buf, '', ok, ko));
}

/* ---------------- render ---------------- */
function resize(){ const r = stage.getBoundingClientRect(); renderer.setSize(r.width, r.height, false); camera.aspect = r.width / r.height; camera.fov = r.width < 560 ? 36 : 30; camera.updateProjectionMatrix(); }
new ResizeObserver(resize).observe(stage); resize();
let anim = null, visible = true;
new IntersectionObserver(es => { visible = es[0].isIntersecting; }).observe(stage);
function loop(t){
  requestAnimationFrame(loop);
  if(!visible) return;
  if(anim){ const k = Math.min(1, (t - anim.t0)/700), e = 1 - Math.pow(1-k, 3); camera.position.lerpVectors(anim.from, anim.to, e); if(k>=1) anim = null; }
  controls.update(); renderer.render(scene, camera);
}
const VIEWS = { hero:[0.42, 0.22, 0.5], front:[0, 0.15, 0.64], left:[0.66, 0.11, -0.04], right:[-0.66, 0.11, -0.04], back:[-0.04, 0.18, -0.63], top:[0.002, 0.7, 0.1] };
function goView(v){ controls.autoRotate = false; $('#stRot').classList.remove('on'); anim = { t0: performance.now(), from: camera.position.clone(), to: new THREE.Vector3(...VIEWS[v]) }; $$('#views button').forEach(b => b.classList.toggle('on', b.dataset.v===v)); }
$('#views').onclick = e => { const b = e.target.closest('[data-v]'); if(b) goView(b.dataset.v); };
$('#stRot').onclick = () => { controls.autoRotate = !controls.autoRotate; controls.autoRotateSpeed = 2.2; $('#stRot').classList.toggle('on', controls.autoRotate); };
$('#stShot').onclick = () => { renderer.render(scene, camera); const a = document.createElement('a'); a.download = 'mi-gorra-becap.png'; a.href = renderer.domElement.toDataURL('image/png'); a.click(); };
$('#stReset').onclick = () => { S = defaults(); zone = 'front'; for(const z in ZONES) drawDecal(z); syncUI(); recolor(); goView('front'); };
let hinted = false; canvas.addEventListener('pointerdown', () => { if(!hinted){ hinted = true; $('#stHint').style.opacity = 0; } controls.autoRotate=false; $('#stRot').classList.remove('on'); });

/* ---------------- precios (studio/precios.js) ---------------- */
const PRE = Object.assign({ gorra:{}, texto:{bordado3d:38, bordado2d:38, parche:38, dtf:38}, logo:{bordado3d:205, bordado2d:185, parche:205, dtf:40} }, window.BECAP_PRECIOS || {});
const TK = {puff:'bordado3d', flat:'bordado2d', patch:'parche', dtf:'dtf'};
const priceOf = (mode, tech) => { const t = mode==='text' ? PRE.texto : PRE.logo; return Math.max(0, +(typeof t === 'number' ? t : (t && t[TK[tech]])) || 0); };
const zonePrice = z => { const d = S.decals[z]; return hasContent(d) ? priceOf(d.mode, d.tech) : 0; };
const basePrice = () => Math.max(0, +(PRE.gorra || {})[T().id] || 0);
const money = n => '$' + Math.round(n).toLocaleString('es-MX');
const qtyN = () => parseInt(S.qty, 10) || 1;
function quote(){
  const zones = Object.keys(ZONES).filter(z => hasContent(S.decals[z])).map(z => ({z, p:zonePrice(z)}));
  const extra = zones.reduce((s, x) => s + x.p, 0), base = basePrice(), unit = base + extra, n = qtyN();
  return {zones, extra, base, unit, n, total: unit*n};
}

/* ---------------- UI ---------------- */
let step = 0;
const NEXT = ['Siguiente: Colores','Siguiente: Diseño','Siguiente: Cotizar','Enviar diseño por WhatsApp'];
function setStep(n, user=true){
  step = Math.max(0, Math.min(3, n));
  $$('#stTabs button').forEach(b => b.classList.toggle('on', +b.dataset.s===step));
  $$('.st-step').forEach(s => s.classList.toggle('on', +s.dataset.s===step));
  $('#stNext').innerHTML = (step===3 ? '<svg class="ic"><use href="#i-wa"/></svg> ' : '') + NEXT[step] + (step<3 ? ' <svg class="ic"><use href="#i-arrow"/></svg>' : '');
  $('#stPrev').style.visibility = step===0 ? 'hidden' : 'visible';
  if(step===2) goView(zone);
  if(step===3){ renderSpec(); loadPDF(); }
  if(user && window.innerWidth < 1180) document.querySelector('.st-panel').scrollIntoView({behavior:'smooth', block:'start'});
}
$('#stTabs').onclick = e => { const b = e.target.closest('[data-s]'); if(b) setStep(+b.dataset.s); };
$('#stPrev').onclick = () => setStep(step-1);
$('#stNext').onclick = () => { if(step<3) setStep(step+1); else sendQuote(); };

const esc = s => String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
function swBg(c){
  if(c.camo) return `url(${ASSETS}tex/camo_${c.camo}.jpg) center/260%`;
  if(c.f) return `conic-gradient(from 200deg, ${c.f} 0 120deg, ${c.c} 120deg 240deg, ${c.v} 240deg)`;
  if(c.v) return `linear-gradient(135deg, ${c.c} 0 50%, ${c.v} 50% 100%)`;
  return c.c;
}
// tipos de gorra
function tiposUI(){
  $('#oTipo').innerHTML = TIPOS.map((t, i) => {
    const s = t.colors[t.id.endsWith('2') ? 0 : Math.min(5, t.colors.length-1)];
    const bp = +(PRE.gorra || {})[t.id] || 0;
    return `<button class="opt ${i===S.tipo?'on':''}" data-tipo="${i}"><span class="tsw"><i style="background:${swBg(s)}"></i><b>${esc(t.name)} · ${esc(t.sub)}</b></span><small>${esc(t.desc)}</small><span class="cnt">${t.colors.length} colores${bp ? ' · ' + money(bp) : ''}</span></button>`;
  }).join('');
}
$('#oTipo').onclick = e => { const b = e.target.closest('[data-tipo]'); if(!b) return; S.tipo = +b.dataset.tipo; S.color = 0; tiposUI(); colorUI(); recolor(); };
// colores del tipo elegido
function colorUI(){
  const t = T();
  $('#colTitle').textContent = `Colores de ${t.name.toLowerCase()} · ${t.sub} (${t.colors.length})`;
  $('#swatches').innerHTML = t.colors.map((c, i) => `<button class="swatch ${t.fabric==='washed'?'washed':''} ${i===S.color?'on':''}" style="background:${swBg(c)}" title="${esc(c.n)}" aria-label="${esc(c.n)}" data-col="${i}"></button>`).join('');
  $('#cname').textContent = `Color: ${CUR().n}`;
}
$('#swatches').addEventListener('click', e => { const b = e.target.closest('[data-col]'); if(!b) return; S.color = +b.dataset.col; colorUI(); recolor(); });
function swatchesUI(el, cur, attr){ el.innerHTML = PAL.map(([n,h]) => `<button class="swatch ${cur.toLowerCase()===h?'on':''}" style="background:${h}" title="${n}" aria-label="${n}" data-${attr}="${h}"></button>`).join('') + `<label class="swatch custom" title="Color personalizado"><input type="color" value="${cur}" data-${attr}-custom></label>`; }

// bordados
let zone = 'front';
const D = () => S.decals[zone];
const PLACE = {front:`El bordado frontal va a ${GAP_FRONT_CM} cm de la visera.`, left:`Los nombres laterales van abajo, a ${GAP_SIDE_CM} cm del borde de la gorra.`, right:`Los nombres laterales van abajo, a ${GAP_SIDE_CM} cm del borde de la gorra.`, back:'Arriba del ajustador trasero.'};
function decalUI(){
  const d = D(), logo = d.mode==='logo';
  $$('#zones button').forEach(b => { const z = b.dataset.z; b.classList.toggle('on', z===zone); b.classList.toggle('has', hasContent(S.decals[z])); });
  $$('#dMode .opt').forEach(b => b.classList.toggle('on', b.dataset.m===d.mode));
  $('#dText').style.display = d.mode==='text' ? '' : 'none';
  $('#dLogo').style.display = logo ? '' : 'none';
  $('#dCommon').style.display = d.mode==='none' ? 'none' : '';
  $('#dT1').value = d.t1; $('#dT2').value = d.t2; $('#dTint').checked = d.tint;
  // bordado 3D solo con tipografías anchas
  $$('#dFont option').forEach(o => { const ok = d.tech!=='puff' || WIDE.includes(o.value); o.disabled = !ok; o.textContent = FONTN[o.value] + (ok ? '' : ' (no disponible en 3D)'); });
  $('#dFont').value = d.font;
  $('#dFileL').textContent = d.imgName ? `✓ ${d.imgName} · toca para cambiar` : 'Sube tu logo (PNG, JPG; quitamos el fondo liso)';
  $$('#dTech .opt').forEach(b => { b.classList.toggle('on', b.dataset.t===d.tech); const p = b.querySelector('.pr'); if(p) p.textContent = '+' + money(priceOf(d.mode==='none' ? 'text' : d.mode, b.dataset.t)); });
  $('#tintRow').style.display = logo && (d.tech==='puff' || d.tech==='flat') ? '' : 'none';
  $('#patchOpts').style.display = d.tech==='patch' ? '' : 'none';
  $$('#patchSw button').forEach(b => b.classList.toggle('on', b.dataset.pa===d.patch));
  const showColor = !fullColor(d);
  $('#threadBox').style.display = showColor ? '' : 'none';
  $('#threadL').textContent = d.tech==='dtf' ? 'Color de la impresión' : 'Color del hilo';
  $('#techNote').textContent = d.tech==='puff' ? 'Bordado 3D: solo con tipografías anchas (Bloque, Varsity o Redondeada).' : d.tech==='patch' ? 'El parche se recorta siguiendo la forma de tu diseño, con borde blanco o negro.' : d.tech==='dtf' ? 'Impresión a todo color, ideal para logos con degradados o muchos colores.' : '';
  swatchesUI($('#threadSw'), d.color, 'th');
  $('#dSize').value = d.size; $('#dY').value = d.y;
  const Z = ZONES[zone];
  $('#dYL').textContent = Z.anchor==='bottom' ? 'Subir diseño' : 'Posición vertical';
  $('#dY').min = Z.anchor==='bottom' ? 0 : -100;
  $('#placeNote').textContent = PLACE[zone];
  priceUI();
}
function priceUI(){
  const q = quote(), zp = zonePrice(zone);
  $('#zPrice').innerHTML = `<span>${esc(ZL[zone])}: <b>${zp ? '+' + money(zp) : 'sin costo'}</b></span><span>Personalización: <b>${q.extra ? '+' + money(q.extra) : '$0'}</b> por gorra</span>`;
  $$('#dMode .opt').forEach(b => { const s = b.querySelector('.pr'); if(!s) return; const m = b.dataset.m; s.textContent = m==='text' ? '+' + money(priceOf('text', D().tech)) : m==='logo' ? 'desde +' + money(Math.min(...Object.keys(TK).map(t => priceOf('logo', t)))) : ''; });
}
const redraw = () => { drawDecal(zone); updateSummary(); decalUI(); };
$('#zones').onclick = e => { const b = e.target.closest('[data-z]'); if(!b) return; zone = b.dataset.z; decalUI(); goView(zone); };
$('#dMode').onclick = e => { const b = e.target.closest('[data-m]'); if(!b) return; D().mode = b.dataset.m; redraw(); };
$('#dTech').onclick = e => {
  const b = e.target.closest('[data-t]'); if(!b) return; const d = D(); d.tech = b.dataset.t;
  if(d.tech==='puff' && !WIDE.includes(d.font)) d.font = 'Archivo Black';
  // parche de texto: el borde contrasta con el hilo para que se lea
  if(d.tech==='patch' && d.mode==='text'){ const l = new THREE.Color(d.color).getHSL({}).l; d.patch = l > 0.6 ? '#141414' : '#f3f1ec'; }
  redraw();
};
$('#dT1').oninput = e => { D().t1 = e.target.value; drawDecal(zone); updateSummary(); priceUI(); $$('#zones button').forEach(b => b.classList.toggle('has', hasContent(S.decals[b.dataset.z]))); };
$('#dT2').oninput = e => { D().t2 = e.target.value; drawDecal(zone); updateSummary(); priceUI(); };
$('#dFont').onchange = async e => { D().font = e.target.value; try{ await document.fonts.load(`64px "${D().font}"`); }catch(_){} redraw(); };
$('#dTint').onchange = e => { D().tint = e.target.checked; redraw(); };
$('#dSize').oninput = e => { D().size = +e.target.value; drawDecal(zone); };
$('#dY').oninput = e => { D().y = +e.target.value; drawDecal(zone); };
$('#threadSw').addEventListener('click', e => { const b = e.target.closest('[data-th]'); if(b){ D().color = b.dataset.th; redraw(); } });
$('#threadSw').addEventListener('input', e => { if(e.target.matches('[data-th-custom]')){ D().color = e.target.value; drawDecal(zone); } });
$('#patchSw').addEventListener('click', e => { const b = e.target.closest('[data-pa]'); if(b){ D().patch = b.dataset.pa; redraw(); } });
$('#dFile').onchange = e => { const f = e.target.files[0]; if(!f) return; const r = new FileReader(); r.onload = () => { const im = new Image(); im.onload = () => { const d = D(); d.img = cleanLogo(im); d.imgName = f.name.length>28 ? f.name.slice(0,26)+'…' : f.name; redraw(); }; im.src = r.result; }; r.readAsDataURL(f); };

// cotización
$('#qtys').onclick = e => { const b = e.target.closest('[data-q]'); if(!b) return; S.qty = b.dataset.q; $$('#qtys button').forEach(x => x.classList.toggle('on', x===b)); renderSpec(); };
$('#qDate').min = new Date().toISOString().slice(0,10);
const cm = n => (Math.round(n*10)/10).toString().replace(/\.0$/, '');
function placeText(z){ const i = S.decals[z]._info; if(!i) return ''; return i.gap != null ? (z==='front' ? `a ${cm(i.gap)} cm de la visera` : `a ${cm(i.gap)} cm del borde inferior`) : 'arriba del ajustador'; }
function decalText(z){
  const d = S.decals[z]; if(!hasContent(d)) return 'Lisa';
  const what = d.mode==='text' ? `“${[d.t1.trim(), d.t2.trim()].filter(Boolean).join(' / ')}” (${FONTN[d.font]})` : `Logo${d.imgName ? ' (' + d.imgName + ')' : ''}`;
  const color = d.tech==='patch' ? (d.mode==='text' ? `hilo ${nameOf(d.color).toLowerCase()} · ` : '') + `borde ${d.patch==='#141414' ? 'negro' : 'blanco'}` : fullColor(d) ? 'a todo color' : (d.tech==='dtf' ? 'color ' : 'hilo ') + nameOf(d.color).toLowerCase();
  const i = d._info;
  return `${what} · ${TECH[d.tech]} · ${color}${i ? ` · aprox. ${cm(i.w)} × ${cm(i.h)} cm, ${placeText(z)}` : ''}`;
}
function specText(){
  const t = T();
  return [
    `Gorra: ${t.name} ${t.sub} · unitalla 58 cm · strapback`,
    `Color: ${CUR().n}`,
    ...Object.keys(ZONES).map(z => `${ZL[z]}: ${decalText(z)}`),
  ];
}
function renderSpec(){
  const q = quote();
  $('#spec').innerHTML = specText().map(l => { const [a, ...b] = l.split(': '); return `<b>${esc(a)}:</b> ${esc(b.join(': '))}`; }).join('<br>') + `<br><b>Piezas:</b> ${esc(S.qty)}`;
  const rows = [[`Gorra ${T().name} ${T().sub}`, q.base ? money(q.base) : 'por confirmar']]
    .concat(q.zones.map(x => [`${ZL[x.z]} · ${S.decals[x.z].mode==='text' ? 'Texto' : 'Logo'} · ${TECH[S.decals[x.z].tech]}`, '+' + money(x.p)]));
  $('#quote').innerHTML = rows.map(r => `<div class="qr"><span>${esc(r[0])}</span><b>${esc(r[1])}</b></div>`).join('')
    + `<div class="qr sub"><span>${q.base ? 'Precio por gorra' : 'Personalización por gorra'}</span><b>${money(q.unit)}</b></div>`
    + `<div class="qr tot"><span>Total estimado · ${S.qty} pieza${q.n===1 ? '' : 's'}</span><b>${S.qty.endsWith('+') ? 'desde ' : ''}${money(q.total)}</b></div>`
    + `<p class="qnote">${q.base ? '' : 'Más el precio de la gorra, que te confirmamos por WhatsApp. '}Precio estimado; Be Cap confirma la cotización final.</p>`;
}

/* ---------------- orden de producción en PDF ---------------- */
let pdfLib = null;
function loadPDF(){
  if(window.jspdf || pdfLib) return pdfLib;
  pdfLib = new Promise((ok, ko) => { const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'; s.onload = ok; s.onerror = ko; document.head.appendChild(s); });
  pdfLib.catch(() => { pdfLib = null; });
  return pdfLib;
}
// foto de la gorra desde un ángulo, sobre fondo crema
function shot(v){
  const keep = camera.position.clone(), rot = controls.autoRotate;
  camera.position.set(...VIEWS[v]); camera.lookAt(controls.target); renderer.render(scene, camera);
  const src = renderer.domElement, s = Math.min(src.width, src.height)*0.74, o = mk(560, 560), g = o.getContext('2d');
  g.fillStyle = '#f4efe6'; g.fillRect(0, 0, 560, 560);
  g.drawImage(src, (src.width - s)/2, (src.height - s)/2 - s*0.04, s, s, 0, 0, 560, 560);
  camera.position.copy(keep); camera.lookAt(controls.target); controls.autoRotate = rot; renderer.render(scene, camera);
  return o.toDataURL('image/jpeg', 0.9);
}
function folio(){ const d = new Date(), p = n => String(n).padStart(2, '0'); return `BC-${String(d.getFullYear()).slice(2)}${p(d.getMonth()+1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}`; }
function makePDF(){
  const { jsPDF } = window.jspdf, doc = new jsPDF({unit:'mm', format:'letter'}), q = quote(), F = folio();
  const W = 215.9, M = 14; let y = M;
  const txt = (s, x, yy, o={}) => { doc.setFont('helvetica', o.b ? 'bold' : 'normal'); doc.setFontSize(o.s || 10); doc.setTextColor(...(o.c || [20,20,20])); doc.text(String(s), x, yy, o.a ? {align:o.a} : undefined); };
  doc.setFillColor(255, 210, 74); doc.rect(0, 0, W, 26, 'F');
  txt('BE CAP', M, 12, {b:1, s:20}); txt('Orden de producción · Be Cap Studio', M, 19.5, {s:10.5});
  txt(`Folio ${F}`, W-M, 12, {b:1, s:11, a:'right'}); txt(new Date().toLocaleString('es-MX', {dateStyle:'long', timeStyle:'short'}), W-M, 19.5, {s:9.5, a:'right'});
  y = 34;
  const nm = $('#qName').value.trim() || '—';
  const info = [['Cliente', nm], ['Piezas', S.qty], ['Uso', $('#qUse').value], ['Para cuándo', $('#qDate').value || 'Sin fecha'], ['Gorra', `${T().name} ${T().sub} · unitalla 58 cm · strapback`], ['Color', CUR().n]];
  info.forEach((r, i) => { const x = M + (i % 2) * 96, yy = y + Math.floor(i/2) * 7; txt(r[0] + ':', x, yy, {b:1, s:9.5}); txt(r[1], x + 24, yy, {s:9.5}); });
  y += 24;
  // vistas
  const views = [['front','Frente'], ['left','Lateral izq.'], ['right','Lateral der.'], ['back','Atrás']], iw = (W - 2*M - 9) / 4;
  views.forEach(([v, n], i) => { const x = M + i*(iw + 3); doc.addImage(shot(v), 'JPEG', x, y, iw, iw); txt(n, x + iw/2, y + iw + 4.5, {s:8.5, a:'center', c:[90,90,90]}); });
  y += iw + 11;
  // zonas
  txt('Personalización', M, y, {b:1, s:12}); y += 3;
  doc.setDrawColor(220); doc.line(M, y, W-M, y); y += 5;
  Object.keys(ZONES).forEach(z => {
    const d = S.decals[z], has = hasContent(d), p = zonePrice(z);
    txt(ZL[z], M, y, {b:1, s:10}); txt(has ? '+' + money(p) : '—', W-M, y, {b:1, s:10, a:'right'});
    const lines = doc.splitTextToSize(has ? decalText(z) : 'Lisa (sin bordado)', W - 2*M - 62);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5); doc.setTextColor(60,60,60); doc.text(lines, M + 40, y);
    let hgt = lines.length * 4.4;
    if(has && d.mode==='logo' && d.img){ const r = d.img.width / d.img.height, lh = 14, lw = Math.min(30, lh*r); try { doc.addImage(d.img.toDataURL('image/png'), 'PNG', W - M - 22 - lw, y - 3.5, lw, lw / r); } catch(e){} hgt = Math.max(hgt, lw / r + 1); }
    y += hgt + 3.5; doc.setDrawColor(235); doc.line(M, y - 2.5, W-M, y - 2.5); y += 2;
  });
  // precios
  y += 2;
  const row = (a, b, o={}) => { txt(a, M + 90, y, {s:o.s || 10, b:o.b}); txt(b, W-M, y, {s:o.s || 10, b:o.b, a:'right'}); y += o.s ? 7 : 5.5; };
  row('Gorra', q.base ? money(q.base) + ' c/u' : 'por confirmar');
  row('Personalización', money(q.extra) + ' c/u');
  row(q.base ? 'Precio por gorra' : 'Personalización por gorra', money(q.unit), {b:1});
  doc.setFillColor(20,20,20); doc.rect(M + 86, y - 4.6, W - 2*M - 86, 9, 'F');
  txt(`Total estimado (${S.qty} pz)`, M + 90, y + 1.4, {b:1, s:11, c:[255,210,74]}); txt((S.qty.endsWith('+') ? 'desde ' : '') + money(q.total), W-M-3, y + 1.4, {b:1, s:11, c:[255,255,255], a:'right'});
  y += 12;
  const notes = $('#qNotes').value.trim();
  if(notes){ txt('Comentarios del cliente', M, y, {b:1, s:10}); y += 5; const l = doc.splitTextToSize(notes, W - 2*M); doc.setFont('helvetica','normal'); doc.setFontSize(9.5); doc.text(l, M, y); y += l.length*4.4 + 3; }
  txt(`${q.base ? '' : 'Total sin el precio de la gorra. '}Precio estimado generado por Be Cap Studio; Be Cap confirma la cotización final por WhatsApp.`, M, 268, {s:8, c:[110,110,110]});
  txt('Be Cap · Av. Revolución 503, Pachuca, Hgo. · WhatsApp 771 288 4753', M, 273, {s:8, c:[110,110,110]});
  doc.save(`orden-becap-${F}.pdf`);
}
async function downloadPDF(){
  const b = $('#qPdf'); b.disabled = true; const old = b.innerHTML; b.textContent = 'Generando PDF…';
  try { await loadPDF(); makePDF(); } catch(e){ console.error(e); alert('No se pudo generar el PDF. Revisa tu conexión e inténtalo de nuevo.'); }
  b.disabled = false; b.innerHTML = old;
}
$('#qPdf').onclick = downloadPDF;
function sendQuote(){
  const nm = $('#qName').value.trim(); if(!nm){ $('#qName').focus(); $('#qName').style.borderColor = '#ff6b5b'; return; }
  const q = quote();
  const precio = [
    ...q.zones.map(x => `${ZL[x.z]}: +${money(x.p)}`),
    `Gorra: ${q.base ? money(q.base) + ' c/u' : 'por confirmar'}`,
    `${q.base ? 'Precio por gorra' : 'Personalización por gorra'}: ${money(q.unit)}`,
    `Total estimado (${S.qty} pz): ${S.qty.endsWith('+') ? 'desde ' : ''}${money(q.total)}${q.base ? '' : ' + gorra'}`,
  ];
  const msg = `¡Hola Be Cap! 🧢 Diseñé esta gorra en Be Cap Studio y quiero cotizarla:\n\n${specText().map(l=>'• '+l).join('\n')}\n• Piezas: ${S.qty}\n• Uso: ${$('#qUse').value}${$('#qDate').value?'\n• Para: '+$('#qDate').value:''}${$('#qNotes').value.trim()?'\n• Notas: '+$('#qNotes').value.trim():''}\n\n💲 Cotización estimada:\n${precio.map(l=>'• '+l).join('\n')}\n\nNombre: ${nm}\n(Te adjunto mi orden de producción en PDF)`;
  if(window.jspdf){ try { makePDF(); } catch(e){ console.error(e); } }
  window.open(`https://wa.me/${WA}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener');
}
function updateSummary(){ const t = T(), q = quote(); $('#stSum').innerHTML = [`${t.name} · ${t.sub}`, CUR().n, q.extra ? `Personalización +${money(q.extra)}` : 'Strapback'].map(x=>`<span>${esc(x)}</span>`).join(''); }
function syncUI(){ tiposUI(); colorUI(); decalUI(); $$('#qtys button').forEach(x => x.classList.toggle('on', x.dataset.q===S.qty)); }

/* ---------------- arranque ---------------- */
(async () => {
  try { await Promise.race([Promise.all(['Archivo Black','Graduate','Pirata One','Yellowtail','Bungee'].map(f => document.fonts.load(`64px "${f}"`))), new Promise(r => setTimeout(r, 2500))]); } catch(e){}
  syncUI(); setStep(0, false); resize();
  camera.position.set(0.55, 0.28, 0.7);
  let gltf;
  try { gltf = await loadModel(); }
  catch(e){ $('#stLoad').textContent = 'No se pudo cargar la gorra 3D. Recarga la página.'; console.error(e); return; }
  const meshes = {};
  gltf.scene.traverse(o => { if(!o.isMesh) return; o.material = M[PART[o.name] || 'inner']; o.castShadow = o.receiveShadow = true; meshes[o.name] = o; });
  scene.add(gltf.scene);
  gltf.scene.updateMatrixWorld(true);
  const box = new THREE.Box3().setFromObject(gltf.scene); ground.position.y = box.min.y;
  recolor(); buildDecals(meshes);
  $('#stLoad').remove();
  window.__studioReady = true;
  requestAnimationFrame(loop);
  setTimeout(() => goView('hero'), 400);
})();
