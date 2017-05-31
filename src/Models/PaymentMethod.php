<?php

namespace PlacetoPay\Models;

use Dnetix\Redirection\Message\RedirectInformation;
use PlacetoPay\Constants\Environment;
use PlacetoPay\Constants\PaymentStatus;
use PlacetoPay\Constants\PaymentUrl;
use PlacetoPay\Exceptions\PaymentException;
use \PaymentModule;
use \Configuration;
use \Db;
use \OrderState;
use \Language;
use \Tools;
use \Cart;
use \Validate;
use \AdminController;
use \Customer;
use \Address;
use \Currency;
use \CurrencyCore;
use \Order;
use \OrderHistory;
use \OrderDetail;
use \Product;
use \Context;
use \Country;
use \State;
use \Shop;
use \Exception;

/**
 * Class PlaceToPayPaymentMethod
 */
class PaymentMethod extends PaymentModule
{
    /**
     * Configuration module vars
     */
    const COMPANY_DOCUMENT = 'PLACETOPAY_COMPANYDOCUMENT';
    const COMPANY_NAME = 'PLACETOPAY_COMPANYNAME';
    const DESCRIPTION = 'PLACETOPAY_DESCRIPTION';

    const EMAIL_CONTACT = 'PLACETOPAY_EMAILCONTACT';
    const TELEPHONE_CONTACT = 'PLACETOPAY_TELEPHONECONTACT';

    const LOGIN = 'PLACETOPAY_LOGIN';
    const TRAN_KEY = 'PLACETOPAY_TRANKEY';
    const ENVIRONMENT = 'PLACETOPAY_ENVIRONMENT';
    const STOCK_REINJECT = 'PLACETOPAY_STOCKREINJECT';
    const CIFIN_MESSAGE = 'PLACETOPAY_CIFINMESSAGE';
    const HISTORY_CUSTOMIZED = 'PLACETOPAY_HISTORYCUSTOMIZED';

    const OPTION_ENABLED = '1';
    const OPTION_DISABLED = '0';

    const ORDER_STATE = 'PS_OS_PLACETOPAY';

    /**
     * @var string
     */
    private $tablePayment = '';

    /**
     * @var string
     */
    private $tableOrder = '';


    /**
     * PlacetoPayPayment constructor.
     */
    public function __construct()
    {
        /**
         * PHP < 5.6 no allowed this definitions in constructor
         */
        $this->tablePayment = _DB_PREFIX_ . 'payment_placetopay';
        $this->tableOrder = _DB_PREFIX_ . 'orders';

        $this->name = 'placetopaypayment';
        $this->version = '2.4.1';
        $this->author = 'EGM Ingeniería sin Fronteras S.A.S';
        $this->tab = 'payments_gateways';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');

        parent::__construct();

        if (isset($this->context->controller)) {
            $this->context->controller->addCSS($this->_path . 'views/css/style.css', 'all');
        }

        $this->displayName = $this->l('Place to Pay');
        $this->description = $this->l('Accept payments by credit cards and debits account');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }


    /**
     * @return string
     */
    private function getVersion()
    {
        return $this->version;
    }

    /**
     * Create payments table and status order
     *
     * @return bool
     * @throws PaymentException
     */
    public function install()
    {
        switch (true) {
            case !parent::install():
                throw new PaymentException('error on install', 101);
            case !$this->createPaymentTable():
                throw new PaymentException('error on install', 102);
            case !$this->createOrderState();
                throw new PaymentException('error on install', 103);
            case !$this->alterColumnIpAddress();
                throw new PaymentException('error on install', 104);
            case !$this->addColumnEmail();
                throw new PaymentException('error on install', 105);
            case !$this->addColumnRequestId();
                throw new PaymentException('error on install', 106);
            case !$this->addColumnReference();
                throw new PaymentException('error on install', 107);
            case !$this->registerHook('payment'):
                throw new PaymentException('error on install', 108);
            case !$this->registerHook('paymentReturn');
                throw new PaymentException('error on install', 109);
                break;
        }

        // Default values
        Configuration::updateValue(self::COMPANY_DOCUMENT, '');
        Configuration::updateValue(self::COMPANY_NAME, '');
        Configuration::updateValue(self::DESCRIPTION, 'Pago en PlacetoPay No: %s');

        Configuration::updateValue(self::EMAIL_CONTACT, '');
        Configuration::updateValue(self::TELEPHONE_CONTACT, '');

        Configuration::updateValue(self::LOGIN, '');
        Configuration::updateValue(self::TRAN_KEY, '');
        Configuration::updateValue(self::ENVIRONMENT, Environment::TEST);
        Configuration::updateValue(self::STOCK_REINJECT, self::OPTION_ENABLED);
        Configuration::updateValue(self::CIFIN_MESSAGE, self::OPTION_DISABLED);
        Configuration::updateValue(self::HISTORY_CUSTOMIZED, self::OPTION_ENABLED);

        return true;
    }

