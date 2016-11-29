<?php
/**
 * Modulo para el procesamiento de pagos a traves de PlacetoPay.
 */

// carga la configuracion de prestashop, 
$path = dirname(__FILE__);
require $path . '/../../config/config.inc.php';
require $path . '/../../init.php';
require $path . '/placetopaypayment.php';

// si se ha cerrado la sesion retorna a la pagina de autenticacion
if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest && empty(file_get_contents("php://input"))) {
    Tools::redirect('authentication.php?back=order.php');
}

$cart_id = (isset($_GET['cart_id'])) ? $_GET['cart_id'] : null;

// instancia el componente de PlacetoPay y redirige al cliente a la plataforma
$placetopay = new PlacetoPayPayment();
$placetopay->process($cart_id);