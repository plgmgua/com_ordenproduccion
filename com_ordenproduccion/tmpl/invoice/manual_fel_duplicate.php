<?php
/**
 * Factura manual modal on invoice detail (duplicate from this invoice — invoice data only).
 *
 * @package     com_ordenproduccion
 * @since       3.119.178
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorFactNitLookupHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Invoice\HtmlView $this */

if (empty($this->showManualFelDuplicateModal) || empty($this->manualFelSeedFromInvoice)) {
    return;
}

$l = static function ($key, $fallbackEn, $fallbackEs = null) {
    $t = Text::_($key);
    if ($t === $key || (\is_string($t) && strpos($t, 'COM_ORDENPRODUCCION_') === 0)) {
        $lang = Factory::getApplication()->getLanguage()->getTag();

        return (strpos($lang, 'es') !== false && $fallbackEs !== null) ? $fallbackEs : $fallbackEn;
    }

    return $t;
};

$item   = $this->item;
$seed   = $this->manualFelSeedFromInvoice;
$invId  = (int) ($item->id ?? 0);
$quotationId = 0;

$felForDirectCheck = new FelInvoiceIssuanceService();
$digifactCredsCheck = $felForDirectCheck->getActiveCertificadorCredentials();
$digifactCfgOk = (trim((string) ($digifactCredsCheck['url_cert_cf'] ?? '')) !== ''
    || trim((string) ($digifactCredsCheck['url_cert_nit'] ?? '')) !== '')
    && $felForDirectCheck->getActiveCertificadorBearerToken() !== '';

$manualFelIssueUrl = Route::_('index.php?option=com_ordenproduccion&task=invoice.manualFelIssueFromInvoiceDuplicate&format=json', false);
$manualFelPreviewUrl = Route::_('index.php?option=com_ordenproduccion&task=invoice.manualFelPreviewFromInvoiceDuplicate&format=json', false);
$manualFelExchangeRateUrl = Route::_('index.php?option=com_ordenproduccion&task=cotizacion.manualFelExchangeRate&format=json', false);
$digifactVerifyCuiUrl = Route::_('index.php?option=com_ordenproduccion&task=cliente.verifyDigifactCui&format=json', false);

$manualBuyerNameInitial = trim((string) ($seed['buyer_name'] ?? ''));
$manualBuyerNitInitial  = trim((string) ($seed['buyer_nit'] ?? ''));
$manualFelBillingIsCf   = CertificadorFactNitLookupHelper::billingIdIndicatesConsumidorFinal($manualBuyerNitInitial);
$manualFelQuotationRef  = '';
$manualFelLinePresets   = \is_array($this->manualFelLinePresets ?? null) ? $this->manualFelLinePresets : [];
$manualFelOrdensForClient = \is_array($this->manualFelOrdensForClient ?? null) ? $this->manualFelOrdensForClient : [];
$manualFelOtherQuotations = [];
$manualFelLinesUrl = '';
$manualFelIssueDateDefault = Factory::getDate('now', 'America/Guatemala')->format('Y-m-d');
$manualFelSeedFromInvoice = $seed;
$manualFelSourceInvoiceId = $invId;
$manualFelInvoiceDuplicateMode = true;

include dirname(__DIR__) . '/cotizacion/manual_fel_modal.php';
