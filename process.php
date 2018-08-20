<?php
/**
 * Process payment
 */

use PlacetoPay\Loggers\PaymentLogger;

try {
    require_once 'helpers.php';

    $pathCMS = getPathCMS('process.php');

    require fixPath($pathCMS . '/config/config.inc.php');
    require fixPath($pathCMS . '/init.php');
    require fixPath(sprintf('%s/%2$s/%2$s.php', _PS_MODULE_DIR_, getModuleName()));

    if (!Context::getContext()->customer->isLogged()
        && !Context::getContext()->customer->is_guest
        && empty(file_get_contents("php://input"))) {
        PaymentLogger::log('Access not allowed', PaymentLogger::WARNING, 17, __FILE__, __LINE__);
        Tools::redirect('authentication.php?back=order.php');
    }

    (new PlacetoPayPayment())->process(isset($_GET['_']) ? $_GET['_'] : null);
} catch (Exception $e) {
    PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 999, __FILE__, __LINE__);
    die($e->getMessage());
}
