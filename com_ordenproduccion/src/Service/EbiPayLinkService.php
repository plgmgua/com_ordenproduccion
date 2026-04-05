<?php
/**
 * Mock ebi pay link API (Documentación Técnica ebi pay): login → network/all → link/maintenance.
 * Does not call production URLs; simulates JSON responses and writes debug artifacts under media.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * Mock ebi pay payment-link engine.
 *
 * Production endpoints (reference only):
 * - POST https://admlink.ebi.com.gt/api/login
 * - POST https://admlink.ebi.com.gt/api/network/all
 * - POST https://admlink.ebi.com.gt/api/link/maintenance
 *
 * @since  3.101.55
 */
class EbiPayLinkService
{
    /** @var DatabaseInterface */
    protected $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    public function isEngineAvailable(): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_quotations', false);

        return \is_array($cols) && isset(array_change_key_case($cols, CASE_LOWER)['ebipay_mock_json']);
    }

    /**
     * Build mock payment links for a quotation (no invoice required).
     *
     * @return array{success: bool, message?: string, codigo_interno?: string, links?: list<array{nombre: string, url: string}>, debug?: mixed}
     */
    public function createMockLinkForQuotation(int $quotationId): array
    {
        Factory::getLanguage()->load('com_ordenproduccion', JPATH_SITE);

        if ($quotationId < 1) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_QUOTATION')];
        }

        if (!$this->isEngineAvailable()) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_EBIPAY_COLUMNS_MISSING')];
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $row = $this->db->loadObject();
        if (!$row) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $total = round((float) ($row->total_amount ?? 0), 2);
        if ($total <= 0) {
            return ['success' => false, 'message' => 'Quotation total must be greater than zero'];
        }

        $num   = trim((string) ($row->quotation_number ?? ''));
        $title = $num !== '' ? $num : ('COT-' . $quotationId);
        $client = trim((string) ($row->client_name ?? ''));
        $titulo = $title . ($client !== '' ? ' — ' . $client : '');
        if (\strlen($titulo) > 100) {
            $titulo = \substr($titulo, 0, 97) . '...';
        }

        $token = bin2hex(random_bytes(20));
        $slug  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title . '-' . $quotationId));
        $slug  = trim($slug, '-');
        if ($slug === '') {
            $slug = 'cot-' . $quotationId;
        }

        $codigoInterno = 'EBI-' . $quotationId . '-' . \substr(sha1($token . $titulo), 0, 10);

        // --- Mock: login (see doc: llave, usuario, clave → token) ---
        $loginRequest = [
            'llave'   => '***mock***',
            'usuario' => '***mock-user***',
            'clave'   => '***',
        ];
        $loginResponse = [
            'result'  => 'ok',
            'message' => 'Login exitoso (simulación local; no se llama a admlink.ebi.com.gt)',
            'data'    => ['token' => $token],
        ];

        // --- Mock: network/all (redes para link) ---
        $networkRequest = [
            'llave' => '***mock***',
            'token' => $token,
        ];
        $mockRedes = [
            ['codigo' => 'ebipay_web_01', 'nombre' => 'Web / Link directo'],
            ['codigo' => 'ebipay_wa_01', 'nombre' => 'WhatsApp'],
        ];
        $networkResponse = [
            'result'  => 'ok',
            'message' => 'Catálogo de redes (mock)',
            'data'    => $mockRedes,
        ];

        $redesCsv = implode(',', array_column($mockRedes, 'codigo'));

        // --- Mock: link/maintenance ---
        $linkRequest = [
            'llave'           => '***mock***',
            'token'           => $token,
            'codigo_interno'  => $codigoInterno,
            'titulo'          => $titulo,
            'cuotas'          => 'VC00',
            'nombre_interno'  => 'Cotización ' . $title,
            'descripcion'     => 'Pago cotización ' . $title . ($client !== '' ? ' — ' . $client : ''),
            'monto'           => $total,
            'redes_sociales'  => $redesCsv,
            'estado'          => 1,
        ];

        $relBase = 'media/com_ordenproduccion/ebipay_mock/' . $quotationId . '/' . $slug;
        $absBase = JPATH_ROOT . '/' . $relBase;
        if (!Folder::create($absBase)) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_EBIPAY_LINK_DIR_ERROR')];
        }

        $root = Uri::root();
        $linksOut = [];
        foreach ($mockRedes as $r) {
            $code = $r['codigo'];
            $url  = $root . $relBase . '/checkout-' . preg_replace('/[^a-z0-9_\-]/i', '', $code) . '.html?q=' . rawurlencode($codigoInterno);
            $linksOut[] = [
                'nombre' => $r['nombre'],
                'url'    => $url,
            ];
        }

        $linkResponse = [
            'result'  => 'ok',
            'message' => 'Link creado (simulación; respuesta tipo API link/maintenance)',
            'data'    => $linksOut,
        ];

        $payStub = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>ebi pay (mock)</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:480px;margin:2rem auto;padding:1rem;border:1px solid #ccc;border-radius:8px}'
            . 'h1{font-size:1.1rem}.muted{color:#666;font-size:.9rem}</style></head><body>'
            . '<h1>Página de pago simulada (ebi pay)</h1>'
            . '<p class="muted">Esta URL es generada por el motor de pruebas del componente. No procesa tarjetas reales.</p>'
            . '<p><strong>Código interno:</strong> ' . htmlspecialchars($codigoInterno, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Monto:</strong> Q ' . htmlspecialchars(number_format($total, 2), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</body></html>';
        file_put_contents($absBase . '/checkout-ebipay_web_01.html', $payStub);
        file_put_contents($absBase . '/checkout-ebipay_wa_01.html', $payStub);

        $debug = [
            'doc'              => 'Documentación Técnica ebi pay (mock engine)',
            'production_hints' => [
                'login'     => 'https://admlink.ebi.com.gt/api/login',
                'network'   => 'https://admlink.ebi.com.gt/api/network/all',
                'link'      => 'https://admlink.ebi.com.gt/api/link/maintenance',
            ],
            'login_request'    => $loginRequest,
            'login_response'   => $loginResponse,
            'network_request'  => $networkRequest,
            'network_response' => $networkResponse,
            'link_request'     => $linkRequest,
            'link_response'    => $linkResponse,
            'local_artifacts'  => [
                'directory' => $relBase,
            ],
            'generated_at'     => Factory::getDate()->toSql(),
        ];

        $debugPath = $absBase . '/ebipay_mock_debug.json';
        file_put_contents($debugPath, json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $jsonStore = $debug;
        $jsonStore['links'] = $linksOut;

        $update = (object) [
            'id'                  => $quotationId,
            'ebipay_codigo_interno' => $codigoInterno,
            'ebipay_mock_json'    => json_encode($jsonStore, JSON_UNESCAPED_UNICODE),
            'ebipay_mock_at'      => Factory::getDate()->toSql(),
        ];

        if (!$this->db->updateObject('#__ordenproduccion_quotations', $update, 'id')) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_EBIPAY_LINK_SAVE_FAILED')];
        }

        return [
            'success'        => true,
            'message'        => 'OK',
            'codigo_interno' => $codigoInterno,
            'links'          => $linksOut,
            'debug'          => $debug,
        ];
    }
}
