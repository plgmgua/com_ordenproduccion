<?php
/**
 * GTQ / USD display toggle (amounts stored in GTQ).
 *
 * @var bool   $cotizacionCurrencyCanUsd
 * @var float  $cotizacionExchangeRate
 * @var string $cotizacionExchangeRateDate
 * @var int    $cotizacionCurrencyQuotationId
 * @var string $cotizacionCurrencyPdfLinkId
 * @var bool   $cotizacionCurrencyIsCreate
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$cotizacionCurrencyCanUsd = !empty($cotizacionCurrencyCanUsd);
$cotizacionExchangeRate   = isset($cotizacionExchangeRate) ? (float) $cotizacionExchangeRate : 0.0;
$cotizacionExchangeRateDate = trim((string) ($cotizacionExchangeRateDate ?? ''));
$cotizacionCurrencyQuotationId = (int) ($cotizacionCurrencyQuotationId ?? 0);
$cotizacionCurrencyPdfLinkId = trim((string) ($cotizacionCurrencyPdfLinkId ?? ''));
$cotizacionCurrencyIsCreate = !empty($cotizacionCurrencyIsCreate);

$l = $l ?? function ($key, $fallbackEn, $fallbackEs = null) {
    $t = \Joomla\CMS\Language\Text::_($key);
    if ($t === $key || (is_string($t) && strpos($t, 'COM_ORDENPRODUCCION_') === 0)) {
        $lang = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();

        return (strpos($lang, 'es') !== false && $fallbackEs !== null) ? $fallbackEs : $fallbackEn;
    }

    return $t;
};
?>
<div class="cotizacion-currency-panel mb-3">
    <?php if ($cotizacionCurrencyIsCreate) : ?>
        <p class="text-muted small mb-0">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <?php echo htmlspecialchars($l(
                'COM_ORDENPRODUCCION_COTIZACION_CURRENCY_CREATE_HINT',
                'Amounts are stored in quetzales (GTQ). USD display is available after the quotation is saved.',
                'Los montos se guardan en quetzales (GTQ). La vista en USD estará disponible después de guardar la cotización.'
            )); ?>
        </p>
    <?php elseif (!$cotizacionCurrencyCanUsd) : ?>
        <p class="text-muted small mb-0">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <?php echo htmlspecialchars($l(
                'COM_ORDENPRODUCCION_COTIZACION_CURRENCY_LEGACY_HINT',
                'USD display is not available for this quotation (no exchange rate stored).',
                'La vista en USD no está disponible para esta cotización (no tiene tipo de cambio guardado).'
            )); ?>
        </p>
    <?php else : ?>
        <div id="cotizacion-currency-toggle"
             class="d-flex flex-wrap align-items-center gap-2"
             data-quotation-id="<?php echo $cotizacionCurrencyQuotationId; ?>"
             data-exchange-rate="<?php echo htmlspecialchars(number_format($cotizacionExchangeRate, 6, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
             data-active-currency="GTQ"
             <?php if ($cotizacionCurrencyPdfLinkId !== '') : ?>data-pdf-link-id="<?php echo htmlspecialchars($cotizacionCurrencyPdfLinkId, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
            <span class="small text-muted fw-semibold"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_COTIZACION_DISPLAY_CURRENCY', 'Display currency', 'Moneda de visualización')); ?>:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_COTIZACION_DISPLAY_CURRENCY', 'Display currency', 'Moneda de visualización')); ?>">
                <button type="button" class="btn btn-outline-secondary active" data-currency-choice="GTQ" aria-pressed="true">GTQ</button>
                <button type="button" class="btn btn-outline-secondary" data-currency-choice="USD" aria-pressed="false">USD</button>
            </div>
            <?php if ($cotizacionExchangeRateDate !== '') : ?>
                <span class="small text-muted">
                    <?php
                    $rateFmt = number_format($cotizacionExchangeRate, 4, '.', ',');
                    echo htmlspecialchars(sprintf(
                        $l(
                            'COM_ORDENPRODUCCION_COTIZACION_EXCHANGE_RATE_SNAPSHOT',
                            'Exchange rate (BANGUAT) on %s: %s GTQ/USD',
                            'Tipo de cambio (BANGUAT) al %s: %s GTQ/USD'
                        ),
                        $cotizacionExchangeRateDate,
                        $rateFmt
                    ));
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <script src="<?php echo htmlspecialchars(Uri::root() . 'media/com_ordenproduccion/js/cotizacion-currency.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
</div>
