<?php
/**
 * Payments pending
 */
try {
    require_once 'helpers.php';
    $pathCMS = getPathCMS('sonda.php');

    require fixPath($pathCMS . '/config/config.inc.php');
    require fixPath(sprintf('%s/%2$s/%2$s.php', _PS_MODULE_DIR_, getModuleName()));

    (new PlaceToPayPayment())->resolvePendingPayments();
} catch (Exception $e) {
    die($e->getMessage());
}
