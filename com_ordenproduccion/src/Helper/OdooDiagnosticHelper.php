<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Full Odoo connection diagnostic using stored com_ordenproduccion options and Joomla users.
 *
 * Used by troubleshooting.php (web) and tools/test_odoo_connection.php (CLI).
 */
class OdooDiagnosticHelper
{
    /** @var array<string, mixed> */
    private array $params;

    private string $odooUrl = '';
    private string $odooDb = '';
    private int $odooUserId = 0;
    private string $odooApiKey = '';
    private string $baseUrl = '';
    private string $commonUrl = '';
    private string $objectUrl = '';

    private int $failures = 0;
    private int $warnings = 0;

    /** @var array<int, array<string, mixed>> */
    private array $sections = [];

    /**
     * @param   array<string, mixed>  $options
     *   - joomla_user_id (int): test one Joomla user for Mis Clientes
     *   - agent (string): test one agent name directly
     *   - odoo_login (string): run authenticate() and compare UID
     *   - scan_users (bool): scan active Joomla users (default true)
     *   - user_limit (int): max users to scan (default 30)
     *
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $this->failures = 0;
        $this->warnings = 0;
        $this->sections = [];

        $this->params     = ComponentHelper::getParams('com_ordenproduccion')->toArray();
        $this->odooUrl    = trim((string) ($this->params['odoo_url'] ?? ''));
        $this->odooDb     = trim((string) ($this->params['odoo_db'] ?? ''));
        $this->odooUserId = (int) ($this->params['odoo_user_id'] ?? 0);
        $this->odooApiKey = trim((string) ($this->params['odoo_api_key'] ?? ''));
        $this->baseUrl    = self::normalizeOdooBaseUrl($this->odooUrl);
        $this->commonUrl  = $this->baseUrl !== '' ? $this->baseUrl . '/xmlrpc/2/common' : '';
        $this->objectUrl  = $this->baseUrl !== '' ? $this->baseUrl . '/xmlrpc/2/object' : '';

        $joomlaUserId = max(0, (int) ($options['joomla_user_id'] ?? 0));
        $agentName    = trim((string) ($options['agent'] ?? ''));
        $odooLogin    = trim((string) ($options['odoo_login'] ?? ''));
        $scanUsers    = !array_key_exists('scan_users', $options) || (bool) $options['scan_users'];
        $userLimit    = max(1, min(100, (int) ($options['user_limit'] ?? 30)));

        if ($joomlaUserId > 0 && $agentName === '') {
            $agentName = $this->loadJoomlaUserName($joomlaUserId);
        }

        $this->runConfigSection();
        if ($this->odooUrl === '' || $this->odooDb === '' || $this->odooApiKey === '') {
            return $this->buildReport($agentName, $joomlaUserId, [], [], []);
        }

        $this->runVersionSection();
        $this->runAuthenticateSection($odooLogin);
        $this->runHelperTestSection();
        $this->runPartnerCountSection();
        $odooAgentSample = $this->runStudioFieldSection();

        $agentTests = [];
        if ($agentName !== '') {
            $agentTests[] = $this->runAgentTest($joomlaUserId, $agentName, $odooAgentSample);
            $this->runHelperAgentSection($agentName);
        }

        $joomlaUsers = [];
        if ($scanUsers) {
            $joomlaUsers = $this->loadActiveJoomlaUsers($userLimit);
            if ($agentName === '') {
                foreach ($joomlaUsers as $user) {
                    $agentTests[] = $this->runAgentTest(
                        (int) $user['id'],
                        (string) $user['name'],
                        $odooAgentSample,
                        true
                    );
                }
            }
            $this->runJoomlaOdooNameMatchSection($joomlaUsers, $odooAgentSample);
        }

        return $this->buildReport($agentName, $joomlaUserId, $joomlaUsers, $odooAgentSample, $agentTests);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(
        string $focusAgent,
        int $focusUserId,
        array $joomlaUsers,
        array $odooAgentSample,
        array $agentTests
    ): array {
        $status = 'ok';
        if ($this->failures > 0) {
            $status = 'fail';
        } elseif ($this->warnings > 0) {
            $status = 'warn';
        }

        return [
            'meta' => [
                'time'        => date('Y-m-d H:i:s'),
                'joomla_root' => defined('JPATH_ROOT') ? JPATH_ROOT : (defined('JPATH_BASE') ? JPATH_BASE : ''),
                'failures'    => $this->failures,
                'warnings'    => $this->warnings,
                'status'      => $status,
            ],
            'config' => [
                'odoo_url'      => $this->odooUrl,
                'object_url'    => $this->objectUrl,
                'odoo_db'       => $this->odooDb,
                'odoo_user_id'  => $this->odooUserId,
                'odoo_api_key'  => self::maskSecret($this->odooApiKey),
                'focus_agent'   => $focusAgent,
                'focus_user_id' => $focusUserId,
            ],
            'sections'         => $this->sections,
            'joomla_users'     => $joomlaUsers,
            'odoo_agent_sample'=> $odooAgentSample,
            'agent_tests'      => $agentTests,
        ];
    }

    private function runConfigSection(): void
    {
        $checks = [];
        $checks[] = $this->check('odoo_url is set', $this->odooUrl !== '', 'Set Odoo URL in Components → Orden de Producción → Options → Odoo connection');
        $checks[] = $this->check('odoo_db is set', $this->odooDb !== '', 'Set database name (Odoo Online instance name)');
        $checks[] = $this->check('odoo_api_key is set', $this->odooApiKey !== '', 'Generate API key in Odoo → Preferences → Account Security');
        $checks[] = $this->check('odoo_user_id > 0', $this->odooUserId > 0, 'Set numeric UID from authenticate() after cloud migration');

        $this->sections[] = [
            'id'     => 'config',
            'title'  => '1. Joomla component options (stored credentials)',
            'checks' => $checks,
            'details' => [
                'odoo_url'     => $this->odooUrl !== '' ? $this->odooUrl : '(empty)',
                'object_url'   => $this->objectUrl !== '' ? $this->objectUrl : '(empty)',
                'odoo_db'      => $this->odooDb !== '' ? $this->odooDb : '(empty)',
                'odoo_user_id' => (string) $this->odooUserId,
                'odoo_api_key' => self::maskSecret($this->odooApiKey),
            ],
        ];
    }

    private function runVersionSection(): void
    {
        $checks = [];
        $result = $this->xmlRpcCall($this->commonUrl, self::buildVersionXml());
        if ($result['fault'] !== null) {
            $checks[] = $this->fail('common.version', $result['fault']);
        } elseif ($result['http_code'] !== 200) {
            $checks[] = $this->fail('common.version HTTP ' . $result['http_code'], $result['curl_error'] ?: 'Non-200 response');
        } else {
            $version = self::extractVersionString($result['parsed']);
            $checks[] = $this->pass('common.version', $version !== '' ? $version : 'OK');
        }

        $this->sections[] = ['id' => 'version', 'title' => '2. Odoo server reachability', 'checks' => $checks];
    }

    private function runAuthenticateSection(string $odooLogin): void
    {
        $checks = [];
        if ($odooLogin === '') {
            $checks[] = $this->warn(
                'authenticate() skipped',
                'No odoo_login stored in Joomla. Pass ?odoo_login=email or CLI --login= to verify UID after migration.'
            );
        } else {
            $result = $this->xmlRpcCall($this->commonUrl, self::buildAuthenticateXml($this->odooDb, $odooLogin, $this->odooApiKey));
            if ($result['fault'] !== null) {
                $checks[] = $this->fail('common.authenticate', $result['fault']);
            } else {
                $authUid = self::extractIntParam($result['parsed']);
                if ($authUid === false || $authUid === 0) {
                    $checks[] = $this->fail('common.authenticate', 'Returned false/0 — wrong db, login, or API key');
                } elseif ($authUid === $this->odooUserId) {
                    $checks[] = $this->pass('common.authenticate', "UID {$authUid} matches stored odoo_user_id");
                } else {
                    $checks[] = $this->warn(
                        "authenticate UID ({$authUid}) ≠ stored odoo_user_id ({$this->odooUserId})",
                        'Update odoo_user_id in component options to ' . $authUid
                    );
                }
            }
        }

        $this->sections[] = ['id' => 'auth', 'title' => '3. User authentication (optional)', 'checks' => $checks];
    }

    private function runHelperTestSection(): void
    {
        $checks = [];
        try {
            $helper = new OdooHelper();
            $test   = $helper->testConnection();
            if (!empty($test['success'])) {
                $checks[] = $this->pass('OdooHelper::testConnection()', (string) ($test['message'] ?? 'OK'));
            } else {
                $checks[] = $this->fail('OdooHelper::testConnection()', (string) ($test['message'] ?? 'Failed'));
            }
        } catch (\Throwable $e) {
            $checks[] = $this->fail('OdooHelper::testConnection()', $e->getMessage());
        }

        $this->sections[] = ['id' => 'helper', 'title' => '4. Component OdooHelper::testConnection()', 'checks' => $checks];
    }

    private function runPartnerCountSection(): void
    {
        $checks = [];
        $result = $this->xmlRpcCall(
            $this->objectUrl,
            self::buildExecuteKwXml($this->odooDb, $this->odooUserId, $this->odooApiKey, 'res.partner', 'search_count', '[[]]')
        );
        if ($result['fault'] !== null) {
            $checks[] = $this->fail('res.partner search_count', $result['fault']);
        } else {
            $count = self::extractIntParam($result['parsed']);
            if ($count === false) {
                $checks[] = $this->fail('res.partner search_count', 'Could not parse count');
            } else {
                $checks[] = $this->pass('res.partner search_count', $count . ' partner(s)');
                if ($count === 0) {
                    $checks[] = $this->warn('Zero partners', 'Database empty or API user lacks read access');
                }
            }
        }

        $this->sections[] = ['id' => 'partners', 'title' => '5. res.partner API access', 'checks' => $checks];
    }

    /**
     * @return array<int, string>
     */
    private function runStudioFieldSection(): array
    {
        $checks = [];
        $sample = [];

        $fieldsResult = $this->xmlRpcCall(
            $this->objectUrl,
            self::buildExecuteKwXml(
                $this->odooDb,
                $this->odooUserId,
                $this->odooApiKey,
                'res.partner',
                'fields_get',
                '[]',
                '{"attributes": ["string", "type"]}'
            )
        );

        if ($fieldsResult['fault'] !== null) {
            $checks[] = $this->fail('fields_get', $fieldsResult['fault']);
        } else {
            $fieldNames = self::extractFieldsGetNames($fieldsResult['parsed']);
            if (in_array('x_studio_agente_de_ventas', $fieldNames, true)) {
                $checks[] = $this->pass('x_studio_agente_de_ventas', 'Field exists on res.partner');
            } else {
                $checks[] = $this->fail(
                    'x_studio_agente_de_ventas',
                    'Field missing — restore Studio field on Contacts after Odoo migration'
                );
            }
        }

        $sampleResult = $this->xmlRpcCall(
            $this->objectUrl,
            self::buildExecuteKwXml(
                $this->odooDb,
                $this->odooUserId,
                $this->odooApiKey,
                'res.partner',
                'search_read',
                '[[["parent_id", "=", false], ["x_studio_agente_de_ventas", "!=", false]]]',
                '{"fields": ["x_studio_agente_de_ventas"], "limit": 50}'
            )
        );
        if ($sampleResult['fault'] === null) {
            $sample = self::extractDistinctAgentValues($sampleResult['parsed']);
        }

        $this->sections[] = [
            'id'     => 'studio_field',
            'title'  => '6. Studio sales-agent field',
            'checks' => $checks,
            'details' => ['sample_agents' => $sample],
        ];

        return $sample;
    }

