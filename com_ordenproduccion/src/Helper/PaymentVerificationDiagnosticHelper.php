<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Grimpsa\Component\Ordenproduccion\Site\Service\Mt940PaymentMatchService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

/**
 * Verificar pago — MT-940 matching + payment_proof approval workflow diagnostic.
 *
 * @since  3.119.229
 */
class PaymentVerificationDiagnosticHelper
{
    private int $failures = 0;
    private int $warnings = 0;

    /** @var array<int, array<string, mixed>> */
    private array $sections = [];

    /**
     * @return array<string, mixed>
     *
     * @since  3.119.229
     */
    public function run(): array
    {
        $this->failures = 0;
        $this->warnings = 0;
        $this->sections = [];

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $wfOn   = (int) $params->get('approval_workflow_payment_proof', 0) === 1;
        $mt940On = (int) $params->get('payment_proof_mt940_verification', 1) === 1;
        $enabled = Mt940PaymentMatchLogHelper::isMt940VerificationEnabled();

        $this->addDeploymentSection();
        $this->addComponentOptionsSection($wfOn, $mt940On, $enabled);
        $this->addDatabaseSection();
        $this->addApprovalWorkflowSection();
        $this->addCronSection();
        $this->addQueueStatsSection();
        $this->addTelegramSection();

        return $this->buildReport([
            'approval_workflow_payment_proof'    => $wfOn ? '1' : '0',
            'payment_proof_mt940_verification'   => $mt940On ? '1' : '0',
            'mt940_verification_active'          => $enabled ? '1' : '0',
        ]);
    }

    private function addDeploymentSection(): void
    {
        $root = defined('JPATH_ROOT') ? JPATH_ROOT : (defined('JPATH_BASE') ? JPATH_BASE : '');
        $checks = [];

        $versionFile = $root . '/components/com_ordenproduccion/VERSION';
        $version     = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
        $need        = '3.119.228';
        if ($version === '') {
            $checks[] = $this->check('Component VERSION file', 'warn', 'VERSION file not found on disk');
        } elseif (version_compare(preg_replace('/-.*$/', '', $version), preg_replace('/-.*$/', '', $need), '<')) {
            $checks[] = $this->check('Component version', 'fail', $version . ' — need ' . $need . '-STABLE or newer for Verificar pago');
        } else {
            $checks[] = $this->check('Component version', 'pass', $version);
        }

        $paths = [
            'Mt940PaymentMatchService.php' => $root . '/components/com_ordenproduccion/src/Service/Mt940PaymentMatchService.php',
            'PaymentverificationController.php' => $root . '/components/com_ordenproduccion/src/Controller/PaymentverificationController.php',
            'Mt940PaymentMatchLogHelper.php' => $root . '/components/com_ordenproduccion/src/Helper/Mt940PaymentMatchLogHelper.php',
            'PaymentVerificationDiagnosticHelper.php' => $root . '/components/com_ordenproduccion/src/Helper/PaymentVerificationDiagnosticHelper.php',
        ];
        foreach ($paths as $label => $path) {
            $exists = $path !== '' && is_file($path);
            $checks[] = $this->check($label, $exists ? 'pass' : 'fail', $exists ? 'Deployed' : 'Missing — ' . $path);
        }

        $configXml = $root . '/administrator/components/com_ordenproduccion/config.xml';
        if (!is_file($configXml)) {
            $configXml = $root . '/components/com_ordenproduccion/admin/config.xml';
        }
        if (is_file($configXml)) {
            $src = (string) file_get_contents($configXml);
            $hasOpts = str_contains($src, 'approval_workflow_payment_proof')
                && str_contains($src, 'payment_proof_mt940_verification');
            $checks[] = $this->check(
                'Joomla Options UI (admin/config.xml)',
                $hasOpts ? 'pass' : 'fail',
                $hasOpts
                    ? 'Payment verification toggles visible under Components → Options → Configuration (3.119.229+)'
                    : 'Options missing from config.xml — upgrade to 3.119.229-STABLE'
            );
        } else {
            $checks[] = $this->check('Joomla Options UI (admin/config.xml)', 'warn', 'config.xml not found');
        }

        $dispatcher = $root . '/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php';
        if (is_file($dispatcher)) {
            $src = (string) file_get_contents($dispatcher);
            $hasCron = str_contains($src, 'paymentverification') && str_contains($src, 'runscheduledmatch');
            $checks[] = $this->check(
                'Dispatcher cron exception',
                $hasCron ? 'pass' : 'fail',
                $hasCron
                    ? 'paymentverification.runScheduledMatch allowed without login'
                    : 'Missing — cron will redirect to login (403/HTML)'
            );
        }

        $this->addSection('Deployment (Verificar pago 3.119.228+)', $checks);
    }

