<?php

namespace PlacetoPay\Exceptions;

use Exception;
use PlacetoPay\Loggers\PaymentLogger;
use Throwable;

/**
 * Class PaymentException
 * @package PlacetoPay\Exceptions
 */
class PaymentException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        PaymentLogger::log($message, PaymentLogger::ERROR, $code, $this->getFile(), $this->getLine());

        parent::__construct($message, $code, $previous);
    }
}
