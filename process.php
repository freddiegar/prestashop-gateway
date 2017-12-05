<?php
/**
 * Process payment
 */
try {
    require_once 'helpers.php';
    $pathCMS = getPathCMS('process.php');

    require $pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php';
    require $pathCMS . DIRECTORY_SEPARATOR . 'init.php';
    require _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . getModuleName() . DIRECTORY_SEPARATOR . getModuleName() . '.php';

    // Authentication error
    if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest && empty(file_get_contents("php://input"))) {
        Tools::redirect('authentication.php?back=order.php');
    }

    $_reference = isset($_GET['_']) ? $_GET['_'] : null;

    (new PlaceToPayPayment())->process($_reference);
} catch (Exception $e) {
    die($e->getMessage());
}
