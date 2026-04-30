-- Optional short label shown for this workflow on pending approvals (module + Aprobaciones list).
-- Version: 3.115.62

ALTER TABLE `#__ordenproduccion_approval_workflows`
    ADD COLUMN `pending_module_type_label` varchar(255) DEFAULT NULL COMMENT 'Displayed as request type in pending approvals UI when set' AFTER `description`;
