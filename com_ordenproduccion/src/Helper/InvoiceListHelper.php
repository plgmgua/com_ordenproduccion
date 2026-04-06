<?php
/**
 * Display helpers for invoice lists (Facturas tab).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.103.1
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Client name and invoice "tipo" (valid vs mock-up) for lista/export.
 */
class InvoiceListHelper
{
    public const SOURCE_MOCKUP = 'cotizacion_fel';

    /**
     * Best-effort client display: stored client_name, then FEL receptor name from import.
     */
    public static function displayClientName(object $invoice): string
    {
        $n = trim((string) ($invoice->client_name ?? ''));
        if ($n !== '') {
            return $n;
        }

        $n = trim((string) ($invoice->fel_receptor_nombre ?? ''));
        if ($n !== '') {
            return $n;
        }

        return '';
    }

    /**
     * Mock-up / simulacro FEL issued from cotización queue (not SAT XML import).
     */
    public static function isMockupInvoice(object $invoice): bool
    {
        return (string) ($invoice->invoice_source ?? 'order') === self::SOURCE_MOCKUP;
    }
}
