/* =====================================================================
   BE CAP STUDIO · LISTA DE PRECIOS
   Para cambiar un precio, edita solo el número y guarda el archivo.
   Todos los precios son en pesos mexicanos, por pieza (por gorra).
   ===================================================================== */
window.BECAP_PRECIOS = {

  // Precio de la gorra lisa (sin personalizar), por modelo.
  // Si lo dejas en 0, la cotización dice "precio de la gorra por confirmar".
  gorra: {
    'algodon-1':   0,   // Algodón 1 tono
    'algodon-2':   0,   // Algodón 2 tonos
    'deslavado-1': 0,   // Deslavado 1 tono
    'deslavado-2': 0    // Deslavado 2 tonos
  },

  // Texto (nombre o frase) en cualquier zona: frente, laterales o atrás.
  texto: {
    bordado3d: 38,
    bordado2d: 38,
    parche:    38,
    dtf:       38
  },

  // Logo (imagen que sube el cliente) en cualquier zona, según la técnica.
  logo: {
    bordado3d: 205,
    bordado2d: 185,
    parche:    205,
    dtf:       40
  }

};
