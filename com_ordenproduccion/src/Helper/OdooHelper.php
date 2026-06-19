<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion (clientes)
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Helper class for Odoo API operations
 */
class OdooHelper
{
    /**
     * Odoo configuration
     */
    private $config;

    /** @var array<int, string>|null  Cached res.partner field names from fields_get */
    private static $partnerFieldNamesCache = null;

    /** Mis Clientes list fields when present on res.partner (Odoo 19+ may omit mobile). */
    private const PARTNER_LIST_FIELDS_PREFERRED = [
        'id', 'name', 'email', 'phone', 'mobile', 'street', 'city', 'vat', 'type',
    ];

    /** Child contacts under a parent res.partner. */
    private const PARTNER_CHILD_FIELDS_PREFERRED = [
        'id', 'type', 'name', 'complete_name', 'vat', 'street', 'city', 'email', 'phone', 'mobile',
        'display_name', 'x_studio_agente_de_ventas',
    ];

    /** Single partner detail (cliente edit). */
    private const PARTNER_DETAIL_FIELDS_PREFERRED = [
        'id', 'type', 'name', 'complete_name', 'vat', 'street', 'city', 'email', 'phone', 'mobile',
        'x_studio_agente_de_ventas', 'display_name', 'parent_id', 'credit_limit',
        'property_payment_term_id', 'property_supplier_payment_term_id', 'invoice_sending_method',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = ComponentHelper::getParams('com_ordenproduccion');
    }

