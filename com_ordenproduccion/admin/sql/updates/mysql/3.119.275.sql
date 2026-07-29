-- com_ordenproduccion 3.119.275-STABLE: widen invoices.orden_de_trabajo for multi-OT manual FEL rows.

ALTER TABLE `#__ordenproduccion_invoices`
    MODIFY COLUMN `orden_de_trabajo` varchar(500) NOT NULL DEFAULT '';
