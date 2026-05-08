<?php
/**
 * Ajustes → Certificador de Fact → Cert. Logs: Digifact HTTP audit trail.
 *
 * @package     com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$rows       = is_array($this->certificadorDigifactLogRows ?? null) ? $this->certificadorDigifactLogRows : [];
$tableOk    = (bool) ($this->certificadorDigifactLogTableAvailable ?? false);
$pagination = $this->certificadorDigifactLogPagination ?? null;

if ($rows !== []) {
    HTMLHelper::_('bootstrap.collapse');
}
?>
<div class="admin-dashboard px-2 py-1 mx-auto" style="max-width: 1400px; font-size: 0.8125rem;">
    <h2 class="h5 mb-2">
        <i class="fas fa-clipboard-list"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_TITLE'); ?>
    </h2>
    <p class="text-muted mb-2 small">
        <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_INTRO'); ?>
    </p>

    <?php if (!$tableOk) : ?>
        <div class="alert alert-warning py-2 mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_NO_TABLE'); ?>
        </div>
    <?php elseif ($rows === []) : ?>
        <div class="alert alert-info py-2 mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_EMPTY'); ?>
        </div>
    <?php else : ?>
        <?php if ($pagination !== null) : ?>
            <div class="mb-2"><?php echo $pagination->getListFooter(); ?></div>
        <?php endif; ?>
        <div class="table-responsive mb-2">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-muted" style="font-size: 0.75rem;">
                        <th scope="col" style="width:2rem;"></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_CREATED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_ENV'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_OP'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_METHOD'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_HTTP'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_MS'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_INV'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_QUOT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_URL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $idx => $row) : ?>
                        <?php
                        /** @var object $row */
                        $rid    = 'digifact-log-' . (int) ($row->id ?? $idx);
                        $url    = (string) ($row->request_url ?? '');
                        $urlShort = $url;
                        if (function_exists('mb_strlen') && mb_strlen($urlShort, 'UTF-8') > 72) {
                            $urlShort = mb_substr($urlShort, 0, 69, 'UTF-8') . '…';
                        } elseif (\strlen($urlShort) > 72) {
                            $urlShort = substr($urlShort, 0, 69) . '…';
                        }
                        $reqBody = (string) ($row->request_body ?? '');
                        $resBody = (string) ($row->response_body ?? '');
                        $hdrs    = (string) ($row->request_headers_json ?? '');
                        ?>
                        <tr>
                            <td class="py-1">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#<?php echo $rid; ?>"
                                        aria-expanded="false" aria-controls="<?php echo $rid; ?>"
                                        title="<?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_EXPAND'); ?>">
                                    <i class="fas fa-chevron-down small"></i>
                                </button>
                            </td>
                            <td class="text-nowrap small"><?php echo htmlspecialchars((string) ($row->created ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string) ($row->environment ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td class="small"><?php echo htmlspecialchars((string) ($row->operation ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="small"><?php echo htmlspecialchars((string) ($row->request_method ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="small"><?php echo (int) ($row->response_http_code ?? 0); ?></td>
                            <td class="small"><?php echo (int) ($row->duration_ms ?? 0); ?></td>
                            <td class="small"><?php echo (int) ($row->invoice_id ?? 0) > 0 ? (int) $row->invoice_id : '—'; ?></td>
                            <td class="small"><?php echo (int) ($row->quotation_id ?? 0) > 0 ? (int) $row->quotation_id : '—'; ?></td>
                            <td class="small"><span title="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($urlShort, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                        <tr class="collapse" id="<?php echo $rid; ?>">
                            <td colspan="10" class="bg-light border-top-0 small py-2">
                                <?php if ($hdrs !== '') : ?>
                                    <div class="mb-2"><strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_HEADERS'); ?></strong>
                                        <pre class="mb-0 mt-1 p-2 bg-white border rounded" style="font-size: 0.7rem; max-height: 200px; overflow: auto; white-space: pre-wrap;"><?php echo htmlspecialchars($hdrs, ENT_QUOTES, 'UTF-8'); ?></pre>
                                    </div>
                                <?php endif; ?>
                                <?php if ($reqBody !== '') : ?>
                                    <div class="mb-2"><strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_REQUEST'); ?></strong>
                                        <pre class="mb-0 mt-1 p-2 bg-white border rounded" style="font-size: 0.7rem; max-height: 320px; overflow: auto; white-space: pre-wrap;"><?php echo htmlspecialchars($reqBody, ENT_QUOTES, 'UTF-8'); ?></pre>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-1"><strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESPONSE'); ?></strong>
                                    <?php $cerr = (string) ($row->client_error ?? ''); ?>
                                    <?php if ($cerr !== '') : ?>
                                        <div class="text-danger small mb-1"><?php echo htmlspecialchars($cerr, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <pre class="mb-0 mt-1 p-2 bg-white border rounded" style="font-size: 0.7rem; max-height: 420px; overflow: auto; white-space: pre-wrap;"><?php echo htmlspecialchars($resBody !== '' ? $resBody : '—', ENT_QUOTES, 'UTF-8'); ?></pre>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination !== null) : ?>
            <div class="mb-0"><?php echo $pagination->getListFooter(); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
