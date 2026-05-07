<?php

/**
 * Scheduled task: refresh FEL (Digifact-style) bearer tokens for com_ordenproduccion.
 *
 * @copyright   (C) 2026 Grimpsa.
 * @license     GNU General Public License version 2 or later
 *
 * After install/update, create a task in System → Scheduled Tasks (e.g. daily at 03:00)
 * using routine "FEL certifier: refresh bearer tokens".
 */

\defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

/**
 * @since  3.118.8
 */
final class PlgTaskOpfel extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var array<string, array{langConstPrefix: string, method: string, form?: string}>
     */
    protected const TASKS_MAP = [
        'plg_task_opfel_refresh' => [
            'langConstPrefix' => 'PLG_TASK_OPFEL_REFRESH',
            'method'            => 'refreshCertificadorTokens',
            'form'              => 'refresh',
        ],
    ];

    /** @var bool */
    protected $autoloadLanguage = true;

    /**
     * @param   DispatcherInterface     $dispatcher  Event dispatcher
     * @param   array<string, mixed>    $config      Plugin config
     */
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'   => 'advertiseRoutines',
            'onExecuteTask'       => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function refreshCertificadorTokens(ExecuteTaskEvent $event): int
    {
        $args   = method_exists($event, 'getArguments') ? $event->getArguments() : [];
        $params = $args['params'] ?? null;
        $force  = false;

        if ($params instanceof \Joomla\Registry\Registry) {
            $force = (bool) $params->get('force', false);
        } elseif (\is_array($params)) {
            $force = !empty($params['force']);
        }

        try {
            Factory::getApplication()->bootComponent('com_ordenproduccion');
        } catch (\Throwable $e) {
            $this->logTask('Cannot load com_ordenproduccion: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        try {
            $model   = new AdministracionModel();
            $summary = $model->maintainCertificadorBearerTokens($force);
            $this->logTask('com_ordenproduccion FEL token maintenance: ' . json_encode($summary, JSON_UNESCAPED_UNICODE), 'info');

            if (!empty($summary['errors'])) {
                foreach ($summary['errors'] as $err) {
                    $this->logTask((string) $err, 'warning');
                }

                return Status::KNOCKOUT;
            }
        } catch (\Throwable $e) {
            $this->logTask('com_ordenproduccion FEL token maintenance failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        return Status::OK;
    }
}
