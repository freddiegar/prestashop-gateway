<?php
/**
 * Payments pending
 */
try {
    require_once 'helpers.php';
    $pathCMS = getPathCMS('sonda.php');

    require $pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php';
    require _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . getModuleName() . DIRECTORY_SEPARATOR . getModuleName() . '.php';

    (new PlaceToPayPayment())->resolvePendingPayments();
} catch (Exception $e) {
    die($e->getMessage());
}