    /**
     * XML-RPC execute_kw auth params (db, uid, api key).
     */
    private function buildAuthParamsCompact(): string
    {
        $db = htmlspecialchars((string) $this->config->get('odoo_db', ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $uid = (int) $this->config->get('odoo_user_id', 2);
        $key = htmlspecialchars((string) $this->config->get('odoo_api_key', ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<param><value><string>' . $db . '</string></value></param>'
            . '<param><value><int>' . $uid . '</int></value></param>'
            . '<param><value><string>' . $key . '</string></value></param>';
    }

    /**
     * Object endpoint URL for XML-RPC (normalizes base URL).
     */
    private function getObjectEndpointUrl(): string
    {
        $url = trim((string) $this->config->get('odoo_url', ''));
        if ($url === '') {
            return '';
        }
        if (strpos($url, '/xmlrpc/') !== false) {
            return $url;
        }

        return rtrim($url, '/') . '/xmlrpc/2/object';
    }

    /**
     * Execute Odoo XML-RPC call
     *
     * @param   string  $xmlPayload  The XML payload
     *
     * @return  mixed  The response data or false on failure
     */
    private function executeOdooCall($xmlPayload)
    {
        if ($this->config->get('enable_debug', 0)) {
            Log::add('Odoo API Request: ' . substr($xmlPayload, 0, 1000) . '...', Log::DEBUG, 'com_ordenproduccion.clientes');
        }

        $endpoint = $this->getObjectEndpointUrl();
        $apiKey = (string) $this->config->get('odoo_api_key', '');

        if ($endpoint === '' || $apiKey === '') {
            Log::add('Odoo API: missing odoo_url or odoo_api_key in com_ordenproduccion options', Log::ERROR, 'com_ordenproduccion.clientes');

            return false;
        }

        $rpc = OdooDiagnosticHelper::postXmlRpc($endpoint, $xmlPayload);

        if ($this->config->get('enable_debug', 0)) {
            Log::add('Odoo API Call - HTTP Code: ' . $rpc['http_code'], Log::DEBUG, 'com_ordenproduccion.clientes');
            if ($rpc['curl_error'] !== '') {
                Log::add('Odoo API Error: ' . $rpc['curl_error'], Log::ERROR, 'com_ordenproduccion.clientes');
            }
            if ($rpc['fault'] !== null) {
                Log::add('Odoo API Fault: ' . $rpc['fault'], Log::ERROR, 'com_ordenproduccion.clientes');
            }
        }

        if ($rpc['parsed'] === null) {
            Log::add(
                'Odoo API Failed - HTTP: ' . $rpc['http_code'] . ', Error: ' . $rpc['curl_error'],
                Log::ERROR,
                'com_ordenproduccion.clientes'
            );

            return false;
        }

        return $rpc['parsed'];
    }

    /**
     * res.partner fields to use for Mis Clientes search_read (only fields that exist in this Odoo DB).
     *
     * @return array<int, string>
     */
    public function getResolvedPartnerListFields(): array
    {
        return $this->filterPartnerFields(self::PARTNER_LIST_FIELDS_PREFERRED);
    }

    /**
     * res.partner fields for child contact search_read.
     *
     * @return array<int, string>
     */
    public function getResolvedPartnerChildFields(): array
    {
        return $this->filterPartnerFields(self::PARTNER_CHILD_FIELDS_PREFERRED);
    }

    /**
     * res.partner fields for single-partner detail (cliente edit).
     *
     * @return array<int, string>
     */
    public function getResolvedPartnerDetailFields(): array
    {
        return $this->filterPartnerFields(self::PARTNER_DETAIL_FIELDS_PREFERRED);
    }

    /**
     * Whether a res.partner field exists (from fields_get cache).
     */
    public function isPartnerFieldAvailable(string $fieldName): bool
    {
        return in_array($fieldName, $this->loadPartnerFieldNames(), true);
    }

    /**
     * @param   array<int, string>  $preferred
     * @return  array<int, string>
     */
    private function filterPartnerFields(array $preferred): array
    {
        $available = $this->loadPartnerFieldNames();
        if ($available === []) {
            return ['id', 'name'];
        }

        $set = array_fill_keys($available, true);
        $out = [];
        foreach ($preferred as $field) {
            if (isset($set[$field])) {
                $out[] = $field;
            }
        }

        return $out !== [] ? $out : ['id', 'name'];
    }

    /**
     * @return array<int, string>
     */
    private function loadPartnerFieldNames(): array
    {
        if (self::$partnerFieldNamesCache !== null) {
            return self::$partnerFieldNamesCache;
        }

        $xml = $this->buildExecuteKwXml(
            'res.partner',
            'fields_get',
            [[]],
            ['attributes' => ['string', 'type']]
        );
        $result = $this->executeOdooCall($xml);

        if (!$result || $this->hasXmlRpcFault($result)) {
            // Safe fallback for Odoo 19 (no mobile on res.partner).
            self::$partnerFieldNamesCache = ['id', 'name', 'email', 'phone', 'street', 'city', 'vat', 'type'];

            return self::$partnerFieldNamesCache;
        }

        $names = OdooDiagnosticHelper::extractFieldsGetNames($result);
        self::$partnerFieldNamesCache = $names !== [] ? $names : ['id', 'name'];

        return self::$partnerFieldNamesCache;
    }

    /**
     * Get contacts by sales agent - using exact same structure as your working PHP script
     *
     * @param   string   $agentName  The sales agent name
     * @param   integer  $page       The page number
     * @param   integer  $limit      The number of contacts per page
     * @param   string   $search     The search term
     *
     * @return  array  Array of contacts
     */
    public function getContactsByAgent($agentName, $page = 1, $limit = 20, $search = '')
    {
        $offset = max(0, ((int) $page - 1) * (int) $limit);
        $kwargs = [
            'fields' => $this->getResolvedPartnerListFields(),
            'limit'  => max(1, (int) $limit),
        ];
        if ($offset > 0) {
            $kwargs['offset'] = $offset;
        }

        $xmlPayload = $this->buildExecuteKwXml(
            'res.partner',
            'search_read',
            [$this->buildAgentParentDomain($agentName, $search)],
            $kwargs
        );

        $result = $this->executeOdooCall($xmlPayload);

        if (!$result) {
            return [];
        }

        if ($this->hasXmlRpcFault($result)) {
            $fault = OdooDiagnosticHelper::extractFaultString($result);
            Log::add(
                'getContactsByAgent XML-RPC fault for agent "' . $agentName . '": ' . ($fault ?? 'unknown'),
                Log::ERROR,
                'com_ordenproduccion.clientes'
            );

            return [];
        }

        $rows = OdooDiagnosticHelper::extractSearchReadRecords($result);
        if ($rows === []) {
            $names = OdooDiagnosticHelper::extractSearchReadNames($result);
            foreach ($names as $id => $name) {
                $rows[] = ['id' => (string) $id, 'name' => $name];
            }
        }

        return array_map(static function (array $row): array {
            return [
                'id'     => (string) ($row['id'] ?? '0'),
                'name'   => (string) ($row['name'] ?? ''),
                'email'  => (string) ($row['email'] ?? ''),
                'phone'  => (string) ($row['phone'] ?? ''),
                'mobile' => (string) ($row['mobile'] ?? ''),
                'street' => (string) ($row['street'] ?? ''),
                'city'   => (string) ($row['city'] ?? ''),
                'vat'    => (string) ($row['vat'] ?? ''),
                'type'   => (string) ($row['type'] ?? 'contact'),
            ];
        }, $rows);
    }

    /**
     * Build domain XML for Odoo API calls
     *
     * @param   array  $domain  The search domain
     *
     * @return  string  XML string representation of the domain
     */
    private function buildDomainXml($domain)
    {
        if (empty($domain)) {
            return '';
        }
        
        $xml = '';
        foreach ($domain as $condition) {
            if (is_array($condition)) {
                if (count($condition) === 3) {
                    $xml .= '<value><array><data>';
                    $xml .= '<value><string>' . htmlspecialchars((string) $condition[0], ENT_XML1, 'UTF-8') . '</string></value>';
                    $xml .= '<value><string>' . htmlspecialchars((string) $condition[1], ENT_XML1, 'UTF-8') . '</string></value>';
                    if ($condition[2] === false) {
                        $xml .= '<value><boolean>0</boolean></value>';
                    } elseif ($condition[2] === true) {
                        $xml .= '<value><boolean>1</boolean></value>';
                    } else {
                        $xml .= '<value><string>' . htmlspecialchars((string) $condition[2], ENT_XML1, 'UTF-8') . '</string></value>';
                    }
                    $xml .= '</data></array></value>';
                } elseif (count($condition) === 1) {
                    // OR operator: ['|']
                    $xml .= '<value><string>' . htmlspecialchars($condition[0]) . '</string></value>';
                }
            }
        }
        
        return $xml;
    }

    /**
     * Get total count of contacts for an agent
     *
     * @param   string  $agentName  The sales agent name
     * @param   string  $search     The search term
     *
     * @return  integer  Total number of contacts
     */
    public function getContactsCountByAgent($agentName, $search = '')
    {
        $xmlPayload = $this->buildExecuteKwXml(
            'res.partner',
            'search_count',
            [$this->buildAgentParentDomain($agentName, $search)]
        );
        $result = $this->executeOdooCall($xmlPayload);

        if (!$result || $this->hasXmlRpcFault($result)) {
            return 0;
        }

        $count = OdooDiagnosticHelper::extractIntParam($result);

        return $count === false ? 0 : $count;
    }

    /**
     * Odoo domain: parent companies for a sales agent (Mis Clientes).
     *
     * @return array<int, array<int, mixed>>
     */
    private function buildAgentParentDomain(string $agentName, string $search = ''): array
    {
        $domain = [
            ['x_studio_agente_de_ventas', '=', $agentName],
            ['parent_id', '=', false],
        ];

        if ($search !== '') {
            $domain[] = ['name', 'ilike', $search];
        }

        return $domain;
    }

    /**
     * Build execute_kw XML using the same encoder as the diagnostic tool (Odoo 19 compatible).
     *
     * @param   array<int, mixed>        $args
     * @param   array<string, mixed>     $kwargs
     */
    private function buildExecuteKwXml(string $model, string $method, array $args, array $kwargs = []): string
    {
        return OdooDiagnosticHelper::buildExecuteKwXml(
            (string) $this->config->get('odoo_db', ''),
            (int) $this->config->get('odoo_user_id', 2),
            (string) $this->config->get('odoo_api_key', ''),
            $model,
            $method,
            json_encode($args, JSON_UNESCAPED_UNICODE) ?: '[]',
            $kwargs !== [] ? (json_encode($kwargs, JSON_UNESCAPED_UNICODE) ?: '') : ''
        );
    }

    /**
     * @param   array<int, array<int, mixed>>  $domain
     * @param   array<int, string>             $fields
     * @deprecated Use buildExecuteKwXml()
     */
    private function buildPartnerSearchReadXml(array $domain, array $fields, int $limit, int $offset): string
    {
        $fieldsXml = '';
        foreach ($fields as $field) {
            $fieldsXml .= '<value><string>' . htmlspecialchars($field, ENT_XML1, 'UTF-8') . '</string></value>';
        }

        return '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param><value><string>res.partner</string></value></param>
      <param><value><string>search_read</string></value></param>
      ' . $this->wrapDomainAsExecuteKwArg($this->buildDomainXml($domain)) . '
      <param>
         <value>
            <struct>
               <member>
                  <name>fields</name>
                  <value><array><data>' . $fieldsXml . '</data></array></value>
               </member>
               <member>
                  <name>limit</name>
                  <value><int>' . max(1, $limit) . '</int></value>
               </member>
               <member>
                  <name>offset</name>
                  <value><int>' . max(0, $offset) . '</int></value>
               </member>
            </struct>
         </value>
      </param>
   </params>
</methodCall>';
    }

    /**
     * @param   array<int, array<int, mixed>>  $domain
     */
    private function buildPartnerSearchCountXml(array $domain): string
    {
        return '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param><value><string>res.partner</string></value></param>
      <param><value><string>search_count</string></value></param>
      ' . $this->wrapDomainAsExecuteKwArg($this->buildDomainXml($domain)) . '
   </params>
</methodCall>';
    }

    /**
     * @param   mixed  $result
     */
    private function hasXmlRpcFault($result): bool
    {
        return is_array($result) && OdooDiagnosticHelper::extractFaultString($result) !== null;
    }

    /**
     * Wrap Odoo domain conditions as the first execute_kw argument: [domain].
     */
    private function wrapDomainAsExecuteKwArg(string $domainXml): string
    {
        return '<param><value><array><data><value><array><data>'
            . $domainXml
            . '</data></array></value></data></array></value></param>';
    }

    /**
     * Parse search_read rows (already filtered server-side).
     *
     * @param   array  $result
     * @return  array<int, array<string, mixed>>
     */
    private function parseContactsFromSearchRead(array $result): array
    {
        if (!isset($result['params']['param']['value']['array']['data']['value'])) {
            return [];
        }

        $contacts = [];
        $values = $result['params']['param']['value']['array']['data']['value'];

        if (isset($values['struct'])) {
            $values = [$values];
        }

        foreach ($values as $value) {
            if (!isset($value['struct']['member'])) {
                continue;
            }

            $row = $this->parseStructMembers($value['struct']['member']);
            $contacts[] = [
                'id'     => (string) ($row['id'] ?? '0'),
                'name'   => (string) ($row['name'] ?? ''),
                'email'  => (string) ($row['email'] ?? ''),
                'phone'  => (string) ($row['phone'] ?? ''),
                'mobile' => (string) ($row['mobile'] ?? ''),
                'street' => (string) ($row['street'] ?? ''),
                'city'   => (string) ($row['city'] ?? ''),
                'vat'    => (string) ($row['vat'] ?? ''),
                'type'   => (string) ($row['type'] ?? 'contact'),
            ];
        }

        return $contacts;
    }

    /**
     * @param   array<string, mixed>|array<int, array<string, mixed>>  $members
     * @return  array<string, string>
     */
    private function parseStructMembers($members): array
    {
        if (isset($members['name'])) {
            $members = [$members];
        }

        $row = [];
        foreach ($members as $member) {
            $name = (string) ($member['name'] ?? '');
            $value = $member['value'] ?? [];
            if ($name === '') {
                continue;
            }
            if (isset($value['string'])) {
                $row[$name] = (string) $value['string'];
            } elseif (isset($value['int'])) {
                $row[$name] = (string) $value['int'];
            } elseif (isset($value['boolean'])) {
                $row[$name] = $value['boolean'] ? '1' : '0';
            } elseif (isset($value['double'])) {
                $row[$name] = (string) $value['double'];
            }
        }

        return $row;
    }

    /**
     * Parse contacts from all results and filter by agent - like your working PHP script
     *
     * @param   array   $result      The API response
     * @param   string  $agentName   The agent name to filter by
     *
     * @return  array  Array of contacts
     */
    private function parseContactsFromAllResults($result, $agentName)
    {
        if (!isset($result['params']['param']['value']['array']['data']['value'])) {
            return [];
        }

        $contacts = [];
        $seenIds = []; // Track seen contact IDs to prevent duplicates
        $values = $result['params']['param']['value']['array']['data']['value'];

        // Handle single contact response
        if (isset($values['struct'])) {
            $values = [$values];
        }

        foreach ($values as $value) {
            if (!isset($value['struct']['member'])) {
                continue;
            }

            $contact = [];
            $hasParentId = false;
            $contactId = '0';
            
            foreach ($value['struct']['member'] as $member) {
                $fieldName = $member['name'];
                $fieldValue = '';
                
                if (isset($member['value']['string'])) {
                    $fieldValue = $member['value']['string'];
                } elseif (isset($member['value']['int'])) {
                    $fieldValue = (string)$member['value']['int'];
                } elseif (isset($member['value']['array']['data']['value'])) {
                    // Handle array fields (parent_id, child_ids, etc.)
                    if ($fieldName === 'parent_id') {
                        // Check if parent_id has a valid value (not empty/false)
                        $parentIdArray = $member['value']['array']['data']['value'];
                        if (isset($parentIdArray[0]['int']) && (int)$parentIdArray[0]['int'] > 0) {
                            $hasParentId = true;
                            $fieldValue = (string)$parentIdArray[0]['int'];
                        } else {
                            // Empty parent_id means this is a parent contact
                            $hasParentId = false;
                            $fieldValue = '';
                        }
                    } elseif ($fieldName === 'child_ids') {
                        // Convert child_ids array to comma-separated string
                        $childIds = [];
                        if (isset($member['value']['array']['data']['value'])) {
                            $childValues = $member['value']['array']['data']['value'];
                            // Handle single value or multiple values
                            if (isset($childValues['int'])) {
                                $childIds[] = (string)$childValues['int'];
                            } else {
                                foreach ($childValues as $childValue) {
                                    if (isset($childValue['int'])) {
                                        $childIds[] = (string)$childValue['int'];
                                    }
                                }
                            }
                        }
                        $fieldValue = implode(',', $childIds);
                    } else {
                        // For any other array field, convert to string representation
                        $fieldValue = '';
                    }
                } elseif (isset($member['value']['boolean'])) {
                    // Boolean fields - parent_id should not be boolean, but handle it if Odoo returns it
                    if ($fieldName === 'parent_id') {
                        // If parent_id is boolean true, it means there IS a parent
                        $hasParentId = ($member['value']['boolean'] === true);
                    }
                    $fieldValue = $member['value']['boolean'] ? '1' : '0';
                } elseif (isset($member['value']['double'])) {
                    $fieldValue = (string)$member['value']['double'];
                }
                
                $contact[$fieldName] = $fieldValue;
                
                // Store contact ID for deduplication
                if ($fieldName === 'id') {
                    $contactId = $fieldValue;
                }
            }
            
            // Filter out child contacts (those with parent_id set)
            // Only show parent contacts (no parent_id) that belong to this agent
            if (isset($contact['x_studio_agente_de_ventas']) && 
                $contact['x_studio_agente_de_ventas'] === $agentName && 
                !$hasParentId) {
                
                // Skip if we've already seen this contact ID (prevent duplicates)
                if (isset($seenIds[$contactId]) && $contactId !== '0') {
                    continue;
                }
                $seenIds[$contactId] = true;
                
                // Map fields to match expected structure
                $contactType = isset($contact['type']) ? $contact['type'] : 'contact';
                
                $normalizedContact = [
                    'id' => $contactId,
                    'name' => isset($contact['name']) ? $contact['name'] : '',
                    'email' => isset($contact['email']) ? $contact['email'] : '',
                    'phone' => isset($contact['phone']) ? $contact['phone'] : '',
                    'mobile' => isset($contact['mobile']) ? $contact['mobile'] : '',
                    'street' => isset($contact['street']) ? $contact['street'] : '',
                    'city' => isset($contact['city']) ? $contact['city'] : '',
                    'vat' => isset($contact['vat']) ? $contact['vat'] : '',
                    'type' => $contactType
                ];
                
                $contacts[] = $normalizedContact;
            }
        }

        return $contacts;
    }

    /**
     * Get single contact by ID - using exact same structure as contact_edit.php
     *
     * @param   integer  $contactId  The contact ID
     *
     * @return  array|null  Contact data or null if not found
     */
    public function getContact($contactId)
    {
        $contactId = (int) $contactId;
        if ($contactId <= 0) {
            return null;
        }

        $xmlPayload = $this->buildExecuteKwXml(
            'res.partner',
            'search_read',
            [[['id', '=', $contactId]]],
            ['fields' => $this->getResolvedPartnerDetailFields()]
        );

        $result = $this->executeOdooCall($xmlPayload);

        if (!$result || $this->hasXmlRpcFault($result)) {
            if ($result && $this->hasXmlRpcFault($result)) {
                $fault = OdooDiagnosticHelper::extractFaultString($result);
                Log::add(
                    'getContact XML-RPC fault for id ' . $contactId . ': ' . ($fault ?? 'unknown'),
                    Log::ERROR,
                    'com_ordenproduccion.clientes'
                );
            }

            return null;
        }

        $contacts = $this->parseContactsResponse($result);
        $one = !empty($contacts) ? $contacts[0] : null;
        if ($one !== null) {
            self::finalizePartnerFinanceDisplayFields($one);
        }

        return $one;
    }

    /**
     * Get child contacts by parent ID
     *
     * @param   integer  $parentId  The parent contact ID
     *
     * @return  array  Array of child contacts
     */
    public function getChildContacts($parentId)
    {
        $parentId = (int) $parentId;
        if ($parentId <= 0) {
            return [];
        }

        $xmlPayload = $this->buildExecuteKwXml(
            'res.partner',
            'search_read',
            [[['parent_id', '=', $parentId]]],
            ['fields' => $this->getResolvedPartnerChildFields()]
        );

        $result = $this->executeOdooCall($xmlPayload);

        if (!$result || $this->hasXmlRpcFault($result)) {
            if ($result && $this->hasXmlRpcFault($result)) {
                $fault = OdooDiagnosticHelper::extractFaultString($result);
                Log::add(
                    'getChildContacts XML-RPC fault for parent ' . $parentId . ': ' . ($fault ?? 'unknown'),
                    Log::ERROR,
                    'com_ordenproduccion.clientes'
                );
            }

            return [];
        }

        $rows = OdooDiagnosticHelper::extractSearchReadRecords($result);
        if ($rows === []) {
            return $this->parseContactsFromSearchRead($result);
        }

        return array_map(static function (array $row): array {
            $phone = (string) ($row['phone'] ?? '');
            if ($phone === '' && isset($row['mobile'])) {
                $phone = (string) $row['mobile'];
            }

            return [
                'id'     => (string) ($row['id'] ?? '0'),
                'name'   => (string) ($row['name'] ?? ($row['complete_name'] ?? '')),
                'email'  => (string) ($row['email'] ?? ''),
                'phone'  => $phone,
                'mobile' => (string) ($row['mobile'] ?? ''),
                'street' => (string) ($row['street'] ?? ''),
                'city'   => (string) ($row['city'] ?? ''),
                'vat'    => (string) ($row['vat'] ?? ''),
                'type'   => (string) ($row['type'] ?? 'contact'),
            ];
        }, $rows);
    }

    /**
     * Fields used by Nuevo Cliente form mapped to res.partner.
     *
     * @return array<int, string>
     */
    public function getPartnerFormFieldNames(): array
    {
        return [
            'name', 'type', 'email', 'phone', 'mobile', 'street', 'city', 'vat',
            'x_studio_agente_de_ventas', 'parent_id',
        ];
    }

    /**
     * Form/Odoo fields missing on this database (e.g. mobile on Odoo 19).
     *
     * @return array<int, string>
     */
    public function getSkippedPartnerFormFields(): array
    {
        $skipped = [];
        foreach ($this->getPartnerFormFieldNames() as $field) {
            if (!$this->isPartnerFieldAvailable($field)) {
                $skipped[] = $field;
            }
        }

        return $skipped;
    }

    /**
     * Diagnostic: attempt res.partner create with the same field filter as Nuevo Cliente.
     *
     * @param   array<string, mixed>  $contactData
     * @return  array{values: array<string, mixed>, contact_id: int|false, fault: ?string, http_code: int, skipped_fields: array<int, string>}
     */
    public function probeCreateContact(array $contactData): array
    {
        $vals = $this->buildContactValuesForOdoo($contactData, true);
        $xml  = $this->buildExecuteKwXml('res.partner', 'create', [$vals]);
        $rpc  = OdooDiagnosticHelper::postXmlRpc($this->getObjectEndpointUrl(), $xml);
        $id   = false;
        if (is_array($rpc['parsed'])) {
            $id = $this->parseCreateResponse($rpc['parsed']);
        }

        return [
            'values'          => $vals,
            'contact_id'      => $id,
            'fault'           => $rpc['fault'],
            'http_code'       => $rpc['http_code'],
            'skipped_fields'  => $this->getSkippedPartnerFormFields(),
        ];
    }

    /**
     * Create new contact in Odoo (Nuevo Cliente).
     *
     * @param   array<string, mixed>  $contactData
     *
     * @return  int|false  New partner id on success
     */
    public function createContact($contactData)
    {
        $vals = $this->buildContactValuesForOdoo($contactData, true);
        if (trim((string) ($vals['name'] ?? '')) === '') {
            Log::add('createContact: name is required', Log::ERROR, 'com_ordenproduccion.clientes');

            return false;
        }

        $xmlPayload = $this->buildExecuteKwXml('res.partner', 'create', [$vals]);
        $result     = $this->executeOdooCall($xmlPayload);

        if (!$result || $this->hasXmlRpcFault($result)) {
            $fault = OdooDiagnosticHelper::extractFaultString(is_array($result) ? $result : null);
            Log::add(
                'createContact XML-RPC fault: ' . ($fault ?? 'unknown'),
                Log::ERROR,
                'com_ordenproduccion.clientes'
            );

            return false;
        }

        return $this->parseCreateResponse($result);
    }

    /**
     * Update existing contact
     *
     * @param   integer  $contactId    The contact ID
     * @param   array    $contactData  The contact data
     *
     * @return  boolean  True on success, false on failure
     */
    public function updateContact($contactId, $contactData)
    {
        $contactId = (int) $contactId;
        if ($contactId <= 0) {
            return false;
        }

        $vals = $this->buildContactValuesForOdoo($contactData, false);
        if ($vals === []) {
            return true;
        }

        $xmlPayload = $this->buildExecuteKwXml('res.partner', 'write', [[$contactId], $vals]);
        $result     = $this->executeOdooCall($xmlPayload);

        if (!$result || $this->hasXmlRpcFault($result)) {
            $fault = OdooDiagnosticHelper::extractFaultString(is_array($result) ? $result : null);
            Log::add(
                'updateContact XML-RPC fault: ' . ($fault ?? 'unknown'),
                Log::ERROR,
                'com_ordenproduccion.clientes'
            );

            return false;
        }

        return true;
    }

    /**
     * Delete contact
     *
     * @param   integer  $contactId  The contact ID
     *
     * @return  boolean  True on success, false on failure
     */
    public function deleteContact($contactId)
    {
        $xmlPayload = '<?xml version="1.0"?>
        <methodCall>
            <methodName>execute_kw</methodName>
            <params>
                ' . $this->buildAuthParamsCompact() . '
                <param><value><string>res.partner</string></value></param>
                <param><value><string>unlink</string></value></param>
                <param>
                    <value>
                        <array>
                            <data>
                                <value>
                                    <array>
                                        <data>
                                            <value><int>' . $contactId . '</int></value>
                                        </data>
                                    </array>
                                </value>
                            </data>
                        </array>
                    </value>
                </param>
            </params>
        </methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
        return $result !== false;
    }

    /**
     * Build Odoo values dict for res.partner create/write (skips invalid fields).
     *
     * @param   array<string, mixed>  $contactData
     * @return  array<string, mixed>
     */
    private function buildContactValuesForOdoo(array $contactData, bool $forCreate): array
    {
        $fieldMap = [
            'name'                      => 'name',
            'type'                      => 'type',
            'email'                     => 'email',
            'phone'                     => 'phone',
            'mobile'                    => 'mobile',
            'street'                    => 'street',
            'city'                      => 'city',
            'vat'                       => 'vat',
            'x_studio_agente_de_ventas' => 'x_studio_agente_de_ventas',
        ];
        $requiredOnCreate = ['name', 'type', 'x_studio_agente_de_ventas'];
        $out              = [];

        foreach ($fieldMap as $odooField => $dataField) {
            if (!$this->isPartnerFieldAvailable($odooField)) {
                continue;
            }

            $value = isset($contactData[$dataField]) ? trim((string) $contactData[$dataField]) : '';
            if ($forCreate && $odooField === 'type' && $value === '') {
                $value = 'contact';
            }

            if ($forCreate && in_array($odooField, $requiredOnCreate, true)) {
                if ($odooField === 'name' && $value === '') {
                    continue;
                }
                $out[$odooField] = $value;
            } elseif ($value !== '') {
                $out[$odooField] = $value;
            }
        }

        if (
            isset($contactData['parent_id'])
            && (int) $contactData['parent_id'] > 0
            && $this->isPartnerFieldAvailable('parent_id')
        ) {
            $out['parent_id'] = (int) $contactData['parent_id'];
        }

        return $out;
    }

    /**
     * Build XML fields for contact data
     *
     * @param   array  $contactData  The contact data
     *
     * @return  string  The XML fields
     * @deprecated Superseded by buildContactValuesForOdoo() + buildExecuteKwXml()
     */
    private function buildContactXmlFields($contactData)
    {
        $fields = '';
        $vals   = $this->buildContactValuesForOdoo($contactData, false);
        foreach ($vals as $xmlField => $value) {
            if ($xmlField === 'parent_id') {
                $fields .= '<member><name>parent_id</name><value><int>' . (int) $value . '</int></value></member>';
                continue;
            }
            $fields .= '<member>
                    <name>' . htmlspecialchars((string) $xmlField, ENT_XML1, 'UTF-8') . '</name>
                    <value><string>' . htmlspecialchars((string) $value, ENT_XML1, 'UTF-8') . '</string></value>
                </member>';
        }

        return $fields;
    }

    /**
     * Parse contacts response from Odoo
     *
     * @param   mixed  $result  The API response
     *
     * @return  array  Array of contacts
     */
    private function parseContactsResponse($result)
    {
        if (!$result || !isset($result['params']['param']['value']['array']['data']['value'])) {
            return [];
        }

        $contacts = [];
        $values = $result['params']['param']['value']['array']['data']['value'];

        // Handle single contact response
        if (isset($values['struct'])) {
            $values = [$values];
        }

        foreach ($values as $value) {
            if (!isset($value['struct']['member'])) {
                continue;
            }

            $contact = [];
            foreach ($value['struct']['member'] as $member) {
                $fieldName = $member['name'];
                $fieldValue = '';

                if (
                    $fieldName === 'property_payment_term_id'
                    && isset($member['value']['boolean'])
                    && !$member['value']['boolean']
                ) {
                    $contact['payment_term_id'] = '';
                    $contact[$fieldName] = '';
                    $contact['payment_terms'] = '';

                    continue;
                }

                if (
                    $fieldName === 'property_supplier_payment_term_id'
                    && isset($member['value']['boolean'])
                    && !$member['value']['boolean']
                ) {
                    $contact['supplier_payment_term_id'] = '';
                    $contact[$fieldName] = '';
                    $contact['supplier_payment_terms'] = '';

                    continue;
                }

                if (isset($member['value']['string'])) {
                    $fieldValue = $member['value']['string'];
                } elseif (isset($member['value']['int'])) {
                    $fieldValue = (string) $member['value']['int'];
                } elseif (isset($member['value']['double'])) {
                    $fieldValue = (string) $member['value']['double'];
                } elseif (isset($member['value']['boolean'])
                    && $fieldName === 'invoice_sending_method') {
                    // Odoo selection False → no method set
                    $fieldValue = $member['value']['boolean'] ? '1' : '';
                } elseif (isset($member['value']['boolean'])) {
                    $fieldValue = $member['value']['boolean'] ? '1' : '0';
                } elseif (
                    isset($member['value']['array']['data']['value'])
                    && $fieldName === 'property_supplier_payment_term_id'
                ) {
                    [$termId, $termName] = self::many2oneIdAndLabelFromOdooRpcMember($member);
                    $contact['supplier_payment_term_id'] = $termId !== null ? (string) $termId : '';
                    $fieldValue                            = $termName;
                    if ($termName !== '') {
                        $contact['supplier_payment_terms'] = $termName;
                    }

                    $contact[$fieldName] = $termName;

                    continue;
                } elseif (
                    isset($member['value']['array']['data']['value'])
                    && $fieldName === 'property_payment_term_id'
                ) {
                    [$termId, $termName] = self::many2oneIdAndLabelFromOdooRpcMember($member);
                    $contact['payment_term_id'] = $termId !== null ? (string) $termId : '';
                    $fieldValue                = $termName;
                    if ($termName !== '') {
                        $contact['payment_terms'] = $termName;
                    }

                    // Many2one: store label in canonical field slot used by Accounting UI imports
                    $contact[$fieldName] = $termName;

                    continue;
                } elseif (
                    isset($member['value']['array']['data']['value'])
                    && $fieldName === 'parent_id'
                ) {
                    [$parentId, $parentName] = self::many2oneIdAndLabelFromOdooRpcMember($member);
                    $contact['parent_id'] = $parentId !== null ? (string) $parentId : '';
                    if ($parentName !== '') {
                        $contact['parent_name'] = $parentName;
                    }

                    continue;
                }

                $contact[$fieldName] = $fieldValue;
            }

            self::finalizePartnerFinanceDisplayFields($contact);
            $contacts[] = $contact;
        }

        return $contacts;
    }

    /**
     * Normalize Odoo accounting / sales fields after search_read parsing.
     * Sets readable keys: payment_terms (label), credit_limit_numeric (float|null), invoice_sending_method_label.
     *
     * @param   array  $partner  Mutable row keyed by Odoo field names
     */
    private static function finalizePartnerFinanceDisplayFields(array &$partner): void
    {
        // Payment terms: prefer Ventas/customer ({@see property_payment_term_id}); if empty, Odoo COMPRAS/vendor ({@see property_supplier_payment_term_id}).
        if (!isset($partner['payment_terms']) || $partner['payment_terms'] === '') {
            $raw = isset($partner['property_payment_term_id'])
                ? trim((string) $partner['property_payment_term_id'])
                : '';
            if ($raw !== '' && !ctype_digit($raw)) {
                $partner['payment_terms'] = $raw;
            }
        }

        if (!isset($partner['payment_terms']) || $partner['payment_terms'] === '') {
            $supLabel = isset($partner['supplier_payment_terms']) ? trim((string) $partner['supplier_payment_terms']) : '';
            if ($supLabel === '' && isset($partner['property_supplier_payment_term_id'])) {
                $spr = trim((string) $partner['property_supplier_payment_term_id']);
                if ($spr !== '' && !ctype_digit($spr)) {
                    $supLabel = $spr;
                }
            }

            if ($supLabel !== '') {
                $partner['payment_terms'] = $supLabel;
                $spi        = isset($partner['supplier_payment_term_id']) ? trim((string) $partner['supplier_payment_term_id']) : '';
                $existingId = isset($partner['payment_term_id']) ? trim((string) $partner['payment_term_id']) : '';
                if ($spi !== '' && $existingId === '') {
                    $partner['payment_term_id'] = $spi;
                }
            }
        }

        // Credit limit (Contabilidad) as float when possible
        $clRaw = isset($partner['credit_limit']) ? trim((string) $partner['credit_limit']) : '';
        if ($clRaw === '' || strtolower($clRaw) === 'false') {
            $partner['credit_limit_numeric'] = null;
        } else {
            $partner['credit_limit_numeric'] = is_numeric($clRaw) ? (float) $clRaw : null;
        }

        // Envío de facturas (Odoo Accounting → res.partner.invoice_sending_method)
        $ismRaw = isset($partner['invoice_sending_method']) ? trim((string) $partner['invoice_sending_method']) : '';
        if ($ismRaw === '' || $ismRaw === '0' || strtolower($ismRaw) === 'false') {
            $partner['invoice_sending_method']        = '';
            $partner['invoice_sending_method_label'] = '';
        } else {
            $partner['invoice_sending_method_label'] = self::mapInvoiceSendingMethodToLabel($ismRaw);
        }
    }

    /**
     * Human-readable invoice sending mode for UI (prefer technical code → always resolves with active language).
     * Also fixes stale values stored when {@see Text::_('…')} fell back to a COM_* key during API sync without site language loaded.
     *
     * @since  3.119.30
     */
    public static function invoiceSendingDisplayLabel(string $storedCode, string $storedLabel): string
    {
        $cRaw = strtolower(str_replace(['-', ' '], '_', trim($storedCode)));
        $cRaw = $cRaw !== '' ? preg_replace('/_+/', '_', $cRaw) : '';
        if ($cRaw !== '' && !in_array($cRaw, ['0', 'false'], true)) {
            $byCode = self::mapInvoiceSendingMethodToLabel($cRaw);
            if ($byCode !== '') {
                return $byCode;
            }
        }

        $lb = trim($storedLabel);
        if ($lb === '') {
            return '';
        }
        if (strncmp($lb, 'COM_', 4) === 0) {
            $tr = (string) Text::_($lb);

            return ($tr !== '' && $tr !== $lb) ? $tr : self::recoverInvoiceSendingLabelThroughCode($lb);
        }

        return $lb;
    }

    /**
     * Humanize unknown technical selection codes from ERP (Odoo-style) for display when no Joomla string exists.
     */
    private static function friendlyInvoiceSendingUnknownTechnical(string $rawCode): string
    {
        $s = trim($rawCode);
        if ($s === '') {
            return '';
        }
        if (strncmp($s, 'COM_', 4) === 0) {
            return $s;
        }
        $s = strtolower(str_replace('_', ' ', $s));
        /** @lang text */
        return (string) ucwords(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    /**
     * Recover user-facing invoice sending mode when Joomla returned the literal COM_* key (e.g. language not loaded during sync).
     */
    private static function recoverInvoiceSendingLabelThroughCode(string $comKey): string
    {
        if (!preg_match('/INVOICE_SENDING_(EMAIL|MANUAL|POST|PEPPOL)$/i', $comKey, $m)) {
            return $comKey;
        }

        switch (strtoupper((string) $m[1])) {
            case 'EMAIL':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_EMAIL');
            case 'MANUAL':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_MANUAL');
            case 'POST':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_POST');
            case 'PEPPOL':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_PEPPOL');
            default:
                return $comKey;
        }
    }

    /**
     * Localized label for ERP (Odoo) {@see res.partner} field invoice_sending_method (selection).
     *
     * @since  3.118.99
     */
    private static function mapInvoiceSendingMethodToLabel(string $code): string
    {
        $trim = trim($code);
        if ($trim === '' || strtolower($trim) === 'false' || $trim === '0') {
            return '';
        }

        if (strncmp($trim, 'COM_', 4) === 0) {
            $t = (string) Text::_($trim);

            return ($t !== '' && $t !== $trim) ? $t : self::recoverInvoiceSendingLabelThroughCode($trim);
        }

        $c = strtolower(str_replace([' ', '-'], '_', $trim));
        $c = preg_replace('/_+/', '_', $c);

        switch ($c) {
            case 'email':
            case 'mail':
            case 'by_email':
            case 'byemail':
            case 'einvoicing_and_email':
            case 'einvoicing_email':
            case 'e_invoicing_and_email':
            case 'e_invoicing_email':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_EMAIL');
            case 'manual':
            case 'download':
            case 'account_manual':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_MANUAL');
            case 'post':
            case 'snailmail':
            case 'by_post':
            case 'bypost':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_POST');
            case 'peppol':
            case 'by_peppol':
                return (string) Text::_('COM_ORDENPRODUCCION_ODOO_INVOICE_SENDING_PEPPOL');
            default:
                return self::friendlyInvoiceSendingUnknownTechnical($trim);
        }
    }

    /**
     * @param   array  $member  One struct member from Odoo XML-RPC (json decode path)
     *
     * @return  array  [termId ?int, termLabel string]
     */
    private static function many2oneIdAndLabelFromOdooRpcMember(array $member): array
    {
        $cells = isset($member['value']['array']['data']['value']) ? $member['value']['array']['data']['value'] : null;

        if ($cells === null) {
            return [null, ''];
        }

        if (isset($cells['struct'])) {
            $cells = [$cells];
        }

        $cell0 = $cells[0] ?? null;
        $cell1 = $cells[1] ?? null;
        $termId = null;
        $label  = '';

        if (is_array($cell0)) {
            if (isset($cell0['int'])) {
                $termId = (int) $cell0['int'];
            } elseif (isset($cell0['boolean']) && !$cell0['boolean']) {
                return [null, ''];
            }
        }

        if (is_array($cell1) && isset($cell1['string'])) {
            $label = (string) $cell1['string'];
        }

        return [$termId, $label];
    }

    /**
     * Parse create response from Odoo
     *
     * @param   mixed  $result  The API response
     *
     * @return  mixed  The contact ID on success, false on failure
     */
    private function parseCreateResponse($result)
    {
        if (!is_array($result) || $this->hasXmlRpcFault($result)) {
            return false;
        }

        $param = $result['params']['param'] ?? null;
        if (!is_array($param)) {
            return false;
        }
        if (isset($param[0]) && is_array($param[0]) && !isset($param['value'])) {
            $param = $param[0];
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

        return false;
    }

    /**
     * Get suppliers by reference containing "OTE"
     * Accessible by all users (no agent filter)
     *
     * @return  array  Array of suppliers
     */
    public function getSuppliersByOTEReference()
    {
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value>
      </param>
      <param>
         <value><string>search_read</string></value>
      </param>
      <param>
         <value>
            <array>
               <data>
                  <value>
                     <array>
                        <data>
                           <value>
                              <array>
                                 <data>
                                    <value><string>supplier_rank</string></value>
                                    <value><string>&gt;</string></value>
                                    <value><int>0</int></value>
                                 </data>
                              </array>
                           </value>
                           <value>
                              <array>
                                 <data>
                                    <value><string>ref</string></value>
                                    <value><string>ilike</string></value>
                                    <value><string>%OTE%</string></value>
                                 </data>
                              </array>
                           </value>
                        </data>
                     </array>
                  </value>
               </data>
            </array>
         </value>
      </param>
      <param>
         <value>
            <struct>
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>id</string></value>
                           <value><string>name</string></value>
                           <value><string>ref</string></value>
                           <value><string>email</string></value>
                           <value><string>phone</string></value>
                           <value><string>mobile</string></value>
                           <value><string>vat</string></value>
                           <value><string>property_payment_term_id</string></value>
                           <value><string>supplier_rank</string></value>
                        </data>
                     </array>
                  </value>
               </member>
            </struct>
         </value>
      </param>
   </params>
</methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
        
        if (!$result) {
            return [];
        }

        return $this->parseSuppliersResponse($result);
    }

    /**
     * Get all suppliers (no OTE filter)
     * For CRUD management interface
     *
     * @return  array  Array of suppliers
     */
    public function getAllSuppliers()
    {
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value>
      </param>
      <param>
         <value><string>search_read</string></value>
      </param>
      <param>
         <value>
            <array>
               <data>
                  <value>
                     <array>
                        <data>
                           <value>
                              <array>
                                 <data>
                                    <value><string>supplier_rank</string></value>
                                    <value><string>&gt;</string></value>
                                    <value><int>0</int></value>
                                 </data>
                              </array>
                           </value>
                        </data>
                     </array>
                  </value>
               </data>
            </array>
         </value>
      </param>
      <param>
         <value>
            <struct>
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>id</string></value>
                           <value><string>name</string></value>
                           <value><string>ref</string></value>
                           <value><string>email</string></value>
                           <value><string>phone</string></value>
                           <value><string>mobile</string></value>
                           <value><string>vat</string></value>
                           <value><string>street</string></value>
                           <value><string>city</string></value>
                           <value><string>property_payment_term_id</string></value>
                           <value><string>supplier_rank</string></value>
                        </data>
                     </array>
                  </value>
               </member>
            </struct>
         </value>
      </param>
   </params>
</methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
        
        if (!$result) {
            return [];
        }

        return $this->parseSuppliersResponse($result);
    }

    /**
     * Get supplier by ID
     *
     * @param   integer  $supplierId  The supplier ID
     *
     * @return  mixed  Supplier data or null
     */
    public function getSupplierById($supplierId)
    {
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value>
      </param>
      <param>
         <value><string>search_read</string></value>
      </param>
      <param>
        <value>
        <array>
          <data>
            <value>
              <array>
                <data>
                  <value>
                    <array>
                      <data>
                        <value><string>id</string></value>
                        <value><string>=</string></value>
                        <value><int>' . (int)$supplierId . '</int></value>
                      </data>
                    </array>
                  </value>
                </data>
              </array>
            </value>
          </data>
        </array>
      </value>
    </param>
    <param>
         <value>
            <struct>
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>id</string></value>
                           <value><string>name</string></value>
                           <value><string>ref</string></value>
                           <value><string>email</string></value>
                           <value><string>phone</string></value>
                           <value><string>mobile</string></value>
                           <value><string>vat</string></value>
                           <value><string>street</string></value>
                           <value><string>city</string></value>
                           <value><string>property_payment_term_id</string></value>
                           <value><string>supplier_rank</string></value>
                        </data>
                     </array>
                  </value>
               </member>
            </struct>
         </value>
      </param>
   </params>
</methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
        
        if (!$result) {
            return null;
        }

        $suppliers = $this->parseSuppliersResponse($result);
        return !empty($suppliers) ? $suppliers[0] : null;
    }

    /**
     * Parse suppliers response from Odoo
     *
     * @param   mixed  $result  The API response
     *
     * @return  array  Array of suppliers
     */
    private function parseSuppliersResponse($result)
    {
        if (!$result || !isset($result['params']['param']['value']['array']['data']['value'])) {
            return [];
        }

        $suppliers = [];
        $values = $result['params']['param']['value']['array']['data']['value'];
        
        // Handle both single and multiple results
        if (isset($values['struct'])) {
            $values = [$values];
        }

        foreach ($values as $value) {
            if (!isset($value['struct']['member'])) {
                continue;
            }

            $supplier = [];
            foreach ($value['struct']['member'] as $member) {
                $fieldName = $member['name'];
                $fieldValue = '';

                if (isset($member['value']['string'])) {
                    $fieldValue = $member['value']['string'];
                } elseif (isset($member['value']['int'])) {
                    $fieldValue = (string)$member['value']['int'];
                } elseif (isset($member['value']['boolean'])) {
                    $fieldValue = $member['value']['boolean'] ? '1' : '0';
                } elseif (isset($member['value']['double'])) {
                    $fieldValue = (string)$member['value']['double'];
                } elseif (isset($member['value']['array']['data']['value'])) {
                    // Handle array fields like property_payment_term_id [id, name]
                    if ($fieldName === 'property_payment_term_id') {
                        $paymentTermData = $member['value']['array']['data']['value'];
                        if (isset($paymentTermData[1]['string'])) {
                            $fieldValue = $paymentTermData[1]['string']; // Get the name
                        }
                    }
                }

                $supplier[$fieldName] = $fieldValue;
            }

            // Normalize supplier data
            $normalizedSupplier = [
                'id' => isset($supplier['id']) ? $supplier['id'] : '0',
                'name' => isset($supplier['name']) ? $supplier['name'] : '',
                'ref' => isset($supplier['ref']) ? $supplier['ref'] : '',
                'email' => isset($supplier['email']) ? $supplier['email'] : '',
                'phone' => isset($supplier['phone']) ? $supplier['phone'] : '',
                'mobile' => isset($supplier['mobile']) ? $supplier['mobile'] : '',
                'vat' => isset($supplier['vat']) ? $supplier['vat'] : '',
                'street' => isset($supplier['street']) ? $supplier['street'] : '',
                'city' => isset($supplier['city']) ? $supplier['city'] : '',
                'payment_terms' => isset($supplier['property_payment_term_id']) ? $supplier['property_payment_term_id'] : '',
                'supplier_rank' => isset($supplier['supplier_rank']) ? $supplier['supplier_rank'] : '0'
            ];

            $suppliers[] = $normalizedSupplier;
        }

        return $suppliers;
    }

    /**
     * Credit limit + payment terms + invoice sending from Odoo (res.partner).
     *
     * Customer payment terms use property_payment_term_id (Odoo Ventas). If that is empty,
     * property_supplier_payment_term_id (Odoo COMPRAS — proveedor) is used for the same UI field.
     *
     * Field names: credit_limit, property_payment_term_id, property_supplier_payment_term_id,
     * invoice_sending_method (Accounting).
     *
     * @param   integer  $clientId  res.partner id
     *
     * @return  array{credit_limit: ?float, payment_term_id: ?int, payment_term_name: string, invoice_sending_method: string, invoice_sending_method_label: string}
     */
    public function getPartnerSalesAccountingInfo(int $clientId): array
    {
        $defaults = [
            'credit_limit'                  => null,
            'payment_term_id'               => null,
            'payment_term_name'             => '',
            'invoice_sending_method'        => '',
            'invoice_sending_method_label'  => '',
        ];

        if ($clientId <= 0) {
            return $defaults;
        }

        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
      <param>
         <value><string>' . htmlspecialchars((string) $this->config->get('odoo_db', ''), ENT_XML1, 'UTF-8') . '</string></value>
      </param>
      <param>
         <value><int>' . (int) $this->config->get('odoo_user_id', '2') . '</int></value>
      </param>
      <param>
         <value><string>' . htmlspecialchars((string) $this->config->get('odoo_api_key', ''), ENT_XML1, 'UTF-8') . '</string></value>
      </param>
      <param>
         <value><string>res.partner</string></value>
      </param>
      <param>
         <value><string>search_read</string></value>
      </param>
      <param>
         <value>
            <array>
               <data>
                  <value>
                     <array>
                        <data>
                           <value>
                              <array>
                                 <data>
                                    <value><string>id</string></value>
                                    <value><string>=</string></value>
                                    <value><int>' . (int) $clientId . '</int></value>
                                 </data>
                              </array>
                           </value>
                        </data>
                     </array>
                  </value>
               </data>
            </array>
         </value>
      </param>
      <param>
         <value>
            <struct>
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>credit_limit</string></value>
                           <value><string>property_payment_term_id</string></value>
                           <value><string>property_supplier_payment_term_id</string></value>
                           <value><string>invoice_sending_method</string></value>
                        </data>
                     </array>
                  </value>
               </member>
            </struct>
         </value>
      </param>
   </params>
</methodCall>';

        $result = $this->executeOdooCall($xmlPayload);

        if (!$result) {
            return $defaults;
        }

        $contacts = $this->parseContactsResponse($result);

        if ($contacts === [] || !\is_array($contacts[0])) {
            return $defaults;
        }

        $p = $contacts[0];

        $defaults['credit_limit'] = $p['credit_limit_numeric'] ?? null;

        $ptName = isset($p['payment_terms']) ? trim((string) $p['payment_terms']) : '';
        if ($ptName === '' && isset($p['property_payment_term_id'])) {
            $fallback = trim((string) $p['property_payment_term_id']);
            if ($fallback !== '' && !ctype_digit($fallback)) {
                $ptName = $fallback;
            }
        }

        $defaults['payment_term_name'] = $ptName;

        if (isset($p['payment_term_id']) && $p['payment_term_id'] !== '') {
            $defaults['payment_term_id'] = (int) $p['payment_term_id'];
        }

        $defaults['invoice_sending_method'] = isset($p['invoice_sending_method'])
            ? trim((string) $p['invoice_sending_method'])
            : '';
        $defaults['invoice_sending_method_label'] = isset($p['invoice_sending_method_label'])
            ? trim((string) $p['invoice_sending_method_label'])
            : '';

        return $defaults;
    }

    /**
     * Get credit limit for a client
     *
     * @param   integer  $clientId  The client ID
     *
     * @return  float|null  The credit limit or null if not found
     */
    public function getCreditLimit($clientId)
    {
        return $this->getPartnerSalesAccountingInfo((int) $clientId)['credit_limit'];
    }

    /**
     * Test the Odoo connection
     *
     * @return  array  Array with 'success' (boolean) and 'message' (string)
     */
    public function testConnection()
    {
        // Get configuration values
        $odooUrl = $this->getObjectEndpointUrl();
        $odooDb = $this->config->get('odoo_db', '');
        $odooUserId = $this->config->get('odoo_user_id', '2');
        $odooApiKey = $this->config->get('odoo_api_key', '');
        
        // Validate required parameters
        if (empty($odooUrl) || empty($odooDb) || empty($odooApiKey)) {
            return [
                'success' => false,
                'message' => 'Missing required Odoo configuration parameters'
            ];
        }
        
        // Test with search_count on res.partner (Odoo 19 requires domain argument)
        $testXmlPayload = $this->buildExecuteKwXml('res.partner', 'search_count', [[]]);

        $result = $this->executeOdooCall($testXmlPayload);

        if ($result === false || $this->hasXmlRpcFault($result)) {
            return [
                'success' => false,
                'message' => 'Failed to connect to Odoo server. Please check your configuration and network connection.',
            ];
        }

        $count = OdooDiagnosticHelper::extractIntParam($result);
        if ($count === false) {
            return [
                'success' => false,
                'message' => 'Odoo responded but partner search_count failed (check API user permissions).',
            ];
        }

        return [
            'success' => true,
            'message' => 'Successfully connected to Odoo ' . $odooDb . ' database (' . $count . ' partners)',
        ];
    }
}