    /**
     * @param   array<int, string>  $odooAgentSample
     * @return array<string, mixed>
     */
    private function runAgentTest(int $joomlaUserId, string $agentName, array $odooAgentSample, bool $compact = false): array
    {
        $row = [
            'joomla_user_id'   => $joomlaUserId,
            'joomla_user_name' => $agentName,
            'odoo_exact_match' => in_array($agentName, $odooAgentSample, true),
            'rpc_count'        => 0,
            'helper_count'     => 0,
            'sample_contacts'  => [],
            'status'           => 'warn',
            'message'          => '',
        ];

        if ($agentName === '') {
            $row['message'] = 'Empty Joomla user name';
            return $row;
        }

        $domain = json_encode([
            ['x_studio_agente_de_ventas', '=', $agentName],
            ['parent_id', '=', false],
        ], JSON_UNESCAPED_UNICODE);

        $agentResult = $this->xmlRpcCall(
            $this->objectUrl,
            self::buildExecuteKwXml(
                $this->odooDb,
                $this->odooUserId,
                $this->odooApiKey,
                'res.partner',
                'search_read',
                '[' . $domain . ']',
                '{"fields": ["id", "name"], "limit": 5}'
            )
        );

        if ($agentResult['fault'] !== null) {
            $row['status']  = 'fail';
            $row['message'] = $agentResult['fault'];
            if (!$compact) {
                $this->sections[] = [
                    'id'     => 'agent_' . $joomlaUserId,
                    'title'  => '7. Mis Clientes filter — ' . $agentName,
                    'checks' => [$this->fail('search_read (agent filter)', $agentResult['fault'])],
                ];
            }
            return $row;
        }

        $matches = self::extractSearchReadNames($agentResult['parsed']);
        $row['rpc_count'] = count($matches);
        $row['sample_contacts'] = $matches;

        try {
            $helper = new OdooHelper();
            $contacts = $helper->getContactsByAgent($agentName, 1, 10, '');
            $row['helper_count'] = is_array($contacts) ? count($contacts) : 0;
        } catch (\Throwable $e) {
            $row['helper_count'] = -1;
            $row['message'] = $e->getMessage();
        }

        if ($row['rpc_count'] > 0 && $row['helper_count'] === 0) {
            $helperKw = '{"fields": ["id", "name", "email", "phone", "mobile", "street", "city", "vat", "type"], "limit": 10}';
            $helperProbe = $this->xmlRpcCall(
                $this->objectUrl,
                self::buildExecuteKwXml(
                    $this->odooDb,
                    $this->odooUserId,
                    $this->odooApiKey,
                    'res.partner',
                    'search_read',
                    '[' . $domain . ']',
                    $helperKw
                )
            );
            $probeCount = count(self::extractSearchReadRecords($helperProbe['parsed']));
            $row['helper_probe_count'] = $probeCount;

            if ($helperProbe['fault'] !== null) {
                $row['helper_fault'] = 'XML-RPC fault: ' . $helperProbe['fault'];
            } elseif ($probeCount > 0) {
                $installed = self::readComponentVersion();
                $row['helper_fault'] = 'Odoo returns ' . $probeCount
                    . ' row(s) with Mis Clientes fields but OdooHelper returned 0 — redeploy src/Helper/OdooHelper.php'
                    . ' (installed VERSION: ' . $installed . ') and clear PHP opcache';
            } elseif ($helperProbe['http_code'] !== 200) {
                $row['helper_fault'] = 'HTTP ' . $helperProbe['http_code']
                    . ($helperProbe['curl_error'] !== '' ? ' — ' . $helperProbe['curl_error'] : '');
            } else {
                $row['helper_fault'] = 'Parser found 0 rows in Mis Clientes field probe';
            }
        }

        if ($row['rpc_count'] > 0) {
            $row['status']  = 'pass';
            $row['message'] = $row['rpc_count'] . ' parent contact(s) in Odoo';
        } elseif ($row['odoo_exact_match']) {
            $row['status']  = 'warn';
            $row['message'] = 'Agent name exists in Odoo sample but 0 parent contacts';
        } else {
            $row['status']  = 'warn';
            $row['message'] = 'No exact agent match in Odoo — check x_studio_agente_de_ventas spelling';
        }

        if (!$compact) {
            $checks = [];
            if ($row['status'] === 'pass') {
                $checks[] = $this->pass('Agent filter', $row['message']);
            } elseif ($row['status'] === 'fail') {
                $checks[] = $this->fail('Agent filter', $row['message']);
            } else {
                $checks[] = $this->warn('Agent filter', $row['message']);
            }
            if ($row['helper_count'] >= 0) {
                $helperDetail = $row['helper_count'] > 0
                    ? $row['helper_count'] . ' contact(s)'
                    : '0 contacts (same as Mis Clientes view)';
                if ($row['helper_count'] === 0 && !empty($row['helper_fault'])) {
                    $helperDetail .= ' — ' . $row['helper_fault'];
                }
                $checks[] = $row['helper_count'] > 0
                    ? $this->pass('getContactsByAgent()', $helperDetail)
                    : $this->warn('getContactsByAgent()', $helperDetail);
            }
            $this->sections[] = [
                'id'     => 'agent_' . $joomlaUserId,
                'title'  => '7. Mis Clientes filter — ' . $agentName,
                'checks' => $checks,
            ];
        }

        return $row;
    }

