CREATE TABLE IF NOT EXISTS ajustes (
  clave VARCHAR(40) PRIMARY KEY,
  valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  usuario VARCHAR(40) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  creado DATETIME NOT NULL,
  INDEX (ip, tipo, creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  direccion TEXT NULL,
  notas TEXT NULL,
  origen VARCHAR(20) NOT NULL DEFAULT 'manual',
  creado DATETIME NOT NULL,
  actualizado DATETIME NOT NULL,
  INDEX (telefono), INDEX (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS citas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  motivo TEXT NULL,
  estado ENUM('pendiente','confirmada','atendida','cancelada','no_asistio') NOT NULL DEFAULT 'pendiente',
  origen ENUM('web','manual') NOT NULL DEFAULT 'manual',
  usuario_id INT NULL,
  creado DATETIME NOT NULL,
  INDEX (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cotizaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NULL,
  fecha DATE NOT NULL,
  vigencia DATE NULL,
  estado ENUM('borrador','enviada','aceptada','rechazada') NOT NULL DEFAULT 'borrador',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  envio DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  notas TEXT NULL,
  venta_id INT NULL,
  usuario_id INT NULL,
  creado DATETIME NOT NULL,
  actualizado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NULL,
  fecha DATETIME NOT NULL,
  origen ENUM('web','whatsapp','tienda','otro') NOT NULL DEFAULT 'tienda',
  estado ENUM('por_confirmar','confirmada','cancelada') NOT NULL DEFAULT 'confirmada',
  entrega ENUM('tienda','envio') NOT NULL DEFAULT 'tienda',
  entregada TINYINT(1) NOT NULL DEFAULT 0,
  direccion TEXT NULL,
  guia VARCHAR(80) NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  envio DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  pagado DECIMAL(10,2) NOT NULL DEFAULT 0,
  notas TEXT NULL,
  cotizacion_id INT NULL,
  usuario_id INT NULL,
  creado DATETIME NOT NULL,
  actualizado DATETIME NOT NULL,
  INDEX (fecha), INDEX (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doc ENUM('venta','cotizacion') NOT NULL,
  doc_id INT NOT NULL,
  producto_web_id INT NULL,
  descripcion VARCHAR(200) NOT NULL,
  cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0,
  importe DECIMAL(10,2) NOT NULL DEFAULT 0,
  INDEX (doc, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venta_id INT NOT NULL,
  fecha DATETIME NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  metodo ENUM('efectivo','transferencia','tarjeta','deposito','otro') NOT NULL DEFAULT 'efectivo',
  referencia VARCHAR(80) NULL,
  usuario_id INT NULL,
  creado DATETIME NOT NULL,
  INDEX (venta_id), INDEX (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_web_id INT NULL,
  descripcion VARCHAR(190) NOT NULL,
  categoria VARCHAR(40) NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NULL,
  minimo INT NOT NULL DEFAULT 1,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  actualizado DATETIME NOT NULL,
  UNIQUE KEY prod_desc (producto_web_id, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inventario_id INT NOT NULL,
  tipo ENUM('entrada','salida','ajuste','venta','devolucion') NOT NULL,
  cantidad INT NOT NULL,
  stock_final INT NULL,
  nota VARCHAR(200) NULL,
  venta_id INT NULL,
  usuario_id INT NULL,
  creado DATETIME NOT NULL,
  INDEX (inventario_id), INDEX (venta_id), INDEX (creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ajustes (clave, valor) VALUES ('schema', '0')
