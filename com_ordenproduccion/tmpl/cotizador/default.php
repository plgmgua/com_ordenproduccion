<?php
/**
 * Pre-Cotización list (current user). Same URL as cotizador; "Nueva cotización (pliego)" is in modal on document view.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Cotizador\HtmlView $this */

$items      = $this->items ?? [];
$pagination = $this->pagination ?? null;
$createUrl  = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.create&' . Session::getFormToken() . '=1');
?>

<div class="com-ordenproduccion-precotizacion-list container py-4">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_TITLE'); ?></h1>

    <p class="lead"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_DESC'); ?></p>

    <div class="mb-3">
        <a href="<?php echo $createUrl; ?>" class="btn btn-primary">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NEW'); ?>
        </a>
    </div>

    <?php if (empty($items)) : ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_ITEMS'); ?>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NUMBER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CREATED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ASSOCIATED_QUOTATION'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $associatedMap = $this->associatedQuotationNumbersByPreId ?? [];
                    foreach ($items as $item) :
                        $docUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . (int) $item->id);
                        $deleteUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.delete&id=' . (int) $item->id . '&' . Session::getFormToken() . '=1');
                        $created = $item->created ? (new \DateTime($item->created))->format('d/m/Y H:i') : '-';
                        $quotationNumbers = $associatedMap[(int) $item->id] ?? [];
                    ?>
                        <tr>
                            <td>
                                <a href="<?php echo htmlspecialchars($docUrl); ?>"><?php echo htmlspecialchars($item->number ?? ''); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($created); ?></td>
                            <td>
                                <?php
                                if (empty($quotationNumbers)) {
                                    echo '<span class="text-muted">—</span>';
                                } else {
                                    $parts = [];
                                    foreach ($quotationNumbers as $q) {
                                        $qid = is_array($q) ? (int) $q['id'] : 0;
                                        $qnum = is_array($q) ? ($q['quotation_number'] ?? '') : (string) $q;
                                        if ($qid) {
                                            $parts[] = '<a href="' . htmlspecialchars(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $qid)) . '">' . htmlspecialchars($qnum) . '</a>';
                                        } else {
                                            $parts[] = htmlspecialchars($qnum);
                                        }
                                    }
                                    echo implode(', ', $parts);
                                }
                                ?>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo htmlspecialchars($docUrl); ?>" class="btn btn-sm btn-outline-primary">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_VIEW'); ?>
                                </a>
                                <?php if (empty($quotationNumbers)) : ?>
                                <a href="<?php echo htmlspecialchars($deleteUrl); ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE')); ?>');">
                                    <?php echo Text::_('JACTION_DELETE'); ?>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination && $pagination->total > $pagination->limit) : ?>
            <div class="com-ordenproduccion-pagination">
                <?php echo $pagination->getListFooter(); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
