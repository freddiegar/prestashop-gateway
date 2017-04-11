<?php
/**
 * Modulo para el procesamiento de pagos a traves de PlacetoPay.
 */

// Carga la configuracion de prestashop,
if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
    $pathPrestaShop = dirname(dirname($_SERVER['PWD']));
} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
    $pathPrestaShop = str_replace('/modules/placetopaypayment/process.php', '', $_SERVER['SCRIPT_FILENAME']);
} else {
    $pathPrestaShop = dirname(dirname(getcwd()));
}

if (!file_exists($pathPrestaShop . '/config/config.inc.php')) {
    die('Miss-configuration in Server [Process], valid setup.');
}

require $pathPrestaShop . '/config/config.inc.php';
require $pathPrestaShop . '/init.php';
require _PS_MODULE_DIR_ . "/placetopaypayment/placetopaypayment.php";

// si se ha cerrado la sesion retorna a la pagina de autenticacion
if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest && empty(file_get_contents("php://input"))) {
    Tools::redirect('authentication.php?back=order.php');
}

$cart_id = (isset($_GET['cart_id'])) ? $_GET['cart_id'] : null;

// instancia el componente de PlacetoPay y redirige al cliente a la plataforma
(new PlaceToPayPayment())->process($cart_id);