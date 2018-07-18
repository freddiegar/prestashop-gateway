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
        $logger->setFilename(self::getLogFilename());
        $logger->logDebug(print_r($message, 1));

        return true;
    }

    /**
     * @return string
     */
    public static function getLogFilename()
    {
        $logfile = sprintf('%s_%s.log', getModuleName(), date('Y-m-d'));

        // PS < 1.7.0.0
        $pathLogs = '/log/';

        if (version_compare(_PS_VERSION_, '1.7.4.0', '>=')) {
            $pathLogs = '/var/logs/';
        } elseif (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $pathLogs = '/app/logs/';
        }

        return fixPath(_PS_ROOT_DIR_ . $pathLogs . $logfile);
    }
}
