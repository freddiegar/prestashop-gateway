<?php

namespace PlacetoPay\Models;

use Dnetix\Redirection\PlacetoPay;

/**
 * Class PaymentRedirection
 * @package PlacetoPay\Models
 */
class PaymentRedirection extends PlacetoPay
{
    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     *
     * @param array $login
     * @param $tranKey
     * @param string $uri_service
     * @param string $type soap|rest
     * @throws \Dnetix\Redirection\Exceptions\PlacetoPayException
     */
    public function __construct($login, $tranKey, $uri_service = '', $type = 'rest')
    {
        parent::__construct([
            'login' => $login,
            'tranKey' => $tranKey,
            'url' => $uri_service,
            'type' => $type,
        ]);
    }
}
