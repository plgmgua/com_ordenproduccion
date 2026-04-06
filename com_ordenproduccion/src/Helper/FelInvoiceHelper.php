<?php
/**
 * FEL mock invoice artifact download URLs (PDF/XML).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Build download URLs that survive SEF routing (Route::_ can strip task/token query vars).
 */
class FelInvoiceHelper
{
    /**
     * Absolute non-SEF URL for invoice.downloadFelArtifact with CSRF token in query string.
     *
     * Same behaviour as cotizacion.downloadPdf: PDF opens in the browser (inline) unless $forceDownload is true (adds download=1).
     *
     * @param   int     $invoiceId      Invoice primary key
     * @param   string  $type           pdf|xml
     * @param   bool    $forceDownload  If true, send Content-Disposition attachment (save file)
     *
     * @return  string
     */
    public static function downloadFelArtifactUrl(int $invoiceId, string $type = 'pdf', bool $forceDownload = false): string
    {
        $type = ($type === 'xml') ? 'xml' : 'pdf';
        $params = [
            'option'     => 'com_ordenproduccion',
            'task'       => 'invoice.downloadFelArtifact',
            'invoice_id' => $invoiceId,
            'type'       => $type,
            'format'     => 'raw',
            'tmpl'       => 'component',
        ];
        if ($forceDownload) {
            $params['download'] = '1';
        }
        $params[Session::getFormToken()] = '1';
        $root = rtrim(Uri::root(false), '/');

        return $root . '/index.php?' . http_build_query($params);
    }
}
