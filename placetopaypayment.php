<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/PlacetoPay.class.php';
require_once __DIR__ . '/libs/RemoteAddress.class.php';

class PlacetoPayPayment extends PaymentModule
{

    public function __construct()
    {
        $this->name = 'placetopaypayment';
        $this->version = '2.0';
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

    public function install()
    {

        // genera la tabla con datos adicionales de la operacion
        // permite acceder al modulo desde la factura
        // hace al modulo disponible en el proceso de pago
        // permite al modulo cambiar los contenidos del retorno
        if (
            !parent::install()
            || !$this->createPlacetoPayTable()
            || !$this->createPlacetoPayOrderState()
            || !$this->addPlacetoPayColumnEmail()
            || !$this->addPlacetoPayColumnRequestId()
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentReturn')
            // || !$this->registerHook('adminOrder')
            // || !$this->registerHook('orderDetailDisplayed')
            // || !$this->registerHook('DisplayOverrideTemplate')
        ) {
            return false;
        }

        // define las variables requeridas por el módulo
        Configuration::updateValue('PLACETOPAY_COMPANYDOCUMENT', '');
        Configuration::updateValue('PLACETOPAY_COMPANYNAME', '');
        Configuration::updateValue('PLACETOPAY_DESCRIPTION', 'Pago en PlacetoPay - %s');

        Configuration::updateValue('PLACETOPAY_LOGIN', '');
        Configuration::updateValue('PLACETOPAY_TRANKEY', '');
        Configuration::updateValue('PLACETOPAY_ENVIRONMENT', 'TEST');
        Configuration::updateValue('PLACETOPAY_STOCKREINJECT', '1');
        Configuration::updateValue('PLACETOPAY_CIFINMESSAGE', '0');

        return true;
    }

    /**
     * Desinstala el modulo, eliminando las variables de configuracion
     * generadas, NO se elimina la tabla con el historico y el nuevo estado creado
     *
     * @retun bool
     */
    public function uninstall()
    {
        // elimina los parametros de configuracion generados por el modulo
        if (
            !Configuration::deleteByName('PLACETOPAY_COMPANYDOCUMENT')
            || !Configuration::deleteByName('PLACETOPAY_COMPANYNAME')
            || !Configuration::deleteByName('PLACETOPAY_DESCRIPTION')
            || !Configuration::deleteByName('PLACETOPAY_LOGIN')
            || !Configuration::deleteByName('PLACETOPAY_TRANKEY')
            || !Configuration::deleteByName('PLACETOPAY_ENVIRONMENT')
            || !Configuration::deleteByName('PLACETOPAY_STOCKREINJECT')
            || !Configuration::deleteByName('PLACETOPAY_CIFINMESSAGE')
            || !parent::uninstall()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Crea la tabla en la cual se almacena informacion adicional de la transaccion,
     * es generada en el proceso de instalacion
     * @return bool
     */
    private function createPlacetoPayTable()
    {
        $db = Db::getInstance();
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "payment_placetopay` (
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

        if ($db->Execute($sql)) {
            return true;
        }
    }

    /**
     * Crea un estado para las ordenes procesadas con PlacetoPay en espera de respuesta
     * @return bool
     */
    private function createPlacetoPayOrderState()
    {
        // genera un nuevo estado de la orden, el pendiente de autorizacion en PlacetoPay
        if (!Configuration::get('PS_OS_PLACETOPAY')) {
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
                Configuration::updateValue('PS_OS_PLACETOPAY', $orderState->id);
                copy(_PS_MODULE_DIR_ . $this->name . '/views/img/logo.png', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
            } else {
                return false;
            }
        }

        return true;
    }

    private function addPlacetoPayColumnEmail()
    {
        $db = Db::getInstance();
        $sql = "ALTER TABLE `" . _DB_PREFIX_ . "payment_placetopay` ADD `payer_email` VARCHAR(80) NULL;";
        $db->Execute($sql);
        return true;
    }

    private function addPlacetoPayColumnRequestId()
    {
        $db = Db::getInstance();
        $sql = "ALTER TABLE `" . _DB_PREFIX_ . "payment_placetopay` ADD `id_request` INT NULL;";
        $db->Execute($sql);
        return true;
    }

    /**
     * Muestra la página de configuración del módulo
     */
    public function getContent()
    {
        // Asienta la configuración y genera una salida estandar
        $html = $this->savePlacetoPayConfiguration();

        // Muestra el formulario para la configuración de PlacetoPay
        $html .= $this->displayPlacetoPayConfiguration();

        return $html;
    }

    /**
     * Valida y almacena la información de configuración de PlacetoPay
     */
    private function savePlacetoPayConfiguration()
    {
        global $smarty;

        if (!Tools::isSubmit('submitPlacetoPayConfiguraton')) {
            return;
        }

        $errors = array();

        // almacena los datos de la compañía
        Configuration::updateValue('PLACETOPAY_COMPANYDOCUMENT', Tools::getValue('companydocument'));
        Configuration::updateValue('PLACETOPAY_COMPANYNAME', Tools::getValue('companyname'));
        Configuration::updateValue('PLACETOPAY_DESCRIPTION', Tools::getValue('description'));
        // Configura cuenta Place ti Pay
        Configuration::updateValue('PLACETOPAY_LOGIN', Tools::getValue('login'));
        Configuration::updateValue('PLACETOPAY_TRANKEY', Tools::getValue('trankey'));
        Configuration::updateValue('PLACETOPAY_ENVIRONMENT', Tools::getValue('environment'));

        // el comportamiento del inventario ante una transacción fallida o declinada
        Configuration::updateValue('PLACETOPAY_STOCKREINJECT', (Tools::getValue('stockreinject') == '1' ? '1' : '0'));
        // habilitar el mensaje de cifin
        Configuration::updateValue('PLACETOPAY_CIFINMESSAGE', (Tools::getValue('cifinmessage') == '1' ? '1' : '0'));

        // genera el volcado de errores
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
     * Genera el formulario para la configuración de PlacetoPay
     * @return string
     */
    private function displayPlacetoPayConfiguration()
    {
        global $smarty;

        $smarty->assign(
            array(
                'actionURL' => Tools::safeOutput($_SERVER['REQUEST_URI']),
                'actionBack' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),

                'companydocument' => Configuration::get('PLACETOPAY_COMPANYDOCUMENT'),
                'companyname' => Configuration::get('PLACETOPAY_COMPANYNAME'),
                'description' => Configuration::get('PLACETOPAY_DESCRIPTION'),

                'login' => Configuration::get('PLACETOPAY_LOGIN'),
                'trankey' => Configuration::get('PLACETOPAY_TRANKEY'),
                'environment' => Configuration::get('PLACETOPAY_ENVIRONMENT'),
                'stockreinject' => Configuration::get('PLACETOPAY_STOCKREINJECT'),
                'cifinmessage' => Configuration::get('PLACETOPAY_CIFINMESSAGE'),
            )
        );

        return $this->display(__DIR__, '/views/templates/setting.tpl');
    }

    /**
     * Muestra a PlacetoPay como uno de los medios de pagos disponibles en el checkout
     *
     * @param array $params
     * @return string
     */
    public function hookPayment($params)
    {

        global $smarty;

        // aborta si el medio no esta activo
        if (!$this->active)
            return;

        // Si la cuenta de Place to Pay no esta correctamente cnfigurado, aborta
        if (
            empty(Configuration::get('PLACETOPAY_LOGIN'))
            || empty(Configuration::get('PLACETOPAY_TRANKEY'))
            || empty(Configuration::get('PLACETOPAY_ENVIRONMENT'))
        ) {
            return;
        }

        // obtiene la última operación pendiente
        $pending = $this->getLastPendingTransaction($params['cart']->id_customer);
        if (!empty($pending)) {
            $smarty->assign(array(
                'hasPending' => true,
                'lastOrder' => $pending['id_order'],
                'lastAuthorization' => $pending['authcode'],
                'storePhone' => Configuration::get('PS_SHOP_PHONE'),
                'storeEmail' => Configuration::get('PS_SHOP_EMAIL')
            ));
        } else {
            $smarty->assign('hasPending', false);
        }

        // Asigna variable del modelo para el link
        $smarty->assign('module', $this->name);
        // asigne la variable para el mensaje cifin
        $smarty->assign('cifinmessage', Configuration::get('PLACETOPAY_CIFINMESSAGE'));
        // asigne el nombre del sitio
        $smarty->assign('sitename', Configuration::get('PS_SHOP_NAME'));
        // asigne el nombre de la compañía
        $smarty->assign('companyname', Configuration::get('PLACETOPAY_COMPANYNAME'));

        // muestra la opción de medio de pago
        return $this->display(__DIR__, '/views/templates/payment.tpl');
    }

    /**
     * Obtiene la última transacción pendiente de pago utilizando el medio
     * @return array
     */
    private function getLastPendingTransaction($customerID)
    {
        $result = Db::getInstance()->ExecuteS(
            'SELECT p.* 
            FROM `' . _DB_PREFIX_ . 'payment_placetopay` p
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart = p.id_order
            WHERE o.`id_customer` = ' . $customerID . ' 
                AND p.`status` = ' . PlacetoPay::P2P_PENDING . ' 
            LIMIT 1'
        );

        if (!empty($result)) {
            $result = $result[0];
        }

        return $result;
    }

    public function getUri()
    {
        switch (Configuration::get('PLACETOPAY_ENVIRONMENT')) {
            case 'PRODUCTION':
                $uri = PlacetoPay::P2P_PRODUCTION;
                break;
            case 'TEST':
                $uri = PlacetoPay::P2P_TEST;
                break;
            case 'DEVELOPMENT':
            default:
                $uri = PlacetoPay::P2P_DEVELOPMENT;
                break;
        }

        return $uri;
    }

    /**
     * Obtiene el ID y redirecciona al Flujo
     *
     * @param Cart $cart
     */
    public function redirect(Cart $cart)
    {
        // obtiene algunos datos de la orden
        $customer = new Customer((int)($cart->id_customer));
        $currency = new Currency((int)($cart->id_currency));
        // $currency = new CurrencyCore($cart->id_currency);
        // $currency_iso = $currency->iso_code;
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $deliveryAddress = new Address((int)($cart->id_address_delivery));
        $language = new Language((int)($cart->id_lang));
        $totalAmount = (float)($cart->getOrderTotal(true, Cart::BOTH));
        $taxAmount = $totalAmount - (float)($cart->getOrderTotal(false, Cart::BOTH));

        // verifica que los objetos se hayan cargado
        if (!Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($invoiceAddress)
            || !Validate::isLoadedObject($deliveryAddress)
            || !Validate::isLoadedObject($currency)
        ) {
            die($this->l('Place to Pay error: (invalid address or customer)'));
        }

        // recupera otra informacion relacionada con la orden
        $invoiceCountry = new Country((int)($invoiceAddress->id_country));
        $invoiceState = null;
        if ($invoiceAddress->id_state) {
            $invoiceState = new State((int)($invoiceAddress->id_state));
        }

        $deliveryCountry = new Country((int)($deliveryAddress->id_country));
        $deliveryState = null;
        if ($deliveryAddress->id_state) {
            $deliveryState = new State((int)($deliveryAddress->id_state));
        }

        // construye la URL de retorno para la tienda
        $returnURL = Configuration::get('PS_SHOP_DOMAIN_SSL');
        if (!$returnURL) {
            $returnURL = Tools::getHttpHost();
        }

        $returnURL = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://')
            . $returnURL
            . __PS_BASE_URI__
            . 'modules/' . $this->name . '/process.php?cart_id=' . $cart->id;

        // carga la clase de soporte de placetopay
        $placetopay = new PlacetoPay(
            Configuration::get('PLACETOPAY_LOGIN'),
            Configuration::get('PLACETOPAY_TRANKEY'),
            $this->getUri()
        );

        $req = [
            'returnUrl' => $returnURL,
            'expiration' => date('c', strtotime('+2 days')),
            'ipAddress' => (new RemoteAddress())->getIpAddress(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'buyer' => [
                'name' => utf8_decode($deliveryAddress->firstname),
                'surname' => utf8_decode($deliveryAddress->lastname),
                'email' => $customer->email,
                'mobile' => $deliveryAddress->phone_mobile,
                'address' => [
                    'street' => utf8_decode($deliveryAddress->address1 . "\n" . $deliveryAddress->address2),
                    'city' => utf8_decode($deliveryAddress->city),
                    'state' => (empty($deliveryState) ? null : utf8_decode($deliveryState->name)),
                    'country' => $deliveryCountry->iso_code,
                ]
            ],
            'payment' => [
                'reference' => $cart->id . ' - ' . time(),
                'description' => 'Prestashop',
                'amount' => [
                    'currency' => $currency->iso_code,
                    'total' => floatval($totalAmount)
                ]
            ]
        ];

        $paymentURL = '';

        try {
            $response = $placetopay->redirection->request($req);

            if ($response->isSuccessful()) {
                $requestId = $response->requestId();
                $_SESSION['requestId'] = $requestId;
                $paymentURL = $response->processUrl();
                $orderMessage = null;
                $orderStatus = Configuration::get('PS_OS_PLACETOPAY');
                $status = PlacetoPay::P2P_PENDING;

            } else {
                $requestId = 0;
                $paymentURL = '';
                $orderMessage = $response->status()->message();
                $orderStatus = Configuration::get('PS_OS_ERROR');
                $status = PlacetoPay::P2P_FAILED;
                $totalAmount = 0;
            }

            // genera la orden en prestashop, si no se generó la URL
            // crea la orden con el error, al menos para que quede asentada
            $this->validateOrder(
                $cart->id,
                $orderStatus,
                $totalAmount,
                $this->displayName,
                $orderMessage,
                null,
                null,
                false,
                $cart->secure_key
            );

            // inserta la transacción en la tabla de PlacetoPay
            $this->insertTransaction($requestId, $cart->id, $cart->id_currency, $totalAmount, $status, $orderMessage);

            // genera la redirección al estado de la orden si no se pudo hacer la redireccion
            if (empty($paymentURL)) {
                $order = new Order($this->currentOrder);
                $paymentURL = __PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cart->id
                    . '&id_module=' . $this->id
                    . '&id_order=' . $this->currentOrder
                    . '&key=' . $order->secure_key;
            }

            Tools::redirectLink($paymentURL);

        } catch (Exception $e) {
            die($response->status()->message());
        }
    }

    private function insertTransaction($requestId, $orderID, $currencyID, $amount, $status, $message)
    {
        $reason = '';
        Db::getInstance()->Execute('
            INSERT INTO `' . _DB_PREFIX_ . 'payment_placetopay` (`id_order`, `id_currency`, `date`, `amount`, `status`, `reason`, `reason_description`, `conversion`, `ipaddress`, `id_request`)
            VALUES (' . $orderID . ',' . $currencyID . ',\'' . date('Y-m-d H:i:s') . '\',' . $amount . ',' . $status . ',\'' . $reason . '\',\'' . pSQL($message) . '\',1,\'' . pSQL($_SERVER['REMOTE_ADDR']) . '\', ' . $requestId . ')');
    }

    /**
     * Procesa la respuesta de pago dada por la plataforma
     * @param array $cart_id
     */
    public function process($cart_id = null)
    {

        if (!is_null($cart_id)) {
            $requestId = $_SESSION['requestId'];
        } else {
            $json = file_get_contents("php://input");
            $obj = json_decode($json);
            $requestId = $obj->requestId;
            $cart_id = $this->getCartByRequestId((int)$requestId);
        }

        $orderID = Order::getOrderByCartId((int)$cart_id);

        // si no se halla la orden aborta
        if (!$orderID) {
            die(Tools::displayError());
        }

        $order = new Order($orderID);
        if (!Validate::isLoadedObject($order)) {
            die(Tools::displayError());
        }

        // Consulta el estado de la transaccion
        $placetopay = new PlacetoPay(
            Configuration::get('PLACETOPAY_LOGIN'),
            Configuration::get('PLACETOPAY_TRANKEY'),
            $this->getUri()
        );

        $response = $placetopay->redirection->query($requestId);

        $status = $this->getStatus($response);

        // asienta la operacion
        $this->settleTransaction($status, $cart_id, $order, $response);

        if (!isset($json)) {
            // redirige el flujo a la pagina de confirmación de orden
            Tools::redirectLink($paymentURL = __PS_BASE_URI__ . 'order-confirmation.php'
                . '?id_cart=' . $cart_id
                . '&id_module=' . $this->id
                . '&id_order=' . $order->id
                . '&key=' . $order->secure_key
            );
        } else {
            echo 'Success status: ' . $status;
        }
    }

    private function getCartByRequestId($id_request = null)
    {
        $requestId = (!empty($id_request)) ? $id_request : 0;
        $rows = Db::getInstance()->ExecuteS('SELECT id_order FROM  `' . _DB_PREFIX_ . 'payment_placetopay` WHERE id_request = ' . $requestId);
        return (!empty($rows[0]['id_order'])) ? $rows[0]['id_order'] : false;
    }

    public function getStatus($response)
    {
        // By default ss pending so make a query for it later (see information.php example)
        $status = PlacetoPay::P2P_PENDING;

        if ($response->isSuccessful()) {
            // In order to use the functions please refer to the RedirectInformation class
            if ($response->status()->isApproved()) {
                // Approved status
                $status = PlacetoPay::P2P_APPROVED;
            } else {
                if ($response->status()->isRejected()) {
                    // This is why it has been rejected
                    $status = PlacetoPay::P2P_DECLINED;
                } elseif ($response->status()->isFailed()) {
                    // Failed
                    $status = PlacetoPay::P2P_FAILED;
                }
            }
        }

        return $status;
    }

    private function settleTransaction($status, $cart_id, Order $order, $transactionInfo)
    {
        // echo "Cart: {$cart_id}, Status: {$status}<br />";
        // si ya habia sido aprobada no vuelva a reprocesar
        if ($order->getCurrentState() != (int)Configuration::get('PS_OS_PAYMENT')) {
            // procese la respuesta y dependiendo del tipo de respuesta
            switch ($status) {
                case PlacetoPay::P2P_FAILED:
                case PlacetoPay::P2P_DECLINED:
                    if ($order->getCurrentState() == (int)Configuration::get('PS_OS_ERROR')) {
                        break;
                    }

                    // genera un nuevo estado en la orden de declinación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                    $history->addWithemail();
                    $history->save();

                    // obtiene los productos de la orden, los recorre y vuelve a recargar las cantidades
                    // en el inventario
                    if (Configuration::get('PLACETOPAY_STOCKREINJECT') == '1') {
                        $products = $order->getProducts();
                        foreach ($products as $product) {
                            $orderDetail = new OrderDetail((int)($product['id_order_detail']));
                            Product::reinjectQuantities($orderDetail, $product['product_quantity']);
                        }
                    }
                    break;
                case PlacetoPay::P2P_DUPLICATE:
                case PlacetoPay::P2P_APPROVED:
                    // genera un nuevo estado en la orden de aprobación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $history->id_order);
                    $history->addWithemail();
                    $history->save();
                    break;
                case PlacetoPay::P2P_PENDING:
                    break;
            }
        }
        // actualiza la tabla de PlacetoPay con la información de la transacción
        $r = $this->updateTransaction($cart_id, $status, $transactionInfo);
    }

    private function updateTransaction($cart_id, $status, $transactionInfo)
    {
        $date = pSQL($transactionInfo->payment[0]->status()->date());
        $reason = pSQL($transactionInfo->payment[0]->status()->reason());
        $reason_description = pSQL($transactionInfo->payment[0]->status()->message());

        $franchise = pSQL($transactionInfo->payment[0]->paymentMethod());
        $franchise_name = pSQL($transactionInfo->payment[0]->paymentMethodName());
        $authcode = pSQL($transactionInfo->payment[0]->authorization());
        $receipt = pSQL($transactionInfo->payment[0]->receipt());
        $conversion = pSQL($transactionInfo->payment[0]->amount()->factor());

        $payer_email = pSQL($transactionInfo->request()->payer()->email());

        return Db::getInstance()->Execute('
            UPDATE `' . _DB_PREFIX_ . 'payment_placetopay` SET
                `date` = \'' . $date . '\',
                `status` = ' . $status . ',
                `reason` = \'' . $reason . '\',
                `reason_description` = \'' . $reason_description . '\',
                `franchise` = \'' . $franchise . '\',
                `franchise_name` = \'' . $franchise_name . '\',
                `bank` = \'' . $bank . '\',
                `authcode` = \'' . $authcode . '\',
                `receipt` = \'' . $receipt . '\',
                `conversion` = ' . $conversion . ',
                `payer_email` = \'' . $payer_email . '\'
            WHERE `id_order` = ' . $cart_id);
    }

    /**
     * Bloque a visualizar cuando se retorna con el pago
     * @array $params
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        global $smarty;

        if ((!$this->active) || ($params['objOrder']->module != $this->name)) {
            return;
        }

        // provee a la plantilla de la informacion
        $transaction = $this->getTransactionInformation($params['objOrder']->id_cart);
        $cart = new Cart((int)$params['objOrder']->id_cart);
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $deliveryAddress = new Address((int)($cart->id_address_delivery));
        $totalAmount = (float)($cart->getOrderTotal(true, Cart::BOTH));
        $taxAmount = $totalAmount - (float)($cart->getOrderTotal(false, Cart::BOTH));
        $payerEmail = $transaction['payer_email'];
        $transaction['tax'] = $taxAmount;

        $smarty->assign('transaction', $transaction);
        switch ($transaction['status']) {
            case PlacetoPay::P2P_APPROVED:
            case PlacetoPay::P2P_DUPLICATE:
                $smarty->assign('status', 'ok');
                $smarty->assign('status_description', 'Transacción aprobada');
                break;
            case PlacetoPay::P2P_FAILED:
                $smarty->assign('status', 'fail');
                $smarty->assign('status_description', 'Transacción fallida');
                break;
            case PlacetoPay::P2P_DECLINED:
                $smarty->assign('status', 'rejected');
                $smarty->assign('status_description', 'Transacción rechazada');
                break;
            case PlacetoPay::P2P_PENDING:
                $smarty->assign('status', 'pending');
                $smarty->assign('status_description', 'Transacción pendiente');
                break;
        }
        $smarty->assign($params);
        $smarty->assign('companyDocument', Configuration::get('PLACETOPAY_COMPANYDOCUMENT'));
        $smarty->assign('companyName', Configuration::get('PLACETOPAY_COMPANYNAME'));
        $smarty->assign('paymentDescription', sprintf(Configuration::get('PLACETOPAY_DESCRIPTION'), $transaction['id_order']));
        $smarty->assign('storePhone', Configuration::get('PS_SHOP_PHONE'));
        $smarty->assign('storeEmail', Configuration::get('PS_SHOP_EMAIL'));

        // obtiene los datos del cliente
        $customer = new Customer((int)($params['objOrder']->id_customer));
        if (Validate::isLoadedObject($customer)) {
            if (empty($invoiceAddress)) {
                $smarty->assign('payerName', $customer->firstname . ' ' . $customer->lastname);
                $smarty->assign('payerEmail', $customer->email);
            } else {
                $smarty->assign('payerName', $invoiceAddress->firstname . ' ' . $invoiceAddress->lastname);
                $smarty->assign('payerEmail', (isset($payerEmail) ? $payerEmail : $customer->email));
            }
        }

        // asocia la ruta base donde encuentra las imagenes
        $smarty->assign('placetopayImgUrl', _MODULE_DIR_ . $this->name . '/views/img/');
        // asocia la moneda
        $currency = new CurrencyCore($cart->id_currency);
        $currency_iso = $currency->iso_code;
        $smarty->assign('currency_iso', $currency_iso);
        $smarty->assign('customer_id', $cart->id_customer);
        $smarty->assign('logged', (Context::getContext()->customer->isLogged() ? true : false));
        $context = Context::getContext();
        $context->cookie->__set('customer_id', $cart->id_customer);

        return $this->display(__DIR__, '/views/templates/response.tpl');
    }

    private function getTransactionInformation($cartID, $orderID = null)
    {
        $result = Db::getInstance()->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'payment_placetopay`
            WHERE `id_order` = ' . (empty($cartID)
                ? '(SELECT `id_cart` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . $orderID . ')'
                : $cartID));
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
     * Busca las transacciones que estan pendientes de ser resueltas
     * @param int $minutes
     */
    public function sonda($minutes = 7)
    {
        // echo 'Init<br />';
        // busca las operaciones que estan pendientes de resolver
        // que tienen una antiguedad superior a n minutos
        $result = Db::getInstance()->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'payment_placetopay`
            WHERE `date` < \'' . date('Y-m-d H:i:s', time() - $minutes * 60) . '\' AND `status` = ' . PlacetoPay::P2P_PENDING);

        if (!empty($result)) {
            $placetopay = new PlacetoPay(
                Configuration::get('PLACETOPAY_LOGIN'),
                Configuration::get('PLACETOPAY_TRANKEY'),
                $this->getUri()
            );

            foreach ($result as $row) {
                $currency = new Currency((int)$row['id_currency']);
                $requestId = (int)$row['id_request'];
                $cart_id = (int)$row['id_order'];

                // busca la operación en PlacetoPay
                $response = $placetopay->redirection->query($requestId);
                $status = $this->getStatus($response);
                $orderID = Order::getOrderByCartId($cart_id);
                if ($orderID) {
                    $order = new Order($orderID);
                    if (Validate::isLoadedObject($order)) {
                        $this->settleTransaction($status, $cart_id, $order, $response);
                    }
                }
            }
        }
        // echo 'End<br />';
    }
}