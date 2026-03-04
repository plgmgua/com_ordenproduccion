-- Set all payment proofs to "Ingresado" and change column default to "ingresado".
-- Run in phpMyAdmin (replace joomla_ with your table prefix if different).
-- After this, only proofs marked "Verificado" (via the button) will affect client balance.

-- 1) Set every existing proof to Ingresado
UPDATE `joomla_ordenproduccion_payment_proofs`
SET `verification_status` = 'ingresado'
WHERE 1;

-- 2) Change the column default to ingresado (for any future direct inserts)
ALTER TABLE `joomla_ordenproduccion_payment_proofs`
MODIFY COLUMN `verification_status` VARCHAR(20) NOT NULL DEFAULT 'ingresado'
COMMENT 'ingresado=not yet validated; verificado=counts toward client balance';
