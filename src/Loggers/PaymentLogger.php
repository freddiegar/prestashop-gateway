<?php

namespace PlacetoPay\Loggers;

use Exception;
use \FileLogger;
use \PrestaShopLogger;

/**
 * Class PaymentLogger
 * @package PlacetoPay\Loggers
 */
class PaymentLogger
{
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const NOTIFY = 99;

    /**
     * @param string $message
     * @param int $severity
     * @param null $errorCode
     * @param null $file
     * @param null $line
     * @return bool
     */
    public static function log(
        $message = '',
        $severity = self::INFO,
        $errorCode = null,
        $file = null,
        $line = null
    ) {
        $format = sprintf("[%s:%d] => [%d]\n %s", $file, $line, $errorCode, $message);

        if (self::getLogInstance()) {
            self::getLogInstance()->log($format, $severity, $errorCode);
        }

        if ($severity >= self::WARNING) {
            return self::logInDatabase(
                preg_replace(['/(\s+)/', '/(<([^>=]+)>)/'], ' ', $message),
                $severity,
                $errorCode
            );
        }

        return true;
    }

    /**
     * @param $message
     * @param int $severity
     * @param null $errorCode
     * @return bool
     */
    public static function logInDatabase($message, $severity = self::INFO, $errorCode = null)
    {
        try {
            $errorCode = $errorCode > 0 ? $errorCode : 999;

            PrestaShopLogger::addLog($message, $severity, $errorCode, getModuleName(), $errorCode);
        } catch (Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public static function getLogFilename()
    {
        static $logfile = null;

        if (is_null($logfile)) {
            $filename = sprintf('%s_%s_%s.log', (isDebugEnable() ? 'dev' : 'prod'), date('Ymd'), getModuleName());

            // PS < 1.7.0.0
            $pathLogs = '/log/';

            if (version_compare(_PS_VERSION_, '1.7.4.0', '>=')) {
                $pathLogs = '/var/logs/';
            } elseif (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $pathLogs = '/app/logs/';
            }

            $logfile = fixPath(_PS_ROOT_DIR_ . $pathLogs . $filename);
        }

        return $logfile;
    }

    /**
     * @return FileLogger|null
     */
    private static function getLogInstance()
    {
        static $logger = null;

        if (is_null($logger)) {
            $logger = false;
            $logfile = self::getLogFilename();

            if (is_writable($logfile)) {
                $logger = new FileLogger(0);
                $logger->setFilename($logfile);
            }
        }

        return $logger;
    }
}
