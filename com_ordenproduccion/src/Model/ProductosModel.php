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
}
