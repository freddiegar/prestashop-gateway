<?php
/**
 * Validar el estado de los pagos pendientes de aprobación
 */

// Carga la configuracion de prestashop,
if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
    $pathPrestaShop = dirname(dirname($_SERVER['PWD']));
} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
    $pathPrestaShop = str_replace( '/modules/placetopaypayment/sonda.php', '', $_SERVER['SCRIPT_FILENAME']);
} else {
    $pathPrestaShop = dirname(dirname(getcwd()));
}

if (!file_exists($pathPrestaShop . '/config/config.inc.php')) {
    die('Miss-configuration in Server [Sonda], valid setup.');
}

require $pathPrestaShop . '/config/config.inc.php';
require _PS_MODULE_DIR_ . "/placetopaypayment/placetopaypayment.php";

// Ejecuta la sonda
(new PlaceToPayPayment())->sonda();
