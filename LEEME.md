# Web de Be Cap (APARECE · Plan Esencial)

Sitio estático publicado en Hostinger desde la rama `main`.

- `index.html`, `productos.js`, `img/`: la web.
- `studio/`: modelo 3D de la gorra (`becap-gorra.glb`) y texturas de Be Cap Studio 3D.
- `_fuente/`: código sin minificar. `node build.mjs` regenera `index.html` (requiere `npm i terser csso`).
- Catálogo de tipos y colores de gorra: `_fuente/catalogo.js` (del PDF "ORIGINAL COLORES BECAP").
