<?php
/**
 * Payments pending
 */

try {
    if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
        $pathCMS = dirname(dirname($_SERVER['PWD']));
    } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
        $pathCMS = str_replace('/modules/placetopaypayment/sonda.php', '', $_SERVER['SCRIPT_FILENAME']);
    } else {
        $pathCMS = dirname(dirname(getcwd()));
    }

    if (!file_exists($pathCMS . '/config/config.inc.php')) {
        die('Miss-configuration in Server [Sonda], valid setup.');
    }

    require $pathCMS . '/config/config.inc.php';
    require _PS_MODULE_DIR_ . "/placetopaypayment/placetopaypayment.php";

    (new PlaceToPayPayment())->sonda();
} catch (Exception $e) {
    die($e->getMessage());
}
