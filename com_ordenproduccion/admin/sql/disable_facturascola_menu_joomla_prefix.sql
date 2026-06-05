-- Disable / re-enable the "Facturas New" (facturascola) site menu item.
-- Table prefix: joomla_ (adjust if your site uses a different prefix).
-- Safe to run in phpMyAdmin; does not rebuild the menu tree.

-- ---------------------------------------------------------------------------
-- Disable (unpublish)
-- ---------------------------------------------------------------------------
UPDATE `joomla_menu`
SET `published` = 0
WHERE `client_id` = 0
  AND `link` LIKE '%option=com_ordenproduccion%view=facturascola%'
  AND `published` = 1;

-- Optional: target by known id from Menu Manager (Administrativo → Facturas New)
-- UPDATE `joomla_menu` SET `published` = 0 WHERE `id` = 2236 AND `alias` = 'facturas-new';

-- ---------------------------------------------------------------------------
-- Re-enable later (publish)
-- ---------------------------------------------------------------------------
-- UPDATE `joomla_menu`
-- SET `published` = 1
-- WHERE `client_id` = 0
--   AND `link` LIKE '%option=com_ordenproduccion%view=facturascola%'
--   AND `published` = 0;
