<?php

namespace PlacetoPay\Models;

use Dnetix\Redirection\Message\RedirectInformation;
use PlacetoPay\Exception\PaymentException;
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
use \Exception;

/**
 * Class PlaceToPayPaymentMethod
 */
class PaymentMethod extends PaymentModule
{
    /**
     * Variables de configuracion del módulo
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

    const ORDER_STATE = 'PS_OS_PLACETOPAY';

    /**
     * Tabla de pagos
     */
    private $tablePayment = null;
    /**
     * Tabla de ordenes
     */
    private $tableOrder = null;


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
                throw new PaymentException('error on install', 101);
            case !$this->createPaymentTable():
                throw new PaymentException('error on install', 102);
            case !$this->createOrderState();
                throw new PaymentException('error on install', 104);
            case !$this->alterColumnIpAddress();
                throw new PaymentException('error on install', 104);
            case !$this->addColumnEmail();
                throw new PaymentException('error on install', 113);
            case !$this->addColumnRequestId();
                throw new PaymentException('error on install', 114);
            case !$this->addColumnReference();
                throw new PaymentException('error on install', 114);
            case !$this->registerHook('payment'):
                throw new PaymentException('error on install', 104);
            case !$this->registerHook('paymentReturn');
                throw new PaymentException('error on install', 105);
                break;
        }

        // Variables de configuración del  módulo
        Configuration::updateValue(self::COMPANY_DOCUMENT, '');
        Configuration::updateValue(self::COMPANY_NAME, '');
        Configuration::updateValue(self::DESCRIPTION, 'Pago en PlacetoPay No: %s');

        Configuration::updateValue(self::EMAIL_CONTACT, '');
        Configuration::updateValue(self::TELEPHONE_CONTACT, '');

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

            || !Configuration::deleteByName(self::EMAIL_CONTACT)
            || !Configuration::deleteByName(self::TELEPHONE_CONTACT)

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
                copy($this->getPathThisModule() . '/views/img/logo.png', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
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
        // Almacena los datos de contacto
        Configuration::updateValue(self::EMAIL_CONTACT, Tools::getValue('email'));
        Configuration::updateValue(self::TELEPHONE_CONTACT, Tools::getValue('telephone'));
        // Configura cuenta PaymentRedirection
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
                'email' => $this->getEmailContact(),
                'telephone' => $this->getTelephoneContact(),

                'login' => $this->getLogin(),
                'trankey' => $this->getTrankey(),
                'urlnotification' => $this->getReturnURL(),
                'schudeletask' => $this->getPathSchudeleTask(),
                'environment' => $this->getEnvironment(),
                'stockreinject' => $this->getStockReinject(),
                'cifinmessage' => $this->getCifinMessage(),
            )
        );

        return $this->display($this->getPathThisModule(), '/views/templates/setting.tpl');
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
                'lastOrder' => $pending['reference'],
                'lastAuthorization' => (string) $pending['authcode'],
                'storeEmail' => $this->getEmailContact(),
                'storePhone' => $this->getTelephoneContact()
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
        return $this->display($this->getPathThisModule(), '/views/templates/payment.tpl');
    }

    /**
     * Obtiene la última transacción pendiente de pago utilizando el medio
     * @param $customer_id
     * @return mixed
     */
    private function getLastPendingTransaction($customer_id)
    {
        $status = PaymentRedirection::P2P_PENDING;

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
            case 'PRODUCTION':
                $uri = PaymentRedirection::P2P_PRODUCTION;
                break;
            case 'TEST':
                $uri = PaymentRedirection::P2P_TEST;
                break;
            case 'DEVELOPMENT':
                $uri = PaymentRedirection::P2P_DEVELOPMENT;
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
    public function getOrderState()
    {
        return Configuration::get(self::ORDER_STATE);
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
            throw new PaymentException(Tools::displayError(), 109);
        }

        $order = new Order($order_id);

        if (!Validate::isLoadedObject($order)) {
            throw new PaymentException(Tools::displayError(), 110);
        }

        return $order;
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
        $invoice_address = new Address((int)($cart->id_address_invoice));
        $delivery_address = new Address((int)($cart->id_address_delivery));
        $total_amount = floatval($cart->getOrderTotal(true, Cart::BOTH));
        $tax_amount = $total_amount - floatval($cart->getOrderTotal(false, Cart::BOTH));

        // Verifica que los objetos se hayan cargado correctamente
        if (!Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($invoice_address)
            || !Validate::isLoadedObject($delivery_address)
            || !Validate::isLoadedObject($currency)
        ) {
            throw new PaymentException('invalid address or customer', 106);
        }

        // Recupera otra informacion relacionada con la orden
        $delivery_country = new Country((int)($delivery_address->id_country));
        $delivery_state = null;
        if ($delivery_address->id_state) {
            $delivery_state = new State((int)($delivery_address->id_state));
        }

        // Construye la URL de retorno, al que se redirecciona desde el proceso de pago
        $reference = date('YmdHi') . $cart->id;
        $ip_address = (new RemoteAddress())->getIpAddress();
        $return_url = $this->getReturnURL('?cart_id=' . $cart->id);

        // Crea solicitud de pago en Redirección
        $request = [
            'locale' => ($language == 'en') ? 'en_US' : 'es_CO',
            'returnUrl' => $return_url,
            'ip_address' => $ip_address,
            'expiration' => date('c', strtotime('+2 days')),
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
                    'taxes' => [
                        [
                            'kind' => 'valueAddedTax',
                            'amount' => $tax_amount,
                            'base' => $total_amount - $tax_amount,
                        ]
                    ]
                ]
            ]
        ];

        // Crea Instancia Placetopay
        $placetopay = new PaymentRedirection($this->getLogin(), $this->getTrankey(), $this->getUri());
        $response = $placetopay->request($request);

        try {

            if ($response->isSuccessful()) {
                $request_id = $response->requestId();
                $_SESSION['request_id'] = $request_id;
                $order_message = 'Success';
                $order_status = $this->getOrderState();
                $status = PaymentRedirection::P2P_PENDING;
                $payment_url = $response->processUrl();

            } else {
                $request_id = 0;
                $order_message = $response->status()->message();
                $order_status = Configuration::get('PS_OS_ERROR');
                $status = PaymentRedirection::P2P_FAILED;
                $total_amount = 0;

                // Genera la redirección al estado de la orden si no se pudo hacer la redireccion
                $order = new Order($this->currentOrder);
                $payment_url = __PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cart->id
                    . '&id_module=' . $this->id
                    . '&id_order=' . $this->currentOrder
                    . '&key=' . $order->secure_key;
            }

            // Genera la orden en prestashop, si no se generó la URL
            // crea la orden con el error, al menos para que quede asentada

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

            // Inserta la transacción en la tabla de PlacetoPay
            $this->insertPaymentPlaceToPay($request_id, $cart->id, $cart->id_currency, $total_amount, $status, $order_message, $ip_address, $reference);

            // Envia flujo a redireccion para realizar el pago
            Tools::redirectLink($payment_url);

        } catch (Exception $e) {
            throw new PaymentException($response->status()->message() . "\n" . $e->getMessage(), 107);
        }
    }

    /**
     * Registra orden en los pagos de PaymentRedirection
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
            throw new PaymentException('Cannot insert transaction ' . $sql, 111);
        }

        return true;
    }

    /**
     * Procesa la respuesta de pago dada por la plataforma
     * @param array $cart_id
     */
    public function process($cart_id = null)
    {

        if (!is_null($cart_id) && !empty($_SESSION['request_id'])) {
            // Redireccion desde el proceso de pagos
            $request_id = $_SESSION['request_id'];
        } elseif (!empty(file_get_contents("php://input"))) {
            // Respuesta por norificationURL enviado desde PaymentRedirection
            $json = file_get_contents("php://input");
            $obj = json_decode($json);
            $request_id = $obj->requestId;
            $cart_id = $this->getCartByRequestId((int)$request_id);
        } else {
            // Opción no válida, se cancela
            throw new PaymentException('option not valid in process', 108);
        }

        $order = $this->getRelatedOrder($cart_id);

        // Consulta el estado de la transaccion
        $placetopay = new PaymentRedirection($this->getLogin(), $this->getTrankey(), $this->getUri());

        $response = $placetopay->query($request_id);

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
        // By default ss pending so make a query for it later (see information.php example)
        $status = PaymentRedirection::P2P_PENDING;

        if ($response->isSuccessful()) {
            // In order to use the functions please refer to the RedirectInformation class
            if ($response->status()->isApproved()) {
                // Approved status
                $status = PaymentRedirection::P2P_APPROVED;
            } elseif ($response->status()->isRejected()) {
                // This is why it has been rejected
                $status = PaymentRedirection::P2P_DECLINED;
            }
        }

        return $status;
    }

    private function settleTransaction($status, $cart_id, Order $order, $response)
    {
        // Si ya habia sido aprobada no vuelva a reprocesar
        if ($order->getCurrentState() != (int)Configuration::get('PS_OS_PAYMENT')) {
            // procese la respuesta y dependiendo del tipo de respuesta
            switch ($status) {
                case PaymentRedirection::P2P_FAILED:
                case PaymentRedirection::P2P_DECLINED:

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

                    // Genera un nuevo estado en la orden de declinación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);

                    if ($status == PaymentRedirection::P2P_FAILED) {
                        $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                    } elseif ($status == PaymentRedirection::P2P_DECLINED) {
                        $history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $history->id_order);
                    }
                    $history->addWithemail();
                    $history->save();

                    // obtiene los productos de la orden, los recorre y vuelve a recargar las cantidades
                    // en el inventario
                    if ($this->getStockReinject() == '1') {
                        $products = $order->getProducts();
                        foreach ($products as $product) {
                            $order_detail = new OrderDetail((int)($product['id_order_detail']));
                            Product::reinjectQuantities($order_detail, $product['product_quantity']);
                        }
                    }
                    break;
                case PaymentRedirection::P2P_DUPLICATE:
                case PaymentRedirection::P2P_APPROVED:
                    // genera un nuevo estado en la orden de aprobación
                    $history = new OrderHistory();
                    $history->id_order = (int)($order->id);
                    $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $history->id_order);
                    $history->addWithemail();
                    $history->save();
                    break;
                case PaymentRedirection::P2P_PENDING:
                    break;
            }
        }

        // Actualiza la tabla de PlacetoPay con la información de la transacción
        $this->updateTransaction($cart_id, $status, $response);
    }

    /**
     * @param $id_order
     * @param $status
     * @param $payment
     * @return mixed
     */
    private function updateTransaction($id_order, $status, $payment)
    {
        $date = pSQL($payment->status()->date());
        $reason = pSQL($payment->status()->reason());
        $reason_description = pSQL($payment->status()->message());

        $bank = '';
        $franchise = '';
        $franchise_name = '';
        $auth_code = '';
        $receipt = '';
        $conversion = '';
        $payer_email = '';

        if (isset($payment->payment)) {
            $date = pSQL($payment->payment[0]->status()->date());
            $reason = pSQL($payment->payment[0]->status()->reason());
            $reason_description = pSQL($payment->payment[0]->status()->message());

            $bank = pSQL($payment->payment[0]->issuerName());
            $franchise = pSQL($payment->payment[0]->paymentMethod());
            $franchise_name = pSQL($payment->payment[0]->paymentMethodName());
            $auth_code = pSQL($payment->payment[0]->authorization());
            $receipt = pSQL($payment->payment[0]->receipt());
            $conversion = pSQL($payment->payment[0]->amount()->factor());
        }

        if (!empty($payment->request()->buyer()->email())) {
            $payer_email = pSQL($payment->request()->buyer()->email());
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
            throw new PaymentException('Cannot update transaction ' . $sql, 112);
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
        $invoice_address = new Address((int)($cart->id_address_invoice));
        $delivery_address = new Address((int)($cart->id_address_delivery));
        $total_amount = (float)($cart->getOrderTotal(true, Cart::BOTH));
        $tax_amount = $total_amount - (float)($cart->getOrderTotal(false, Cart::BOTH));
        $payer_email = $transaction['payer_email'];
        $transaction['tax'] = $tax_amount;

        $smarty->assign('transaction', $transaction);

        switch ($transaction['status']) {
            case PaymentRedirection::P2P_APPROVED:
            case PaymentRedirection::P2P_DUPLICATE:
                $smarty->assign('status', 'ok');
                $smarty->assign('status_description', 'Transacción aprobada');
                break;
            case PaymentRedirection::P2P_FAILED:
                $smarty->assign('status', 'fail');
                $smarty->assign('status_description', 'Transacción fallida');
                break;
            case PaymentRedirection::P2P_DECLINED:
                $smarty->assign('status', 'rejected');
                $smarty->assign('status_description', 'Transacción rechazada');
                break;
            case PaymentRedirection::P2P_PENDING:
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

        // Obtiene los datos del cliente
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

        return $this->display($this->getPathThisModule(), '/views/templates/response.tpl');
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
              AND `status` = " . PaymentRedirection::P2P_PENDING;;

        if ($result = Db::getInstance()->ExecuteS($sql)) {

            echo "Found (" . count($result) . ") payments pending." . PHP_EOL;

            $placetopay = new PaymentRedirection($this->getLogin(), $this->getTrankey(), $this->getUri());

            foreach ($result as $row) {
                $request_id = (int)$row['id_request'];
                $cart_id = (int)$row['id_order'];

                // Consta estado de la transaccion en PaymentRedirection
                $response = $placetopay->query($request_id);
                $status = $this->getStatusPayment($response);
                $order = $this->getRelatedOrder($cart_id);

                if ($order) {
                    $this->settleTransaction($status, $cart_id, $order, $response);
                }
            }
        }
        echo 'Finished' . PHP_EOL;
    }
}
