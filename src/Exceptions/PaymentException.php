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
        PaymentLogger::log(sprintf("Error on [%s:%d] => [%d]\n %s", $this->getFile(), $this->getLine(), $code, $message));
        parent::__construct($message, $code, $previous);
    }
}
