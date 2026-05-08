<?php
/**
 * Ajustes → Certificador de Fact → Cert. Logs: Digifact HTTP audit trail.
 *
 * @package     com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$rows       = is_array($this->certificadorDigifactLogRows ?? null) ? $this->certificadorDigifactLogRows : [];
$tableOk    = (bool) ($this->certificadorDigifactLogTableAvailable ?? false);
$pagination = $this->certificadorDigifactLogPagination ?? null;

if ($rows !== []) {
    HTMLHelper::_('bootstrap.collapse');
}

$jsBeautify   = json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_BEAUTIFY_JSON'));
$jsRestore    = json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESTORE_RAW'));
$jsDecodeErr  = json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_DECODE_ERROR'));

$actorNameById = [];
if ($rows !== []) {
    $ids = [];
    foreach ($rows as $r) {
        $aid = (int) ($r->created_by ?? 0);
        if ($aid > 0) {
            $ids[$aid] = true;
        }
    }
    $idList = array_keys($ids);
    if ($idList !== []) {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('username'),
            ])
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $idList)) . ')');
        $db->setQuery($query);
        foreach ($db->loadObjectList() ?: [] as $u) {
            $uid = (int) $u->id;
            $nm  = trim((string) ($u->name ?? ''));
            $un  = trim((string) ($u->username ?? ''));
            if ($nm !== '') {
                $actorNameById[$uid] = $un !== '' && strcasecmp($nm, $un) !== 0
                    ? $nm . ' (' . $un . ')'
                    : $nm;
            } elseif ($un !== '') {
                $actorNameById[$uid] = $un;
            } else {
                $actorNameById[$uid] = '#' . $uid;
            }
        }
    }
}
?>
<style>
.digifact-log-wrap {
    max-width: 100%;
    overflow-x: hidden;
}
.digifact-log-table {
    table-layout: fixed;
    width: 100%;
}
.digifact-log-table th,
.digifact-log-table td {
    word-break: break-word;
    overflow-wrap: anywhere;
    vertical-align: middle;
}
.digifact-log-table td.digifact-log-col-expand {
    width: 2rem;
    white-space: nowrap;
}
.digifact-log-url-row td {
    border-top: 0;
    background: #f8f9fa;
    vertical-align: top;
}
.digifact-log-url-text {
    font-size: 0.75rem;
    word-break: break-all;
    overflow-wrap: anywhere;
    white-space: normal;
}
.digifact-log-pre {
    display: block;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    word-break: break-word;
    overflow-x: hidden;
    max-height: 480px;
    overflow-y: auto;
    font-size: 0.7rem;
    line-height: 1.35;
}
.digifact-log-toolbar .btn {
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
}
</style>
<div class="admin-dashboard digifact-log-wrap px-2 py-1 mx-auto" style="max-width: 1400px; font-size: 0.8125rem;">
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
        <div class="mb-2 digifact-log-wrap">
            <table class="table table-hover table-sm align-middle mb-0 digifact-log-table">
                <thead class="table-light">
                    <tr class="text-muted" style="font-size: 0.75rem;">
                        <th scope="col" class="digifact-log-col-expand"></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_CREATED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_ENV'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_OP'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_METHOD'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_HTTP'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_MS'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_INV'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_QUOT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_USER'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $idx => $row) : ?>
                        <?php
                        /** @var object $row */
                        $rid    = 'digifact-log-' . (int) ($row->id ?? $idx);
                        $url    = (string) ($row->request_url ?? '');
                        $actorId = (int) ($row->created_by ?? 0);
                        if ($actorId > 0 && isset($actorNameById[$actorId])) {
                            $actorLabel = $actorNameById[$actorId];
                        } elseif ($actorId > 0) {
                            $actorLabel = '#' . $actorId;
                        } else {
                            $actorLabel = Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_ACTOR_NONE');
                        }
                        $reqBody = (string) ($row->request_body ?? '');
                        $resBody = (string) ($row->response_body ?? '');
                        $hdrs    = (string) ($row->request_headers_json ?? '');
                        ?>
                        <tr>
                            <td class="py-1 digifact-log-col-expand">
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
                            <td class="small text-break"><?php echo htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr class="digifact-log-url-row">
                            <td colspan="10" class="small py-1 digifact-log-wrap">
                                <span class="text-muted text-uppercase" style="font-size: 0.65rem;"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_COL_URL'); ?></span>
                                <div class="digifact-log-url-text mt-1"><code class="text-dark"><?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?></code></div>
                            </td>
                        </tr>
                        <tr class="collapse" id="<?php echo $rid; ?>">
                            <td colspan="10" class="bg-light border-top-0 small py-2 digifact-log-wrap">
                                <?php if ($hdrs !== '') : ?>
                                    <div class="mb-2 digifact-log-json-block" data-digifact-block="headers">
                                        <div class="digifact-log-toolbar d-flex flex-wrap align-items-center gap-1 mb-1">
                                            <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_HEADERS'); ?></strong>
                                            <button type="button" class="btn btn-sm btn-outline-primary js-digifact-beautify-json"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_BEAUTIFY_JSON'); ?></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-digifact-restore-raw d-none"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESTORE_RAW'); ?></button>
                                        </div>
                                        <pre class="digifact-log-pre p-2 bg-white border rounded" dir="ltr"><?php echo htmlspecialchars($hdrs, ENT_QUOTES, 'UTF-8'); ?></pre>
                                    </div>
                                <?php endif; ?>
                                <?php if ($reqBody !== '') : ?>
                                    <div class="mb-2 digifact-log-json-block" data-digifact-block="request">
                                        <div class="digifact-log-toolbar d-flex flex-wrap align-items-center gap-1 mb-1">
                                            <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_REQUEST'); ?></strong>
                                            <button type="button" class="btn btn-sm btn-outline-primary js-digifact-beautify-json"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_BEAUTIFY_JSON'); ?></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-digifact-restore-raw d-none"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESTORE_RAW'); ?></button>
                                        </div>
                                        <pre class="digifact-log-pre p-2 bg-white border rounded" dir="ltr"><?php echo htmlspecialchars($reqBody, ENT_QUOTES, 'UTF-8'); ?></pre>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-1 digifact-log-json-block" data-digifact-block="response">
                                    <?php if ($resBody !== '') : ?>
                                        <div class="digifact-log-toolbar d-flex flex-wrap align-items-center gap-1 mb-1">
                                            <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESPONSE'); ?></strong>
                                            <button type="button" class="btn btn-sm btn-outline-primary js-digifact-beautify-json"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_BEAUTIFY_JSON'); ?></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-digifact-restore-raw d-none"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESTORE_RAW'); ?></button>
                                            <button type="button" class="btn btn-sm btn-outline-success js-digifact-decode-b64-xml"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_DECODE_B64_XML'); ?></button>
                                        </div>
                                    <?php else : ?>
                                        <div class="mb-1"><strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_RESPONSE'); ?></strong></div>
                                    <?php endif; ?>
                                    <?php $cerr = (string) ($row->client_error ?? ''); ?>
                                    <?php if ($cerr !== '') : ?>
                                        <div class="text-danger small mb-1"><?php echo htmlspecialchars($cerr, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if ($resBody !== '') : ?>
                                        <pre class="digifact-log-pre p-2 bg-white border rounded js-digifact-response-raw" dir="ltr"><?php echo htmlspecialchars($resBody, ENT_QUOTES, 'UTF-8'); ?></pre>
                                        <div class="digifact-log-xml-panel mt-2 d-none">
                                            <strong class="d-block mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_LOG_XML_DECODED'); ?></strong>
                                            <pre class="digifact-log-pre p-2 bg-white border rounded border-success js-digifact-xml-out" dir="ltr"></pre>
                                        </div>
                                    <?php else : ?>
                                        <pre class="digifact-log-pre p-2 bg-white border rounded text-muted" dir="ltr">—</pre>
                                    <?php endif; ?>
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
<?php if ($rows !== []) : ?>
<script>
(function () {
    'use strict';
    var labels = {
        beautify: <?php echo $jsBeautify; ?>,
        restore: <?php echo $jsRestore; ?>,
        decodeErr: <?php echo $jsDecodeErr; ?>
    };

    function ensureRawBackup(pre) {
        if (!pre.dataset.digifactRawBackup) {
            pre.dataset.digifactRawBackup = pre.textContent;
        }
    }

    function beautifyJsonText(text) {
        var t = text.replace(/^\uFEFF/, '').trim();
        if (t === '' || t === '—') {
            throw new Error('empty');
        }
        var obj = JSON.parse(t);
        return JSON.stringify(obj, null, 2);
    }

    function formatXml(xml) {
        xml = xml.replace(/^\uFEFF/, '').trim();
        if (!xml) {
            return xml;
        }
        try {
            var parser = new DOMParser();
            var doc = parser.parseFromString(xml, 'application/xml');
            if (doc.getElementsByTagName('parsererror').length) {
                return simpleFormatXml(xml);
            }
            var ser = new XMLSerializer();
            var node = doc.documentElement;
            if (!node) {
                return simpleFormatXml(xml);
            }
            return simpleFormatXml(ser.serializeToString(node));
        } catch (e) {
            return simpleFormatXml(xml);
        }
    }

    function simpleFormatXml(xml) {
        var formatted = '';
        var reg = /(>)(<)(\/*)/g;
        xml = xml.replace(reg, '$1\n$2$3');
        var pad = 0;
        xml.split('\n').forEach(function (node) {
            var trim = node.trim();
            if (!trim) {
                return;
            }
            var indent = 0;
            if (trim.match(/^<\/.+/)) {
                pad = Math.max(pad - 1, 0);
            }
            formatted += new Array(pad + 1).join('  ') + trim + '\n';
            if (trim.match(/^<[^!?][^>]*[^\/]>/) && !trim.match(/^<\/|\/\s*>/) && !trim.match(/<\/.+?>$/)) {
                pad++;
            }
        });
        return formatted.trim();
    }

    function base64ToUtf8(b64) {
        var clean = b64.replace(/\s/g, '');
        var binary = atob(clean);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return new TextDecoder('utf-8').decode(bytes);
    }

    function findBase64Field(obj, depth) {
        if (!obj || typeof obj !== 'object' || depth > 10) {
            return null;
        }
        var preferred = ['responseData1', 'ResponseData1', 'responseData2', 'ResponseData2',
            'RESPONSEDATA1', 'RESPONSEDATA2', 'XmlDocument', 'xmlDocument', 'XML'];
        var k, v, i, nested, inner;
        for (i = 0; i < preferred.length; i++) {
            k = preferred[i];
            v = obj[k];
            if (typeof v === 'string' && v.replace(/\s/g, '').length > 64) {
                return v;
            }
        }
        if (typeof obj.response === 'string' && obj.response.trim() !== '') {
            try {
                inner = JSON.parse(obj.response);
                nested = findBase64Field(inner, depth + 1);
                if (nested) {
                    return nested;
                }
            } catch (e) {
                /* ignore */
            }
        }
        for (k in obj) {
            if (!Object.prototype.hasOwnProperty.call(obj, k)) {
                continue;
            }
            v = obj[k];
            if (typeof v === 'string') {
                var s = v.replace(/\s/g, '');
                if (s.length > 120 && /^[A-Za-z0-9+/]+=*$/.test(s)) {
                    return v;
                }
            } else if (v && typeof v === 'object') {
                nested = findBase64Field(v, depth + 1);
                if (nested) {
                    return nested;
                }
            }
        }
        return null;
    }

    function parseResponseForB64(text) {
        var t = text.replace(/^\uFEFF/, '').trim();
        if (!t || t === '—') {
            throw new Error(labels.decodeErr);
        }
        var data = JSON.parse(t);
        var raw = findBase64Field(data, 0);
        if (!raw) {
            throw new Error(labels.decodeErr);
        }
        return base64ToUtf8(raw);
    }

    document.addEventListener('click', function (ev) {
        var el = ev.target;
        if (!el || !el.closest) {
            return;
        }
        var beautifyBtn = el.closest('.js-digifact-beautify-json');
        var restoreBtn = el.closest('.js-digifact-restore-raw');
        var decodeBtn = el.closest('.js-digifact-decode-b64-xml');

        if (beautifyBtn) {
            ev.preventDefault();
            var blockB = beautifyBtn.closest('.digifact-log-json-block');
            var preB = blockB ? (blockB.querySelector('.js-digifact-response-raw') || blockB.querySelector('.digifact-log-pre')) : null;
            if (!preB) {
                return;
            }
            ensureRawBackup(preB);
            try {
                preB.textContent = beautifyJsonText(preB.textContent);
                beautifyBtn.classList.add('d-none');
                var rb = blockB.querySelector('.js-digifact-restore-raw');
                if (rb) {
                    rb.classList.remove('d-none');
                }
            } catch (e) {
                window.alert(labels.decodeErr);
            }
            return;
        }

        if (restoreBtn) {
            ev.preventDefault();
            var blockR = restoreBtn.closest('.digifact-log-json-block');
            var preR = blockR ? (blockR.querySelector('.js-digifact-response-raw') || blockR.querySelector('.digifact-log-pre')) : null;
            if (!preR || preR.dataset.digifactRawBackup === undefined) {
                return;
            }
            preR.textContent = preR.dataset.digifactRawBackup;
            restoreBtn.classList.add('d-none');
            var bb = blockR.querySelector('.js-digifact-beautify-json');
            if (bb) {
                bb.classList.remove('d-none');
            }
            if (blockR.getAttribute('data-digifact-block') === 'response') {
                var panel = blockR.querySelector('.digifact-log-xml-panel');
                var xmlOut = blockR.querySelector('.js-digifact-xml-out');
                if (panel) {
                    panel.classList.add('d-none');
                }
                if (xmlOut) {
                    xmlOut.textContent = '';
                }
            }
            return;
        }

        if (decodeBtn) {
            ev.preventDefault();
            var blockD = decodeBtn.closest('.digifact-log-json-block');
            var rawPre = blockD ? blockD.querySelector('.js-digifact-response-raw') : null;
            var xmlPanel = blockD ? blockD.querySelector('.digifact-log-xml-panel') : null;
            var xmlPre = blockD ? blockD.querySelector('.js-digifact-xml-out') : null;
            if (!rawPre || !xmlPanel || !xmlPre) {
                return;
            }
            try {
                var decoded = parseResponseForB64(rawPre.textContent);
                var pretty = decoded.trim().startsWith('<') ? formatXml(decoded) : decoded;
                xmlPre.textContent = pretty;
                xmlPanel.classList.remove('d-none');
            } catch (err) {
                window.alert(labels.decodeErr + (err && err.message ? ' ' + err.message : ''));
            }
        }
    });
})();
</script>
<?php endif; ?>
