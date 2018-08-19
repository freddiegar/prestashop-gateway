<?php

/**
 * MIT License
 *
 * Copyright (c) 2018 Freddie Gar
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author Freddie Gar <freddie.gar@outlook.com>
 * @copyright 2018
 * @license https://github.com/freddiegar/prestashop-gateway/blob/master/LICENSE
 */

namespace PlacetoPay\Models;

use Dnetix\Redirection\PlacetoPay;

/**
 * Class PaymentRedirection
 * @package PlacetoPay\Models
 */
class PaymentRedirection extends PlacetoPay
{
    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     *
     * @param string $login
     * @param string $tranKey
     * @param string $uriService
     * @param string $type soap|rest
     * @throws \Dnetix\Redirection\Exceptions\PlacetoPayException
     */
    public function __construct($login, $tranKey, $uriService = '', $type = 'rest')
    {
        parent::__construct([
            'login' => $login,
            'tranKey' => $tranKey,
            'url' => $uriService,
            'type' => $type,
        ]);
    }
}
