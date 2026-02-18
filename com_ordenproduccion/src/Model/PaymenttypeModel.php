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

/**
 * Payment Type Model for managing payment types (efectivo, cheque, etc.)
 *
 * @since  3.65.0
 */
class PaymenttypeModel extends BaseDatabaseModel
{
    /**
     * Get all payment types ordered by ordering field
     *
     * @return  array  Array of payment type objects
     *
     * @since   3.65.0
     */
    public function getPaymentTypes()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_payment_types'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get a single payment type by ID
     *
     * @param   int  $id  Payment type ID
     *
     * @return  object|null  Payment type object or null
     *
     * @since   3.65.0
     */
    public function getPaymentType($id)
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_payment_types'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Save a payment type (create or update)
     *
     * @param   array  $data  Payment type data
     *
     * @return  int|false  Payment type ID on success, false on failure
     *
     * @since   3.65.0
     */
    public function savePaymentType($data)
    {
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $date = Factory::getDate()->toSql();

        $obj = new \stdClass();

        if (!empty($data['id'])) {
            $obj->id = (int) $data['id'];
            $obj->modified = $date;
            $obj->modified_by = $user->id;
        } else {
            $obj->created = $date;
            $obj->created_by = $user->id;

            $query = $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ')')
                ->from($db->quoteName('#__ordenproduccion_payment_types'));
            $db->setQuery($query);
            $maxOrdering = $db->loadResult() ?: 0;
            $obj->ordering = $maxOrdering + 1;
        }

        $obj->code = $data['code'] ?? '';
        $obj->name = $data['name'] ?? '';
        $obj->name_en = $data['name_en'] ?? $data['name'] ?? '';
        $obj->name_es = $data['name_es'] ?? $data['name'] ?? '';
        $obj->requires_bank = isset($data['requires_bank']) ? (int) $data['requires_bank'] : 1;
        $obj->state = isset($data['state']) ? (int) $data['state'] : 1;

        try {
            if (!empty($obj->id)) {
                $db->updateObject('#__ordenproduccion_payment_types', $obj, 'id');

                return $obj->id;
            }

            $db->insertObject('#__ordenproduccion_payment_types', $obj);

            return $db->insertid();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }

    /**
     * Delete a payment type
     *
     * @param   int  $id  Payment type ID
     *
     * @return  bool  True on success
     *
     * @since   3.65.0
     */
    public function deletePaymentType($id)
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ordenproduccion_payment_types'))
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
     * Update payment type ordering
     *
     * @param   array  $order  Array of payment type IDs in new order
     *
     * @return  bool  True on success
     *
     * @since   3.65.0
     */
    public function updateOrdering($order)
    {
        $db = $this->getDatabase();

        try {
            foreach ($order as $index => $typeId) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_payment_types'))
                    ->set($db->quoteName('ordering') . ' = ' . (int) ($index + 1))
                    ->where($db->quoteName('id') . ' = ' . (int) $typeId);

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
     * Get payment type options for dropdown (code => label)
     *
     * @return  array  Array of payment type options (code => display name)
     *
     * @since   3.65.0
     */
    public function getPaymentTypeOptions()
    {
        $db = $this->getDatabase();

        try {
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'ordenproduccion_payment_types';
            $exists = false;

            foreach ($tables as $t) {
                if (strcasecmp($t, $tableName) === 0) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_payment_types'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        $db->setQuery($query);
        $types = $db->loadObjectList() ?: [];

        $options = [];
        $lang = Factory::getLanguage();
        $isSpanish = (strpos($lang->getTag(), 'es') === 0);

        foreach ($types as $t) {
            if (empty($t->code)) {
                continue;
            }

            if ($isSpanish && !empty(trim($t->name_es ?? ''))) {
                $label = trim($t->name_es);
            } elseif (!empty(trim($t->name_en ?? ''))) {
                $label = trim($t->name_en);
            } else {
                $label = trim($t->name ?? $t->code);
            }

            $options[$t->code] = $label;
        }

        return $options;
    }
}
