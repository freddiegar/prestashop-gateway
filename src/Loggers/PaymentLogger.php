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
        $pathLogs = '/log/';
        $logfile = 'placetopaypayment_' . date('Y-m-d') . '.log';

        if (version_compare(_PS_VERSION_, '1.7.4.0', '>=')) {
            $pathLogs = '/var/logs/';
        } elseif (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $pathLogs = '/app/logs/';
        }

        $logger = new FileLogger(0);
        $logger->setFilename(fixPath(_PS_ROOT_DIR_ . $pathLogs . $logfile));
        $logger->logDebug(print_r($message, 1));

        return true;
    }
}
