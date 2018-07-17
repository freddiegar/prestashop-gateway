<?php
/**
 * Redirect to PlacetoPay
 */
try {
    require_once 'helpers.php';
    $pathCMS = getPathCMS('redirect.php');

    require fixPath($pathCMS . '/config/config.inc.php');
    require fixPath($pathCMS . '/init.php');
    require fixPath(sprintf('%s/%2$s/%2$s.php', _PS_MODULE_DIR_, getModuleName()));

    // Authentication error
    if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
        Tools::redirect('authentication.php?back=order.php');
    }

    (new PlaceToPayPayment())->redirect($cart);
} catch (Exception $e) {
    die($e->getMessage());
}
