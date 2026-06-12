<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Bank accounts (cuentas bancarias) for Herramientas CRUD.
 *
 * @since  3.118.0
 */
class BankaccountModel extends BaseDatabaseModel
{
    /**
     * All accounts for admin list (active and inactive).
     *
     * @return  array<int, object>
     *
     * @since   3.118.0
     */
    public function getBankAccounts()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_bank_accounts'))
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Resolve display names for bank account ids (any state).
     *
     * @param   array<int>  $ids
     *
     * @return  array<int, string>  id => trimmed name
     *
     * @since   3.118.2
     */
    public function getNamesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return [];
        }
        $db = $this->getDatabase();
        $q = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('name')])
            ->from($db->quoteName('#__ordenproduccion_bank_accounts'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        $db->setQuery($q);
        $rows = $db->loadObjectList() ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = trim((string) ($r->name ?? ''));
        }

        return $out;
    }

    /**
     * Active default account (for forms / display), if any.
     *
     * @return  object|null
     *
     * @since   3.118.1
     */
    public function getDefaultBankAccount(): ?object
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_bank_accounts'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('is_default') . ' = 1')
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query);
        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * @param   array  $data  keys: id?, name, state, is_default (0|1)
     *
     * @return  int|false  New or updated id
     *
     * @since   3.118.0
     */
    public function saveBankAccount(array $data)
    {
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $date = Factory::getDate()->toSql();

        $obj = new \stdClass();

        if (!empty($data['id'])) {
            $obj->id = (int) $data['id'];
            $obj->modified = $date;
            $obj->modified_by = (int) $user->id;
        } else {
            $obj->created = $date;
            $obj->created_by = (int) $user->id;
        }

        $obj->name = trim((string) ($data['name'] ?? ''));
        $acctNo = trim((string) ($data['account_number'] ?? ''));
        if ($this->hasAccountNumberColumn()) {
            $obj->account_number = $acctNo !== '' ? $acctNo : null;
        }
        $obj->state = isset($data['state']) ? (int) $data['state'] : 1;
        if ($obj->state !== 0) {
            $obj->state = 1;
        }

        $wantDefault = !empty($data['is_default'])
            && ($data['is_default'] === 1 || $data['is_default'] === '1' || $data['is_default'] === true);
        if ($wantDefault) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_bank_accounts'))
                ->set($db->quoteName('is_default') . ' = 0');
            $db->setQuery($query);
            $db->execute();
            $obj->is_default = 1;
        } else {
            $obj->is_default = 0;
        }

        try {
            if (!empty($obj->id)) {
                $db->updateObject('#__ordenproduccion_bank_accounts', $obj, 'id');

                return (int) $obj->id;
            }

            $db->insertObject('#__ordenproduccion_bank_accounts', $obj);

            return (int) $db->insertid();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }

    /**
     * @param   int  $id  Primary key
     *
     * @return  bool
     *
     * @since   3.118.0
     */
    public function deleteBankAccount(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ordenproduccion_bank_accounts'))
            ->where($db->quoteName('id') . ' = ' . $id);

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
     * Set one account as default (clears others).
     *
     * @param   int  $id  Account id
     *
     * @return  bool
     *
     * @since   3.118.1
     */
    public function setDefault(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $db = $this->getDatabase();

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_bank_accounts'))
                ->set($db->quoteName('is_default') . ' = 0');
            $db->setQuery($query);
            $db->execute();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_bank_accounts'))
                ->set($db->quoteName('is_default') . ' = 1')
                ->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($query);
            $db->execute();

            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }

    /**
     * @return  bool
     *
     * @since   3.119.149
     */
    public function hasAccountNumberColumn(): bool
    {
        try {
            $cols = $this->getDatabase()->getTableColumns('#__ordenproduccion_bank_accounts', false);

            return isset($cols['account_number']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param   array<int>  $ids
     *
     * @return  array<int, object>
     *
     * @since   3.119.149
     */
    public function getBankAccountsByIds(array $ids): array
    {
        $ids = \array_values(\array_unique(\array_filter(\array_map('intval', $ids), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_bank_accounts'))
            ->where($db->quoteName('id') . ' IN (' . \implode(',', $ids) . ')')
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $out  = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = $row;
        }

        return $out;
    }
}
