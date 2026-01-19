-- Create banks management table
-- Version 3.5.1
-- Table prefix: joomla_

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_banks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `code` varchar(100) NOT NULL COMMENT 'Unique bank code (e.g., banco_industrial)',
    `name` varchar(255) NOT NULL COMMENT 'Bank display name',
    `name_en` varchar(255) DEFAULT NULL COMMENT 'English display name',
    `name_es` varchar(255) DEFAULT NULL COMMENT 'Spanish display name',
    `ordering` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order',
    `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is default bank',
    `state` tinyint(3) NOT NULL DEFAULT 1 COMMENT 'Published state',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_code` (`code`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`),
    KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank management for payment slips';

-- Insert initial banks from hardcoded list
INSERT INTO `joomla_ordenproduccion_banks` (`code`, `name`, `name_en`, `name_es`, `ordering`, `is_default`, `state`, `created_by`) VALUES
('banco_industrial', 'Banco Industrial', 'Banco Industrial', 'Banco Industrial', 1, 0, 1, 0),
('banco_gyt', 'Banco G&T Continental', 'Banco G&T Continental', 'Banco G&T Continental', 2, 0, 1, 0),
('banco_promerica', 'Banco Promerica', 'Banco Promerica', 'Banco Promerica', 3, 0, 1, 0),
('banco_agricola', 'Banco Agrícola', 'Banco Agrícola', 'Banco Agrícola', 4, 0, 1, 0),
('banco_azteca', 'Banco Azteca', 'Banco Azteca', 'Banco Azteca', 5, 0, 1, 0),
('banco_citibank', 'Citibank', 'Citibank', 'Citibank', 6, 0, 1, 0),
('banco_davivienda', 'Davivienda', 'Davivienda', 'Davivienda', 7, 0, 1, 0),
('banco_ficohsa', 'Ficohsa', 'Ficohsa', 'Ficohsa', 8, 0, 1, 0),
('banco_gyte', 'GYT Entreprise', 'GYT Entreprise', 'GYT Entreprise', 9, 0, 1, 0),
('banco_inmobiliario', 'Banco Inmobiliario', 'Banco Inmobiliario', 'Banco Inmobiliario', 10, 0, 1, 0),
('banco_internacional', 'Banco Internacional', 'Banco Internacional', 'Banco Internacional', 11, 0, 1, 0),
('banco_metropolitano', 'Banco Metropolitano', 'Banco Metropolitano', 'Banco Metropolitano', 12, 0, 1, 0),
('banco_promerica_guatemala', 'Promerica Guatemala', 'Promerica Guatemala', 'Promerica Guatemala', 13, 0, 1, 0),
('banco_refaccionario', 'Banco Refaccionario', 'Banco Refaccionario', 'Banco Refaccionario', 14, 0, 1, 0),
('banco_rural', 'Banco Rural', 'Banco Rural', 'Banco Rural', 15, 0, 1, 0),
('banco_salud', 'Banco Salud', 'Banco Salud', 'Banco Salud', 16, 0, 1, 0),
('banco_vivibanco', 'ViviBanco', 'ViviBanco', 'ViviBanco', 17, 0, 1, 0);