    /**
     * @param bool $wfOn
     * @param bool $mt940On
     * @param bool $enabled
     */
    private function addComponentOptionsSection(bool $wfOn, bool $mt940On, bool $enabled): void
    {
        $checks = [
            $this->check(
                'approval_workflow_payment_proof',
                $wfOn ? 'pass' : 'fail',
                $wfOn
                    ? 'Yes — payment verification enabled'
                    : 'No — set Components → Orden Producción → Options → Configuration → Flujo de aprobación para comprobantes de pago = Sí'
            ),
            $this->check(
                'payment_proof_mt940_verification',
                !$wfOn ? 'info' : ($mt940On ? 'pass' : 'warn'),
                !$wfOn
                    ? 'N/A (master switch off)'
                    : ($mt940On
                        ? 'Yes — cron MT-940 matching active'
                        : 'No — legacy flow (approval on save). Set to Sí for Verificar pago.')
            ),
            $this->check(
                'MT-940 verification active',
                $enabled ? 'pass' : ($wfOn && !$mt940On ? 'warn' : 'fail'),
                $enabled
                    ? 'Both switches on — cron will create approvals after bank match'
                    : 'Inactive — matching cron returns SKIPPED'
            ),
        ];

        $this->addSection('Component options (stored in #__extensions.params)', $checks, [
            'options_path' => 'Joomla Administrator → Components → Orden de Producción → Options → tab Configuration',
            'workflow_path' => 'Site → Administración → Ajustes → Flujos de aprobaciones → Comprobante de pago (must be Publicado)',
        ]);
    }

