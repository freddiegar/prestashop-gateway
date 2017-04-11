<?php
/**
 * Modulo para el procesamiento de pagos a traves de PlacetoPay.
 */

// Carga la configuracion de prestashop,
if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
    $pathPrestaShop = dirname(dirname($_SERVER['PWD']));
} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
    $pathPrestaShop = str_replace( '/modules/placetopaypayment/redirect.php', '', $_SERVER['SCRIPT_FILENAME']);
} else {
    $pathPrestaShop = dirname(dirname(getcwd()));
}

if (!file_exists($pathPrestaShop . '/config/config.inc.php')) {
    die('Miss-configuration in Server [Redirect], valid setup.');
}

require $pathPrestaShop . '/config/config.inc.php';
require $pathPrestaShop . '/init.php';
require _PS_MODULE_DIR_ . "/placetopaypayment/placetopaypayment.php";

// si se ha cerrado la sesion retorna a la pagina de autenticacion
if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
    Tools::redirect('authentication.php?back=order.php');
}

// instancia el componente de PlacetoPay y redirige al cliente a la plataforma
(new PlaceToPayPayment())->redirect($cart);