    private function runHelperAgentSection(string $agentName): void
    {
        // Section already added in runAgentTest when not compact
    }

    /**
     * @param   array<int, array<string, mixed>>  $joomlaUsers
     * @param   array<int, string>                $odooAgentSample
     */
    private function runJoomlaOdooNameMatchSection(array $joomlaUsers, array $odooAgentSample): void
    {
        $checks = [];
        $matched = 0;

        foreach ($joomlaUsers as $user) {
            $name = (string) ($user['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if (in_array($name, $odooAgentSample, true)) {
                $matched++;
            }
        }

        $checks[] = $this->pass(
            'Joomla active users scanned',
            count($joomlaUsers) . ' user(s)'
        );
        $checks[] = $matched > 0
            ? $this->pass('Joomla names matching Odoo agents', $matched . ' exact match(es)')
            : $this->warn(
                'Joomla names matching Odoo agents',
                '0 exact matches — Mis Clientes uses Joomla user.name; must match x_studio_agente_de_ventas exactly'
            );

        $this->sections[] = [
            'id'     => 'name_match',
            'title'  => '8. Joomla user.name ↔ Odoo agent cross-check',
            'checks' => $checks,
            'details' => [
                'odoo_agents_sample' => $odooAgentSample,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadActiveJoomlaUsers(int $limit): array
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('username'),
                $db->quoteName('email'),
            ])
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('block') . ' = 0')
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query, 0, $limit);

