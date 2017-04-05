<?php
/**
 * Validar el estado de los pagos pendientes de aprobación
 */

// Carga la configuracion de prestashop,
$pathPrestaShop = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
require $pathPrestaShop . '/config/config.inc.php';
require _PS_MODULE_DIR_ . "/placetopaypayment/placetopaypayment.php";

if(php_sapi_name() == 'cli') {
    // instancia el componente de PlacetoPay y redirige al cliente a la plataforma
    (new PlaceToPayPayment())->sonda();
} else {
    header('Location: ../../index.php');
}
