<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

class PlaceToPayPayment extends PlacetoPay\Models\PaymentMethod
{
}