        $rows = $db->loadAssocList() ?: [];

        return array_map(static function (array $row): array {
            return [
                'id'       => (int) ($row['id'] ?? 0),
                'name'     => trim((string) ($row['name'] ?? '')),
                'username' => trim((string) ($row['username'] ?? '')),
                'email'    => trim((string) ($row['email'] ?? '')),
            ];
        }, $rows);
    }

    private function loadJoomlaUserName(int $userId): string
    {
        if ($userId < 1) {
            return '';
        }
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' = ' . $userId);
        $db->setQuery($query);

        return trim((string) $db->loadResult());
    }

    /**
     * @return array{status: string, label: string, detail: string, hint: string}
     */
    private function check(string $label, bool $ok, string $hint): array
    {
        return $ok ? $this->pass($label, 'OK') : $this->fail($label, $hint);
    }

    /**
     * @return array{status: string, label: string, detail: string, hint: string}
     */
    private function pass(string $label, string $detail): array
    {
        return ['status' => 'pass', 'label' => $label, 'detail' => $detail, 'hint' => ''];
    }

    /**
     * @return array{status: string, label: string, detail: string, hint: string}
     */
    private function fail(string $label, string $detail): array
    {
        $this->failures++;

        return ['status' => 'fail', 'label' => $label, 'detail' => $detail, 'hint' => ''];
    }

    /**
     * @return array{status: string, label: string, detail: string, hint: string}
     */
    private function warn(string $label, string $detail): array
    {
        $this->warnings++;

        return ['status' => 'warn', 'label' => $label, 'detail' => $detail, 'hint' => ''];
    }

    public static function normalizeOdooBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#/xmlrpc/#', $url)) {
            $url = preg_replace('#/xmlrpc/.*$#', '', $url) ?? $url;
        }

        return rtrim($url, '/');
    }

    public static function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '(empty)';
        }
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($secret, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($secret, -4);
    }

    /**
     * POST XML-RPC to an Odoo endpoint (shared by diagnostic and OdooHelper).
     *
     * @return array{http_code: int, curl_error: string, fault: ?string, parsed: ?array}
     */
    public static function postXmlRpc(string $endpoint, string $xml): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml'],
        ]);
        $body     = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        $parsed = null;
        $fault  = null;

        if (is_string($body) && $body !== '') {
            $sx = @simplexml_load_string($body);
            if ($sx !== false) {
                $parsed = json_decode(json_encode($sx), true);
                $fault  = self::extractFaultString($parsed);
            }
        }

        return [
            'http_code'  => $httpCode,
            'curl_error' => $err,
            'fault'      => $fault,
            'parsed'     => $parsed,
        ];
    }

    /**
     * Read installed component VERSION file (for deploy / opcache checks).
     */
    public static function readComponentVersion(): string
    {
        $paths = [
            JPATH_ROOT . '/components/com_ordenproduccion/VERSION',
            dirname(__DIR__, 2) . '/VERSION',
        ];
        foreach ($paths as $path) {
            if (is_readable($path)) {
                $v = trim((string) file_get_contents($path));

                return $v !== '' ? $v : 'unknown';
            }
        }

        return 'unknown';
    }

    /**
     * @return array{http_code: int, curl_error: string, fault: ?string, parsed: ?array}
     */
    private function xmlRpcCall(string $endpoint, string $xml): array
    {
        return self::postXmlRpc($endpoint, $xml);
    }

    public static function extractFaultString(?array $parsed): ?string
    {
        if (!is_array($parsed)) {
            return null;
        }
        $fault = $parsed['fault'] ?? null;
        if (!is_array($fault)) {
            return null;
        }
        $value = $fault['value'] ?? null;
        if (!is_array($value)) {
            return null;
        }
        $struct = $value['struct'] ?? null;
        if (!is_array($struct)) {
            return null;
        }
        $members = $struct['member'] ?? [];
        if (isset($members['name'])) {
            $members = [$members];
        }
        foreach ($members as $member) {
            if (($member['name'] ?? '') === 'faultString') {
                $v = $member['value']['string'] ?? '';

                return is_string($v) ? trim($v) : null;
            }
        }

        return 'XML-RPC fault (no message)';
    }

    public static function buildVersionXml(): string
    {
        return '<?xml version="1.0"?><methodCall><methodName>version</methodName><params/></methodCall>';
    }

    public static function buildAuthenticateXml(string $db, string $login, string $apiKey): string
    {
        $db    = htmlspecialchars($db, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $login = htmlspecialchars($login, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $key   = htmlspecialchars($apiKey, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0"?>
<methodCall>
  <methodName>authenticate</methodName>
  <params>
    <param><value><string>' . $db . '</string></value></param>
    <param><value><string>' . $login . '</string></value></param>
    <param><value><string>' . $key . '</string></value></param>
    <param><value><struct/></value></param>
  </params>
</methodCall>';
    }

    public static function buildExecuteKwXml(
        string $db,
        int $uid,
        string $apiKey,
        string $model,
        string $method,
        string $argsJson,
        string $kwJson = ''
    ): string {
        $db     = htmlspecialchars($db, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $key    = htmlspecialchars($apiKey, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $model  = htmlspecialchars($model, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $method = htmlspecialchars($method, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $kwParam = '';
        if ($kwJson !== '') {
            $kwParam = '<param><value>' . self::jsonXmlRpcValue(json_decode($kwJson, true)) . '</value></param>';
        }

        return '<?xml version="1.0"?>
<methodCall>
  <methodName>execute_kw</methodName>
  <params>
    <param><value><string>' . $db . '</string></value></param>
    <param><value><int>' . $uid . '</int></value></param>
    <param><value><string>' . $key . '</string></value></param>
    <param><value><string>' . $model . '</string></value></param>
    <param><value><string>' . $method . '</string></value></param>
    <param><value>' . self::jsonXmlRpcValue(json_decode($argsJson, true)) . '</value></param>
    ' . $kwParam . '
  </params>
</methodCall>';
    }

    /**
     * @param mixed $value
     */
    public static function jsonXmlRpcValue($value): string
    {
        if ($value === null) {
            return '<value><nil/></value>';
        }
        if (is_bool($value)) {
            return '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>';
        }
        if (is_int($value)) {
            return '<value><int>' . $value . '</int></value>';
        }
        if (is_float($value)) {
            return '<value><double>' . $value . '</double></value>';
        }
        if (is_string($value)) {
            return '<value><string>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</string></value>';
        }
        if (is_array($value)) {
            if ($value === []) {
                return '<value><array><data/></array></value>';
            }
            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                $inner = '';
                foreach ($value as $item) {
                    $inner .= self::jsonXmlRpcValue($item);
                }

                return '<value><array><data>' . $inner . '</data></array></value>';
            }
            $members = '';
            foreach ($value as $k => $v) {
                $members .= '<member><name>' . htmlspecialchars((string) $k, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</name>'
                    . self::jsonXmlRpcValue($v) . '</member>';
            }

            return '<value><struct>' . $members . '</struct></value>';
        }

        return '<value><string></string></value>';
    }

    /**
     * @param mixed $parsed
     */
    public static function extractIntParam($parsed): int|false
    {
        if (!is_array($parsed)) {
            return false;
        }
        $param = $parsed['params']['param'] ?? null;
        if (!is_array($param)) {
            return false;
        }
        $value = $param['value'] ?? null;
        if (!is_array($value)) {
            return false;
        }
        if (isset($value['int'])) {
            return (int) $value['int'];
        }
        if (isset($value['i4'])) {
            return (int) $value['i4'];
        }
        if (isset($value['boolean'])) {
            return (int) $value['boolean'];
        }

        return false;
    }

    /**
     * @param mixed $parsed
     */
    public static function extractVersionString($parsed): string
    {
        if (!is_array($parsed)) {
            return '';
        }
        $struct = $parsed['params']['param']['value']['struct']['member'] ?? [];
        if (isset($struct['name'])) {
            $struct = [$struct];
        }
        foreach ($struct as $member) {
            if (($member['name'] ?? '') === 'server_version') {
                return (string) ($member['value']['string'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param mixed $parsed
     * @return array<int, string>
     */
    public static function extractFieldsGetNames($parsed): array
    {
        $names = [];
        if (!is_array($parsed)) {
            return $names;
        }
        $struct = $parsed['params']['param']['value']['struct']['member'] ?? [];
        if (isset($struct['name'])) {
            $struct = [$struct];
        }
        foreach ($struct as $member) {
            if (isset($member['name'])) {
                $names[] = (string) $member['name'];
            }
        }

        return $names;
    }

    /**
     * @param mixed $parsed
     * @return array<int, array<string, string>>
     */
    public static function extractSearchReadRecords($parsed): array
    {
        $out = [];
        if (!is_array($parsed)) {
            return $out;
        }

        foreach (self::getSearchReadValueNodes($parsed) as $row) {
            if (!isset($row['struct']['member'])) {
                continue;
            }
            $members = $row['struct']['member'];
            if (isset($members['name'])) {
                $members = [$members];
            }
            $record = [];
            foreach ($members as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $scalar = self::extractRpcScalarValue($m['value'] ?? null);
                if ($scalar !== null) {
                    $record[$name] = $scalar;
                }
            }
            if (!empty($record['id'])) {
                $out[] = $record;
            }
        }

        return $out;
    }

    /**
     * @param mixed $parsed
     * @return array<int, array<string, mixed>>
     */
    private static function getSearchReadValueNodes($parsed): array
    {
        if (!is_array($parsed)) {
            return [];
        }

        $param = $parsed['params']['param'] ?? null;
        if (!is_array($param)) {
            return [];
        }
        if (isset($param[0]) && is_array($param[0]) && !isset($param['value'])) {
            $param = $param[0];
        }

        $values = $param['value']['array']['data']['value'] ?? [];
        if ($values === [] || $values === null) {
            return [];
        }
        if (isset($values['struct'])) {
            return [$values];
        }

        return is_array($values) ? $values : [];
    }

    /**
     * @param mixed $value
     */
    private static function extractRpcScalarValue($value): ?string
    {
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['string'])) {
            return (string) $value['string'];
        }
        if (isset($value['int'])) {
            return (string) $value['int'];
        }
        if (isset($value['i4'])) {
            return (string) $value['i4'];
        }
        if (isset($value['boolean'])) {
            $bool = $value['boolean'];

            return ($bool === '1' || $bool === 1 || $bool === true) ? '1' : '0';
        }
        if (isset($value['double'])) {
            return (string) $value['double'];
        }

        return null;
    }

    /**
     * @param mixed $parsed
     * @return array<int, string>
     */
    public static function extractSearchReadNames($parsed): array
    {
        $out = [];
        if (!is_array($parsed)) {
            return $out;
        }

        foreach (self::getSearchReadValueNodes($parsed) as $row) {
            if (!isset($row['struct']['member'])) {
                continue;
            }
            $members = $row['struct']['member'];
            if (isset($members['name'])) {
                $members = [$members];
            }
            $id = 0;
            $name = '';
            foreach ($members as $m) {
                $n = $m['name'] ?? '';
                if ($n === 'id') {
                    $id = (int) ($m['value']['int'] ?? 0);
                }
                if ($n === 'name') {
                    $name = (string) ($m['value']['string'] ?? '');
                }
            }
            if ($id > 0) {
                $out[$id] = $name;
            }
        }

        return $out;
    }

    /**
     * @param mixed $parsed
     * @return array<int, string>
     */
    public static function extractDistinctAgentValues($parsed): array
    {
        $agents = [];
        if (!is_array($parsed)) {
            return $agents;
        }
        $values = $parsed['params']['param']['value']['array']['data']['value'] ?? [];
        if (isset($values['struct'])) {
            $values = [$values];
        }
        foreach ($values as $row) {
            if (!isset($row['struct']['member'])) {
                continue;
            }
            $members = $row['struct']['member'];
            if (isset($members['name'])) {
                $members = [$members];
            }
            foreach ($members as $m) {
                if (($m['name'] ?? '') === 'x_studio_agente_de_ventas') {
                    $v = trim((string) ($m['value']['string'] ?? ''));
                    if ($v !== '' && !in_array($v, $agents, true)) {
                        $agents[] = $v;
                    }
                }
            }
        }

        return $agents;
    }

    /**
     * Render diagnostic report as plain text (CLI).
     *
     * @param array<string, mixed> $report
     */
    public static function renderCli(array $report): string
    {
        $out = [];
        $meta = $report['meta'] ?? [];
        $out[] = str_repeat('=', 72);
        $out[] = 'Odoo connection diagnostic — com_ordenproduccion';
        $out[] = str_repeat('=', 72);
        $out[] = 'Time:        ' . ($meta['time'] ?? '');
        $out[] = 'Joomla root: ' . ($meta['joomla_root'] ?? '');
        $out[] = '';

        foreach ($report['sections'] ?? [] as $section) {
            $out[] = '--- ' . ($section['title'] ?? '') . ' ---';
            if (!empty($section['details']) && is_array($section['details'])) {
                foreach ($section['details'] as $k => $v) {
                    if ($k === 'sample_agents' || $k === 'odoo_agents_sample') {
                        continue;
                    }
                    $out[] = '  ' . $k . ': ' . (is_scalar($v) ? $v : json_encode($v));
                }
            }
            foreach ($section['checks'] ?? [] as $check) {
                $tag = strtoupper((string) ($check['status'] ?? 'info'));
                $out[] = '  [' . $tag . '] ' . ($check['label'] ?? '') . ': ' . ($check['detail'] ?? '');
            }
            $out[] = '';
        }

        $agents = $report['odoo_agent_sample'] ?? [];
        if ($agents !== []) {
            $out[] = '--- Odoo agent values (sample) ---';
            foreach ($agents as $a) {
                $out[] = '  - ' . $a;
            }
            $out[] = '';
        }

        $tests = $report['agent_tests'] ?? [];
        if ($tests !== []) {
            $out[] = '--- Mis Clientes per Joomla user ---';
            foreach ($tests as $t) {
                $status = strtoupper((string) ($t['status'] ?? ''));
                $out[] = sprintf(
                    '  [%s] user #%d "%s" — RPC:%d Helper:%d — %s',
                    $status,
                    (int) ($t['joomla_user_id'] ?? 0),
                    (string) ($t['joomla_user_name'] ?? ''),
                    (int) ($t['rpc_count'] ?? 0),
                    (int) ($t['helper_count'] ?? 0),
                    (string) ($t['message'] ?? '')
                );
            }
            $out[] = '';
        }

        $failures = (int) ($meta['failures'] ?? 0);
        $warnings = (int) ($meta['warnings'] ?? 0);
        $out[] = 'Failures: ' . $failures;
        $out[] = 'Warnings: ' . $warnings;
        $out[] = '';
        if ($failures > 0) {
            $out[] = 'Result: FAIL';
        } elseif ($warnings > 0) {
            $out[] = 'Result: OK with warnings';
        } else {
            $out[] = 'Result: OK';
        }

        return implode("\n", $out) . "\n";
    }
}
