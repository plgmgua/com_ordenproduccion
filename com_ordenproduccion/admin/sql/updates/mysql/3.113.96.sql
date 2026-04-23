-- Orden de compra numbering (configurable prefix + padded sequence).
ALTER TABLE `#__ordenproduccion_settings`
    ADD COLUMN `next_orden_compra_number` int(11) NOT NULL DEFAULT 1,
    ADD COLUMN `orden_compra_prefix` varchar(10) NOT NULL DEFAULT 'ORC',
    ADD COLUMN `orden_compra_number_width` tinyint(3) unsigned NOT NULL DEFAULT 5;
