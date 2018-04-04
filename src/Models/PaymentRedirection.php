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
     * @param string $login
     * @param string $tranKey
     * @param string $uriService
     * @param string $type soap|rest
     * @throws \Dnetix\Redirection\Exceptions\PlacetoPayException
     */
    public function __construct($login, $tranKey, $uriService = '', $type = 'rest')
    {
        parent::__construct([
            'login' => $login,
            'tranKey' => $tranKey,
            'url' => $uriService,
            'type' => $type,
        ]);
    }
}
