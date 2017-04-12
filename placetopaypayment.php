<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

session_start();

require_once __DIR__ . '/vendor/autoload.php';

class PlaceToPayPayment extends PlacetoPay\Models\PaymentMethod
{
}