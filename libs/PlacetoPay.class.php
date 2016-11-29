<?php

/**
 * Incluye las librerias
 */
// require

use Dnetix\Redirection\PlacetoPay as Redirection;
use Dnetix\Redirection\Validators\Currency;

/**
 * Clase para la definición de excepciones
 */
class PlacetoPayException extends Exception
{
}

/**
 * Clase para el procesamiento de pagos a traves de PlacetoPay.
 */
class PlacetoPay
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
    const P2P_DEVELOPMENT = 'http://redirection.dnetix.co/';

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
     * [$redirection description]
     * @var null
     */
    public $redirection = NULL;

    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     *
     * @return \PlacetoPay
     */
    function __construct($login, $trankey, $uri_service = '')
    {

        if (empty($login)) {
            new PlacetoPayException('Login Place to Pay is required.');
        }

        if (empty($trankey)) {
            new PlacetoPayException('TranKey Place to Pay is required.');
        }

        $this->redirection = new Redirection([
            'login' => $login,
            'tranKey' => $trankey,
            'url' => $uri_service,
        ]);
    }

}