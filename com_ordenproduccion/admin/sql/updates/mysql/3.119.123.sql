-- com_ordenproduccion 3.119.123-STABLE: link invoices to multiple cotizaciones (manual FEL multi-cot)

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_invoice_quotations` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `invoice_id` int(11) NOT NULL,
    `quotation_id` int(11) NOT NULL,
    `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = primary quotation on invoice row',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoice_quotation` (`invoice_id`, `quotation_id`),
    KEY `idx_invoice_id` (`invoice_id`),
    KEY `idx_quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
