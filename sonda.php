<?php

// carga la configuracion de prestashop, 
$path = dirname(__FILE__);
require $path . '/../../config/config.inc.php';
require $path . '/placetopaypayment.php';

// instancia el objeto link en el contexto si no viene inicializado
if (empty(Context::getContext()->link)) {
    Context::getContext()->link = new Link();
}

// instancia el componente de PlacetoPay y redirige al cliente a la plataforma
$placetopay = new PlaceToPayPayment();
$placetopay->sonda();