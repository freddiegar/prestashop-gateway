<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/PlaceToPay.class.php';
require_once __DIR__ . '/libs/RemoteAddress.class.php';

/**
 * Class PlacetoPayPayment
 */
class PlaceToPayPayment extends PaymentModule
{

    /**
     * Tabla de pagos
     */
    private $tablePayment = null;
    /**
     * Tabla de ordenes
     */
    private $tableOrder = null;

    /**
     * Variables de configuracion del módulo
     */
    const COMPANY_DOCUMENT = 'PLACETOPAY_COMPANYDOCUMENT';
    const COMPANY_NAME = 'PLACETOPAY_COMPANYNAME';
    const DESCRIPTION = 'PLACETOPAY_DESCRIPTION';
    const LOGIN = 'PLACETOPAY_LOGIN';
    const TRAN_KEY = 'PLACETOPAY_TRANKEY';
    const ENVIRONMENT = 'PLACETOPAY_ENVIRONMENT';
    const STOCK_REINJECT = 'PLACETOPAY_STOCKREINJECT';
    const CIFIN_MESSAGE = 'PLACETOPAY_CIFINMESSAGE';
    const ORDER_STATE = 'PS_OS_PLACETOPAY';


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
        $this->version = '2.1';
        $this->author = 'EGM Ingeniería sin Fronteras S.A.S';
        $this->tab = 'payments_gateways';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);

        parent::__construct();

        if (isset($this->context->controller)) {
            $this->context->controller->addCSS($this->_path . 'views/css/style.css', 'all');
        }

        $this->displayName = $this->l('Place to Pay');
        $this->description = $this->l('Accept payments by credit cards and debits account');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Crea la tabla de pagos y la tabla de estado de la orden.
     * Además crea las variables de configuración para realizar la conexión con Redirección de Pagos.
     * Registran.
     *
     * @return bool
     */
    public function install()
    {
        switch (true) {
            case !parent::install():
                throw new PlaceToPayPaymentException('error on install', 101);
            case !$this->createPaymentTable():
                throw new PlaceToPayPaymentException('error on install', 102);
            case !$this->createOrderState();
                throw new PlaceToPayPaymentException('error on install', 104);
            case !$this->addColumnEmail();
                throw new PlaceToPayPaymentException('error on install', 113);
            case !$this->addColumnRequestId();
                throw new PlaceToPayPaymentException('error on install', 114);
            case !$this->registerHook('payment'):
                throw new PlaceToPayPaymentException('error on install', 104);
            case !$this->registerHook('paymentReturn');
                throw new PlaceToPayPaymentException('error on install', 105);
                break;
        }

        // Variables de configuración del  módulo
        Configuration::updateValue(self::COMPANY_DOCUMENT, '');
        Configuration::updateValue(self::COMPANY_NAME, '');
        Configuration::updateValue(self::DESCRIPTION, 'Pago en PlacetoPay - %s');

        Configuration::updateValue(self::LOGIN, '');
        Configuration::updateValue(self::TRAN_KEY, '');
        Configuration::updateValue(self::ENVIRONMENT, 'TEST');
        Configuration::updateValue(self::STOCK_REINJECT, '1');
        Configuration::updateValue(self::CIFIN_MESSAGE, '0');

        return true;
    }

    /**
     * Desinstala el modulo, eliminando unicamente las variables de configuración
     * generadas.
     * NO se elimina la tabla con el historico y el nuevo estado creado
     *
     * @retun bool
     */
    public function uninstall()
    {
        if (
            !Configuration::deleteByName(self::COMPANY_DOCUMENT)
            || !Configuration::deleteByName(self::COMPANY_NAME)
            || !Configuration::deleteByName(self::DESCRIPTION)

            || !Configuration::deleteByName(self::LOGIN)
            || !Configuration::deleteByName(self::TRAN_KEY)
            || !Configuration::deleteByName(self::ENVIRONMENT)
            || !Configuration::deleteByName(self::STOCK_REINJECT)
            || !Configuration::deleteByName(self::CIFIN_MESSAGE)
            || !parent::uninstall()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Crea la tabla en la cúal se almacena información adicional de la transacción,
     * es generada en el proceso de instalacion (self::install())
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
     * Crea un estado para las ordenes procesadas con PlacetoPay en espera de respuesta
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
                copy(_PS_MODULE_DIR_ . $this->name . '/views/img/logo.png', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Muestra la página de configuración del módulo
     */
    public function getContent()
    {
        // Asienta la configuración y genera una salida estandar
        $html = $this->saveConfiguration();

        // Muestra el formulario para la configuración de PlacetoPay
        $html .= $this->displayConfiguration();

        return $html;
    }

    /**
     * Valida y almacena la información de configuración de PlacetoPay
     */
    private function saveConfiguration()
    {
        if (!Tools::isSubmit('submitPlacetoPayConfiguraton')) {
            return;
        }

        $errors = array();

        // Almacena los datos de la compañía
        Configuration::updateValue(self::COMPANY_DOCUMENT, Tools::getValue('companydocument'));
        Configuration::updateValue(self::COMPANY_NAME, Tools::getValue('companyname'));
        Configuration::updateValue(self::DESCRIPTION, Tools::getValue('description'));
        // Configura cuenta PlaceToPay
        Configuration::updateValue(self::LOGIN, Tools::getValue('login'));
        Configuration::updateValue(self::TRAN_KEY, Tools::getValue('trankey'));
        Configuration::updateValue(self::ENVIRONMENT, Tools::getValue('environment'));

        // El comportamiento del inventario ante una transacción fallida o declinada
        Configuration::updateValue(self::STOCK_REINJECT, (Tools::getValue('stockreinject') == '1' ? '1' : '0'));
        // habilitar el mensaje de CIFIN
        Configuration::updateValue(self::CIFIN_MESSAGE, (Tools::getValue('cifinmessage') == '1' ? '1' : '0'));

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
    private function displayConfiguration()
    {
        global $smarty;

        $smarty->assign(
            array(
                'actionURL' => Tools::safeOutput($_SERVER['REQUEST_URI']),
                'actionBack' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),

                'companydocument' => $this->getCompanyDocument(),
                'companyname' => $this->getCompanyName(),
                'description' => $this->getDescription(),

                'login' => $this->getLogin(),
                'trankey' => $this->getTrankey(),
                'urlnotification' => $this->getReturnURL(),
                'schudeletask' => $this->getPathSchudeleTask(),
                'environment' => $this->getEnvironment(),
                'stockreinject' => $this->getStockReinject(),
                'cifinmessage' => $this->getCifinMessage(),
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
            return false;

        // Si la cuenta de Place to Pay no esta correctamente configurado, aborta el proceso
        if (
            empty($this->getLogin())
            || empty($this->getTrankey())
            || empty($this->getEnvironment())
        ) {
            return false;
        }

        // Obtiene la última operación pendiente
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
        // Asigna el nombre del sitio
        $smarty->assign('sitename', Configuration::get('PS_SHOP_NAME'));
        // Asigna la variable para el mensaje CIFIN
        $smarty->assign('cifinmessage', $this->getCifinMessage());
        // Asigna el nombre de la compañía
        $smarty->assign('companyname', $this->getCompanyName());

        // Muestra la opción de medio de pago
        return $this->display(__DIR__, '/views/templates/payment.tpl');
    }

    /**
     * Obtiene la última transacción pendiente de pago utilizando el medio
     * @return array
     */
    private function getLastPendingTransaction($customerID)
    {
        $status = PlacetoPay::P2P_PENDING;

        $result = Db::getInstance()->ExecuteS("
            SELECT p.* 
            FROM `{$this->tablePayment}` p
                INNER JOIN `{$this->tableOrder}` o ON o.id_cart = p.id_order
            WHERE o.`id_customer` = {$customerID} 
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
            case 'PRODUCTION':
                $uri = PlaceToPay::P2P_PRODUCTION;
                break;
            case 'TEST':
                $uri = PlaceToPay::P2P_TEST;
                break;
            case 'DEVELOPMENT':
                $uri = PlaceToPay::P2P_DEVELOPMENT;
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
    public function getOrderState()
    {
        return Configuration::get(self::ORDER_STATE);
    }

    /**
     * Obtiene el ID y redirecciona al Flujo
     *
     * @param Cart $cart
     */
    public function redirect(Cart $cart)
    {
        // Obtiene algunos datos de la orden
        $language = Language::getIsoById((int)($cart->id_lang));
        $customer = new Customer((int)($cart->id_customer));
        $currency = new Currency((int)($cart->id_currency));
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $deliveryAddress = new Address((int)($cart->id_address_delivery));
        $totalAmount = floatval($cart->getOrderTotal(true, Cart::BOTH));
        $taxAmount = $totalAmount - floatval($cart->getOrderTotal(false, Cart::BOTH));

        // Verifica que los objetos se hayan cargado correctamente
        if (!Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($invoiceAddress)
            || !Validate::isLoadedObject($deliveryAddress)
            || !Validate::isLoadedObject($currency)
        ) {
            throw new PlaceToPayPaymentException('invalid address or customer', 106);
        }

        // Recupera otra informacion relacionada con la orden
        $deliveryCountry = new Country((int)($deliveryAddress->id_country));
        $deliveryState = null;
        if ($deliveryAddress->id_state) {
            $deliveryState = new State((int)($deliveryAddress->id_state));
        }

        // Construye la URL de retorno, aqu'se redireccion desde el proceso de pago
        $ipAddress = (new RemoteAddress())->getIpAddress();
        $returnURL = $this->getReturnURL('?cart_id=' . $cart->id);

        // Crea solicitud de pago en Redirección
        $request = [
            'locale' => ($language == 'en') ? 'en_US' : 'es_CO',
            'returnUrl' => $returnURL,
            'ipAddress' => $ipAddress,
            'expiration' => date('c', strtotime('+2 days')),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'buyer' => [
                'name' => $deliveryAddress->firstname,
                'surname' => $deliveryAddress->lastname,
                'email' => $customer->email,
                'mobile' => $deliveryAddress->phone_mobile,
                'address' => [
                    'country' => $deliveryCountry->iso_code,
                    'state' => (empty($deliveryState) ? null : $deliveryState->name),
                    'city' => $deliveryAddress->city,
                    'street' => $deliveryAddress->address1 . " " . $deliveryAddress->address2,
                ]
            ],
            'payment' => [
                'reference' => $cart->id . ' - ' . time(),
                'description' => 'Prestashop',
                'amount' => [
                    'currency' => $currency->iso_code,
                    'total' => $totalAmount,
                    'taxAmount:' => $taxAmount,
                ]
            ]
        ];

        // Crea Instancia Placetopay
        $placetopay = new PlaceToPay($this->getLogin(), $this->getTrankey(), $this->getUri());
        $response = $placetopay->request($request);

        try {

            if ($response->isSuccessful()) {
                $requestId = $response->requestId();
                $_SESSION['requestId'] = $requestId;
                $orderMessage = 'Success';
                $orderStatus = $this->getOrderState();
                $status = PlaceToPay::P2P_PENDING;
                $paymentURL = $response->processUrl();

            } else {
                $requestId = 0;
                $orderMessage = $response->status()->message();
                $orderStatus = Configuration::get('PS_OS_ERROR');
                $status = PlaceToPay::P2P_FAILED;
                $totalAmount = 0;

                // Genera la redirección al estado de la orden si no se pudo hacer la redireccion
                $order = new Order($this->currentOrder);
                $paymentURL = __PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cart->id
                    . '&id_module=' . $this->id
                    . '&id_order=' . $this->currentOrder
                    . '&key=' . $order->secure_key;
            }

            // Genera la orden en prestashop, si no se generó la URL
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

            // Inserta la transacción en la tabla de PlacetoPay
            $this->insertPaymentPlaceToPay($requestId, $cart->id, $cart->id_currency, $totalAmount, $status, $orderMessage, $ipAddress);

            // Envia flujo a redireccion para realizar el pago
            Tools::redirectLink($paymentURL);

        } catch (Exception $e) {
            throw new PlaceToPayPaymentException($response->status()->message() . "\n" . $e->getMessage(), 107);
        }
    }

    /**
     * Registra orden en los pagos de PlaceToPay
     *
     * @param $requestId
     * @param $orderID
     * @param $currencyID
     * @param $amount
     * @param $status
     * @param $message
     * @param $ipAddress
     * @return bool
     */
    private function insertPaymentPlaceToPay($requestId, $orderID, $currencyID, $amount, $status, $message, $ipAddress)
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
                id_request
            ) VALUES (
                '$orderID',
                '$currencyID',
                '$date',
                '$amount',
                '$status',
                '$reason',
                '$reason_description',
                '$conversion',
                '$ipAddress',
                '$requestId'
            )
        ";

        if (!Db::getInstance()->Execute($sql)) {
            throw new PlaceToPayPaymentException('Cannot insert transaction ' . $sql, 111);
        }

        return true;
    }

    /**
     * Procesa la respuesta de pago dada por la plataforma
     * @param array $cart_id
     */
    public function process($cart_id = null)
    {

        if (!is_null($cart_id) && !empty($_SESSION['requestId'])) {
            // Redireccion desde el proceso de pagos
            $requestId = $_SESSION['requestId'];
        } elseif (!empty(file_get_contents("php://input"))) {
            // Respuesta por norificationURL enviado desde PlaceToPay
            $json = file_get_contents("php://input");
            $obj = json_decode($json);
            $requestId = $obj->requestId;
            $cart_id = $this->getCartByRequestId((int)$requestId);
        } else {
            // Opción no válida, se cancela
            throw new PlaceToPayPaymentException('option not valid in process', 108);
        }

        $orderID = Order::getOrderByCartId((int)$cart_id);

        // si no se halla la orden aborta
        if (!$orderID) {
            throw new PlaceToPayPaymentException(Tools::displayError(), 109);
        }

        $order = new Order($orderID);
        if (!Validate::isLoadedObject($order)) {
            throw new PlaceToPayPaymentException(Tools::displayError(), 110);
        }

        // Consulta el estado de la transaccion
        $placetopay = new PlaceToPay($this->getLogin(), $this->getTrankey(), $this->getUri());

        $response = $placetopay->query($requestId);

        $status = $this->getStatusPayment($response);

        // Asienta la operacion
        $this->settleTransaction($status, $cart_id, $order, $response);

        if (!isset($json)) {
            // Redirige el flujo a la pagina de confirmación de orden
            Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php'
                . '?id_cart=' . $cart_id
                . '&id_module=' . $this->id
                . '&id_order=' . $order->id
                . '&key=' . $order->secure_key
            );
        } else {
            echo $response->status()->message() . PHP_EOL;
        }
    }

    /**
     * @param string $params Query string to add in URL, please include symbol (?), eg: ?var=foo
     * @return string
     */
    public function getReturnURL($params = '')
    {

        $protocol = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://');
        $domain = (Configuration::get('PS_SHOP_DOMAIN_SSL')) ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Tools::getHttpHost();

        return $protocol . $domain . __PS_BASE_URI__ . 'modules/' . $this->name . '/process.php' . $params;
    }

    /**
     * @return string
     */
    private function getPathSchudeleTask()
    {
        return _PS_MODULE_DIR_ . "{$this->name}/sonda.php";
    }


    /**
     * @param null $id_request
     * @return bool
     */
    private function getCartByRequestId($id_request = 0)
    {
        $requestId = (!empty($id_request)) ? $id_request : 0;

        $rows = Db::getInstance()->ExecuteS("
            SELECT id_order 
            FROM  `{$this->tablePayment}` 
            WHERE id_request = {$requestId}
        ");

        return (!empty($rows[0]['id_order'])) ? $rows[0]['id_order'] : false;
    }

    /**
     * @param PlaceToPay $response
     * @return int
     */
    public function getStatusPayment($response)
    {
        // By default ss pending so make a query for it later (see information.php example)
        $status = PlaceToPay::P2P_PENDING;

        if ($response->isSuccessful()) {
            // In order to use the functions please refer to the RedirectInformation class
            if ($response->status()->isApproved()) {
                // Approved status
                $status = PlaceToPay::P2P_APPROVED;
            } elseif ($response->status()->isRejected()) {
                // This is why it has been rejected
                $status = PlaceToPay::P2P_DECLINED;
            }
        }

        return $status;
    }

    private function settleTransaction($status, $cart_id, Order $order, $transactionInfo)
    {
        // Si ya habia sido aprobada no vuelva a reprocesar
        if ($order->getCurrentState() != (int)Configuration::get('PS_OS_PAYMENT')) {
            // procese la respuesta y dependiendo del tipo de respuesta
            switch ($status) {
                case PlaceToPay::P2P_FAILED:
                case PlaceToPay::P2P_DECLINED:
                    if ($order->getCurrentState() == (int)Configuration::get('PS_OS_ERROR')) {
                        break;
                    }

                    // Genera un nuevo estado en la orden de declinación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                    $history->addWithemail();
                    $history->save();

                    // obtiene los productos de la orden, los recorre y vuelve a recargar las cantidades
                    // en el inventario
                    if ($this->getStockReinject() == '1') {
                        $products = $order->getProducts();
                        foreach ($products as $product) {
                            $orderDetail = new OrderDetail((int)($product['id_order_detail']));
                            Product::reinjectQuantities($orderDetail, $product['product_quantity']);
                        }
                    }
                    break;
                case PlaceToPay::P2P_DUPLICATE:
                case PlaceToPay::P2P_APPROVED:
                    // genera un nuevo estado en la orden de aprobación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $history->id_order);
                    $history->addWithemail();
                    $history->save();
                    break;
                case PlaceToPay::P2P_PENDING:
                    break;
            }
        }
        // Actualiza la tabla de PlacetoPay con la información de la transacción
        $this->updateTransaction($cart_id, $status, $transactionInfo);
    }

    /**
     * @param $id_order
     * @param $status
     * @param $transactionInfo
     * @return mixed
     */
    private function updateTransaction($id_order, $status, $transactionInfo)
    {
        $date = date('Y-m-d H:i:s');
        $reason = '';
        $reason_description = pSQL($transactionInfo->status()->message());

        $bank = '';
        $franchise = '';
        $franchise_name = '';
        $authcode = '';
        $receipt = '';
        $conversion = '';

        $payer_email = '';

        if ($status != PlaceToPay::P2P_PENDING) {
            $date = pSQL($transactionInfo->payment[0]->status()->date());
            $reason = pSQL($transactionInfo->payment[0]->status()->reason());
            $reason_description = pSQL($transactionInfo->payment[0]->status()->message());

            $bank = pSQL($transactionInfo->payment[0]->issuerName());
            $franchise = pSQL($transactionInfo->payment[0]->paymentMethod());
            $franchise_name = pSQL($transactionInfo->payment[0]->paymentMethodName());
            $authcode = pSQL($transactionInfo->payment[0]->authorization());
            $receipt = pSQL($transactionInfo->payment[0]->receipt());
            $conversion = pSQL($transactionInfo->payment[0]->amount()->factor());

            $payer_email = pSQL($transactionInfo->request()->payer()->email());
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
                `authcode` = '{$authcode}',
                `receipt` = '{$receipt}',
                `conversion` = '{$conversion}',
                `payer_email` = '{$payer_email}'
            WHERE `id_order` = {$id_order}
        ";

        if (!Db::getInstance()->Execute($sql)) {
            throw new PlaceToPayPaymentException('Cannot update transaction ' . $sql, 112);
        }

        return true;
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
            case PlaceToPay::P2P_APPROVED:
            case PlaceToPay::P2P_DUPLICATE:
                $smarty->assign('status', 'ok');
                $smarty->assign('status_description', 'Transacción aprobada');
                break;
            case PlaceToPay::P2P_FAILED:
                $smarty->assign('status', 'fail');
                $smarty->assign('status_description', 'Transacción fallida');
                break;
            case PlaceToPay::P2P_DECLINED:
                $smarty->assign('status', 'rejected');
                $smarty->assign('status_description', 'Transacción rechazada');
                break;
            case PlaceToPay::P2P_PENDING:
                $smarty->assign('status', 'pending');
                $smarty->assign('status_description', 'Transacción pendiente');
                break;
        }
        $smarty->assign($params);

        $smarty->assign('companyDocument', $this->getCompanyDocument());
        $smarty->assign('companyName', $this->getCompanyName());
        $smarty->assign('paymentDescription', sprintf($this->getDescription(), $transaction['id_order']));

        $smarty->assign('storePhone', Configuration::get('PS_SHOP_PHONE'));
        $smarty->assign('storeEmail', Configuration::get('PS_SHOP_EMAIL'));

        // Obtiene los datos del cliente
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

        // Asocia la ruta base donde encuentra las imagenes
        $smarty->assign('placetopayImgUrl', _MODULE_DIR_ . $this->name . '/views/img/');

        // Asocia la moneda
        $currency = new CurrencyCore($cart->id_currency);
        $currency_iso = $currency->iso_code;
        $smarty->assign('currency_iso', $currency_iso);
        $smarty->assign('customer_id', $cart->id_customer);
        $smarty->assign('logged', (Context::getContext()->customer->isLogged() ? true : false));
        $context = Context::getContext();
        $context->cookie->__set('customer_id', $cart->id_customer);

        return $this->display(__DIR__, '/views/templates/response.tpl');
    }

    /**
     * @param $cartID
     * @param null $orderID
     * @return mixed
     */
    private function getTransactionInformation($cartID, $orderID = null)
    {

        $id_order = (empty($cartID)
            ? "(SELECT `id_cart` FROM `{$this->tableOrder}` WHERE `id_order` = {$orderID})"
            : $cartID);

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
     * Busca las transacciones que estan pendientes
     * @param int $minutes
     */
    public function sonda($minutes = 7)
    {
        // busca las operaciones que estan pendientes de resolver
        // que tienen una antiguedad superior a n minutos
        $date = date('Y-m-d H:i:s', time() - $minutes * 60);

        $sql = "SELECT * 
            FROM `{$this->tablePayment}`
            WHERE `date` < '{$date}' 
              AND `status` = " . PlacetoPay::P2P_PENDING;;

        if ($result = Db::getInstance()->ExecuteS($sql)) {

            echo "Found (" . count($result) . ") payments pending." . PHP_EOL;

            $placetopay = new PlaceToPay($this->getLogin(), $this->getTrankey(), $this->getUri());

            foreach ($result as $row) {
                $requestId = (int)$row['id_request'];
                $cart_id = (int)$row['id_order'];

                // Consta estado de la transaccion en PlaceToPay
                $response = $placetopay->query($requestId);
                $status = $this->getStatusPayment($response);
                $orderID = Order::getOrderByCartId($cart_id);

                if ($orderID) {
                    $order = new Order($orderID);
                    if (Validate::isLoadedObject($order)) {
                        $this->settleTransaction($status, $cart_id, $order, $response);
                    }
                }
            }
        }
        echo 'Finished' . PHP_EOL;
    }
}

/**
 * Class PlacetoPayPaymentLogger
 */
class PlaceToPayPaymentLogger
{
    /**
     * @param string $message
     * @return bool
     */
    public static function log($message = '')
    {

        $logger = new FileLogger(0);
        $logger->setFilename(_PS_ROOT_DIR_ . '/log/placetopaypayment_' . date('Y-m-d') . '.log');
        $logger->logDebug(print_r($message, 1));

        return true;
    }
}

/**
 * Class PlacetoPayPaymentException
 */
class PlaceToPayPaymentException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        PlaceToPayPaymentLogger::log("($code): $message");
        parent::__construct($message, $code, $previous);
    }
}
