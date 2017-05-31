<?php
/**
 * Process payment
 */

try {
    if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
        $pathCMS = dirname(dirname($_SERVER['PWD']));
    } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
        $pathCMS = str_replace('/modules/placetopaypayment/process.php', '', $_SERVER['SCRIPT_FILENAME']);
    } else {
        $pathCMS = dirname(dirname(getcwd()));
    }

    if (!file_exists($pathCMS . '/config/config.inc.php')) {
        die('Miss-configuration in Server [Process], valid setup.');
    }

    require $pathCMS . '/config/config.inc.php';
    require $pathCMS . '/init.php';
    require _PS_MODULE_DIR_ . "/placetopaypayment/placetopaypayment.php";

    // Authentication error
    if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest && empty(file_get_contents("php://input"))) {
        Tools::redirect('authentication.php?back=order.php');
    }

    $cart_id = (isset($_GET['cart_id'])) ? $_GET['cart_id'] : null;

    (new PlaceToPayPayment())->process($cart_id);
} catch (Exception $e) {
    die($e->getMessage());
}
