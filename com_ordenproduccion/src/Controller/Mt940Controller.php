<?php

/**
 * MT-940 scheduled import cron endpoint (no session; secret key in URL).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @since       3.119.158
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\Mt940MailboxImportHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * MT-940 cron controller.
 */
class Mt940Controller extends BaseController
{
    /**
     * Poll the configured IMAP mailbox and import new MT-940 files.
     * Call daily via server cron (e.g. 8:00) with GET:
     * https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&controller=mt940&task=runScheduledImport&format=raw&cron_key=SECRET
     *
     * @return  void
     */
    public function runScheduledImport(): void
    {
        $app = Factory::getApplication();
        $key = $app->input->getString('cron_key', '');
        $key = \is_string($key) ? \trim($key) : '';

        try {
            $model    = new AdministracionModel();
            $expected = $model->getMt940CronKey();
        } catch (\Throwable $e) {
            $this->emitPlainResponse(500, "Error\n\n" . $e->getMessage());

            return;
        }

        if ($expected === '' || $key === '' || !\hash_equals($expected, $key)) {
            $this->emitPlainResponse(
                403,
                "Forbidden\n\nUse GET with query cron_key matching the MT-940 cron secret (Ajustes → MT940 → Importar datos)."
            );

            return;
        }

        try {
            $settings   = $model->getMt940Settings();
            $allowedIds = $model->getMt940BankAccountIds();
        } catch (\Throwable $e) {
            $this->emitPlainResponse(500, "Error\n\n" . $e->getMessage());

            return;
        }

        if (($settings['enabled'] ?? '0') !== '1') {
            $this->emitPlainResponse(200, 'SKIPPED MT-940 import is disabled in Ajustes → MT940.');

            return;
        }

        try {
            $result = Mt940MailboxImportHelper::runInitialImport($settings, $allowedIds);
        } catch (\Throwable $e) {
            $this->emitPlainResponse(500, "Error\n\n" . $e->getMessage());

            return;
        }

        $msgKey = (string) ($result['message'] ?? '');
        $msg    = $msgKey !== '' && \strpos($msgKey, 'COM_ORDENPRODUCCION_') === 0 ? Text::_($msgKey) : $msgKey;

        if (!empty($result['success']) && $msgKey === 'COM_ORDENPRODUCCION_MT940_INITIAL_IMPORT_OK') {
            $msg = Text::sprintf(
                'COM_ORDENPRODUCCION_MT940_INITIAL_IMPORT_OK_DETAIL',
                (int) ($result['emails_scanned'] ?? 0),
                (int) ($result['files_imported'] ?? 0),
                (int) ($result['files_skipped'] ?? 0),
                (int) ($result['transactions_imported'] ?? 0)
            );
        }

        if (!empty($result['imap_error'])) {
            $msg .= ' ' . (string) $result['imap_error'];
        }

        $status = !empty($result['success']) ? 200 : 500;
        $prefix = !empty($result['success']) ? 'OK' : 'FAIL';

        $this->emitPlainResponse(
            $status,
            $prefix . ' ' . $msg
            . ' | emails=' . (int) ($result['emails_scanned'] ?? 0)
            . ' files=' . (int) ($result['files_imported'] ?? 0)
            . ' skipped=' . (int) ($result['files_skipped'] ?? 0)
            . ' tx=' . (int) ($result['transactions_imported'] ?? 0)
        );
    }

    /**
     * @param   int     $status  HTTP status
     * @param   string  $body    Response body
     *
     * @return  void
     */
    private function emitPlainResponse(int $status, string $body): void
    {
        $app = Factory::getApplication();
        if (!\headers_sent()) {
            \http_response_code($status);
        }
        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo $body;
        $app->close();
    }
}