    /**
     * Delete configuration vars
     * This not delete tables and status order
     *
     * @return bool
     */
    public function uninstall()
    {
        if (
            !Configuration::deleteByName(self::COMPANY_DOCUMENT)
            || !Configuration::deleteByName(self::COMPANY_NAME)
            || !Configuration::deleteByName(self::DESCRIPTION)

            || !Configuration::deleteByName(self::EMAIL_CONTACT)
            || !Configuration::deleteByName(self::TELEPHONE_CONTACT)

            || !Configuration::deleteByName(self::LOGIN)
            || !Configuration::deleteByName(self::TRAN_KEY)
            || !Configuration::deleteByName(self::ENVIRONMENT)
            || !Configuration::deleteByName(self::STOCK_REINJECT)
            || !Configuration::deleteByName(self::CIFIN_MESSAGE)
            || !Configuration::deleteByName(self::HISTORY_CUSTOMIZED)
            || !parent::uninstall()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Create payment table
     *
     * @return bool
     */
    private function createPaymentTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->tablePayment}` (
                `id_payment` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_order` INT UNSIGNED NOT NULL,
                `id_currency` INT UNSIGNED NOT NULL,
                `date` DATETIME NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `status` TINYINT NOT NULL,
                `reason` VARCHAR(2) NULL,
                `reason_description` VARCHAR(255) NULL,
                `franchise` VARCHAR(5) NULL,
                `franchise_name` VARCHAR(128) NULL,
                `bank` VARCHAR(128) NULL,
                `authcode` VARCHAR(12) NULL,
                `receipt` VARCHAR(12) NULL,
                `conversion` DOUBLE,
                `ip_address` VARCHAR(30) NULL,
                INDEX `id_orderIX` (`id_order`)
            ) ENGINE = " . _MYSQL_ENGINE_;

        if (Db::getInstance()->Execute($sql)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function addColumnEmail()
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `payer_email` VARCHAR(80) NULL;";
        Db::getInstance()->Execute($sql);
        return true;
    }

