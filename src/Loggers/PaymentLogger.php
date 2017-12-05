<?php

namespace PlacetoPay\Loggers;

use \FileLogger;

/**
 * Class PaymentLogger
 * @package PlacetoPay\Loggers
 */
class PaymentLogger
{
    /**
     * @param string $message
     * @return bool
     */
    public static function log($message = '')
    {
        $logger = new FileLogger(0);

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $logger->setFilename(_PS_ROOT_DIR_ . '/app/logs/placetopaypayment_' . date('Y-m-d') . '.log');
        } else {
            $logger->setFilename(_PS_ROOT_DIR_ . '/log/placetopaypayment_' . date('Y-m-d') . '.log');
        }

        $logger->logDebug(print_r($message, 1));

        return true;
    }
}
