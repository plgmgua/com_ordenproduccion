-- Create the missing ordenes_info table for EAV data
-- This table stores additional work order information like tecnico, detalles, etc.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_ordenes_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_de_orden` varchar(50) NOT NULL,
  `tipo_de_campo` varchar(50) NOT NULL,
  `valor` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_numero_orden` (`numero_de_orden`),
  KEY `idx_tipo_campo` (`tipo_de_campo`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample EAV data for testing
INSERT INTO `joomla_ordenproduccion_ordenes_info` 
(`numero_de_orden`, `tipo_de_campo`, `valor`, `usuario`) 
VALUES 
('ORD-005487', 'tecnico', 'Juan Pérez', 'admin'),
('ORD-005487', 'detalles', 'Impresión en color, acabado brillante', 'admin'),
('ORD-005487', 'estado', 'Terminada', 'admin'),
('ORD-005487', 'tipo', 'Externa', 'admin'),
('ORD-005496', 'tecnico', 'María González', 'admin'),
('ORD-005496', 'detalles', 'Impresión en blanco y negro, acabado mate', 'admin'),
('ORD-005496', 'estado', 'En proceso', 'admin'),
('ORD-005496', 'tipo', 'Interna', 'admin');