    /**
     * @return bool
     */
    private function addColumnRequestId()
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `id_request` INT NULL;";
        Db::getInstance()->Execute($sql);
        return true;
    }

    /**
     * @return bool
     */
    private function addColumnReference()
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `reference` VARCHAR(60) NULL;";
        Db::getInstance()->Execute($sql);
        return true;
    }

    /**
     * @return bool
     */
    private function alterColumnIpAddress()
    {
        // In all version < 2.0 this columns is bad name ipaddress => ip_address
        $sql = "ALTER TABLE `{$this->tablePayment}` CHANGE COLUMN `ipaddress` `ip_address` VARCHAR(30) NULL;";
        Db::getInstance()->Execute($sql);
        return true;
    }

    /**
     * Create status order to Place To Pay
     *
     * @return bool
     */
    private function createOrderState()
    {
        if (!$this->getOrderState()) {
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() AS $language) {
                switch (strtolower($language['iso_code'])) {
                    case 'en':
                        $orderState->name[$language['id_lang']] = 'Awaiting ' . $this->displayName . ' payment confirmation';
                        break;
                    case 'fr':
                        $orderState->name[$language['id_lang']] = 'En attente du paiement par ' . $this->displayName;
                        break;
                    case 'es':
                    default:
                        $orderState->name[$language['id_lang']] = 'En espera de confirmación de pago por ' . $this->displayName;
                        break;
                }
            }
            $orderState->color = 'lightblue';
            $orderState->hidden = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            $orderState->delivery = false;
            $orderState->send_email = false;
            $orderState->unremovable = true;

            if ($orderState->save()) {
                Configuration::updateValue(self::ORDER_STATE, $orderState->id);
                copy($this->getPathThisModule() . '/views/img/logo.png', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Show and save configuration page
     */
    public function getContent()
    {
        $html = $this->saveConfiguration();
        $html .= $this->displayConfiguration();

        return $html;
    }

    /**
     * Update configuration vars
     *
     * @return bool
     */
    private function saveConfiguration()
    {
        if (!Tools::isSubmit('submitPlacetoPayConfiguraton')) {
            return false;
        }

        $errors = array();

        // Company data
        Configuration::updateValue(self::COMPANY_DOCUMENT, Tools::getValue('companydocument'));
        Configuration::updateValue(self::COMPANY_NAME, Tools::getValue('companyname'));
        Configuration::updateValue(self::DESCRIPTION, Tools::getValue('description'));
        // Contact data
        Configuration::updateValue(self::EMAIL_CONTACT, Tools::getValue('email'));
        Configuration::updateValue(self::TELEPHONE_CONTACT, Tools::getValue('telephone'));
        // Redirection data
        Configuration::updateValue(self::LOGIN, Tools::getValue('login'));
        Configuration::updateValue(self::TRAN_KEY, Tools::getValue('trankey'));
        Configuration::updateValue(self::ENVIRONMENT, Tools::getValue('environment'));

        // Stock re-inject option
        Configuration::updateValue(self::STOCK_REINJECT, (Tools::getValue('stockreinject') == '1' ? self::OPTION_ENABLED : self::OPTION_DISABLED));
        // Cifin message option
        Configuration::updateValue(self::CIFIN_MESSAGE, (Tools::getValue('cifinmessage') == '1' ? self::OPTION_ENABLED : self::OPTION_DISABLED));
        // History option
        Configuration::updateValue(self::HISTORY_CUSTOMIZED, (Tools::getValue('historycustomized') == '1' ? self::OPTION_ENABLED : self::OPTION_DISABLED));

        if (!empty($errors)) {
            $error_msg = '';
            foreach ($errors as $error)
                $error_msg .= $error . '<br />';
            return $this->displayError($error_msg);
        } else {
            return $this->displayConfirmation($this->l('Place to Pay settings updated'));
        }
    }

    /**
     * Show configuration form
     *
     * @return string
     */
    private function displayConfiguration()
    {
        global $smarty;

        $smarty->assign(
            array(
                'actionURL' => Tools::safeOutput($_SERVER['REQUEST_URI']),
                'actionBack' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),

                'version' => $this->getVersion(),

                'companydocument' => $this->getCompanyDocument(),
                'companyname' => $this->getCompanyName(),
                'description' => $this->getDescription(),
                'email' => $this->getEmailContact(),
                'telephone' => $this->getTelephoneContact(),

                'login' => $this->getLogin(),
                'trankey' => $this->getTrankey(),
                'urlnotification' => $this->getReturnURL('process.php'),
                'schudeletask' => $this->getPathScheduleTask(),
                'environment' => $this->getEnvironment(),
                'stockreinject' => $this->getStockReinject(),
                'cifinmessage' => $this->getCifinMessage(),
                'historycustomized' => $this->getHistoryCustomized(),
            )
        );

        return $this->display($this->getPathThisModule(), '/views/templates/setting.tpl');
    }

    /**
     * Show payment method in checkout
     *
     * @param array $params
     * @return string
     */
    public function hookPayment($params)
    {
        global $smarty;

        if (!$this->active)
            return false;

        if (
            empty($this->getLogin())
            || empty($this->getTrankey())
            || empty($this->getEnvironment())
        ) {
            return false;
        }

        $lastPendingTransaction = $this->getLastPendingTransaction($params['cart']->id_customer);
        if (!empty($lastPendingTransaction)) {
            $smarty->assign(array(
                'hasPending' => true,
                'lastOrder' => $lastPendingTransaction['reference'],
                'lastAuthorization' => (string)$lastPendingTransaction['authcode'],
                'storeEmail' => $this->getEmailContact(),
                'storePhone' => $this->getTelephoneContact()
            ));
        } else {
            $smarty->assign('hasPending', false);
        }

        $smarty->assign('module', $this->name);
        $smarty->assign('sitename', Configuration::get('PS_SHOP_NAME'));
        $smarty->assign('cifinmessage', $this->getCifinMessage());
        $smarty->assign('companyname', $this->getCompanyName());

        return $this->display($this->getPathThisModule(), '/views/templates/payment.tpl');
    }

    /**
     * Last transaction pending to current costumer
     *
     * @param $customer_id
     * @return mixed
     */
    private function getLastPendingTransaction($customer_id)
    {
        $status = PaymentStatus::PENDING;

        $result = Db::getInstance()->ExecuteS("
            SELECT p.* 
            FROM `{$this->tablePayment}` p
                INNER JOIN `{$this->tableOrder}` o ON o.id_cart = p.id_order
            WHERE o.`id_customer` = {$customer_id} 
                AND p.`status` = {$status} 
            LIMIT 1
        ");

        if (!empty($result)) {
            $result = $result[0];
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        switch ($this->getEnvironment()) {
            case Environment::PRODUCTION:
                $uri = PaymentUrl::PRODUCTION;
                break;
            case Environment::TEST:
                $uri = PaymentUrl::TEST;
                break;
            case Environment::DEVELOPMENT:
                $uri = PaymentUrl::DEVELOPMENT;
                break;
            default:
                $uri = null;
                break;
        }

        return $uri;
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return Configuration::get(self::ENVIRONMENT);
    }

    /**
     * @return mixed
     */
    public function getLogin()
    {
        return Configuration::get(self::LOGIN);
    }

    /**
     * @return mixed
     */
    public function getTrankey()
    {
        return Configuration::get(self::TRAN_KEY);
    }

    /**
     * @return mixed
     */
    public function getCompanyDocument()
    {
        return Configuration::get(self::COMPANY_DOCUMENT);
    }

    /**
     * @return mixed
     */
    public function getCompanyName()
    {
        return Configuration::get(self::COMPANY_NAME);
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return Configuration::get(self::DESCRIPTION);
    }

    /**
     * @return mixed
     */
    public function getEmailContact()
    {
        $emailContact = Configuration::get(self::EMAIL_CONTACT);
        return (empty($emailContact) ? Configuration::get('PS_SHOP_EMAIL') : $emailContact);
    }


    /**
     * @return mixed
     */
    public function getTelephoneContact()
    {
        $telephoneContact = Configuration::get(self::TELEPHONE_CONTACT);
        return (empty($telephoneContact) ? Configuration::get('PS_SHOP_PHONE') : $telephoneContact);
    }

    /**
     * @return mixed
     */
    public function getStockReinject()
    {
        return Configuration::get(self::STOCK_REINJECT);
    }

    /**
     * @return mixed
     */
    public function getCifinMessage()
    {
        return Configuration::get(self::CIFIN_MESSAGE);
    }

    /**
     * @return mixed
     */
    public function getHistoryCustomized()
    {
        return Configuration::get(self::HISTORY_CUSTOMIZED);
    }

    /**
     * @return mixed
     */
    public function getOrderState()
    {
        return Configuration::get(self::ORDER_STATE);
    }

    /**
     * @param string $page process.php
     * @param string $params Query string to add in URL, please include symbol (?), eg: ?var=foo
     * @return string
     */
    public function getReturnURL($page, $params = '')
    {

        $protocol = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://');
        $domain = (Configuration::get('PS_SHOP_DOMAIN_SSL')) ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Tools::getHttpHost();

        return $protocol . $domain . __PS_BASE_URI__ . 'modules/' . $this->name . '/' . $page . $params;
    }

    /**
     * @return string
     */
    private function getPathScheduleTask()
    {
        return $this->getPathThisModule() . '/sonda.php';
    }

    /**
     * @return string
     */
    private function getPathThisModule()
    {
        return _PS_MODULE_DIR_ . $this->name;
    }

    /**
     * @param null $cart_id
     * @return Order
     * @throws PaymentException
     */
    private function getRelatedOrder($cart_id = null)
    {
        $order_id = Order::getOrderByCartId($cart_id);

        if (!$order_id) {
            throw new PaymentException(Tools::displayError(), 201);
        }

        $order = new Order($order_id);

        if (!Validate::isLoadedObject($order)) {
            throw new PaymentException(Tools::displayError(), 202);
        }

        return $order;
    }

    /**
     * Redirect to Place to pay Platform
     *
     * @param Cart $cart
     * @throws PaymentException
     */
    public function redirect(Cart $cart)
    {
        $language = Language::getIsoById((int)($cart->id_lang));
        $customer = new Customer((int)($cart->id_customer));
        $currency = new Currency((int)($cart->id_currency));
        $invoice_address = new Address((int)($cart->id_address_invoice));
        $delivery_address = new Address((int)($cart->id_address_delivery));
        $total_amount = floatval($cart->getOrderTotal(true));
        $tax_amount = floatval($total_amount - floatval($cart->getOrderTotal(false)));

        if (!Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($invoice_address)
            || !Validate::isLoadedObject($delivery_address)
            || !Validate::isLoadedObject($currency)
        ) {
            throw new PaymentException('invalid address or customer', 301);
        }

        $delivery_country = new Country((int)($delivery_address->id_country));
        $delivery_state = null;
        if ($delivery_address->id_state) {
            $delivery_state = new State((int)($delivery_address->id_state));
        }

        try {
            $order_message = 'Success';
            $order_status = $this->getOrderState();
            $request_id = 0;
            $expiration = date('c', strtotime('+2 days'));
            $ip_address = (new RemoteAddress())->getIpAddress();
            $return_url = $this->getReturnURL('process.php', '?cart_id=' . $cart->id);

            // Create order in prestashop
            $this->validateOrder(
                $cart->id,
                $order_status,
                $total_amount,
                $this->displayName,
                $order_message,
                null,
                null,
                false,
                $cart->secure_key
            );

            // After order create in validateOrder
            $reference = $this->currentOrderReference;

            // Request payment
            $request = [
                'locale' => ($language == 'en') ? 'en_US' : 'es_CO',
                'returnUrl' => $return_url,
                'ip_address' => $ip_address,
                'expiration' => $expiration,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'buyer' => [
                    'name' => $delivery_address->firstname,
                    'surname' => $delivery_address->lastname,
                    'email' => $customer->email,
                    'mobile' => $delivery_address->phone_mobile,
                    'address' => [
                        'country' => $delivery_country->iso_code,
                        'state' => (empty($delivery_state) ? null : $delivery_state->name),
                        'city' => $delivery_address->city,
                        'street' => $delivery_address->address1 . " " . $delivery_address->address2,
                    ]
                ],
                'payment' => [
                    'reference' => $reference,
                    'description' => sprintf($this->getDescription(), $reference),
                    'amount' => [
                        'currency' => $currency->iso_code,
                        'total' => $total_amount,
                    ]
                ]
            ];

            if ($tax_amount > 0) {
                // Add taxes
                $request['payment']['amount']['taxes'] = [
                    [
                        'kind' => 'valueAddedTax',
                        'amount' => $tax_amount,
                        'base' => $total_amount - $tax_amount,
                    ]
                ];
            }

            $transaction = (new PaymentRedirection($this->getLogin(), $this->getTrankey(), $this->getUri()))->request($request);

            if ($transaction->isSuccessful()) {
                $_SESSION['request_id'] = $request_id = $transaction->requestId();
                $status = PaymentStatus::PENDING;
                // Redirect to payment:
                $payment_url = $transaction->processUrl();
            } else {
                $order_message = $transaction->status()->message();
                $status = PaymentStatus::FAILED;
                $total_amount = 0;
                // Redirect to error:
                $payment_url = __PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cart->id
                    . '&id_module=' . $this->id
                    . '&id_order=' . $this->currentOrder
                    . '&key=' . $cart->secure_key;

                $history = new OrderHistory();
                $history->id_order = $this->currentOrder;
                $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                $history->save();
            }

            // Register request
            $this->insertPaymentPlaceToPay($request_id, $cart->id, $cart->id_currency, $total_amount, $status, $order_message, $ip_address, $reference);

            // Redirect flow
            Tools::redirectLink($payment_url);
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 302);
        }
    }

    /**
     * Register payment
     *
     * @param $request_id
     * @param $order_id
     * @param $currency_id
     * @param $amount
     * @param $status
     * @param $message
     * @param $ip_address
     * @param $reference
     * @return bool
     * @throws PaymentException
     */
    private function insertPaymentPlaceToPay($request_id, $order_id, $currency_id, $amount, $status, $message, $ip_address, $reference)
    {
        $reason = '';
        $date = date('Y-m-d H:i:s');
        $reason_description = pSQL($message);
        $conversion = 1;

        $sql = "
            INSERT INTO {$this->tablePayment} (
                id_order,
                id_currency,
                date,
                amount,
                status,
                reason,
                reason_description,
                conversion,
                ip_address,
                id_request,
                reference
            ) VALUES (
                '$order_id',
                '$currency_id',
                '$date',
                '$amount',
                '$status',
                '$reason',
                '$reason_description',
                '$conversion',
                '$ip_address',
                '$request_id',
                '$reference'
            )
        ";

        if (!Db::getInstance()->Execute($sql)) {
            throw new PaymentException('Cannot insert transaction ' . $sql, 401);
        }

        return true;
    }

    /**
     * Process response from Place to Pay Platform
     *
     * @param null $cart_id
     * @throws PaymentException
     */
    public function process($cart_id = null)
    {

        if (!is_null($cart_id) && !empty($_SESSION['request_id'])) {
            // From payment URL to return URL
            $request_id = $_SESSION['request_id'];
        } elseif (!empty(file_get_contents("php://input"))) {
            // From sonda process
            $json = file_get_contents("php://input");
            $obj = json_decode($json);
            $request_id = $obj->requestId;
            $cart_id = $this->getCartByRequestId((int)$request_id);
        } else {
            // Option no valid
            throw new PaymentException('option not valid in process', 501);
        }

        $order = $this->getRelatedOrder($cart_id);
        $response = (new PaymentRedirection($this->getLogin(), $this->getTrankey(), $this->getUri()))->query($request_id);
        if ($response->isSuccessful()) {
            $status = $this->getStatusPayment($response);

            // Set status order in CMS
            $this->settleTransaction($status, $cart_id, $order, $response);

            if (!isset($json)) {
                // Redirect to confirmation page
                Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cart_id
                    . '&id_module=' . $this->id
                    . '&id_order=' . $order->id
                    . '&key=' . $order->secure_key
                );
            } else {
                // Show status to reference in console
                echo "{$order->reference} [{$status}]" . PHP_EOL;
            }
        } else {
            throw new PaymentException($response->status()->message(), 502);
        }
    }

    /**
     * @param int $id_request
     * @return bool
     */
    private function getCartByRequestId($id_request = 0)
    {
        $request_id = (!empty($id_request)) ? $id_request : 0;

        $rows = Db::getInstance()->ExecuteS("
            SELECT id_order 
            FROM  `{$this->tablePayment}` 
            WHERE id_request = {$request_id}
        ");

        return (!empty($rows[0]['id_order'])) ? $rows[0]['id_order'] : false;
    }

    /**
     * @param RedirectInformation $response
     * @return int
     */
    public function getStatusPayment(RedirectInformation $response)
    {
        // By default is pending so make a query for it later (see information.php example)
        $status = PaymentStatus::PENDING;
        $lastPayment = $response->lastTransaction();

        if ($response->isSuccessful() && !empty($lastPayment)) {
            // In order to use the functions please refer to the RedirectInformation class
            if ($lastPayment->status()->isApproved()) {
                // Approved status
                $status = PaymentStatus::APPROVED;
            } elseif ($lastPayment->status()->isRejected()) {
                // This is why it has been rejected
                $status = PaymentStatus::REJECTED;
            }
        } elseif ($response->status()->isRejected()) {
            // Canceled by user
            $status = PaymentStatus::REJECTED;
        }

        return $status;
    }

    /**
     * @param $status
     * @param $cart_id
     * @param Order $order
     * @param RedirectInformation $response
     */
    private function settleTransaction($status, $cart_id, Order $order, RedirectInformation $response)
    {
        // Order not has been processed
        if ($order->getCurrentState() != (int)Configuration::get('PS_OS_PAYMENT')) {
            switch ($status) {
                case PaymentStatus::FAILED:
                case PaymentStatus::REJECTED:
                    if (
                    in_array(
                        $order->getCurrentState(),
                        [
                            Configuration::get('PS_OS_ERROR'),
                            Configuration::get('PS_OS_CANCELED')
                        ]
                    )
                    ) {
                        break;
                    }

                    // Update status order
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);

                    if ($status == PaymentStatus::FAILED) {
                        $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                    } elseif ($status == PaymentStatus::REJECTED) {
                        $history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $history->id_order);
                    }
                    $history->addWithemail();
                    $history->save();

                    if ($this->getStockReinject() == self::OPTION_ENABLED) {
                        $products = $order->getProducts();
                        foreach ($products as $product) {
                            $order_detail = new OrderDetail((int)($product['id_order_detail']));
                            Product::reinjectQuantities($order_detail, $product['product_quantity']);
                        }
                    }
                    break;
                case PaymentStatus::DUPLICATE:
                case PaymentStatus::APPROVED:
                    // genera un nuevo estado en la orden de aprobación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $history->id_order);
                    $history->addWithemail();
                    $history->save();
                    break;
                case PaymentStatus::PENDING:
                    break;
            }
        }

        // Update status in payment table
        $this->updateTransaction($cart_id, $status, $response);
    }

    /**
     * @param $id_order
     * @param $status
     * @param $response
     * @return bool
     * @throws PaymentException
     */
    private function updateTransaction($id_order, $status, RedirectInformation $response)
    {
        $date = pSQL($response->status()->date());
        $reason = pSQL($response->status()->reason());
        $reason_description = pSQL($response->status()->message());

        $bank = '';
        $franchise = '';
        $franchise_name = '';
        $auth_code = '';
        $receipt = '';
        $conversion = '';
        $payer_email = '';

        if (!empty($response->lastTransaction())) {
            $payment = $response->lastTransaction();

            $date = pSQL($payment->status()->date());
            $reason = pSQL($payment->status()->reason());
            $reason_description = pSQL($payment->status()->message());

            $bank = pSQL($payment->issuerName());
            $franchise = pSQL($payment->paymentMethod());
            $franchise_name = pSQL($payment->paymentMethodName());
            $auth_code = pSQL($payment->authorization());
            $receipt = pSQL($payment->receipt());
            $conversion = pSQL($payment->amount()->factor());
        }

        if (!empty($response->request()->payer()) && !empty($response->request()->payer()->email())) {
            $payer_email = pSQL($response->request()->payer()->email());
        }

        $sql = "
            UPDATE `{$this->tablePayment}` SET
                `date` = '{$date}',
                `status` = {$status},
                `reason` = '{$reason}',
                `reason_description` = '{$reason_description}',
                `franchise` = '{$franchise}',
                `franchise_name` = '{$franchise_name}',
                `bank` = '{$bank}',
                `authcode` = '{$auth_code}',
                `receipt` = '{$receipt}',
                `conversion` = '{$conversion}',
                `payer_email` = '{$payer_email}'
            WHERE `id_order` = {$id_order}
        ";

        if (!Db::getInstance()->Execute($sql)) {
            throw new PaymentException('Cannot update transaction ' . $sql, 601);
        }

        return true;
    }

    /**
     * Show information response payment to customer
     *
     * @param $params
     * @return bool|mixed
     */
    public function hookPaymentReturn($params)
    {

        if ((!$this->active) || ($params['objOrder']->module != $this->name)) {
            return false;
        }

        if ($this->getHistoryCustomized() == self::OPTION_ENABLED) {
            return $this->getPaymentHistory();
        } else {
            return $this->getPaymentDetails($params);
        }

    }

    /**
     * @param $params
     * @return mixed
     */
    private function getPaymentDetails($params)
    {
        global $smarty;

        // Get information
        $transaction = $this->getTransactionInformation($params['objOrder']->id_cart);
        $cart = new Cart((int)$params['objOrder']->id_cart);
        $invoice_address = new Address((int)($cart->id_address_invoice));
        $total_amount = (float)($cart->getOrderTotal(true, Cart::BOTH));
        $tax_amount = $total_amount - (float)($cart->getOrderTotal(false, Cart::BOTH));
        $payer_email = $transaction['payer_email'];
        $transaction['tax'] = $tax_amount;

        $smarty->assign('transaction', $transaction);

        switch ($transaction['status']) {
            case PaymentStatus::APPROVED:
            case PaymentStatus::DUPLICATE:
                $smarty->assign('status', 'ok');
                $smarty->assign('status_description', 'Transacción aprobada');
                break;
            case PaymentStatus::FAILED:
                $smarty->assign('status', 'fail');
                $smarty->assign('status_description', 'Transacción fallida');
                break;
            case PaymentStatus::REJECTED:
                $smarty->assign('status', 'rejected');
                $smarty->assign('status_description', 'Transacción rechazada');
                break;
            case PaymentStatus::PENDING:
                $smarty->assign('status', 'pending');
                $smarty->assign('status_description', 'Transacción pendiente');
                break;
        }
        $smarty->assign($params);

        $smarty->assign('companyDocument', $this->getCompanyDocument());
        $smarty->assign('companyName', $this->getCompanyName());
        $smarty->assign('paymentDescription', sprintf($this->getDescription(), $transaction['reference']));

        $smarty->assign('storeEmail', $this->getEmailContact());
        $smarty->assign('storePhone', $this->getTelephoneContact());

        // Customer data
        $customer = new Customer((int)($params['objOrder']->id_customer));

        if (Validate::isLoadedObject($customer)) {
            if (empty($invoice_address)) {
                $smarty->assign('payerName', $customer->firstname . ' ' . $customer->lastname);
                $smarty->assign('payerEmail', $customer->email);
            } else {
                $smarty->assign('payerName', $invoice_address->firstname . ' ' . $invoice_address->lastname);
                $smarty->assign('payerEmail', (isset($payer_email) ? $payer_email : $customer->email));
            }
        }

        $smarty->assign('placetopayImgUrl', _MODULE_DIR_ . $this->name . '/views/img/');

        // Currency data
        $currency = new CurrencyCore($cart->id_currency);
        $currency_iso = $currency->iso_code;
        $smarty->assign('currency_iso', $currency_iso);
        $smarty->assign('customer_id', $cart->id_customer);
        $smarty->assign('logged', (Context::getContext()->customer->isLogged() ? true : false));
        $context = Context::getContext();
        $context->cookie->__set('customer_id', $cart->id_customer);

        return $this->display($this->getPathThisModule(), '/views/templates/response.tpl');
    }

    /**
     * @return mixed
     */
    private function getPaymentHistory()
    {

        $orders = self::getCustomerOrders($this->context->customer->id);

        if ($orders) {
            foreach ($orders as &$order) {
                $myOrder = new Order((int)$order['id_order']);
                if (Validate::isLoadedObject($myOrder)) {
                    $order['virtual'] = $myOrder->isVirtual(false);
                }
            }
        }
        $this->context->smarty->assign(array(
            'orders' => $orders,
            'invoiceAllowed' => (int)Configuration::get('PS_INVOICE'),
            'reorderingAllowed' => !(bool)Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
            'slowValidation' => Tools::isSubmit('slowvalidation')
        ));

        return $this->display($this->getPathThisModule(), '/views/templates/history.tpl');
    }

    /**
     * Get customer orders
     *
     * @param $id_customer Customer id
     * @param bool $show_hidden_status Display or not hidden order statuses
     * @param Context|null $context
     * @return array
     */
    public function getCustomerOrders($id_customer, $show_hidden_status = false, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
        SELECT o.`id_order`, o.`id_currency`, o.`payment`, o.`invoice_number`, pp.`date` date_add, pp.`reference`, pp.`amount` total_paid, pp.`authcode` cus, (SELECT SUM(od.`product_quantity`) FROM `' . _DB_PREFIX_ . 'order_detail` od WHERE od.`id_order` = o.`id_order`) nb_products
        FROM `' . $this->tableOrder . '` o
            JOIN `' . $this->tablePayment . '` pp ON pp.id_order = o.id_cart
        WHERE o.`id_customer` = ' . (int)$id_customer .
            Shop::addSqlRestriction(Shop::SHARE_ORDER) . '
        GROUP BY o.`id_order`
        ORDER BY o.`date_add` DESC');
        if (!$res) {
            return array();
        }

        foreach ($res as $key => $val) {
            $res2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT os.`id_order_state`, osl.`name` AS order_state, os.`invoice`, os.`color` as order_state_color
                FROM `' . _DB_PREFIX_ . 'order_history` oh
                LEFT JOIN `' . _DB_PREFIX_ . 'order_state` os ON (os.`id_order_state` = oh.`id_order_state`)
                INNER JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = ' . (int)$context->language->id . ')
            WHERE oh.`id_order` = ' . (int)$val['id_order'] . (!$show_hidden_status ? ' AND os.`hidden` != 1' : '') . '
                ORDER BY oh.`date_add` DESC, oh.`id_order_history` DESC
            LIMIT 1');

            if ($res2) {
                $res[$key] = array_merge($res[$key], $res2[0]);
            }
        }

        return $res;
    }

    /**
     * @param $cart_id
     * @param null $order_id
     * @return mixed
     */
    private function getTransactionInformation($cart_id, $order_id = null)
    {

        $id_order = (empty($cart_id)
            ? "(SELECT `id_cart` FROM `{$this->tableOrder}` WHERE `id_order` = {$order_id})"
            : $cart_id);

        $result = Db::getInstance()->ExecuteS("SELECT * FROM `{$this->tablePayment}` WHERE `id_order` = {$id_order}");

        if (!empty($result)) {
            $result = $result[0];

            if (empty($result['reason_description'])) {
                $result['reason_description'] = ($result['reason'] == '?-') ? $this->l('Processing transaction') : $this->l('No information');
            }

            if (empty($result['status'])) {
                $result['status_description'] = ($result['status'] == '') ? $this->l('Processing transaction') : $this->l('No information');
            }
        }

        return $result;
    }

    /**
     * Update status order in background
     *
     * @param int $minutes
     * @throws PaymentException
     */
    public function sonda($minutes = 12)
    {
        echo 'Begins ' . date('Ymd H:i:s') . '.' . PHP_EOL;

        $date = date('Y-m-d H:i:s', time() - $minutes * 60);
        $sql = "SELECT * 
            FROM `{$this->tablePayment}`
            WHERE `date` < '{$date}' 
              AND `status` = " . PaymentStatus::PENDING;

        if ($result = Db::getInstance()->ExecuteS($sql)) {
            echo "Found (" . count($result) . ") payments pending." . PHP_EOL;

            $place_to_pay = new PaymentRedirection($this->getLogin(), $this->getTrankey(), $this->getUri());

            foreach ($result as $row) {
                $reference = $row['reference'];
                $request_id = (int)$row['id_request'];
                $cart_id = (int)$row['id_order'];

                echo "Processing {$reference}." . PHP_EOL;

                $response = $place_to_pay->query($request_id);
                $status = $this->getStatusPayment($response);
                $order = $this->getRelatedOrder($cart_id);

                if ($order) {
                    $this->settleTransaction($status, $cart_id, $order, $response);
                }

                echo 'Status [' . $status . '].' . PHP_EOL;
            }
        }

        echo 'Finished ' . date('Ymd H:i:s') . '.' . PHP_EOL;
    }
}
