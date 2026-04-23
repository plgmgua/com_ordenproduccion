-- Quotation line item reference images (JSON array of relative paths) for PDF / UI.
ALTER TABLE `#__ordenproduccion_quotation_items`
    ADD COLUMN `line_images_json` TEXT NULL DEFAULT NULL COMMENT 'JSON array of relative paths under media/com_ordenproduccion/quotation_line_images' AFTER `descripcion`;
