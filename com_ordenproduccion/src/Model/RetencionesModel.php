<?php
/**
 * Retenciones list model (Constancia de Exención de IVA).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Model
 * @since       3.119.257
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * List model for #__ordenproduccion_retenciones.
 *
 * @since  3.119.257
 */
class RetencionesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  Config
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'autorizacion', 'serie', 'numero', 'fact_autorizacion',
                'fact_serie', 'fact_numero', 'fact_iva_exento', 'fecha_emision', 'created',
            ];
        }
        parent::__construct($config);
    }

    /**
     * Whether the retenciones table exists.
     *
     * @return  bool
     *
     * @since   3.119.257
     */
    public function isTableAvailable(): bool
    {
        try {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $name   = strtolower($prefix . 'ordenproduccion_retenciones');
            foreach ($tables as $t) {
                if (strtolower((string) $t) === $name) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Build list query.
     *
     * @return  \Joomla\Database\QueryInterface
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('r.*')
            ->from($db->quoteName('#__ordenproduccion_retenciones', 'r'))
            ->where($db->quoteName('r.state') . ' = 1');

        $search = trim((string) $this->getState('filter.search', ''));
        if ($search !== '') {
            $like = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where(
                '('
                . $db->quoteName('r.autorizacion') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.serie') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.numero') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.fact_autorizacion') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.fact_serie') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.fact_numero') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.nombre_receptor') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('r.nit_receptor') . ' LIKE ' . $like
                . ')'
            );
        }

        $fechaFrom = trim((string) $this->getState('filter.fecha_from', ''));
        $fechaTo   = trim((string) $this->getState('filter.fecha_to', ''));
        if ($fechaFrom !== '') {
            $query->where('(COALESCE(r.fecha_emision, r.created) >= ' . $db->quote($fechaFrom . ' 00:00:00') . ')');
        }
        if ($fechaTo !== '') {
            $query->where('(COALESCE(r.fecha_emision, r.created) <= ' . $db->quote($fechaTo . ' 23:59:59') . ')');
        }

        $orderCol = $this->getState('list.ordering', 'r.fecha_emision');
        $orderDir = $this->getState('list.direction', 'DESC');
        if ($orderCol === 'r.fecha_emision' || $orderCol === 'fecha_emision') {
            $query->order('COALESCE(r.fecha_emision, r.created) ' . $db->escape($orderDir));
        } else {
            $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));
        }

        return $query;
    }

    /**
     * Populate state.
     *
     * @param   string  $ordering   Default ordering
     * @param   string  $direction  Default direction
     *
     * @return  void
     */
    protected function populateState($ordering = 'fecha_emision', $direction = 'desc')
    {
        $app = Factory::getApplication();

        $this->setState('filter.search', $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'));
        $this->setState('filter.fecha_from', $app->getUserStateFromRequest($this->context . '.filter.fecha_from', 'filter_fecha_from', '', 'string'));
        $this->setState('filter.fecha_to', $app->getUserStateFromRequest($this->context . '.filter.fecha_to', 'filter_fecha_to', '', 'string'));

        parent::populateState($ordering, $direction);

        $task = strtolower(str_replace('.', '', (string) $app->input->get('task', '')));
        if ($task === 'administracionexportretencionesexcel' || $app->input->getInt('export_all', 0) === 1) {
            $this->setState('list.limit', 1000000);
            $this->setState('list.start', 0);

            return;
        }

        $limit = (int) $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', 20, 'uint');
        if ($limit <= 0) {
            $limit = 20;
        }
        $this->setState('list.limit', $limit);
        $limitstart = (int) $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', max(0, $limitstart));
    }
}
