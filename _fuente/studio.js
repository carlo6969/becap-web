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
const defDecal = (o={}) => Object.assign({mode:'text', t1:'', t2:'', font:'Archivo Black', color:'#ffd24a', tech:'puff', patch:'#eee2c8', size:80, y:0, img:null, tint:true, imgName:''}, o);
const NEGRO = Math.max(0, TIPOS[0].colors.findIndex(c => c.n === 'Negro'));
function defaults(){
  return {
    tipo: 0, color: NEGRO,
    decals:{front:defDecal({t1:'BE CAP'}), side:defDecal({t1:'PACHUCA', size:70, tech:'flat', color:'#f3f1ec'}), back:defDecal({t1:'771', size:65, tech:'flat', color:'#f3f1ec'})},
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

/* ---------------- bordados (texto / logo / parche) ---------------- */
const ZONES = {
  front: {w:1024, h:720, ray:[[0, 0.074, 0.5],[0,0,-1]], mesh:'cap_front', box:[0.1, 0.07], keep:'x'},
  side:  {w:1024, h:440, ray:[[-0.5, 0.082, -0.07],[1,0,0]], mesh:'cap_back', box:[0.085, 0.0365], keep:null},
  back:  {w:1024, h:400, ray:[[0, 0.088, -0.6],[0,0,1]], mesh:'cap_back', box:[0.075, 0.029], keep:'x'},
};
const decalCanvas = {}, decalBump = {}, decalTex = {}, decalBumpTex = {}, decalMat = {}, decalMesh = {};
for(const z in ZONES){
  const {w, h} = ZONES[z];
  decalCanvas[z] = Object.assign(document.createElement('canvas'), {width:w, height:h});
  decalBump[z] = Object.assign(document.createElement('canvas'), {width:w, height:h});
  decalTex[z] = new THREE.CanvasTexture(decalCanvas[z]); decalTex[z].colorSpace = THREE.SRGBColorSpace; decalTex[z].anisotropy = 8;
  decalBumpTex[z] = new THREE.CanvasTexture(decalBump[z]);
  decalMat[z] = new THREE.MeshStandardMaterial({ map:decalTex[z], transparent:true, roughness:0.5, metalness:0, bumpMap:decalBumpTex[z], polygonOffset:true, polygonOffsetFactor:-4, depthWrite:false });
}
function shade(hex, amt){ const c = new THREE.Color(hex); const hsl = {}; c.getHSL(hsl); c.setHSL(hsl.h, hsl.s, Math.max(0, Math.min(1, hsl.l + amt))); return '#' + c.getHexString(); }
function hatch(g, w, h, a){ g.save(); g.globalCompositeOperation = 'source-atop'; g.lineWidth = 2; for(let i=-h; i<w; i+=5){ g.strokeStyle = `rgba(255,255,255,${a})`; g.beginPath(); g.moveTo(i,h); g.lineTo(i+h*0.55,0); g.stroke(); g.strokeStyle=`rgba(0,0,0,${a*1.2})`; g.beginPath(); g.moveTo(i+2.5,h); g.lineTo(i+2.5+h*0.55,0); g.stroke(); } g.restore(); }
function drawDecal(z){
  const d = S.decals[z], {w, h} = ZONES[z];
  const g = decalCanvas[z].getContext('2d'), gb = decalBump[z].getContext('2d');
  g.clearRect(0,0,w,h); gb.fillStyle = '#000'; gb.fillRect(0,0,w,h);
  const has = d.mode==='text' ? (d.t1.trim() || d.t2.trim()) : d.mode==='logo' ? !!d.img : false;
  if(has){
    const L = document.createElement('canvas'); L.width = w; L.height = h; const l = L.getContext('2d');
    const sz = d.size/100, cy = h/2 - (d.y/100) * h * 0.22;
    let boxW = w*0.86*sz, boxH = h*0.78*sz;
    const patch = d.tech==='patch';
    if(patch){
      const pw = Math.min(w*0.92, boxW*1.08), ph = Math.min(h*0.92, boxH*1.08), r = Math.min(pw, ph)*0.18;
      l.save(); l.beginPath(); l.roundRect(w/2-pw/2, cy-ph/2, pw, ph, r); l.fillStyle = d.patch; l.fill();
      l.lineWidth = Math.max(8, ph*0.06); l.strokeStyle = shade(d.color, 0); l.stroke(); l.restore();
      boxW = pw*0.8; boxH = ph*0.66;
    }
    const C = document.createElement('canvas'); C.width = w; C.height = h; const c = C.getContext('2d');
    if(d.mode==='text'){
      const lines = [d.t1.trim(), d.t2.trim()].filter(Boolean);
      const font = d.font;
      let fs = boxH * (lines.length===2 ? 0.52 : 0.78);
      c.textAlign = 'center'; c.textBaseline = 'middle';
      const sizes = lines.map((tx, i) => { let f = fs * (lines.length===2 ? (i===0?1:0.62) : 1); c.font = `${f}px "${font}"`; const mw = c.measureText(tx).width; if(mw > boxW) f *= boxW/mw; return f; });
      const totalH = sizes.reduce((a,b)=>a+b*1.02, 0);
      let yy = cy - totalH/2;
      lines.forEach((tx, i) => { c.font = `${sizes[i]}px "${font}"`; yy += sizes[i]*0.51; c.fillStyle = d.color; c.fillText(tx, w/2, yy + sizes[i]*0.04); yy += sizes[i]*0.51; });
    } else {
      const im = d.img; const sc = Math.min(boxW/im.width, boxH/im.height); const iw = im.width*sc, ih = im.height*sc;
      c.drawImage(im, w/2-iw/2, cy-ih/2, iw, ih);
      if(d.tint){ c.globalCompositeOperation = 'source-in'; c.fillStyle = d.color; c.fillRect(0,0,w,h); c.globalCompositeOperation = 'source-over'; }
    }
    hatch(c, w, h, d.tech==='puff' ? 0.22 : 0.14);
    if(d.tech==='puff'){ l.save(); l.shadowColor = 'rgba(0,0,0,.45)'; l.shadowBlur = 10; l.shadowOffsetY = 6; l.drawImage(C, 0, 0); l.restore(); }
    else { l.save(); l.shadowColor = 'rgba(0,0,0,.25)'; l.shadowBlur = 3; l.shadowOffsetY = 2; l.drawImage(C, 0, 0); l.restore(); }
    g.drawImage(L, 0, 0);
    const B = document.createElement('canvas'); B.width = w; B.height = h; const b = B.getContext('2d');
    if(patch){ b.drawImage(L,0,0); b.globalCompositeOperation = 'source-in'; b.fillStyle = '#5a5a5a'; b.fillRect(0,0,w,h); b.globalCompositeOperation = 'source-over'; }
    const B2 = document.createElement('canvas'); B2.width = w; B2.height = h; const b2 = B2.getContext('2d');
    b2.drawImage(C,0,0); b2.globalCompositeOperation = 'source-in'; b2.fillStyle = d.tech==='puff' ? '#fff' : '#bbb'; b2.fillRect(0,0,w,h);
    hatch(b2, w, h, 0.35);
    gb.filter = d.tech==='puff' ? 'blur(5px)' : 'blur(1.5px)'; gb.drawImage(B, 0, 0); gb.drawImage(B2, 0, 0); gb.filter = 'none';
  }
  decalTex[z].needsUpdate = true; decalBumpTex[z].needsUpdate = true;
  const m = decalMat[z];
  m.roughness = d.tech==='flat' ? 0.55 : 0.5;
  m.bumpScale = d.tech==='puff' ? 6 : d.tech==='patch' ? 3 : 1.6;
  if(decalMesh[z]) decalMesh[z].visible = d.mode !== 'none';
}
// proyecta cada zona sobre la superficie real del modelo (una sola vez)
function buildDecals(meshes){
  const up = new THREE.Vector3(0, 1, 0), mtx = new THREE.Matrix4();
  for(const z in ZONES){
    const Z = ZONES[z], target = meshes[Z.mesh]; if(!target) continue;
    const ray = new THREE.Raycaster(new THREE.Vector3(...Z.ray[0]), new THREE.Vector3(...Z.ray[1]));
    const hit = ray.intersectObject(target, false)[0]; if(!hit) continue;
    const n = hit.face.normal.clone().transformDirection(target.matrixWorld);
    if(n.dot(ray.ray.direction) > 0) n.negate();
    if(Z.keep === 'x') n.x = 0;
    n.normalize();
    mtx.lookAt(n, new THREE.Vector3(), up);
    const rot = new THREE.Euler().setFromRotationMatrix(mtx);
    const geo = new DecalGeometry(target, hit.point, rot, new THREE.Vector3(Z.box[0], Z.box[1], 0.05));
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
const VIEWS = { hero:[0.42, 0.22, 0.5], front:[0, 0.15, 0.64], side:[-0.66, 0.13, 0.02], back:[-0.04, 0.18, -0.63], top:[0.002, 0.7, 0.1] };
function goView(v){ controls.autoRotate = false; $('#stRot').classList.remove('on'); anim = { t0: performance.now(), from: camera.position.clone(), to: new THREE.Vector3(...VIEWS[v]) }; $$('#views button').forEach(b => b.classList.toggle('on', b.dataset.v===v)); }
$('#views').onclick = e => { const b = e.target.closest('[data-v]'); if(b) goView(b.dataset.v); };
$('#stRot').onclick = () => { controls.autoRotate = !controls.autoRotate; controls.autoRotateSpeed = 2.2; $('#stRot').classList.toggle('on', controls.autoRotate); };
$('#stShot').onclick = () => { renderer.render(scene, camera); const a = document.createElement('a'); a.download = 'mi-gorra-becap.png'; a.href = renderer.domElement.toDataURL('image/png'); a.click(); };
$('#stReset').onclick = () => { S = defaults(); syncUI(); recolor(); for(const z in ZONES) drawDecal(z); goView('front'); };
let hinted = false; canvas.addEventListener('pointerdown', () => { if(!hinted){ hinted = true; $('#stHint').style.opacity = 0; } controls.autoRotate=false; $('#stRot').classList.remove('on'); });

/* ---------------- UI ---------------- */
let step = 0;
const NEXT = ['Siguiente: Colores','Siguiente: Diseño','Siguiente: Cotizar','Enviar diseño por WhatsApp'];
function setStep(n, user=true){
  step = Math.max(0, Math.min(3, n));
  $$('#stTabs button').forEach(b => b.classList.toggle('on', +b.dataset.s===step));
  $$('.st-step').forEach(s => s.classList.toggle('on', +s.dataset.s===step));
  $('#stNext').innerHTML = (step===3 ? '<svg class="ic"><use href="#i-wa"/></svg> ' : '') + NEXT[step] + (step<3 ? ' <svg class="ic"><use href="#i-arrow"/></svg>' : '');
  $('#stPrev').style.visibility = step===0 ? 'hidden' : 'visible';
  if(step===2){ goView(zone==='front'?'front':zone==='side'?'side':'back'); }
  if(step===3) renderSpec();
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
    return `<button class="opt ${i===S.tipo?'on':''}" data-tipo="${i}"><span class="tsw"><i style="background:${swBg(s)}"></i><b>${esc(t.name)} · ${esc(t.sub)}</b></span><small>${esc(t.desc)}</small><span class="cnt">${t.colors.length} colores</span></button>`;
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
function decalUI(){
  const d = D();
  $$('#zones button').forEach(b => b.classList.toggle('on', b.dataset.z===zone));
  $$('#dMode .opt').forEach(b => b.classList.toggle('on', b.dataset.m===d.mode));
  $('#dText').style.display = d.mode==='text' ? '' : 'none';
  $('#dLogo').style.display = d.mode==='logo' ? '' : 'none';
  $('#dCommon').style.display = d.mode==='none' ? 'none' : '';
  $('#dT1').value = d.t1; $('#dT2').value = d.t2; $('#dFont').value = d.font; $('#dTint').checked = d.tint;
  $('#dFileL').textContent = d.imgName ? `✓ ${d.imgName} · toca para cambiar` : 'Sube tu logo (PNG con fondo transparente se ve mejor)';
  $$('#dTech .opt').forEach(b => b.classList.toggle('on', b.dataset.t===d.tech));
  $('#patchOpts').style.display = d.tech==='patch' ? '' : 'none';
  swatchesUI($('#threadSw'), d.color, 'th'); swatchesUI($('#patchSw'), d.patch, 'pa');
  $('#dSize').value = d.size; $('#dY').value = d.y;
}
const redraw = () => { drawDecal(zone); updateSummary(); };
$('#zones').onclick = e => { const b = e.target.closest('[data-z]'); if(!b) return; zone = b.dataset.z; decalUI(); goView(zone==='front'?'front':zone==='side'?'side':'back'); };
$('#dMode').onclick = e => { const b = e.target.closest('[data-m]'); if(!b) return; D().mode = b.dataset.m; decalUI(); redraw(); };
$('#dTech').onclick = e => { const b = e.target.closest('[data-t]'); if(!b) return; D().tech = b.dataset.t; decalUI(); redraw(); };
$('#dT1').oninput = e => { D().t1 = e.target.value; redraw(); };
$('#dT2').oninput = e => { D().t2 = e.target.value; redraw(); };
$('#dFont').onchange = async e => { D().font = e.target.value; try{ await document.fonts.load(`64px "${D().font}"`); }catch(_){} redraw(); };
$('#dTint').onchange = e => { D().tint = e.target.checked; redraw(); };
$('#dSize').oninput = e => { D().size = +e.target.value; redraw(); };
$('#dY').oninput = e => { D().y = +e.target.value; redraw(); };
$('#threadSw').addEventListener('click', e => { const b = e.target.closest('[data-th]'); if(b){ D().color = b.dataset.th; decalUI(); redraw(); } });
$('#threadSw').addEventListener('input', e => { if(e.target.matches('[data-th-custom]')){ D().color = e.target.value; redraw(); } });
$('#patchSw').addEventListener('click', e => { const b = e.target.closest('[data-pa]'); if(b){ D().patch = b.dataset.pa; decalUI(); redraw(); } });
$('#patchSw').addEventListener('input', e => { if(e.target.matches('[data-pa-custom]')){ D().patch = e.target.value; redraw(); } });
$('#dFile').onchange = e => { const f = e.target.files[0]; if(!f) return; const r = new FileReader(); r.onload = () => { const im = new Image(); im.onload = () => { D().img = im; D().imgName = f.name.length>28 ? f.name.slice(0,26)+'…' : f.name; decalUI(); redraw(); }; im.src = r.result; }; r.readAsDataURL(f); };

// cotización
$('#qtys').onclick = e => { const b = e.target.closest('[data-q]'); if(!b) return; S.qty = b.dataset.q; $$('#qtys button').forEach(x => x.classList.toggle('on', x===b)); renderSpec(); };
$('#qDate').min = new Date().toISOString().slice(0,10);
const TECH = {puff:'Bordado 3D', flat:'Bordado plano', patch:'Parche'};
function decalText(z){ const d = S.decals[z]; if(d.mode==='none') return 'Lisa'; const what = d.mode==='text' ? `“${[d.t1,d.t2].filter(Boolean).join(' / ') || '—'}”` : `Logo${d.imgName?' ('+d.imgName+')':''}`; return `${what} · ${TECH[d.tech]} · hilo ${nameOf(d.color).toLowerCase()}${d.tech==='patch'?' · parche '+nameOf(d.patch).toLowerCase():''}`; }
function specText(){
  const t = T();
  return [
    `Gorra: ${t.name} ${t.sub} · unitalla 58 cm · strapback`,
    `Color: ${CUR().n}`,
    `Frente: ${decalText('front')}`, `Lateral: ${decalText('side')}`, `Atrás: ${decalText('back')}`,
  ];
}
function renderSpec(){ $('#spec').innerHTML = specText().map(l => { const [a, ...b] = l.split(': '); return `<b>${esc(a)}:</b> ${esc(b.join(': '))}`; }).join('<br>') + `<br><b>Piezas:</b> ${esc(S.qty)}`; }
function sendQuote(){
  const nm = $('#qName').value.trim(); if(!nm){ $('#qName').focus(); $('#qName').style.borderColor = '#ff6b5b'; return; }
  const msg = `¡Hola Be Cap! 🧢 Diseñé esta gorra en Be Cap Studio y quiero cotizarla:\n\n${specText().map(l=>'• '+l).join('\n')}\n• Piezas: ${S.qty}\n• Uso: ${$('#qUse').value}${$('#qDate').value?'\n• Para: '+$('#qDate').value:''}${$('#qNotes').value.trim()?'\n• Notas: '+$('#qNotes').value.trim():''}\n\nNombre: ${nm}\n(Te adjunto la imagen de mi diseño)`;
  window.open(`https://wa.me/${WA}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener');
}
function updateSummary(){ const t = T(); $('#stSum').innerHTML = [`${t.name} · ${t.sub}`, CUR().n, 'Strapback'].map(x=>`<span>${esc(x)}</span>`).join(''); }
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
