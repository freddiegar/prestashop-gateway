<?php

namespace PlacetoPay\Constants;

/**
 * Interface PaymentUrl
 * @package PlacetoPay\Constants
 */
interface PaymentUrl
{
    /**
     * URL Production
     */
    const PRODUCTION = 'https://secure.placetopay.com/redirection';

    /**
     * URL Test
     */
    const TEST = 'https://test.placetopay.com/redirection';

    /**
     * URL Development
     */
    const DEVELOPMENT = 'https://dev.placetopay.com/redirection';
}
