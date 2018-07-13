<?php

namespace PlacetoPay\Constants;

/**
 * Class PaymentUrl
 * @package PlacetoPay\Constants
 */
abstract class PaymentUrl
{
    /**
     * @return array
     */
    public static function getDefaultEndpoints()
    {
        return [
            Environment::PRODUCTION => 'https://secure.placetopay.com/redirection',
            Environment::TEST => 'https://test.placetopay.com/redirection',
            Environment::DEVELOPMENT => 'https://dev.placetopay.com/redirection',
        ];
    }

    /**
     * @param string $countryCode Value of Constants\CountryCode
     * @return array
     */
    public static function getEndpointsTo($countryCode)
    {
        switch ($countryCode) {
            case CountryCode::ECUADOR:
                $endpoints = [
                    Environment::PRODUCTION => 'https://secure.placetopay.ec/redirection',
                    Environment::TEST => 'https://test.placetopay.ec/redirection',
                    Environment::DEVELOPMENT => 'https://dev.placetopay.ec/redirection',
                ];
                break;
            case CountryCode::MEXICO:
            case CountryCode::PERU:
            case CountryCode::COLOMBIA:
            default:
                $endpoints = self::getDefaultEndpoints();
                break;
        }

        return $endpoints;
    }
}
