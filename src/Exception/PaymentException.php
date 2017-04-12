<?php

namespace PlacetoPay\Exception;

use Exception;
use PlacetoPay\Logger\PaymentLogger;
use Throwable;

/**
 * Class PaymentException
 * @package PlacetoPay\Exception
 */
class PaymentException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        PaymentLogger::log("($code): $message");
        parent::__construct($message, $code, $previous);
    }
}
