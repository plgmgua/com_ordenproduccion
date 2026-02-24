<?php

namespace Grimpsa\Component\Ordenproduccion\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

/**
 * Component dispatcher class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Dispatch a controller task. Redirecting the user if appropriate.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function dispatch()
    {
        $this->normalizeCotizacionViewQuery();

        parent::dispatch();
    }

    /**
     * Fix malformed URL when view=cotizacion is followed by ? instead of & (e.g. from Odoo).
     * URL like ?view=cotizacion?client_id=7&... makes view be "cotizacion?client_id=7" or "cotizacionclient_id7".
     * Normalize so view=cotizacion and client_id, contact_name, etc. are separate request vars.
     *
     * @return  void
     * @since   3.74.0
     */
    protected function normalizeCotizacionViewQuery()
    {
        $view = $this->input->getString('view', '');
        if ($view === '') {
            return;
        }

        if (strpos($view, '?') !== false) {
            $parts = explode('?', $view, 2);
            $realView = trim($parts[0]);
            $extra = isset($parts[1]) ? trim($parts[1]) : '';
            if ($realView === 'cotizacion' && $extra !== '') {
                $this->input->set('view', 'cotizacion');
                parse_str($extra, $params);
                foreach ($params as $key => $value) {
                    if ($key !== '' && $value !== null) {
                        $this->input->set($key, $value);
                    }
                }
            }
            return;
        }

        if (strpos($view, 'cotizacion') === 0 && $view !== 'cotizacion') {
            $this->input->set('view', 'cotizacion');
        }
    }
}