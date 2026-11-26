# Web de Be Cap (APARECE · Plan Esencial)

Sitio estático publicado en Hostinger desde la rama `main`.

- `index.html`, `productos.js`, `img/`: la web.
- `studio/`: modelo 3D de la gorra (`becap-gorra.glb`) y texturas de Be Cap Studio 3D.
- `_fuente/`: código sin minificar. `node build.mjs` regenera `index.html` (requiere `npm i terser csso`).
- Catálogo de tipos y colores de gorra: `_fuente/catalogo.js` (del PDF "ORIGINAL COLORES BECAP").

## Panel de control (`panel/`)

Panel del plan Esencial en PHP + MySQL: resumen, ventas y cobros, cotizaciones, agenda y citas, clientes, reportes con descarga a Excel y usuarios (hasta 2).

- Entrada: `/panel/` (enlace "Equipo" al final del menú de la web).
- Configuración: `panel/_privado/config.php` vive SOLO en el servidor (está en `.gitignore`). Plantilla: `panel/_privado/config.example.php`.
- Las tablas se crean solas la primera vez. El primer administrador se crea en `/panel/` con la `setup_key` del config.
- `conexion-panel.js` manda al panel los pedidos y citas que los clientes envían por WhatsApp desde la web (quedan "por confirmar").
