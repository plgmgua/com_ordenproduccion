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

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Analyze and apply client-name standardization across related tables.
 *
 * Documents stay linked via orden_id / foreign keys; denormalized client_name fields are updated in place.
 */
class ClientNameStandardizationHelper
{
    /** @var DatabaseInterface */
    private $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Find distinct client-name variants in ordenes matching a partial search and count related records.
     *
     * @return array{
     *   search: string,
     *   variants: array<int, array{
     *     client_name: string,
     *     display_name: string,
     *     nit_values: array<int, string>,
     *     ordenes: int,
     *     invoices: int,
     *     fel_receptor: int,
     *     payment_proofs: int,
     *     quotations: int,
     *     opening_balance: int,
     *     client_balance: int,
     *     pliego_quotes: int,
     *     sample_ordenes: array<int, string>
     *   }>,
     *   totals: array<string, int>
     * }
     */
    public function analyze(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            throw new \InvalidArgumentException('Search text is required.');
        }
        if (mb_strlen($search) < 2) {
            throw new \InvalidArgumentException('Enter at least 2 characters to search.');
        }

        $clientCol = $this->resolveOrdenesClientColumn();
        $like = '%' . $this->db->escape($search, true) . '%';

        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName($clientCol) . ' AS client_name')
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName($clientCol) . ' LIKE ' . $this->db->quote($like))
            ->order($this->db->quoteName($clientCol) . ' ASC');
        $this->db->setQuery($query);
        $rows = $this->db->loadColumn() ?: [];

        $variants = [];
        $totals = [
            'ordenes'          => 0,
            'invoices'         => 0,
            'fel_receptor'     => 0,
            'payment_proofs'   => 0,
            'quotations'       => 0,
            'opening_balance'  => 0,
            'client_balance'   => 0,
            'pliego_quotes'    => 0,
        ];

        foreach ($rows as $rawName) {
            $name = (string) $rawName;
            if ($name === '') {
                continue;
            }
            $impact = $this->countImpact($name, $clientCol);
            $variants[] = array_merge(
                [
                    'client_name'  => $name,
                    'display_name' => $this->displayClientName($name),
                    'nit_values'   => $this->distinctNitsForClient($name, $clientCol),
                ],
                $impact
            );
            foreach ($totals as $key => $val) {
                $totals[$key] += (int) ($impact[$key] ?? 0);
            }
        }

        return [
            'search'   => $search,
            'variants' => $variants,
            'totals'   => $totals,
        ];
    }

    /**
     * Rename selected source client names to one canonical name across denormalized fields.
     *
     * @param   array<int, array{client_name: string, nit?: string|null}>  $sources
     * @return  array<string, int>
     */
    public function apply(array $sources, string $targetClientName, ?string $targetNit = null): array
    {
        $targetClientName = trim($targetClientName);
        if ($targetClientName === '') {
            throw new \InvalidArgumentException('Target client name is required.');
        }

        $stats = [
            'ordenes'         => 0,
            'invoices'        => 0,
            'fel_receptor'    => 0,
            'quotations'      => 0,
            'opening_balance' => 0,
            'client_balance'  => 0,
            'pliego_quotes'   => 0,
            'sources_merged'  => 0,
        ];

        $clientCol = $this->resolveOrdenesClientColumn();
        $hasNombreDelCliente = $this->columnExists('#__ordenproduccion_ordenes', 'nombre_del_cliente');
        $hasClientName       = $this->columnExists('#__ordenproduccion_ordenes', 'client_name');
        $hasNit              = $this->columnExists('#__ordenproduccion_ordenes', 'nit');
        $targetNitVal        = $targetNit !== null && trim($targetNit) !== '' ? trim($targetNit) : '';

        foreach ($sources as $src) {
            $srcName = trim((string) ($src['client_name'] ?? ''));
            if ($srcName === '' || $srcName === $targetClientName) {
                continue;
            }

            $stats['sources_merged']++;

            $stats['ordenes'] += $this->updateOrdenesClientName(
                $srcName,
                $targetClientName,
                $targetNitVal,
                $clientCol,
                $hasClientName,
                $hasNombreDelCliente,
                $hasNit,
                isset($src['nit']) ? trim((string) $src['nit']) : null
            );

            $stats['invoices'] += $this->updateTableByClientName(
                '#__ordenproduccion_invoices',
                'client_name',
                $srcName,
                $targetClientName
            );

            if ($this->columnExists('#__ordenproduccion_invoices', 'fel_receptor_nombre')) {
                $stats['fel_receptor'] += $this->updateTableByClientName(
                    '#__ordenproduccion_invoices',
                    'fel_receptor_nombre',
                    $srcName,
                    $targetClientName
                );
            }

            if ($this->tableExists('#__ordenproduccion_quotations')) {
                $stats['quotations'] += $this->updateTableByClientName(
                    '#__ordenproduccion_quotations',
                    'client_name',
                    $srcName,
                    $targetClientName
                );
            }

            if ($this->tableExists('#__ordenproduccion_cotizaciones_pliego')) {
                $stats['pliego_quotes'] += $this->updateTableByClientName(
                    '#__ordenproduccion_cotizaciones_pliego',
                    'client_name',
                    $srcName,
                    $targetClientName
                );
            }

            // client_balance: do not UPDATE here (unique idx_client_nit). Model deletes stale rows and refreshClientBalances().
        }

        return $stats;
    }

    /**
     * @return array<string, int|array<int, string>>
     */
    private function countImpact(string $clientName, string $ordenClientCol): array
    {
        $ordenes = $this->countOrdenesByExactName($clientName, $ordenClientCol);

        return [
            'ordenes'         => $ordenes,
            'invoices'        => $this->countTableByExactName('#__ordenproduccion_invoices', 'client_name', $clientName),
            'fel_receptor'    => $this->columnExists('#__ordenproduccion_invoices', 'fel_receptor_nombre')
                ? $this->countTableByExactName('#__ordenproduccion_invoices', 'fel_receptor_nombre', $clientName)
                : 0,
            'payment_proofs'  => $this->countPaymentProofsForClient($clientName, $ordenClientCol),
            'quotations'      => $this->tableExists('#__ordenproduccion_quotations')
                ? $this->countTableByExactName('#__ordenproduccion_quotations', 'client_name', $clientName)
                : 0,
            'opening_balance' => $this->tableExists('#__ordenproduccion_client_opening_balance')
                ? $this->countTableByExactName('#__ordenproduccion_client_opening_balance', 'client_name', $clientName)
                : 0,
            'client_balance'  => $this->tableExists('#__ordenproduccion_client_balance')
                ? $this->countTableByExactName('#__ordenproduccion_client_balance', 'client_name', $clientName)
                : 0,
            'pliego_quotes'   => $this->tableExists('#__ordenproduccion_cotizaciones_pliego')
                ? $this->countTableByExactName('#__ordenproduccion_cotizaciones_pliego', 'client_name', $clientName)
                : 0,
            'sample_ordenes'  => $this->sampleOrdenNumbers($clientName, $ordenClientCol),
        ];
    }

    private function countOrdenesByExactName(string $clientName, string $clientCol): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName($clientCol) . ' = ' . $this->db->quote($clientName));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @return array<int, string>
     */
    private function sampleOrdenNumbers(string $clientName, string $clientCol, int $limit = 5): array
    {
        $numCol = $this->columnExists('#__ordenproduccion_ordenes', 'orden_de_trabajo') ? 'orden_de_trabajo' : 'order_number';
        if (!$this->columnExists('#__ordenproduccion_ordenes', $numCol)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName($numCol))
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName($clientCol) . ' = ' . $this->db->quote($clientName))
            ->order($this->db->quoteName('id') . ' DESC');
        $this->db->setQuery($query, 0, $limit);

        return array_map('strval', $this->db->loadColumn() ?: []);
    }

    /**
     * @return array<int, string>
     */
    private function distinctNitsForClient(string $clientName, string $clientCol): array
    {
        if (!$this->columnExists('#__ordenproduccion_ordenes', 'nit')) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('nit'))
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName($clientCol) . ' = ' . $this->db->quote($clientName))
            ->where('(' . $this->db->quoteName('nit') . ' IS NOT NULL AND ' . $this->db->quoteName('nit') . ' != ' . $this->db->quote('') . ')');
        $this->db->setQuery($query);

        return array_values(array_filter(array_map('strval', $this->db->loadColumn() ?: [])));
    }

    private function countPaymentProofsForClient(string $clientName, string $ordenClientCol): int
    {
        if (!$this->tableExists('#__ordenproduccion_payment_proofs')) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $this->db->quoteName('pp.id') . ')')
            ->from($this->db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->innerJoin(
                $this->db->quoteName('#__ordenproduccion_ordenes', 'o')
                . ' ON ' . $this->db->quoteName('o.id') . ' = ' . $this->db->quoteName('pp.order_id')
            )
            ->where($this->db->quoteName('o.' . $ordenClientCol) . ' = ' . $this->db->quote($clientName));

        if ($this->columnExists('#__ordenproduccion_payment_proofs', 'state')) {
            $query->where($this->db->quoteName('pp.state') . ' = 1');
        }

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    private function countTableByExactName(string $table, string $column, string $value): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName($column) . ' = ' . $this->db->quote($value));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    private function updateOrdenesClientName(
        string $srcName,
        string $targetName,
        string $targetNit,
        string $primaryCol,
        bool $hasClientName,
        bool $hasNombreDelCliente,
        bool $hasNit,
        ?string $srcNit
    ): int {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_ordenes'));

        if ($hasClientName) {
            $query->set($this->db->quoteName('client_name') . ' = ' . $this->db->quote($targetName));
        }
        if ($hasNombreDelCliente) {
            $query->set($this->db->quoteName('nombre_del_cliente') . ' = ' . $this->db->quote($targetName));
        }
        if (!$hasClientName && !$hasNombreDelCliente) {
            $query->set($this->db->quoteName($primaryCol) . ' = ' . $this->db->quote($targetName));
        }
        if ($hasNit && $targetNit !== '') {
            $query->set($this->db->quoteName('nit') . ' = ' . $this->db->quote($targetNit));
        }

        $query->where($this->db->quoteName($primaryCol) . ' = ' . $this->db->quote($srcName));
        if ($this->columnExists('#__ordenproduccion_ordenes', 'state')) {
            $query->where($this->db->quoteName('state') . ' = 1');
        }
        if ($srcNit !== null && $srcNit !== '' && $hasNit) {
            $query->where($this->db->quoteName('nit') . ' = ' . $this->db->quote($srcNit));
        }

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    private function updateTableByClientName(string $table, string $column, string $srcName, string $targetName): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName($table))
            ->set($this->db->quoteName($column) . ' = ' . $this->db->quote($targetName))
            ->where($this->db->quoteName($column) . ' = ' . $this->db->quote($srcName));

        if ($this->columnExists($table, 'state')) {
            $query->where($this->db->quoteName('state') . ' = 1');
        }

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    private function resolveOrdenesClientColumn(): string
    {
        if ($this->columnExists('#__ordenproduccion_ordenes', 'client_name')) {
            return 'client_name';
        }
        if ($this->columnExists('#__ordenproduccion_ordenes', 'nombre_del_cliente')) {
            return 'nombre_del_cliente';
        }

        return 'client_name';
    }

    private function tableExists(string $tableName): bool
    {
        $t = $this->db->replacePrefix($tableName);
        foreach ($this->db->getTableList() as $name) {
            if (strcasecmp($name, $t) === 0) {
                return true;
            }
        }

        return false;
    }

    private function columnExists(string $tableName, string $column): bool
    {
        if (!$this->tableExists($tableName)) {
            return false;
        }
        $cols = $this->db->getTableColumns($tableName, false);

        return isset($cols[$column]);
    }

    private function displayClientName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === $name) {
            return $name;
        }

        return $name . ' → "' . $trimmed . '"';
    }
}
