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

use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Build download URLs that survive SEF routing (Route::_ can strip task/token query vars).
 * Parse stored Digifact NUC request JSON for invoice UI (AdditionalDocumentInfo).
 */
class FelInvoiceHelper
{
    /**
     * Whether this invoice row holds artifacts from the **mock FELplex** pipeline for a cotización
     * (under fel_issued/, not the Digifact direct subfolder). Used for Super User–only download ACL.
     *
     * @param   object  $inv  Invoice row (fel_local_* paths, quotation_id)
     *
     * @return  bool
     *
     * @since   3.118.49
     */
    public static function isMockFelplexQuotationArtifactInvoice(object $inv): bool
    {
        $qid = (int) ($inv->quotation_id ?? 0);
        if ($qid < 1) {
            return false;
        }
        $norm = static function (string $p): string {
            return str_replace('\\', '/', $p);
        };
        $pdf = $norm(trim((string) ($inv->fel_local_pdf_path ?? '')));
        $xml = $norm(trim((string) ($inv->fel_local_xml_path ?? '')));
        $path = $pdf !== '' ? $pdf : $xml;
        if ($path === '') {
            return false;
        }
        if (!str_contains($path, 'fel_issued/')) {
            return false;
        }
        if (str_contains($path, '/digifact')) {
            return false;
        }

        return true;
    }

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

    /**
     * Grimpsa branded invoice PDF (FPDI template + data overlay). Opens inline in the browser unless $forceDownload.
     *
     * @since  3.118.54
     */
    public static function downloadGrimpsaFacturaPdfUrl(int $invoiceId, bool $forceDownload = false): string
    {
        $params = [
            'option'     => 'com_ordenproduccion',
            'task'       => 'invoice.downloadGrimpsaFacturaPdf',
            'invoice_id' => max(1, $invoiceId),
            'format'     => 'raw',
            'tmpl'       => 'component',
        ];
        if ($forceDownload) {
            $params['download'] = '1';
        }
        $root = rtrim(Uri::root(false), '/');

        return $root . '/index.php?' . http_build_query($params);
    }

    /**
     * PDF / factura open URL for órdenes list action button (manual upload, Grimpsa template, or invoice screen).
     *
     * @since  3.119.72
     */
    public static function resolveOpenUrlForOrdenesList(int $invoiceId, string $manualPdfRel, bool $grimpsaTemplatePdfOk): string
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1) {
            return '';
        }

        if (trim($manualPdfRel) !== '') {
            return Route::_(
                'index.php?option=com_ordenproduccion&task=invoice.downloadManualPdf&invoice_id=' . $invoiceId,
                false
            );
        }

        if ($grimpsaTemplatePdfOk) {
            return self::downloadGrimpsaFacturaPdfUrl($invoiceId);
        }

        return Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $invoiceId, false);
    }

    /**
     * Rows from NUC JSON AdditionalDocumentInfo for display: each item has label + value (trimmed strings).
     *
     * Supports compact Digifact shape (@Name + #text) and legacy nested ADENDA Info (Name + Value).
     *
     * @param   string|null  $felRequestJson  Invoice row fel_request_json column
     *
     * @return  list<array{label: string, value: string}>
     *
     * @since   3.118.43
     */
    public static function parseNucAdditionalDocumentRowsFromFelRequest(?string $felRequestJson): array
    {
        if ($felRequestJson === null || trim($felRequestJson) === '') {
            return [];
        }

        $payload = json_decode($felRequestJson, true);
        if (!\is_array($payload)) {
            return [];
        }

        $list = $payload['AdditionalDocumentInfo']['AdditionalInfo'] ?? null;
        if (!\is_array($list)) {
            return [];
        }

        $out   = [];
        $seen  = [];
        $add   = static function (string $label, string $value) use (&$out, &$seen): void {
            $label = trim($label);
            $value = trim($value);
            if ($label === '' && $value === '') {
                return;
            }
            if ($label === '') {
                $label = '—';
            }
            $sig = strtolower($label) . "\0" . $value;
            if (isset($seen[$sig])) {
                return;
            }
            $seen[$sig] = true;
            $out[]      = ['label' => $label, 'value' => $value];
        };

        foreach ($list as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            if (\array_key_exists('#text', $entry) || \array_key_exists('@Name', $entry)) {
                $name = trim((string) ($entry['@Name'] ?? ''));
                $text = trim((string) ($entry['#text'] ?? ''));
                $add($name !== '' ? $name : 'Cotizacion', $text);

                continue;
            }

            $code = trim((string) ($entry['Code'] ?? ''));
            if ($code !== '' && strtoupper((string) ($entry['Type'] ?? '')) === 'ADENDA') {
                $add('Code', $code);
            }

            $dataBlocks = $entry['AditionalData']['Data'] ?? null;
            if (!\is_array($dataBlocks)) {
                continue;
            }
            foreach ($dataBlocks as $block) {
                if (!\is_array($block)) {
                    continue;
                }
                $infos = $block['Info'] ?? null;
                if (!\is_array($infos)) {
                    continue;
                }
                foreach ($infos as $info) {
                    if (!\is_array($info)) {
                        continue;
                    }
                    $n = trim((string) ($info['Name'] ?? ''));
                    $v = isset($info['Value']) ? trim((string) $info['Value']) : '';
                    $add($n, $v);
                }
            }
        }

        return $out;
    }

    /**
     * One-line summary for invoice list (prefers Cotizacion/COTIZACION row, else first non-empty value).
     *
     * @since   3.118.43
     */
    public static function nucAdditionalSummaryForList(?string $felRequestJson): string
    {
        $rows = self::parseNucAdditionalDocumentRowsFromFelRequest($felRequestJson);
        foreach ($rows as $row) {
            $l = $row['label'];
            if (strcasecmp($l, 'Cotizacion') === 0 || strcasecmp($l, 'COTIZACION') === 0) {
                return $row['value'];
            }
        }
        foreach ($rows as $row) {
            if ($row['value'] !== '') {
                return $row['value'];
            }
        }

        return '';
    }
}
