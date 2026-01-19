<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Language\Text;

/**
 * Bank Model for managing banks
 *
 * @since  3.5.1
 */
class BankModel extends BaseDatabaseModel
{
    /**
     * Get all banks ordered by ordering field
     *
     * @return  array  Array of bank objects
     *
     * @since   3.5.1
     */
    public function getBanks()
    {
        $db = $this->getDatabase();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_banks'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');
        
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get a single bank by ID
     *
     * @param   int  $id  Bank ID
     *
     * @return  object|null  Bank object or null
     *
     * @since   3.5.1
     */
    public function getBank($id)
    {
        $db = $this->getDatabase();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_banks'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        
        $db->setQuery($query);
        return $db->loadObject();
    }

    /**
     * Save a bank (create or update)
     *
     * @param   array  $data  Bank data
     *
     * @return  int|false  Bank ID on success, false on failure
     *
     * @since   3.5.1
     */
    public function saveBank($data)
    {
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $date = Factory::getDate()->toSql();
        
        // Prepare data
        $bank = new \stdClass();
        
        if (!empty($data['id'])) {
            // Update existing
            $bank->id = (int) $data['id'];
            $bank->modified = $date;
            $bank->modified_by = $user->id;
        } else {
            // Create new - get next ordering value
            $bank->created = $date;
            $bank->created_by = $user->id;
            
            $query = $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ')')
                ->from($db->quoteName('#__ordenproduccion_banks'));
            $db->setQuery($query);
            $maxOrdering = $db->loadResult() ?: 0;
            $bank->ordering = $maxOrdering + 1;
        }
        
        $bank->code = $data['code'] ?? '';
        $bank->name = $data['name'] ?? '';
        $bank->name_en = $data['name_en'] ?? $data['name'] ?? '';
        $bank->name_es = $data['name_es'] ?? $data['name'] ?? '';
        $bank->state = isset($data['state']) ? (int) $data['state'] : 1;
        
        // Handle default bank - only one can be default
        if (!empty($data['is_default']) && $data['is_default'] == 1) {
            // Remove default from all other banks
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_banks'))
                ->set($db->quoteName('is_default') . ' = 0');
            $db->setQuery($query);
            $db->execute();
            
            $bank->is_default = 1;
        } else {
            $bank->is_default = isset($data['is_default']) ? (int) $data['is_default'] : 0;
        }
        
        try {
            if (!empty($bank->id)) {
                $db->updateObject('#__ordenproduccion_banks', $bank, 'id');
            } else {
                $db->insertObject('#__ordenproduccion_banks', $bank);
            }
            
            return $bank->id ?? $db->insertid();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Delete a bank
     *
     * @param   int  $id  Bank ID
     *
     * @return  bool  True on success
     *
     * @since   3.5.1
     */
    public function deleteBank($id)
    {
        $db = $this->getDatabase();
        
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ordenproduccion_banks'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        
        try {
            $db->setQuery($query);
            $db->execute();
            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Update bank ordering
     *
     * @param   array  $order  Array of bank IDs in new order
     *
     * @return  bool  True on success
     *
     * @since   3.5.1
     */
    public function updateOrdering($order)
    {
        $db = $this->getDatabase();
        
        try {
            foreach ($order as $index => $bankId) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_banks'))
                    ->set($db->quoteName('ordering') . ' = ' . (int) ($index + 1))
                    ->where($db->quoteName('id') . ' = ' . (int) $bankId);
                
                $db->setQuery($query);
                $db->execute();
            }
            
            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Set default bank
     *
     * @param   int  $id  Bank ID
     *
     * @return  bool  True on success
     *
     * @since   3.5.1
     */
    public function setDefault($id)
    {
        $db = $this->getDatabase();
        
        try {
            // Remove default from all banks
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_banks'))
                ->set($db->quoteName('is_default') . ' = 0');
            $db->setQuery($query);
            $db->execute();
            
            // Set new default
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_banks'))
                ->set($db->quoteName('is_default') . ' = 1')
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $db->execute();
            
            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Get bank options for dropdown (compatible with PaymentProofModel)
     *
     * @return  array  Array of bank options (code => name)
     *
     * @since   3.5.1
     */
    public function getBankOptions()
    {
        $banks = $this->getBanks();
        $options = [];
        
        $lang = Factory::getLanguage();
        $langTag = $lang->getTag();
        $isSpanish = (strpos($langTag, 'es') === 0);
        
        foreach ($banks as $bank) {
            // Skip banks without a valid code
            if (empty($bank->code)) {
                continue;
            }
            
            // Use language-specific name if available
            $name = $isSpanish && !empty($bank->name_es) 
                ? $bank->name_es 
                : (!empty($bank->name_en) ? $bank->name_en : $bank->name);
            
            // Ensure we have a valid name
            if (empty($name)) {
                $name = $bank->name;
            }
            
            // Use bank code as key - if duplicate codes exist, later one will overwrite
            // but we should log this as it's a data integrity issue
            if (isset($options[$bank->code])) {
                // Duplicate code detected - log for debugging
                error_log("Warning: Duplicate bank code found: {$bank->code} for bank ID {$bank->id}");
            }
            
            $options[$bank->code] = $name;
        }
        
        return $options;
    }
}
