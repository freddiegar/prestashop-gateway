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
if (!Context::getContext()->customer->isLogged() && !Context::getContext()->customer->is_guest) {
    Tools::redirect('authentication.php?back=order.php');
}

// instancia el componente de PlacetoPay y redirige al cliente a la plataforma
$placetopay = new PlaceToPayPayment();
$placetopay->redirect($cart);