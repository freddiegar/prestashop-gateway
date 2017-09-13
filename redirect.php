<?php
/**
 * Redirect to Place to Pay
 */

try {
    if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
        $option = 'A';
        $pathCMS = dirname(dirname($_SERVER['PWD']));
    } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
        $option = 'B';
        $pathCMS = str_replace(DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'placetopaypayment' . DIRECTORY_SEPARATOR . 'redirect.php', '', $_SERVER['SCRIPT_FILENAME']);
    } else {
        $option = 'C';
        $pathCMS = dirname(dirname(getcwd()));
    }

    if (!file_exists($pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php')) {
        die("Miss-configuration in Server [Redirect]. Option [$option] Path [$pathCMS].");
    }

    require $pathCMS . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.inc.php';
    require $pathCMS . DIRECTORY_SEPARATOR . 'init.php';
    require _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . 'placetopaypayment' . DIRECTORY_SEPARATOR . 'placetopaypayment.php';

    // Authentication error
    if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
        Tools::redirect('authentication.php?back=order.php');
    }

    (new PlaceToPayPayment())->redirect($cart);
} catch (Exception $e) {
    die($e->getMessage());
}
