<?php

use Dnetix\Redirection\PlacetoPay as Redirection;

/**
 * Clase para el procesamiento de pagos a traves de PlacetoPay.
 */
class PlaceToPay extends Redirection
{

    /**
     * URI para el caso de produccion
     */
    const P2P_PRODUCTION = 'https://secure.placetopay.com/redirection';

    /**
     * URI para el caso de pruebas
     */
    const P2P_TEST = 'https://test.placetopay.com/redirection';

    /**
     * URI para el caso de desarrollo
     */
    const P2P_DEVELOPMENT = 'https://dev.placetopay.com/redirection';

    /**
     * Indicador de transaccion fallida
     */
    const P2P_FAILED = 0;

    /**
     * Indicador de transaccion exitosa
     */
    const P2P_APPROVED = 1;

    /**
     * Indicador de transaccion declinada
     */
    const P2P_DECLINED = 2;

    /**
     * Indicador de transaccion pendiente
     */
    const P2P_PENDING = 3;

    /**
     * Indicador de transaccion duplicada (previamente aprobada)
     */
    const P2P_DUPLICATE = 4;

    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     */
    function __construct($login, $trankey, $uri_service = '')
    {
        parent::__construct([
            'login' => $login,
            'tranKey' => $trankey,
            'url' => $uri_service,
        ]);
    }

}