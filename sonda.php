<?php
/**
 * Payments pending
 */

try {
    if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
        $option = 'A';
        $pathCMS = dirname(dirname($_SERVER['PWD']));
    } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
        $option = 'B';
        $pathCMS = str_replace(DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'placetopaypayment' . DIRECTORY_SEPARATOR . 'sonda.php', '', $_SERVER['SCRIPT_FILENAME']);
    } else {
        $option = 'C';
        $pathCMS = dirname(dirname(getcwd()));
    }

    if (!file_exists($pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php')) {
        die("Miss-configuration in Server [Sonda]. Option [$option] Path [$pathCMS].");
    }

    require $pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php';
    require _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . 'placetopaypayment' . DIRECTORY_SEPARATOR . 'placetopaypayment.php';

    (new PlaceToPayPayment())->sonda();
} catch (Exception $e) {
    die($e->getMessage());
}
