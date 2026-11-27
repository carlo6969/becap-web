# Web de Be Cap (APARECE · Plan Esencial)

Sitio estático publicado en Hostinger desde la rama `main`.

- `index.html`, `productos.js`, `img/`: la web.
- `studio/`: modelo 3D de la gorra (`becap-gorra.glb`) y texturas de Be Cap Studio 3D.
- `_fuente/`: código sin minificar. `node build.mjs` regenera `index.html` (requiere `npm i terser csso`).
- Catálogo de tipos y colores de gorra: `_fuente/catalogo.js` (del PDF "ORIGINAL COLORES BECAP").
- **Precios del Studio 3D: `studio/precios.js`.** Precio de la gorra lisa por modelo, texto por zona y logo por técnica (Bordado 3D, Bordado 2D, Parche, DTF). Se edita solo el número; no hay que reconstruir `index.html`.
- Colocación real en el Studio: bordado frontal a 2.5 cm de la visera y nombres laterales (izquierdo y derecho) a 1.5 cm del borde inferior (constantes al inicio de la sección "bordados" en `_fuente/studio.js`).
- Categorías de la tienda: Gorras (Originales, Diseñador, Vintage & Flex, Bucket), Ropa, Personalizado, Paquetes mayoreo, Accesorios y Limpieza. Se asignan desde el panel (Productos → Categoría).

## Panel de control (`panel/`)

Panel del plan Esencial en PHP + MySQL: resumen, ventas y cobros, cotizaciones, agenda y citas, clientes, reportes con descarga a Excel y usuarios (hasta 2). Incluye de regalo el inventario con aviso de faltantes (módulo del plan Negocio): las ventas confirmadas descuentan existencias y las canceladas las regresan.

- Entrada: `/panel/` (enlace "Equipo" al final del menú de la web).
- Configuración: `becap-panel-config.php` junto a `public_html` (recomendado: así una nueva publicación no lo borra) o `panel/_privado/config.php`. Ninguno va en GitHub. Plantilla: `panel/_privado/config.example.php`.
- Fotos de productos: se guardan en `becap-fotos/` junto a `public_html` y se sirven con `panel/api/foto.php`.
- Las tablas se crean solas la primera vez. El primer administrador se crea en `/panel/` con la `setup_key` del config.
- `conexion-panel.js` manda al panel los pedidos y citas que los clientes envían por WhatsApp desde la web (quedan "por confirmar").
