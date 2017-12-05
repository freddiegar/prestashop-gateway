<?php
/**
 * Redirect to Place to Pay
 */
try {
    require_once 'helpers.php';
    $pathCMS = getPathCMS('redirect.php');

    require $pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php';
    require $pathCMS . DIRECTORY_SEPARATOR . 'init.php';
    require _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . getModuleName() . DIRECTORY_SEPARATOR . getModuleName() . '.php';

    // Authentication error
    if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
        Tools::redirect('authentication.php?back=order.php');
    }

    (new PlaceToPayPayment())->redirect($cart);
} catch (Exception $e) {
    die($e->getMessage());
}
