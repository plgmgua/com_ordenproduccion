<?php
/**
 * Scheduled MT-940 payment matching cron endpoint (no session; secret key in URL).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @since       3.119.228
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\Mt940PaymentMatchLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\Mt940PaymentMatchService;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Payment verification cron controller.
 */
class PaymentverificationController extends BaseController
{
    /**
     * Match ingresado payment proofs against MT-940 history.
     * GET: index.php?option=com_ordenproduccion&controller=paymentverification&task=runScheduledMatch&format=raw&cron_key=SECRET
     *
     * @return  void
     */
    public function runScheduledMatch(): void
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
                "Forbidden\n\nUse GET with cron_key matching the MT-940 cron secret (Ajustes → MT940 → Importar datos)."
            );

            return;
        }

        if (!Mt940PaymentMatchLogHelper::isMt940VerificationEnabled()) {
            $this->emitPlainResponse(200, 'SKIPPED MT-940 payment verification disabled (set payment_proof_mt940_verification=1 and approval_workflow_payment_proof=1).');

            return;
        }

        try {
            $svc    = new Mt940PaymentMatchService();
            $result = $svc->runScheduledMatching();
        } catch (\Throwable $e) {
            $this->emitPlainResponse(500, "Error\n\n" . $e->getMessage());

            return;
        }

        $prefix = !empty($result['success']) ? 'OK' : 'FAIL';
        $body   = $prefix . ' ' . ($result['message'] ?? '')
            . ' | scanned=' . (int) ($result['scanned'] ?? 0)
            . ' matched=' . (int) ($result['matched'] ?? 0)
            . ' ambiguous=' . (int) ($result['ambiguous'] ?? 0)
            . ' no_match=' . (int) ($result['no_match'] ?? 0)
            . ' skipped=' . (int) ($result['skipped'] ?? 0)
            . ' approvals=' . (int) ($result['approvals_created'] ?? 0);

        $this->emitPlainResponse(!empty($result['success']) ? 200 : 500, $body);
    }

    /**
     * @param   int     $status
     * @param   string  $body
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