    private function addDatabaseSection(): void
    {
        $checks = [];
        $db     = $this->db();

        $lineCols = [];
        try {
            $lineCols = array_change_key_case($db->getTableColumns('#__ordenproduccion_payment_proof_lines', false) ?: [], CASE_LOWER);
        } catch (\Throwable $e) {
            $checks[] = $this->check('payment_proof_lines table', 'fail', $e->getMessage());
        }

        foreach (['mt940_transaction_id', 'mt940_match_status', 'mt940_match_checked_at'] as $col) {
            $has = isset($lineCols[$col]);
            $checks[] = $this->check(
                'Column payment_proof_lines.' . $col,
                $has ? 'pass' : 'fail',
                $has ? 'Present' : 'Missing — run SQL 3.119.228 in phpMyAdmin'
            );
        }

        $logTable = Mt940PaymentMatchLogHelper::tableAvailable();
        $checks[] = $this->check(
            'Table payment_mt940_match_log',
            $logTable ? 'pass' : 'fail',
            $logTable ? 'joomla_ordenproduccion_payment_mt940_match_log exists' : 'Missing — run SQL 3.119.228'
        );

        $mt940Tx = false;
        try {
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'ordenproduccion_mt940_transactions'))->loadColumn();
            $mt940Tx = !empty($tables);
        } catch (\Throwable $e) {
        }
        $checks[] = $this->check(
            'Table mt940_transactions',
            $mt940Tx ? 'pass' : 'fail',
            $mt940Tx ? 'Bank movements available for matching' : 'Missing — import MT-940 first'
        );

        $wfSvc = new ApprovalWorkflowService();
        $checks[] = $this->check(
            'Approval workflow schema',
            $wfSvc->hasSchema() ? 'pass' : 'fail',
            $wfSvc->hasSchema() ? 'approval_requests tables present' : 'Missing — apply approval workflow SQL (3.102.0)'
        );

        $this->addSection('Database schema', $checks);
    }

    private function addApprovalWorkflowSection(): void
    {
        $checks = [];
        $db     = $this->db();
        $wfSvc  = new ApprovalWorkflowService();

        if (!$wfSvc->hasSchema()) {
            $this->addSection('Approval workflow — Comprobante de pago', [
                $this->check('Schema', 'fail', 'Cannot inspect workflow without approval tables'),
            ]);

            return;
        }

        $wf = null;
        try {
            $q = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_approval_workflows'))
                ->where($db->quoteName('entity_type') . ' = ' . $db->quote(ApprovalWorkflowService::ENTITY_PAYMENT_PROOF))
                ->setLimit(1);
            $db->setQuery($q);
            $wf = $db->loadObject();
        } catch (\Throwable $e) {
            $checks[] = $this->check('Load workflow', 'fail', $e->getMessage());
        }

        if ($wf === null) {
            $checks[] = $this->check('Workflow row payment_proof', 'fail', 'No workflow — seed SQL 3.102.0 or create in Flujos de aprobaciones');
        } else {
            $published = (int) ($wf->published ?? 0) === 1;
            $checks[] = $this->check(
                'Workflow published',
                $published ? 'pass' : 'fail',
                $published
                    ? 'Comprobante de pago is active for new requests'
                    : 'Publicado = No — cron matches but createRequest returns 0. Edit workflow and check Publicado.'
            );
            $checks[] = $this->check('Workflow name', 'info', (string) ($wf->name ?? ''));
            $checks[] = $this->check('Workflow ID', 'info', (string) (int) ($wf->id ?? 0));

            $stepCount = 0;
            $approverSummary = '';
            try {
                $q = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                    ->where($db->quoteName('workflow_id') . ' = ' . (int) ($wf->id ?? 0))
                    ->order($db->quoteName('step_number') . ' ASC');
                $db->setQuery($q);
                $steps = $db->loadObjectList() ?: [];
                $stepCount = count($steps);
                if ($steps !== []) {
                    $parts = [];
                    foreach ($steps as $st) {
                        $parts[] = 'step ' . (int) ($st->step_number ?? 0) . ': '
                            . (string) ($st->approver_type ?? '') . ' = '
                            . (string) ($st->approver_value ?? '');
                    }
                    $approverSummary = implode('; ', $parts);
                }
            } catch (\Throwable $e) {
                $approverSummary = $e->getMessage();
            }

            $checks[] = $this->check(
                'Workflow steps',
                $stepCount > 0 ? 'pass' : 'fail',
                $stepCount > 0 ? $stepCount . ' step(s) — ' . $approverSummary : 'No steps — add approver in Flujos de aprobaciones'
            );
        }

        $pendingCount = 0;
        try {
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_approval_requests'))
                ->where($db->quoteName('entity_type') . ' = ' . $db->quote(ApprovalWorkflowService::ENTITY_PAYMENT_PROOF))
                ->where($db->quoteName('status') . ' = ' . $db->quote('pending'));
            $db->setQuery($q);
            $pendingCount = (int) $db->loadResult();
        } catch (\Throwable $e) {
        }
        $checks[] = $this->check(
            'Pending payment_proof approvals',
            'info',
            (string) $pendingCount . ' open request(s) in Administración → Aprobaciones'
        );

        $this->addSection('Approval workflow — Comprobante de pago', $checks, [
            'edit_url_hint' => 'index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones',
            'approve_on_final' => 'When MT-940 mode is on, approving in Aprobaciones calls setVerificado automatically',
        ]);
    }

    private function addCronSection(): void
    {
        $cronKey      = '';
        $importUrl    = '';
        $matchUrl     = '';
        $matchCrontab = '';

        try {
            BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_ordenproduccion/src/Model');
            /** @var AdministracionModel|null $model */
            $model = BaseDatabaseModel::getInstance('Administracion', 'Grimpsa\\Component\\Ordenproduccion\\Site\\Model');
            if ($model) {
                $cronKey      = $model->getMt940CronKey();
                $importUrl    = $model->getMt940CronEndpointUrl($cronKey !== '' ? $cronKey : 'YOUR_SECRET');
                $matchUrl     = $model->getPaymentMt940MatchCronEndpointUrl($cronKey !== '' ? $cronKey : 'YOUR_SECRET');
                $matchCrontab = $model->getPaymentMt940MatchCronCrontabLine();
            }
        } catch (\Throwable $e) {
        }

        $checks = [
            $this->check(
                'Cron secret (mt940_cron_key)',
                $cronKey !== '' ? 'pass' : 'fail',
                $cronKey !== '' ? 'Set in Ajustes → MT940 → Importar datos' : 'Save cron key first — used by import and payment match'
            ),
            $this->check('MT-940 import cron URL', $importUrl !== '' ? 'info' : 'warn', $importUrl !== '' ? $importUrl : 'Unavailable'),
            $this->check('Payment match cron URL', $matchUrl !== '' ? 'info' : 'warn', $matchUrl !== '' ? $matchUrl : 'Unavailable'),
            $this->check(
                'Payment match crontab',
                'info',
                $matchCrontab !== '' ? $matchCrontab : '*/30 * * * * wget -q -O - \'MATCH_URL\''
            ),
            $this->check(
                'Manual test (match)',
                'info',
                'wget -q -O - "' . ($matchUrl !== '' ? $matchUrl : 'MATCH_URL') . '" — expect OK scanned=… or SKIPPED if options off'
            ),
        ];

        $this->addSection('Cron — payment MT-940 matching', $checks);
    }

    private function addQueueStatsSection(): void
    {
        $checks = [];
        $db     = $this->db();

        $ingresado = 0;
        try {
            $ppCols = array_change_key_case($db->getTableColumns('#__ordenproduccion_payment_proofs', false) ?: [], CASE_LOWER);
            if (isset($ppCols['verification_status'])) {
                $q = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where(
                        '(' . $db->quoteName('verification_status') . ' IS NULL'
                        . ' OR ' . $db->quoteName('verification_status') . ' = ' . $db->quote('')
                        . ' OR LOWER(TRIM(' . $db->quoteName('verification_status') . ')) = ' . $db->quote('ingresado') . ')'
                    );
                $db->setQuery($q);
                $ingresado = (int) $db->loadResult();
            }
        } catch (\Throwable $e) {
        }
        $checks[] = $this->check('Ingresado payment proofs', 'info', (string) $ingresado . ' waiting for MT-940 match / approval');

        $bankLines = 0;
        try {
            $types = array_map(fn ($t) => $db->quote($t), Mt940PaymentMatchService::BANK_PAYMENT_TYPES);
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_payment_proof_lines', 'l'))
                ->innerJoin(
                    $db->quoteName('#__ordenproduccion_payment_proofs', 'pp'),
                    'pp.' . $db->quoteName('id') . ' = l.' . $db->quoteName('payment_proof_id') . ' AND pp.' . $db->quoteName('state') . ' = 1'
                )
                ->where('l.' . $db->quoteName('payment_type') . ' IN (' . implode(',', $types) . ')')
                ->where('l.' . $db->quoteName('bank_account_id') . ' > 0');
            if (isset($ppCols['verification_status'])) {
                $q->where(
                    '(' . 'pp.' . $db->quoteName('verification_status') . ' IS NULL'
                    . ' OR LOWER(TRIM(pp.' . $db->quoteName('verification_status') . ')) = ' . $db->quote('ingresado') . ')'
                );
            }
            $db->setQuery($q);
            $bankLines = (int) $db->loadResult();
        } catch (\Throwable $e) {
        }
        $checks[] = $this->check(
            'Eligible bank lines (transferencia/depósito)',
            $bankLines > 0 ? 'info' : 'warn',
            (string) $bankLines . ' line(s) with bank_account_id on ingresado proofs'
        );

        if (Mt940PaymentMatchLogHelper::tableAvailable()) {
            $recent = [];
            try {
                $q = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_payment_mt940_match_log'))
                    ->order($db->quoteName('created') . ' DESC')
                    ->setLimit(8);
                $db->setQuery($q);
                $recent = $db->loadObjectList() ?: [];
            } catch (\Throwable $e) {
            }

            if ($recent === []) {
                $checks[] = $this->check('Recent match log', 'warn', 'No entries — cron may not have run yet');
            } else {
                foreach ($recent as $row) {
                    $checks[] = $this->check(
                        'Log #' . (int) ($row->id ?? 0) . ' ' . (string) ($row->status ?? ''),
                        ((string) ($row->status ?? '') === 'matched') ? 'pass' : (((string) ($row->status ?? '') === 'ambiguous') ? 'warn' : 'info'),
                        \sprintf(
                            'proof %d line %s — %s (%s)',
                            (int) ($row->payment_proof_id ?? 0),
                            $row->payment_proof_line_id !== null ? (string) (int) $row->payment_proof_line_id : '—',
                            \mb_substr((string) ($row->message ?? ''), 0, 120),
                            (string) ($row->created ?? '')
                        )
                    );
                }
            }
        }

        $this->addSection('Matching queue & recent log', $checks);
    }

    private function addTelegramSection(): void
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $tgOn   = (int) $params->get('telegram_enabled', 0) === 1;
        $token  = trim((string) $params->get('telegram_bot_token', ''));

        $checks = [
            $this->check(
                'Telegram enabled',
                $tgOn ? 'pass' : 'warn',
                $tgOn ? 'Yes' : 'No — approvers will not receive GrimpsaBot assign messages'
            ),
            $this->check(
                'Bot token',
                $token !== '' ? 'pass' : 'fail',
                $token !== '' ? 'Configured' : 'Missing in Options → Telegram'
            ),
            $this->check(
                'Approval assign template',
                'info',
                'Edit in Flujos de aprobaciones → Comprobante de pago → Mensaje para aprobadores (email_body_assign / Telegram)'
            ),
            $this->check(
                'Approver Telegram link',
                'info',
                'Each approver needs chat_id linked (GrimpsaBot) — same as other approval workflows'
            ),
        ];

        $this->addSection('Telegram (approver notification)', $checks);
    }

    /**
     * @param array<string, string> $config
     *
     * @return array<string, mixed>
     */
    private function buildReport(array $config): array
    {
        $status = $this->failures > 0 ? 'fail' : ($this->warnings > 0 ? 'warn' : 'ok');

        return [
            'meta' => [
                'status'   => $status,
                'failures' => $this->failures,
                'warnings' => $this->warnings,
                'time'     => Factory::getDate()->format('Y-m-d H:i:s T'),
            ],
            'config' => $config,
            'sections' => $this->sections,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<string, mixed>             $details
     */
    private function addSection(string $title, array $checks, array $details = []): void
    {
        $this->sections[] = [
            'title'   => $title,
            'checks'  => $checks,
            'details' => $details,
        ];
    }

    /**
     * @return array{status: string, label: string, detail: string}
     */
    private function check(string $label, string $status, string $detail): array
    {
        if ($status === 'fail') {
            $this->failures++;
        } elseif ($status === 'warn') {
            $this->warnings++;
        }

        return [
            'status' => $status,
            'label'  => $label,
            'detail' => $detail,
        ];
    }

    private function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
