<?php

namespace PlacetoPay\Constants;

/**
 * Class PaymentMethod
 * @package PlacetoPay\Constants
 */
abstract class PaymentMethod
{
    const DELIMITER = ',';

    const PAYMENT_METHODS = [
        CountryCode::COLOMBIA => [
            'CR_VS' => 'Visa',
            'CR_CR' => 'Credencial Banco de Occidente',
            'CR_VE' => 'Visa Electron',
            'CR_DN' => 'Diners Club',
            'CR_AM' => 'American Express',
            'RM_MC' => 'MasterCard',
            'TY_EX' => 'Tarjeta Éxito',
            'TY_AK' => 'Alkosto',
            '_PSE_' => 'Débito a cuentas corrientes y ahorros (PSE)',
            'SFPAY' => 'Safety Pay',
            '_ATH_' => 'Corresponsales bancarios Grupo Aval',
            'AC_WU' => 'Western Union',
            'PYPAL' => 'PayPal',
            'T1_BC' => 'Bancolombia Recaudos',
            'AV_BO' => 'Banco de Occidente Recaudos',
            'AV_AV' => 'Banco AV Villas Recaudos',
            'AV_BB' => 'Banco de Bogotá Recaudos',
            'VISAC' => 'Visa Checkout',
            'GNPIN' => 'GanaPIN',
            'GNRIS' => 'Tarjeta RIS',
            'MSTRP' => 'Masterpass',
            'DBTAC' => 'Registro cuentas débito',
            '_PPD_' => 'Débito pre-autorizado (PPD)',
            'CR_DS' => 'Discover',
            'EFCTY' => 'Efecty',
        ],
        CountryCode::ECUADOR => [
            'ID_VS' => 'Visa',
            'ID_MC' => 'MasterCard',
            'ID_DN' => 'Diners Club',
            'ID_DS' => 'Discover',
            'ID_AM' => 'American Express',
            'ID_CR' => 'Credencial Banco de Occidente',
            'ID_VE' => 'Visa Electron',
        ]
    ];

    /**
     * @param $code
     * @return array
     */
    public static function getByCountryCode($code)
    {
        return !empty(self::PAYMENT_METHODS[$code]) && is_array(self::PAYMENT_METHODS[$code])
            ? self::PAYMENT_METHODS[$code]
            : [];
    }

    /**
     * @param array $paymentMethods
     * @param string $countryCode
     * @return string
     */
    public static function getPaymentMethodsSelected($paymentMethods, $countryCode)
    {
        $paymentMethodsSelected = $paymentMethods;
        $paymentMethodsValid = [];

        foreach ($paymentMethodsSelected as $index => $code) {
            if (in_array($code, array_keys(self::getPaymentMethodsAvailable($countryCode)))) {
                $paymentMethodsValid[] = $code;
            }
        }

        return PaymentMethod::toString($paymentMethodsValid);
    }

    /**
     * @param $countryCode
     * @return array
     */
    public static function getPaymentMethodsAvailable($countryCode)
    {
        return PaymentMethod::getByCountryCode($countryCode);
    }

    /**
     * @param $paymentMethods
     * @return array
     */
    public static function toArray($paymentMethods)
    {
        return explode(self::DELIMITER, $paymentMethods);
    }

    /**
     * @param $paymentMethods
     * @return string
     */
    public static function toString($paymentMethods)
    {
        return implode(self::DELIMITER, $paymentMethods);
    }
}
