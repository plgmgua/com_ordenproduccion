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
 * Productos model for pliego sizes, paper types, lamination types, and processes.
 *
 * @since  3.67.0
 */
class ProductosModel extends BaseDatabaseModel
{
    /**
     * Check if pliego quoting tables exist
     *
     * @return  bool
     * @since   3.67.0
     */
    public function tablesExist()
    {
        $db = $this->getDatabase();
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $required = [
            $prefix . 'ordenproduccion_pliego_sizes',
            $prefix . 'ordenproduccion_paper_types',
            $prefix . 'ordenproduccion_lamination_types',
            $prefix . 'ordenproduccion_pliego_processes',
            $prefix . 'ordenproduccion_pliego_print_prices',
            $prefix . 'ordenproduccion_lamination_prices',
        ];
        foreach ($required as $t) {
            $found = false;
            foreach ($tables as $name) {
                if (strcasecmp($name, $t) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all pliego sizes
     *
     * @return  array
     * @since   3.67.0
     */
    public function getSizes()
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_pliego_sizes'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get all paper types
     *
     * @return  array
     * @since   3.67.0
     */
    public function getPaperTypes()
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_paper_types'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get pliego sizes that have at least one non-zero print price (excludes 0-only combinations)
     *
     * @return  array
     * @since   3.67.0
     */
    public function getSizesWithNonZeroPrintPrice()
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $sizesTable = $db->quoteName('#__ordenproduccion_pliego_sizes', 's');
        $pricesTable = $db->quoteName('#__ordenproduccion_pliego_print_prices', 'p');
        $query = $db->getQuery(true)
            ->select('s.*')
            ->from($sizesTable)
            ->innerJoin(
                $pricesTable . ' ON p.size_id = s.id AND p.state = 1 AND p.price_per_sheet > 0'
            )
            ->where($db->quoteName('s.state') . ' = 1')
            ->group($db->quoteName('s.id'))
            ->order($db->quoteName('s.ordering') . ' ASC, s.id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get paper types that have at least one non-zero print price (excludes 0-only combinations)
     *
     * @return  array
     * @since   3.67.0
     */
    public function getPaperTypesWithNonZeroPrintPrice()
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $papersTable = $db->quoteName('#__ordenproduccion_paper_types', 'pt');
        $pricesTable = $db->quoteName('#__ordenproduccion_pliego_print_prices', 'p');
        $query = $db->getQuery(true)
            ->select('pt.*')
            ->from($papersTable)
            ->innerJoin(
                $pricesTable . ' ON p.paper_type_id = pt.id AND p.state = 1 AND p.price_per_sheet > 0'
            )
            ->where($db->quoteName('pt.state') . ' = 1')
            ->group($db->quoteName('pt.id'))
            ->order($db->quoteName('pt.ordering') . ' ASC, pt.id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get size IDs that have a non-zero print price for the given paper type.
     * Used to filter the size dropdown so only (paper type, size) combinations with price > 0 are selectable.
     *
     * @param   int  $paperTypeId  Paper type ID
     * @return  array  List of size_id
     * @since   3.67.0
     */
    public function getSizeIdsWithNonZeroPrintPriceForPaperType($paperTypeId)
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('size_id'))
            ->from($db->quoteName('#__ordenproduccion_pliego_print_prices'))
            ->where($db->quoteName('paper_type_id') . ' = ' . (int) $paperTypeId)
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('price_per_sheet') . ' > 0');
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_map('intval', $rows);
    }

    /**
     * Get size IDs available for a paper type
     *
     * @param   int  $paperTypeId  Paper type ID
     * @return  array  List of size_id
     * @since   3.67.0
     */
    public function getPaperTypeSizeIds($paperTypeId)
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('size_id'))
            ->from($db->quoteName('#__ordenproduccion_paper_type_sizes'))
            ->where($db->quoteName('paper_type_id') . ' = ' . (int) $paperTypeId);
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_map('intval', $rows);
    }

    /**
     * Get all lamination types
     *
     * @return  array
     * @since   3.67.0
     */
    public function getLaminationTypes()
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_lamination_types'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get all additional processes (cut, bend, perforado, etc.)
     *
     * @return  array
     * @since   3.67.0
     */
    public function getProcesses()
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_pliego_processes'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get print price per sheet for given paper, size, tiro/retiro and quantity
     *
     * @param   int     $paperTypeId  Paper type ID
     * @param   int     $sizeId       Size ID
     * @param   string  $tiroRetiro   'tiro' or 'retiro'
     * @param   int     $quantity     Order quantity
     * @return  float|null  Price per sheet or null if not found
     * @since   3.67.0
     */
    public function getPrintPricePerSheet($paperTypeId, $sizeId, $tiroRetiro, $quantity)
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $db = $this->getDatabase();
        $tiroRetiro = $tiroRetiro === 'retiro' ? 'retiro' : 'tiro';
        $quantity = (int) $quantity;
        $query = $db->getQuery(true)
            ->select($db->quoteName('price_per_sheet'))
            ->from($db->quoteName('#__ordenproduccion_pliego_print_prices'))
            ->where($db->quoteName('paper_type_id') . ' = ' . (int) $paperTypeId)
            ->where($db->quoteName('size_id') . ' = ' . (int) $sizeId)
            ->where($db->quoteName('tiro_retiro') . ' = ' . $db->quote($tiroRetiro))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('qty_min') . ' <= ' . $quantity)
            ->where($db->quoteName('qty_max') . ' >= ' . $quantity)
            ->order($db->quoteName('qty_min') . ' DESC')
            ->setLimit(1);
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result !== null ? (float) $result : null;
    }

    /**
     * Get lamination type IDs that have a non-zero price for the given size (and optionally tiro/retiro).
     * Lamination has different prices for tiro vs tiro/retiro; pass 'tiro' or 'retiro' to filter by that.
     *
     * @param   int    $sizeId     Size ID
     * @param   string $tiroRetiro Optional 'tiro' or 'retiro' to filter by side; null = any
     * @return  array  List of lamination_type_id
     * @since   3.67.0
     */
    public function getLaminationTypeIdsWithNonZeroPriceForSize($sizeId, $tiroRetiro = null)
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('lamination_type_id'))
            ->from($db->quoteName('#__ordenproduccion_lamination_prices'))
            ->where($db->quoteName('size_id') . ' = ' . (int) $sizeId)
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('price_per_sheet') . ' > 0');
        if ($tiroRetiro === 'retiro' || $tiroRetiro === 'tiro') {
            $query->where($db->quoteName('tiro_retiro') . ' = ' . $db->quote($tiroRetiro));
        }
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_map('intval', $rows);
    }

    /**
     * Get lamination price per sheet for given type, size, tiro/retiro and quantity
     *
     * @param   int     $laminationTypeId  Lamination type ID
     * @param   int     $sizeId            Size ID
     * @param   string  $tiroRetiro        'tiro' or 'retiro'
     * @param   int     $quantity          Order quantity
     * @return  float|null  Price per sheet or null if not found
     * @since   3.67.0
     */
    public function getLaminationPricePerSheet($laminationTypeId, $sizeId, $tiroRetiro, $quantity)
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $db = $this->getDatabase();
        $tiroRetiro = $tiroRetiro === 'retiro' ? 'retiro' : 'tiro';
        $quantity = (int) $quantity;
        $query = $db->getQuery(true)
            ->select($db->quoteName('price_per_sheet'))
            ->from($db->quoteName('#__ordenproduccion_lamination_prices'))
            ->where($db->quoteName('lamination_type_id') . ' = ' . (int) $laminationTypeId)
            ->where($db->quoteName('size_id') . ' = ' . (int) $sizeId)
            ->where($db->quoteName('tiro_retiro') . ' = ' . $db->quote($tiroRetiro))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('qty_min') . ' <= ' . $quantity)
            ->where($db->quoteName('qty_max') . ' >= ' . $quantity)
            ->order($db->quoteName('qty_min') . ' DESC')
            ->setLimit(1);
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result !== null ? (float) $result : null;
    }

    /**
     * Save a pliego size (create or update)
     *
     * @param   array  $data  Keys: id (0 for new), name, code, width_in, height_in, ordering, state
     * @return  int|false  Id on success, false on failure
     * @since   3.67.0
     */
    public function saveSize($data)
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $user = Factory::getUser();
        $db = $this->getDatabase();
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setError('Name is required.');
            return false;
        }
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;
        $obj = (object) [
            'id' => $id,
            'name' => $name,
            'code' => trim($data['code'] ?? ''),
            'width_in' => isset($data['width_in']) ? (float) $data['width_in'] : null,
            'height_in' => isset($data['height_in']) ? (float) $data['height_in'] : null,
            'ordering' => (int) ($data['ordering'] ?? 0),
            'state' => isset($data['state']) ? (int) $data['state'] : 1,
            'modified' => $now,
            'modified_by' => $userId,
        ];
        if ($id > 0) {
            $db->updateObject('#__ordenproduccion_pliego_sizes', $obj, ['id']);
        } else {
            $obj->created = $now;
            $obj->created_by = $userId;
            unset($obj->id);
            $db->insertObject('#__ordenproduccion_pliego_sizes', $obj);
            $id = (int) $db->insertid();
        }
        return $id;
    }

    /**
     * Save a paper type (create or update)
     *
     * @param   array  $data  Keys: id (0 for new), name, code, ordering, state
     * @return  int|false  Id on success, false on failure
     * @since   3.67.0
     */
    public function savePaperType($data)
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $user = Factory::getUser();
        $db = $this->getDatabase();
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setError('Name is required.');
            return false;
        }
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;
        $obj = (object) [
            'id' => $id,
            'name' => $name,
            'code' => trim($data['code'] ?? ''),
            'ordering' => (int) ($data['ordering'] ?? 0),
            'state' => isset($data['state']) ? (int) $data['state'] : 1,
            'modified' => $now,
            'modified_by' => $userId,
        ];
        if ($id > 0) {
            $db->updateObject('#__ordenproduccion_paper_types', $obj, ['id']);
        } else {
            $obj->created = $now;
            $obj->created_by = $userId;
            unset($obj->id);
            $db->insertObject('#__ordenproduccion_paper_types', $obj);
            $id = (int) $db->insertid();
        }
        return $id;
    }

    /**
     * Save a lamination type (create or update)
     *
     * @param   array  $data  Keys: id (0 for new), name, code, ordering, state
     * @return  int|false  Id on success, false on failure
     * @since   3.67.0
     */
    public function saveLaminationType($data)
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $user = Factory::getUser();
        $db = $this->getDatabase();
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setError('Name is required.');
            return false;
        }
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;
        $obj = (object) [
            'id' => $id,
            'name' => $name,
            'code' => trim($data['code'] ?? ''),
            'ordering' => (int) ($data['ordering'] ?? 0),
            'state' => isset($data['state']) ? (int) $data['state'] : 1,
            'modified' => $now,
            'modified_by' => $userId,
        ];
        if ($id > 0) {
            $db->updateObject('#__ordenproduccion_lamination_types', $obj, ['id']);
        } else {
            $obj->created = $now;
            $obj->created_by = $userId;
            unset($obj->id);
            $db->insertObject('#__ordenproduccion_lamination_types', $obj);
            $id = (int) $db->insertid();
        }
        return $id;
    }

    /**
     * Save an additional process (create or update)
     *
     * @param   array  $data  Keys: id (0 for new), name, code, price_per_pliego, price_1_to_1000, price_1001_plus, ordering, state
     * @return  int|false  Id on success, false on failure
     * @since   3.67.0
     */
    public function saveProcess($data)
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $user = Factory::getUser();
        $db = $this->getDatabase();
        $id = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setError('Name is required.');
            return false;
        }
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;
        $ceiling = (int) ($data['range_1_ceiling'] ?? 1000);
        if ($ceiling < 1) {
            $ceiling = 1000;
        }
        $obj = (object) [
            'id' => $id,
            'name' => $name,
            'code' => trim($data['code'] ?? ''),
            'price_per_pliego' => isset($data['price_per_pliego']) ? (float) $data['price_per_pliego'] : 0,
            'price_1_to_1000' => isset($data['price_1_to_1000']) ? (float) $data['price_1_to_1000'] : 0,
            'price_1001_plus' => isset($data['price_1001_plus']) ? (float) $data['price_1001_plus'] : 0,
            'range_1_ceiling' => $ceiling,
            'ordering' => (int) ($data['ordering'] ?? 0),
            'state' => isset($data['state']) ? (int) $data['state'] : 1,
            'modified' => $now,
            'modified_by' => $userId,
        ];
        if ($id > 0) {
            $db->updateObject('#__ordenproduccion_pliego_processes', $obj, ['id']);
        } else {
            $obj->created = $now;
            $obj->created_by = $userId;
            unset($obj->id);
            $db->insertObject('#__ordenproduccion_pliego_processes', $obj);
            $id = (int) $db->insertid();
        }
        return $id;
    }

    /**
     * Save process prices and range ceilings in bulk.
     *
     * @param   array  $prices1To1000  process_id => price (float)
     * @param   array  $prices1001Plus process_id => price (float)
     * @param   array  $rangeCeilings process_id => ceiling (int, upper bound of first range)
     * @return  bool
     * @since   3.68.0
     */
    public function saveProcessPrices($prices1To1000, $prices1001Plus, $rangeCeilings = [])
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;

        $columns = $db->getTableColumns('#__ordenproduccion_pliego_processes', false);
        $hasRangeCeiling = isset($columns['range_1_ceiling']);

        $ids = array_unique(array_merge(
            array_keys($prices1To1000),
            array_keys($prices1001Plus),
            array_keys($rangeCeilings)
        ));
        if (empty($ids)) {
            $this->setError('No process data received. Ensure the form includes price and range fields.');
            return false;
        }
        foreach ($ids as $processId) {
            $processId = (int) $processId;
            if ($processId < 1) {
                continue;
            }
            $ceiling = isset($rangeCeilings[$processId]) ? (int) $rangeCeilings[$processId] : 1000;
            if ($ceiling < 1) {
                $ceiling = 1000;
            }
            $obj = (object) [
                'id' => $processId,
                'price_1_to_1000' => isset($prices1To1000[$processId]) ? (float) $prices1To1000[$processId] : 0,
                'price_1001_plus' => isset($prices1001Plus[$processId]) ? (float) $prices1001Plus[$processId] : 0,
                'modified' => $now,
                'modified_by' => $userId,
            ];
            if ($hasRangeCeiling) {
                $obj->range_1_ceiling = $ceiling;
            }
            try {
                $db->updateObject('#__ordenproduccion_pliego_processes', $obj, ['id']);
            } catch (\Throwable $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * Get print prices per size for a paper type (tiro and retiro, qty 1–999999).
     *
     * @param   int  $paperTypeId  Paper type ID
     * @return  array  size_id => ['tiro' => price, 'retiro' => price]
     * @since   3.67.0
     */
    public function getPrintPricesForPaperType($paperTypeId)
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $paperTypeId = (int) $paperTypeId;
        if ($paperTypeId <= 0) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('size_id') . ', ' . $db->quoteName('tiro_retiro') . ', ' . $db->quoteName('price_per_sheet'))
            ->from($db->quoteName('#__ordenproduccion_pliego_print_prices'))
            ->where($db->quoteName('paper_type_id') . ' = ' . $paperTypeId)
            ->where($db->quoteName('qty_min') . ' = 1')
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $sid = (int) $row->size_id;
            if (!isset($out[$sid])) {
                $out[$sid] = ['tiro' => null, 'retiro' => null];
            }
            $key = ($row->tiro_retiro === 'retiro') ? 'retiro' : 'tiro';
            $out[$sid][$key] = (float) $row->price_per_sheet;
        }
        return $out;
    }

    /**
     * Save pliego print prices for one paper type: tiro and retiro price per size (qty 1–999999).
     *
     * @param   int    $paperTypeId   Paper type ID
     * @param   array  $pricesTiro    size_id => price_per_sheet (one side)
     * @param   array  $pricesRetiro  size_id => price_per_sheet (both sides)
     * @return  bool
     * @since   3.67.0
     */
    public function savePliegoPrices($paperTypeId, $pricesTiro, $pricesRetiro = [])
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $paperTypeId = (int) $paperTypeId;
        if ($paperTypeId <= 0) {
            $this->setError('Invalid paper type.');
            return false;
        }
        $user = Factory::getUser();
        $db = $this->getDatabase();
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;

        $allSizes = array_unique(array_merge(array_keys($pricesTiro), array_keys($pricesRetiro)));
        foreach ($allSizes as $sizeId) {
            $sizeId = (int) $sizeId;
            if ($sizeId <= 0) {
                continue;
            }
            foreach (['tiro' => $pricesTiro, 'retiro' => $pricesRetiro] as $tiroRetiro => $prices) {
                $price = isset($prices[$sizeId]) ? (float) $prices[$sizeId] : null;
                if ($price === null && $tiroRetiro === 'retiro') {
                    continue;
                }
                if ($price === null) {
                    $price = 0.0;
                }

                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__ordenproduccion_pliego_print_prices'))
                    ->where($db->quoteName('paper_type_id') . ' = ' . $paperTypeId)
                    ->where($db->quoteName('size_id') . ' = ' . $sizeId)
                    ->where($db->quoteName('tiro_retiro') . ' = ' . $db->quote($tiroRetiro))
                    ->where($db->quoteName('qty_min') . ' = 1');
                $db->setQuery($query);
                $id = (int) $db->loadResult();

                if ($id > 0) {
                    $obj = (object) [
                        'id' => $id,
                        'price_per_sheet' => $price,
                        'modified' => $now,
                        'modified_by' => $userId,
                    ];
                    $db->updateObject('#__ordenproduccion_pliego_print_prices', $obj, ['id']);
                } else {
                    $obj = (object) [
                        'paper_type_id' => $paperTypeId,
                        'size_id' => $sizeId,
                        'tiro_retiro' => $tiroRetiro,
                        'qty_min' => 1,
                        'qty_max' => 999999,
                        'price_per_sheet' => $price,
                        'state' => 1,
                        'created' => $now,
                        'created_by' => $userId,
                        'modified' => $now,
                        'modified_by' => $userId,
                    ];
                    $db->insertObject('#__ordenproduccion_pliego_print_prices', $obj);
                }
            }
        }
        return true;
    }

    /**
     * Get lamination prices per size for a lamination type (tiro and retiro, qty 1–999999).
     *
     * @param   int  $laminationTypeId  Lamination type ID
     * @return  array  size_id => ['tiro' => price, 'retiro' => price]
     * @since   3.67.0
     */
    public function getLaminationPricesForType($laminationTypeId)
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $laminationTypeId = (int) $laminationTypeId;
        if ($laminationTypeId <= 0) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('size_id') . ', ' . $db->quoteName('tiro_retiro') . ', ' . $db->quoteName('price_per_sheet'))
            ->from($db->quoteName('#__ordenproduccion_lamination_prices'))
            ->where($db->quoteName('lamination_type_id') . ' = ' . $laminationTypeId)
            ->where($db->quoteName('qty_min') . ' = 1')
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $sid = (int) $row->size_id;
            if (!isset($out[$sid])) {
                $out[$sid] = ['tiro' => null, 'retiro' => null];
            }
            $key = ($row->tiro_retiro === 'retiro') ? 'retiro' : 'tiro';
            $out[$sid][$key] = (float) $row->price_per_sheet;
        }
        return $out;
    }

    /**
     * Save lamination prices for one lamination type: tiro and retiro price per size (qty 1–999999).
     *
     * @param   int    $laminationTypeId  Lamination type ID
     * @param   array  $pricesTiro        size_id => price_per_sheet (one side)
     * @param   array  $pricesRetiro      size_id => price_per_sheet (both sides)
     * @return  bool
     * @since   3.67.0
     */
    public function saveLaminationPrices($laminationTypeId, $pricesTiro, $pricesRetiro = [])
    {
        if (!$this->tablesExist()) {
            $this->setError('Pliego tables not installed.');
            return false;
        }
        $laminationTypeId = (int) $laminationTypeId;
        if ($laminationTypeId <= 0) {
            $this->setError('Invalid lamination type.');
            return false;
        }
        $user = Factory::getUser();
        $db = $this->getDatabase();
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;

        $allSizes = array_unique(array_merge(array_keys($pricesTiro), array_keys($pricesRetiro)));
        foreach ($allSizes as $sizeId) {
            $sizeId = (int) $sizeId;
            if ($sizeId <= 0) {
                continue;
            }
            foreach (['tiro' => $pricesTiro, 'retiro' => $pricesRetiro] as $tiroRetiro => $prices) {
                $price = isset($prices[$sizeId]) ? (float) $prices[$sizeId] : null;
                if ($price === null && $tiroRetiro === 'retiro') {
                    continue;
                }
                if ($price === null) {
                    $price = 0.0;
                }

                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__ordenproduccion_lamination_prices'))
                    ->where($db->quoteName('lamination_type_id') . ' = ' . $laminationTypeId)
                    ->where($db->quoteName('size_id') . ' = ' . $sizeId)
                    ->where($db->quoteName('tiro_retiro') . ' = ' . $db->quote($tiroRetiro))
                    ->where($db->quoteName('qty_min') . ' = 1');
                $db->setQuery($query);
                $id = (int) $db->loadResult();

                if ($id > 0) {
                    $obj = (object) [
                        'id' => $id,
                        'price_per_sheet' => $price,
                        'modified' => $now,
                        'modified_by' => $userId,
                    ];
                    $db->updateObject('#__ordenproduccion_lamination_prices', $obj, ['id']);
                } else {
                    $obj = (object) [
                        'lamination_type_id' => $laminationTypeId,
                        'size_id' => $sizeId,
                        'tiro_retiro' => $tiroRetiro,
                        'qty_min' => 1,
                        'qty_max' => 999999,
                        'price_per_sheet' => $price,
                        'state' => 1,
                        'created' => $now,
                        'created_by' => $userId,
                        'modified' => $now,
                        'modified_by' => $userId,
                    ];
                    $db->insertObject('#__ordenproduccion_lamination_prices', $obj);
                }
            }
        }
        return true;
    }

    /**
     * Check if elementos table exists
     *
     * @return  bool
     * @since   3.71.0
     */
    public function elementosTableExists()
    {
        $db = $this->getDatabase();
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $needle = $prefix . 'ordenproduccion_elementos';
        foreach ($tables as $name) {
            if (strcasecmp($name, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all elementos (name, size, price products)
     *
     * @return  array
     * @since   3.71.0
     */
    public function getElementos()
    {
        if (!$this->elementosTableExists()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_elementos'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, id ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get one elemento by id
     *
     * @param   int  $id  Elemento id
     * @return  \stdClass|null
     * @since   3.71.0
     */
    public function getElemento($id)
    {
        $id = (int) $id;
        if ($id < 1 || !$this->elementosTableExists()) {
            return null;
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_elementos'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query);
        return $db->loadObject() ?: null;
    }

    /**
     * Get unit price for an element by quantity (uses range_1_ceiling, price_1_to_1000, price_1001_plus when present).
     *
     * @param   int  $elementoId  Elemento id
     * @param   int  $quantity    Quantity
     * @return  float  Unit price for that quantity
     * @since   3.73.0
     */
    public function getElementoUnitPrice($elementoId, $quantity)
    {
        $el = $this->getElemento((int) $elementoId);
        if (!$el) {
            return 0.0;
        }
        $qty = (int) $quantity;
        if ($qty < 1) {
            return 0.0;
        }
        $columns = $this->getDatabase()->getTableColumns('#__ordenproduccion_elementos', false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        if (isset($columns['range_1_ceiling']) && isset($columns['price_1_to_1000']) && isset($columns['price_1001_plus'])) {
            $ceiling = (int) ($el->range_1_ceiling ?? 1000);
            if ($ceiling < 1) {
                $ceiling = 1000;
            }
            $p1 = (float) ($el->price_1_to_1000 ?? $el->price ?? 0);
            $p2 = (float) ($el->price_1001_plus ?? 0);
            return $qty <= $ceiling ? $p1 : $p2;
        }
        return (float) ($el->price ?? 0);
    }

    /**
     * Save elemento (create or update)
     *
     * @param   array  $data  name, size, price, range_1_ceiling, price_1_to_1000, price_1001_plus, id (optional)
     * @return  int|false  Id on success
     * @since   3.71.0
     */
    public function saveElemento(array $data)
    {
        if (!$this->elementosTableExists()) {
            $this->setError('Elementos table not installed.');
            return false;
        }
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $userId = (int) $user->id;
        $now = Factory::getDate()->toSql();
        $id = (int) ($data['id'] ?? 0);

        $ceiling = (int) ($data['range_1_ceiling'] ?? 1000);
        if ($ceiling < 1) {
            $ceiling = 1000;
        }

        $obj = (object) [
            'name' => trim($data['name'] ?? ''),
            'size' => trim($data['size'] ?? ''),
            'price' => (float) ($data['price'] ?? 0),
            'ordering' => (int) ($data['ordering'] ?? 0),
            'state' => 1,
            'modified' => $now,
            'modified_by' => $userId,
        ];

        $columns = $db->getTableColumns('#__ordenproduccion_elementos', false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        if (isset($columns['range_1_ceiling'])) {
            $obj->range_1_ceiling = $ceiling;
            $p1 = $data['price_1_to_1000'] ?? null;
            $p2 = $data['price_1001_plus'] ?? null;
            if ($id > 0) {
                $existing = $this->getElemento($id);
                if ($existing) {
                    if ($p1 === null || $p1 === '') {
                        $p1 = (float) ($existing->price_1_to_1000 ?? $existing->price ?? 0);
                    } else {
                        $p1 = (float) $p1;
                    }
                    if ($p2 === null || $p2 === '') {
                        $p2 = (float) ($existing->price_1001_plus ?? 0);
                    } else {
                        $p2 = (float) $p2;
                    }
                } else {
                    $p1 = $p1 !== null && $p1 !== '' ? (float) $p1 : (float) ($data['price'] ?? 0);
                    $p2 = $p2 !== null && $p2 !== '' ? (float) $p2 : 0.0;
                }
            } else {
                $p1 = $p1 !== null && $p1 !== '' ? (float) $p1 : (float) ($data['price'] ?? 0);
                $p2 = $p2 !== null && $p2 !== '' ? (float) $p2 : 0.0;
            }
            $obj->price_1_to_1000 = $p1;
            $obj->price_1001_plus = $p2;
        }

        if ($id > 0) {
            $obj->id = $id;
            try {
                $db->updateObject('#__ordenproduccion_elementos', $obj, 'id');
                return $id;
            } catch (\Exception $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }

        $obj->created = $now;
        $obj->created_by = $userId;
        try {
            $db->insertObject('#__ordenproduccion_elementos', $obj, 'id');
            return (int) $obj->id;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Delete elemento (soft delete: set state = 0)
     *
     * @param   int  $id  Elemento id
     * @return  bool
     * @since   3.71.0
     */
    public function deleteElemento($id)
    {
        $id = (int) $id;
        if ($id < 1 || !$this->elementosTableExists()) {
            return false;
        }
        $db = $this->getDatabase();
        $obj = (object) ['id' => $id, 'state' => 0];
        try {
            $db->updateObject('#__ordenproduccion_elementos', $obj, 'id');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
