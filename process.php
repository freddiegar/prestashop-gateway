<?php
/**
 * Process payment
 */
try {
    require_once 'helpers.php';
    $pathCMS = getPathCMS('process.php');

    require fixPath($pathCMS . '/config/config.inc.php');
    require fixPath($pathCMS . '/init.php');
    require fixPath(sprintf('%s/%2$s/%2$s.php', _PS_MODULE_DIR_, getModuleName()));

    // Authentication error
    if (!Context::getContext()->customer->isLogged()
        && !Context::getContext()->customer->is_guest
        && empty(file_get_contents("php://input"))) {
        // Not authenticate
        Tools::redirect('authentication.php?back=order.php');
    }

    (new PlaceToPayPayment())->process(isset($_GET['_']) ? $_GET['_'] : null);
} catch (Exception $e) {
    die($e->getMessage());
}
