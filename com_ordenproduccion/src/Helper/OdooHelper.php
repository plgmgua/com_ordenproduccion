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

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xmlPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml',
                'X-Openerp-Session-Id: ' . $apiKey,
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($this->config->get('enable_debug', 0)) {
            Log::add('Odoo API Call - HTTP Code: ' . $httpCode, Log::DEBUG, 'com_ordenproduccion.clientes');
            Log::add('Odoo API Response: ' . substr((string) $response, 0, 2000) . '...', Log::DEBUG, 'com_ordenproduccion.clientes');
            if ($error) {
                Log::add('Odoo API Error: ' . $error, Log::ERROR, 'com_ordenproduccion.clientes');
            }
        }

        if ($httpCode !== 200 || !$response) {
            Log::add('Odoo API Failed - HTTP: ' . $httpCode . ', Error: ' . $error, Log::ERROR, 'com_ordenproduccion.clientes');

            return false;
        }

        $xml = simplexml_load_string($response);
        if (!$xml) {
            Log::add('Failed to parse Odoo XML response', Log::ERROR, 'com_ordenproduccion.clientes');

            return false;
        }

        $json = json_encode($xml);

        return json_decode($json, true);
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
        // Use the exact same XML structure as get_contacts_by_vendor.php
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value> <!-- Model -->
      </param>
      <param>
         <value><string>search_read</string></value> <!-- Method -->
      </param>
      <param>
         <value><array><data/></array></value> <!-- Args -->
      </param>
      <param>
         <value>
            <struct>
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>name</string></value>
                           <value><string>x_studio_agente_de_ventas</string></value>
                           <value><string>type</string></value>
                           <value><string>complete_name</string></value>
                           <value><string>vat</string></value>
                           <value><string>street</string></value>
                           <value><string>city</string></value>
                           <value><string>email</string></value>
                           <value><string>phone</string></value>
                           <value><string>mobile</string></value>
                           <value><string>display_name</string></value>
                           <value><string>child_ids</string></value>
                           <value><string>parent_id</string></value>
                        </data>
                     </array>
                  </value>
               </member>
            </struct>
         </value> <!-- Keyword Args -->
      </param>
   </params>
</methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
        
        if (!$result) {
            return [];
        }

        // Parse exactly like your working PHP script
        return $this->parseContactsFromAllResults($result, $agentName);
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
                    // Field condition: ['field', 'operator', 'value']
                    $xml .= '<value><array><data>';
                    $xml .= '<value><string>' . htmlspecialchars($condition[0]) . '</string></value>';
                    $xml .= '<value><string>' . htmlspecialchars($condition[1]) . '</string></value>';
                    $xml .= '<value><string>' . htmlspecialchars($condition[2]) . '</string></value>';
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
        // Use search_count method to get total count
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value>
      </param>
      <param>
         <value><string>search_count</string></value>
      </param>
      <param>
         <value><array><data/></array></value>
      </param>
   </params>
</methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
        
        if (!$result) {
            return 0;
        }

        // Parse the count from the response
        if (isset($result['params']['param']['value']['int'])) {
            $totalCount = (int)$result['params']['param']['value']['int'];
            // Filter by agent name (this is a simplified approach)
            // In a real implementation, you'd need to filter by agent in the search domain
            return $totalCount;
        }

        return 0;
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
        // Use the exact same XML structure as contact_edit.php
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value> <!-- Model -->
      </param>
      <param>
         <value><string>search_read</string></value> <!-- Method -->
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
                        <value><int>'.$contactId.'</int></value>
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
            <struct> <!-- Specify fields to retrieve -->
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>type</string></value>
                           <value><string>name</string></value>
                           <value><string>complete_name</string></value>
                           <value><string>vat</string></value>
                           <value><string>street</string></value>
                           <value><string>city</string></value>
                           <value><string>email</string></value>
                           <value><string>phone</string></value>
                           <value><string>mobile</string></value>
                           <value><string>x_studio_agente_de_ventas</string></value>
                           <value><string>display_name</string></value>
                           <value><string>child_ids</string></value>
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

        $contacts = $this->parseContactsResponse($result);
        return !empty($contacts) ? $contacts[0] : null;
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
        // Use the exact same XML structure as get_contacts_by_parent_id.php
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
' . $this->buildAuthParamsCompact() . '
      <param>
         <value><string>res.partner</string></value> <!-- Model -->
      </param>
      <param>
         <value><string>search_read</string></value> <!-- Method -->
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
                        <value><string>parent_id</string></value>
                        <value><string>=</string></value>
                        <value><int>'.$parentId.'</int></value>
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
            <struct> <!-- Specify fields to retrieve -->
               <member>
                  <name>fields</name>
                  <value>
                     <array>
                        <data>
                           <value><string>type</string></value>
                           <value><string>name</string></value>
                           <value><string>complete_name</string></value>
                           <value><string>vat</string></value>
                           <value><string>street</string></value>
                           <value><string>city</string></value>
                           <value><string>email</string></value>
                           <value><string>phone</string></value>
                           <value><string>mobile</string></value>
                           <value><string>x_studio_agente_de_ventas</string></value>
                           <value><string>display_name</string></value>
                           <value><string>child_ids</string></value>
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

        return $this->parseContactsResponse($result);
    }

    /**
     * Create new contact - using exact same structure as registrar_contacto.php
     *
     * @param   array  $contactData  The contact data
     *
     * @return  mixed  The contact ID on success, false on failure
     */
    public function createContact($contactData)
    {
        // Handle parent_id for child contacts
        $parentIdXml = '';
        if (isset($contactData['parent_id']) && (int)$contactData['parent_id'] > 0) {
            $parentIdXml = '<member>
                <name>parent_id</name>
                <value><int>' . (int)$contactData['parent_id'] . '</int></value>
            </member>';
        }
        
        // Use the exact same XML structure as registrar_contacto.php
        $xmlPayload = '<?xml version="1.0"?>
    <methodCall>
        <methodName>execute_kw</methodName>
        <params>
' . $this->buildAuthParamsCompact() . '
            <param>
                <value>
                    <string>res.partner</string>
                </value>
            </param>
            <param>
                <value>
                    <string>create</string>
                </value>
            </param>
            <param>
                <value>
                    <array>
                        <data>
                            <value>
                                <struct>
                                    <member>
                                        <name>name</name>
                                        <value><string>' . htmlspecialchars($contactData['name'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>type</name>
                                        <value><string>' . htmlspecialchars($contactData['type'] ?? 'contact', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>email</name>
                                        <value><string>' . htmlspecialchars($contactData['email'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>street</name>
                                        <value><string>' . htmlspecialchars($contactData['street'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>vat</name>
                                        <value><string>' . htmlspecialchars($contactData['vat'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>phone</name>
                                        <value><string>' . htmlspecialchars($contactData['phone'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>mobile</name>
                                        <value><string>' . htmlspecialchars($contactData['mobile'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>x_studio_agente_de_ventas</name>
                                        <value><string>' . htmlspecialchars($contactData['x_studio_agente_de_ventas'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    <member>
                                        <name>city</name>
                                        <value><string>' . htmlspecialchars($contactData['city'] ?? '', ENT_XML1, 'UTF-8') . '</string></value>
                                    </member>
                                    ' . $parentIdXml . '
                                </struct>
                            </value>
                        </data>
                    </array>
                </value>
            </param>
        </params>
    </methodCall>';

        $result = $this->executeOdooCall($xmlPayload);
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
        $xmlPayload = '<?xml version="1.0"?>
        <methodCall>
            <methodName>execute_kw</methodName>
            <params>
                ' . $this->buildAuthParamsCompact() . '
                <param><value><string>res.partner</string></value></param>
                <param><value><string>write</string></value></param>
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
                                <value>
                                    <struct>
                                        ' . $this->buildContactXmlFields($contactData) . '
                                    </struct>
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
     * Build XML fields for contact data
     *
     * @param   array  $contactData  The contact data
     *
     * @return  string  The XML fields
     */
    private function buildContactXmlFields($contactData)
    {
        $fields = '';
        $fieldMap = [
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'mobile' => 'mobile',
            'street' => 'street',
            'city' => 'city',
            'vat' => 'vat',
            'type' => 'type',
            'x_studio_agente_de_ventas' => 'x_studio_agente_de_ventas'
        ];

        foreach ($fieldMap as $xmlField => $dataField) {
            if (isset($contactData[$dataField]) && $contactData[$dataField] !== '') {
                $value = htmlspecialchars($contactData[$dataField], ENT_XML1, 'UTF-8');
                $fields .= '<member>
                    <name>' . $xmlField . '</name>
                    <value><string>' . $value . '</string></value>
                </member>';
            }
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
                
                if (isset($member['value']['string'])) {
                    $fieldValue = $member['value']['string'];
                } elseif (isset($member['value']['int'])) {
                    $fieldValue = (string)$member['value']['int'];
                }
                
                $contact[$fieldName] = $fieldValue;
            }
            
            $contacts[] = $contact;
        }

        return $contacts;
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
        if (!$result || !isset($result['params']['param']['value']['int'])) {
            return false;
        }

        return $result['params']['param']['value']['int'];
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
     * Get credit limit for a client
     *
     * @param   integer  $clientId  The client ID
     *
     * @return  float|null  The credit limit or null if not found
     */
    public function getCreditLimit($clientId)
    {
        if ($clientId <= 0) {
            return null;
        }

        // Get configuration values
        $odooDb = $this->config->get('odoo_db', '');
        $odooUserId = $this->config->get('odoo_user_id', '2');
        $odooApiKey = $this->config->get('odoo_api_key', '');

        // Build XML payload to get credit_limit field using search_read (consistent with other methods)
        $xmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
      <param>
         <value><string>' . htmlspecialchars($odooDb, ENT_XML1, 'UTF-8') . '</string></value>
      </param>
      <param>
         <value><int>' . (int)$odooUserId . '</int></value>
      </param>
      <param>
         <value><string>' . htmlspecialchars($odooApiKey, ENT_XML1, 'UTF-8') . '</string></value>
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
                                    <value><int>' . (int)$clientId . '</int></value>
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

        // Parse the response to extract credit_limit (using same pattern as parseContactsResponse)
        if (!isset($result['params']['param']['value']['array']['data']['value'])) {
            return null;
        }

        $values = $result['params']['param']['value']['array']['data']['value'];
        
        // Handle single result
        if (isset($values['struct'])) {
            $values = [$values];
        }
        
        foreach ($values as $value) {
            if (!isset($value['struct']['member'])) {
                continue;
            }
            
            foreach ($value['struct']['member'] as $member) {
                if ($member['name'] === 'credit_limit') {
                    // Handle different number formats
                    if (isset($member['value']['double'])) {
                        return (float)$member['value']['double'];
                    } elseif (isset($member['value']['int'])) {
                        return (float)$member['value']['int'];
                    } elseif (isset($member['value']['string'])) {
                        $creditLimit = trim($member['value']['string']);
                        if ($creditLimit === '' || $creditLimit === 'False' || $creditLimit === 'false') {
                            return null;
                        }
                        $creditLimit = (float)$creditLimit;
                        // Return 0 if explicitly set to 0, null if invalid
                        return $creditLimit >= 0 ? $creditLimit : null;
                    } elseif (isset($member['value']['boolean']) && $member['value']['boolean'] === false) {
                        // Handle False boolean value (Odoo sometimes returns False for empty fields)
                        return null;
                    }
                }
            }
        }

        return null;
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
        
        // Test with a simple search_count on res.partner (compatible with Odoo 19)
        $testXmlPayload = '<?xml version="1.0"?>
<methodCall>
   <methodName>execute_kw</methodName>
   <params>
      <param>
         <value><string>' . htmlspecialchars($odooDb, ENT_XML1, 'UTF-8') . '</string></value>
      </param>
      <param>
         <value><int>' . (int)$odooUserId . '</int></value>
      </param>
      <param>
         <value><string>' . htmlspecialchars($odooApiKey, ENT_XML1, 'UTF-8') . '</string></value>
      </param>
      <param>
         <value><string>res.partner</string></value>
      </param>
      <param>
         <value><string>search_count</string></value>
      </param>
      <param>
         <value><array><data></data></array></value>
      </param>
   </params>
</methodCall>';
        
        $result = $this->executeOdooCall($testXmlPayload);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to connect to Odoo server. Please check your configuration and network connection.'
            ];
        }
        
        // If we get here, the connection is working
        return [
            'success' => true,
            'message' => 'Successfully connected to Odoo ' . $odooDb . ' database'
        ];
    }
}