<?php

namespace PlacetoPay\Models;

use Address;
use Cart;
use Configuration;
use Context;
use Country;
use Currency;
use CurrencyCore;
use Customer;
use Db;
use Dnetix\Redirection\Message\Notification;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\Validators\Currency as CurrencyValidator;
use Exception;
use HelperForm;
use Language;
use Order;
use OrderHistory;
use OrderState;
use PaymentModule;
use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;
use PlacetoPay\Constants\PaymentMethod;
use PlacetoPay\Constants\PaymentStatus;
use PlacetoPay\Constants\PaymentUrl;
use PlacetoPay\Exceptions\PaymentException;
use PlacetoPay\Loggers\PaymentLogger;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopDatabaseException;
use Shop;
use State;
use Tools;
use Validate;

/**
 * Class PlaceToPayPaymentMethod
 * @property string currentOrderReference
 * @property string name
 * @property string version
 * @property bool active
 * @property int id
 * @property mixed context
 * @property mixed currentOrder
 * @property int identifier
 * @property string table
 * @property mixed smarty
 * @property string warning
 * @property string confirmUninstall
 * @property string description
 * @property string displayName
 * @property bool bootstrap
 * @property string currencies_mode
 * @property bool currencies
 * @property int is_eu_compatible
 * @property array controllers
 * @property array ps_versions_compliancy
 * @property array limited_countries
 * @property string tab
 * @property string author
 */
class PaymentPrestaShop extends PaymentModule
{
    /**
     * Configuration module vars
     */
    const COMPANY_DOCUMENT = 'PLACETOPAY_COMPANYDOCUMENT';
    const COMPANY_NAME = 'PLACETOPAY_COMPANYNAME';
    const EMAIL_CONTACT = 'PLACETOPAY_EMAILCONTACT';
    const TELEPHONE_CONTACT = 'PLACETOPAY_TELEPHONECONTACT';
    const DESCRIPTION = 'PLACETOPAY_DESCRIPTION';

    const EXPIRATION_TIME_MINUTES = 'PLACETOPAY_EXPIRATION_TIME_MINUTES';
    const SHOW_ON_RETURN = 'PLACETOPAY_SHOWONRETURN';
    const CIFIN_MESSAGE = 'PLACETOPAY_CIFINMESSAGE';
    const ALLOW_BUY_WITH_PENDING_PAYMENTS = 'PLACETOPAY_ALLOWBUYWITHPENDINGPAYMENTS';
    const FILL_TAX_INFORMATION = 'PLACETOPAY_FILL_TAX_INFORMATION';
    const FILL_BUYER_INFORMATION = 'PLACETOPAY_FILL_BUYER_INFORMATION';
    const SKIP_RESULT = 'PLACETOPAY_SKIP_RESULT';
    const PAYMENT_METHODS_ENABLED = 'PLACETOPAY_PAYMENT_METHODS_ENABLED';
    const STOCK_REINJECT = 'PLACETOPAY_STOCKREINJECT';

    const COUNTRY = 'PLACETOPAY_COUNTRY';
    const ENVIRONMENT = 'PLACETOPAY_ENVIRONMENT';
    const CUSTOM_CONNECTION_URL = 'PLACETOPAY_CUSTOM_CONNECTION_URL';
    const LOGIN = 'PLACETOPAY_LOGIN';
    const TRAN_KEY = 'PLACETOPAY_TRANKEY';
    const CONNECTION_TYPE = 'PLACETOPAY_CONNECTION_TYPE';

    const EXPIRATION_TIME_MINUTES_DEFAULT = 120; // 2 Hours
    const EXPIRATION_TIME_MINUTES_MIN = 10; // 10 Minutes

    const SHOW_ON_RETURN_DEFAULT = 'default';
    const SHOW_ON_RETURN_PSE_LIST = 'pse_list';
    const SHOW_ON_RETURN_DETAILS = 'details';

    const CONNECTION_TYPE_SOAP = 'soap';
    const CONNECTION_TYPE_REST = 'rest';

    const OPTION_ENABLED = '1';
    const OPTION_DISABLED = '0';

    const PAYMENT_METHODS_ENABLED_DEFAULT = 'ALL';

    const ORDER_STATE = 'PS_OS_PLACETOPAY';

    const PAGE_ORDER_CONFIRMATION = 'order-confirmation.php';
    const PAGE_ORDER_HISTORY = 'history.php';
    const PAGE_ORDER_DETAILS = 'index.php?controller=order-detail';

    const MIN_VERSION_PS = '1.6.0.5';

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
         * PHP < 5.6 not allowed this definitions in constructor
         */
        $this->tablePayment = _DB_PREFIX_ . 'payment_placetopay';
        $this->tableOrder = _DB_PREFIX_ . 'orders';

        $this->name = getModuleName();
        $this->version = '3.3.0';
        $this->author = 'EGM Ingeniería sin Fronteras S.A.S';
        $this->tab = 'payments_gateways';

        $this->limited_countries = [
            'gb',
            'us',
            CountryCode::COLOMBIA,
            CountryCode::ECUADOR
        ];

        $this->ps_versions_compliancy = [
            'min' => self::MIN_VERSION_PS,
            'max' => _PS_VERSION_
        ];

        $this->controllers = ['validation'];
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->ll('PlacetoPay');
        $this->description = $this->ll('Accept payments by credit cards and debits account');

        $this->confirmUninstall = $this->ll('Are you sure you want to uninstall?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->ll('No currency has been set for this module.');
        }

        if (!$this->isSetCredentials()) {
            $this->warning = $this->ll('You need to configure your PlacetoPay account before using this module');
        }

        @date_default_timezone_set(Configuration::get('PS_TIMEZONE'));
    }

    /**
     * @return string
     */
    private function getPluginVersion()
    {
        return $this->version;
    }

    /**
     * Create payments table and status order
     *
     * @return bool
     */
    public function install()
    {
        $error = '';

        if (!parent::install()
            || !$this->createPaymentTable()
            || !$this->createOrderState()
            || !$this->alterColumnIpAddress()
            || !$this->addColumnEmail()
            || !$this->addColumnRequestId()
            || !$this->addColumnReference()
        ) {
            $error = 'Error on install module';
        }

        if (empty($error) && !$this->registerHook('paymentReturn')) {
            $error = 'Error registering paymentReturn hook';
        }

        $hookPaymentName = versionComparePlaceToPay('1.7.0.0', '>=') ? 'paymentOptions' : 'payment';

        if (empty($error) && !$this->registerHook($hookPaymentName)) {
            $error = sprintf('Error on install registering %s hook', $hookPaymentName);
        }

        if (empty($error)) {
            // Default values
            Configuration::updateValue(self::COMPANY_DOCUMENT, '');
            Configuration::updateValue(self::COMPANY_NAME, '');
            Configuration::updateValue(self::EMAIL_CONTACT, '');
            Configuration::updateValue(self::TELEPHONE_CONTACT, '');
            Configuration::updateValue(self::DESCRIPTION, 'Pago en PlacetoPay No: %s');

            Configuration::updateValue(self::EXPIRATION_TIME_MINUTES, self::EXPIRATION_TIME_MINUTES_DEFAULT);
            Configuration::updateValue(self::SHOW_ON_RETURN, self::SHOW_ON_RETURN_PSE_LIST);
            Configuration::updateValue(self::CIFIN_MESSAGE, self::OPTION_DISABLED);
            Configuration::updateValue(self::ALLOW_BUY_WITH_PENDING_PAYMENTS, self::OPTION_ENABLED);
            Configuration::updateValue(self::FILL_TAX_INFORMATION, self::OPTION_ENABLED);
            Configuration::updateValue(self::FILL_BUYER_INFORMATION, self::OPTION_ENABLED);
            Configuration::updateValue(self::SKIP_RESULT, self::OPTION_DISABLED);
            Configuration::updateValue(self::PAYMENT_METHODS_ENABLED, self::PAYMENT_METHODS_ENABLED_DEFAULT);

            if (versionComparePlaceToPay('1.7.0.0', '<')) {
                Configuration::updateValue(self::STOCK_REINJECT, self::OPTION_ENABLED);
            }

            Configuration::updateValue(self::COUNTRY, CountryCode::COLOMBIA);
            Configuration::updateValue(self::ENVIRONMENT, Environment::TEST);
            Configuration::updateValue(self::CUSTOM_CONNECTION_URL, '');
            Configuration::updateValue(self::LOGIN, '');
            Configuration::updateValue(self::TRAN_KEY, '');
            Configuration::updateValue(self::CONNECTION_TYPE, self::CONNECTION_TYPE_REST);

            return true;
        } else {
            PaymentLogger::log($error, PaymentLogger::ERROR, 100, __FILE__, __LINE__);

            return false;
        }
    }

    /**
     * Delete configuration vars
     * This not delete tables and status order
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!Configuration::deleteByName(self::COMPANY_DOCUMENT)
            || !Configuration::deleteByName(self::COMPANY_NAME)
            || !Configuration::deleteByName(self::EMAIL_CONTACT)
            || !Configuration::deleteByName(self::TELEPHONE_CONTACT)
            || !Configuration::deleteByName(self::DESCRIPTION)

            || !Configuration::deleteByName(self::EXPIRATION_TIME_MINUTES)
            || !Configuration::deleteByName(self::SHOW_ON_RETURN)
            || !Configuration::deleteByName(self::CIFIN_MESSAGE)
            || !Configuration::deleteByName(self::ALLOW_BUY_WITH_PENDING_PAYMENTS)
            || !Configuration::deleteByName(self::FILL_TAX_INFORMATION)
            || !Configuration::deleteByName(self::FILL_BUYER_INFORMATION)
            || !Configuration::deleteByName(self::SKIP_RESULT)
            || !Configuration::deleteByName(self::PAYMENT_METHODS_ENABLED)

            || !Configuration::deleteByName(self::COUNTRY)
            || !Configuration::deleteByName(self::ENVIRONMENT)
            || !Configuration::deleteByName(self::CUSTOM_CONNECTION_URL)
            || !Configuration::deleteByName(self::LOGIN)
            || !Configuration::deleteByName(self::TRAN_KEY)
            || !Configuration::deleteByName(self::CONNECTION_TYPE)
            || !parent::uninstall()
        ) {
            return false;
        }

        if (versionComparePlaceToPay('1.7.0.0', '<') && !Configuration::deleteByName(self::STOCK_REINJECT)) {
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
                `ipaddress` VARCHAR(30) NULL,
                INDEX `id_orderIX` (`id_order`)
            ) ENGINE = " . _MYSQL_ENGINE_;

        try {
            Db::getInstance()->Execute($sql);
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 1, $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function addColumnEmail()
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `payer_email` VARCHAR(80) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 2, $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function addColumnRequestId()
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `id_request` INT NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 3, $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function addColumnReference()
    {
        $sql = "ALTER TABLE `{$this->tablePayment}` ADD `reference` VARCHAR(60) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 4, $e->getFile(), $e->getLine());
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function alterColumnIpAddress()
    {
        // In all version < 2.0 this columns is bad name ipaddress => ip_address
        $sql = "ALTER TABLE `{$this->tablePayment}` CHANGE COLUMN `ipaddress` `ip_address` VARCHAR(30) NULL;";

        try {
            Db::getInstance()->Execute($sql);
        } catch (PrestaShopDatabaseException $e) {
            // Column had been to change before
            PaymentLogger::log($e->getMessage(), PaymentLogger::INFO, 0, $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 5, $e->getFile(), $e->getLine());
            return false;
        }

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
            $orderState->name = [];

            foreach (Language::getLanguages() as $language) {
                $lang = $language['id_lang'];

                switch (strtolower($language['iso_code'])) {
                    case 'en':
                        $orderState->name[$lang] = 'Awaiting ' . $this->displayName . ' payment confirmation';
                        break;
                    case 'fr':
                        $orderState->name[$lang] = 'En attente du paiement par ' . $this->displayName;
                        break;
                    case 'es':
                    default:
                        $orderState->name[$lang] = 'En espera de confirmación de pago por ' . $this->displayName;
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
                copy(
                    fixPath($this->getPathThisModule() . '/logo.png'),
                    fixPath(_PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif')
                );
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
        $contentExtra = '';

        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            $formErrors = $this->formValidation();

            if (count($formErrors) == 0) {
                $this->formProcess();

                $contentExtra = $this->displayConfirmation($this->ll('PlacetoPay settings updated'));
            } else {
                $contentExtra = $this->showError($formErrors);
            }
        }

        return $contentExtra . $this->displayConfiguration() . $this->renderForm();
    }

    /**
     * Validation data from post settings form
     * @return array
     */
    protected function formValidation()
    {
        $formErrors = [];

        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            // Company data
            if (!Tools::getValue(self::COMPANY_DOCUMENT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Merchant ID'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::COMPANY_NAME)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Legal Name'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::EMAIL_CONTACT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Email contact'), $this->ll('is required.'));
            } elseif (filter_var(Tools::getValue(self::EMAIL_CONTACT), FILTER_VALIDATE_EMAIL) === false) {
                $formErrors[] = sprintf('%s %s', $this->ll('Email contact'), $this->ll('is not valid.'));
            }

            if (!Tools::getValue(self::TELEPHONE_CONTACT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Telephone contact'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::DESCRIPTION)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Payment description'), $this->ll('is required.'));
            }

            // Configuration Connection
            if (!Tools::getValue(self::EXPIRATION_TIME_MINUTES)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Expiration time to pay'), $this->ll('is required.'));
            } elseif (filter_var(Tools::getValue(self::EXPIRATION_TIME_MINUTES), FILTER_VALIDATE_INT) === false
                || Tools::getValue(self::EXPIRATION_TIME_MINUTES) < self::EXPIRATION_TIME_MINUTES_MIN) {
                $formErrors[] = sprintf(
                    '%s %s (min %d)',
                    $this->ll('Expiration time to pay'),
                    $this->ll('is not valid.'),
                    self::EXPIRATION_TIME_MINUTES_MIN
                );
            }

            if (!Tools::getValue(self::COUNTRY)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Country'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::ENVIRONMENT)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Environment'), $this->ll('is required.'));
            } elseif (Tools::getValue(self::ENVIRONMENT) === Environment::CUSTOM
                && filter_var(Tools::getValue(self::CUSTOM_CONNECTION_URL), FILTER_VALIDATE_URL) === false) {
                $formErrors[] = sprintf('%s %s', $this->ll('Custom connection URL'), $this->ll('is not valid.'));
            }

            if (!Tools::getValue(self::LOGIN)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Login'), $this->ll('is required.'));
            }

            if (empty($this->getCurrentValueOf(self::TRAN_KEY)) && !Tools::getValue(self::TRAN_KEY)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Trankey'), $this->ll('is required.'));
            }

            if (!Tools::getValue(self::CONNECTION_TYPE)) {
                $formErrors[] = sprintf('%s %s', $this->ll('Connection type'), $this->ll('is required.'));
            }
        }

        return $formErrors;
    }

    /**
     * Update configuration vars
     */
    private function formProcess()
    {
        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            // Company data
            Configuration::updateValue(self::COMPANY_DOCUMENT, Tools::getValue(self::COMPANY_DOCUMENT));
            Configuration::updateValue(self::COMPANY_NAME, Tools::getValue(self::COMPANY_NAME));
            Configuration::updateValue(self::EMAIL_CONTACT, Tools::getValue(self::EMAIL_CONTACT));
            Configuration::updateValue(self::TELEPHONE_CONTACT, Tools::getValue(self::TELEPHONE_CONTACT));
            Configuration::updateValue(self::DESCRIPTION, Tools::getValue(self::DESCRIPTION));
            // Configuration
            Configuration::updateValue(self::EXPIRATION_TIME_MINUTES, Tools::getValue(self::EXPIRATION_TIME_MINUTES));
            Configuration::updateValue(self::SHOW_ON_RETURN, Tools::getValue(self::SHOW_ON_RETURN));
            Configuration::updateValue(self::CIFIN_MESSAGE, Tools::getValue(self::CIFIN_MESSAGE));
            Configuration::updateValue(
                self::ALLOW_BUY_WITH_PENDING_PAYMENTS,
                Tools::getValue(self::ALLOW_BUY_WITH_PENDING_PAYMENTS)
            );
            Configuration::updateValue(self::FILL_TAX_INFORMATION, Tools::getValue(self::FILL_TAX_INFORMATION));
            Configuration::updateValue(self::FILL_BUYER_INFORMATION, Tools::getValue(self::FILL_BUYER_INFORMATION));
            Configuration::updateValue(self::SKIP_RESULT, Tools::getValue(self::SKIP_RESULT));
            Configuration::updateValue(self::PAYMENT_METHODS_ENABLED, PaymentMethod::getPaymentMethodsSelected(
                Tools::getValue(self::PAYMENT_METHODS_ENABLED),
                Tools::getValue(self::COUNTRY)
            ));

            if (versionComparePlaceToPay('1.7.0.0', '<')) {
                Configuration::updateValue(self::STOCK_REINJECT, Tools::getValue(self::STOCK_REINJECT));
            }

            // Configuration Connection
            Configuration::updateValue(self::COUNTRY, Tools::getValue(self::COUNTRY));
            Configuration::updateValue(self::ENVIRONMENT, Tools::getValue(self::ENVIRONMENT));
            // Set or clean custom URL
            $this->isCustomEnvironment()
                ? Configuration::updateValue(self::CUSTOM_CONNECTION_URL, Tools::getValue(self::CUSTOM_CONNECTION_URL))
                : Configuration::updateValue(self::CUSTOM_CONNECTION_URL, '');
            Configuration::updateValue(self::LOGIN, Tools::getValue(self::LOGIN));

            if (Tools::getValue(self::TRAN_KEY)) {
                // Value changed
                Configuration::updateValue(self::TRAN_KEY, Tools::getValue(self::TRAN_KEY));
            }

            Configuration::updateValue(self::CONNECTION_TYPE, Tools::getValue(self::CONNECTION_TYPE));
        }
    }

    /**
     * Show configuration form
     *
     * @return string
     */
    private function displayConfiguration()
    {
        $this->smarty->assign(
            [
                'version' => $this->getPluginVersion(),
                'url_notification' => $this->getUrl('process.php'),
                'schedule_task' => $this->getPathScheduleTask(),
                'is_set_credentials' => $this->isSetCredentials(),
            ]
        );

        return $this->display($this->getPathThisModule(), fixPath('/views/templates/front/setting.tpl'));
    }

    /**
     * Show warning pending payment
     *
     * @param $lastPendingTransaction
     * @return string
     */
    private function displayPendingPaymentMessage($lastPendingTransaction)
    {
        $this->smarty->assign([
            'last_order' => isset($lastPendingTransaction['reference'])
                ? $lastPendingTransaction['reference']
                : '########',
            'last_authorization' => isset($lastPendingTransaction['authcode'])
                ? $lastPendingTransaction['authcode']
                : null,
            'telephone_contact' => $this->getTelephoneContact(),
            'email_contact' => $this->getEmailContact(),
            'allow_payment' => $this->getAllowBuyWithPendingPayments(),
        ]);

        return $this->display($this->getPathThisModule(), fixPath('/views/templates/hook/pending_payment.tpl'));
    }

    /**
     * Show warning pending payment
     *
     * @return string
     */
    private function displayTransUnionMessage()
    {
        $this->smarty->assign([
            'site_name' => Configuration::get('PS_SHOP_NAME'),
            'company_name' => $this->getCompanyName(),
        ]);

        return $this->display($this->getPathThisModule(), fixPath('/views/templates/hook/message_payment.tpl'));
    }

    /**
     * Show warning pending payment
     *
     * @return string
     */
    private function displayBrandMessage()
    {
        return $this->display($this->getPathThisModule(), fixPath('/views/templates/hook/brand_payment.tpl'));
    }

    /**
     * @return array
     */
    private function getConfigFieldsValues()
    {
        $compatibility_1_6 = [];

        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            $compatibility_1_6 = [self::STOCK_REINJECT => $this->getStockReInject()];
        }

        return array_merge([
            self::COMPANY_DOCUMENT => $this->getCompanyDocument(),
            self::COMPANY_NAME => $this->getCompanyName(),
            self::EMAIL_CONTACT => $this->getEmailContact(),
            self::TELEPHONE_CONTACT => $this->getTelephoneContact(),
            self::DESCRIPTION => $this->getDescription(),

            self::EXPIRATION_TIME_MINUTES => $this->getExpirationTimeMinutes(),
            self::SHOW_ON_RETURN => $this->getShowOnReturn(),
            self::CIFIN_MESSAGE => $this->getTransUnionMessage(),
            self::ALLOW_BUY_WITH_PENDING_PAYMENTS => $this->getAllowBuyWithPendingPayments(),
            self::FILL_TAX_INFORMATION => $this->getFillTaxInformation(),
            self::FILL_BUYER_INFORMATION => $this->getFillBuyerInformation(),
            self::SKIP_RESULT => $this->getSkipResult(),
            $this->getNameInMultipleFormat(self::PAYMENT_METHODS_ENABLED) => PaymentMethod::toArray(
                $this->getPaymentMethodsEnabled()
            ),

            self::COUNTRY => $this->getCountry(),
            self::ENVIRONMENT => $this->getEnvironment(),
            self::CUSTOM_CONNECTION_URL => $this->isCustomEnvironment() ? $this->getCustomConnectionUrl() : '',
            self::LOGIN => $this->getLogin(),
            self::TRAN_KEY => $this->getTranKey(),
            self::CONNECTION_TYPE => $this->getConnectionType(),
        ], $compatibility_1_6);
    }

    /**
     * @return string
     */
    private function renderForm()
    {
        $compatibility_1_6 = null;

        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            $compatibility_1_6 = [
                'type' => 'switch',
                'label' => $this->ll('Re-inject stock on declination?'),
                'name' => self::STOCK_REINJECT,
                'is_bool' => true,
                'values' => $this->getListOptionSwitch(),
            ];
        }

        $fieldsFormCompany = [
            'form' => [
                'legend' => [
                    'title' => $this->ll('Company data'),
                    'icon' => 'icon-building'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->ll('Merchant ID'),
                        'name' => self::COMPANY_DOCUMENT,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->ll('Legal Name'),
                        'name' => self::COMPANY_NAME,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->ll('Email contact'),
                        'name' => self::EMAIL_CONTACT,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->ll('Telephone contact'),
                        'name' => self::TELEPHONE_CONTACT,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->ll('Payment description'),
                        'name' => self::DESCRIPTION,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                ],
                'submit' => [
                    'title' => $this->ll('Save'),
                ]
            ],
        ];

        $fieldsFormSetup = [
            'form' => [
                'legend' => [
                    'title' => $this->ll('Configuration'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->ll('Expiration time to pay'),
                        'name' => self::EXPIRATION_TIME_MINUTES,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->ll('Show on payment return'),
                        'desc' => $this->ll('If you has PSE method payment in your commerce, set it in: PSE List.'),
                        'name' => self::SHOW_ON_RETURN,
                        'options' => [
                            'id' => 'value',
                            'name' => 'label',
                            'query' => $this->getListOptionShowOnReturn(),
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->ll('Enable TransUnion message?'),
                        'name' => self::CIFIN_MESSAGE,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->ll('Allow buy with pending payments?'),
                        'name' => self::ALLOW_BUY_WITH_PENDING_PAYMENTS,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->ll('Fill TAX information?'),
                        'name' => self::FILL_TAX_INFORMATION,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->ll('Fill buyer information?'),
                        'name' => self::FILL_BUYER_INFORMATION,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->ll('Skip result?'),
                        'name' => self::SKIP_RESULT,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ],
                    [
                        'type' => 'select',
                        'multiple' => true,
                        'label' => $this->ll('Payment methods enabled'),
                        'name' => $this->getNameInMultipleFormat(self::PAYMENT_METHODS_ENABLED),
                        'id' => self::PAYMENT_METHODS_ENABLED,
                        // @codingStandardsIgnoreLine
                        'desc' => $this->ll('IMPORTANT: Payment methods in PlacetoPay will restrict by this selection. [Ctrl + Clic] to select several'),
                        'options' => [
                            'id' => 'value',
                            'name' => 'label',
                            'query' => $this->getListOptionPaymentMethods(),
                        ]
                    ],
                    $compatibility_1_6,
                ],
                'submit' => [
                    'title' => $this->ll('Save'),
                ]
            ],
        ];

        $fieldsFormConnection = [
            'form' => [
                'legend' => [
                    'title' => $this->ll('Configuration Connection'),
                    'icon' => 'icon-rocket'
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->ll('Country'),
                        'name' => self::COUNTRY,
                        'required' => true,
                        'options' => [
                            'id' => 'value',
                            'name' => 'label',
                            'query' => [
                                [
                                    'value' => CountryCode::COLOMBIA,
                                    'label' => $this->ll('Colombia'),
                                ],
                                [
                                    'value' => CountryCode::ECUADOR,
                                    'label' => $this->ll('Ecuador'),
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->ll('Environment'),
                        'name' => self::ENVIRONMENT,
                        'required' => true,
                        'options' => [
                            'id' => 'value',
                            'name' => 'label',
                            'query' => [
                                [
                                    'value' => Environment::PRODUCTION,
                                    'label' => $this->ll('Production'),
                                ],
                                [
                                    'value' => Environment::TEST,
                                    'label' => $this->ll('Test'),
                                ],
                                [
                                    'value' => Environment::DEVELOPMENT,
                                    'label' => $this->ll('Development'),
                                ],
                                [
                                    'value' => Environment::CUSTOM,
                                    'label' => $this->ll('Custom'),
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->ll('Custom connection URL'),
                        'desc' => sprintf(
                            '%s %s: %s',
                            // @codingStandardsIgnoreLine
                            $this->ll('By example: "https://alternative.placetopay.com/redirection". This value only is required when you select'),
                            $this->ll('Environment'),
                            $this->ll('Custom')
                        ),
                        'name' => self::CUSTOM_CONNECTION_URL,
                        'required' => $this->isCustomEnvironment(),
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->ll('Login'),
                        'name' => self::LOGIN,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->ll('Trankey'),
                        'name' => self::TRAN_KEY,
                        'required' => true,
                        'autocomplete' => 'off',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->ll('Connection type'),
                        'name' => self::CONNECTION_TYPE,
                        'required' => true,
                        'options' => [
                            'id' => 'value',
                            'name' => 'label',
                            'query' => [
                                [
                                    'value' => self::CONNECTION_TYPE_SOAP,
                                    'label' => $this->ll('SOAP'),
                                ],
                                [
                                    'value' => self::CONNECTION_TYPE_REST,
                                    'label' => $this->ll('REST'),
                                ],
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->ll('Save'),
                ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPlacetoPayConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
            . getModuleName() . '&tab_module=' . $this->tab . '&module_name=' . getModuleName();
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];

        return $helper->generateForm([$fieldsFormCompany, $fieldsFormSetup, $fieldsFormConnection]);
    }

    /**
     * PrestaShop 1.6
     *
     * @param array $params
     * @return string
     * @throws PaymentException
     */
    public function hookPayment($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(
                'Trigger ' . __METHOD__ . ' en PS vr. ' . _PS_VERSION_,
                PaymentLogger::DEBUG,
                0,
                __FILE__,
                __LINE__
            );
        }

        if (!$this->active) {
            return null;
        }

        if (!$this->isSetCredentials()) {
            PaymentLogger::log(
                $this->ll('You need to configure your PlacetoPay account before using this module.'),
                PaymentLogger::WARNING,
                6,
                __FILE__,
                __LINE__
            );

            return null;
        }

        $lastPendingTransaction = $this->getLastPendingTransaction($params['cart']->id_customer);

        if (!empty($lastPendingTransaction)) {
            $hasPendingTransaction = true;

            $this->context->smarty->assign([
                'last_order' => $lastPendingTransaction['reference'],
                'last_authorization' => (string)$lastPendingTransaction['authcode'],
                'store_email' => $this->getEmailContact(),
                'store_phone' => $this->getTelephoneContact()
            ]);

            $paymentUrl = $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED
                ? $this->getUrl('redirect.php')
                : 'javascript:;';

            $this->context->smarty->assign('payment_url', $paymentUrl);
        } else {
            $hasPendingTransaction = false;

            $this->context->smarty->assign('payment_url', $this->getUrl('redirect.php'));
        }

        $allowPayment = $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED || !$hasPendingTransaction;

        $this->context->smarty->assign('has_pending', $hasPendingTransaction);
        $this->context->smarty->assign('site_name', Configuration::get('PS_SHOP_NAME'));
        $this->context->smarty->assign('cifin_message', $this->getTransUnionMessage());
        $this->context->smarty->assign('company_name', $this->getCompanyName());
        $this->context->smarty->assign('allow_payment', $allowPayment);

        return $this->display($this->getPathThisModule(), fixPath('/views/templates/hook_1_6/payment.tpl'));
    }

    /**
     * PrestaShop 1.7 or later
     * @param $params
     * @return array
     * @throws PaymentException
     */
    public function hookPaymentOptions($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(
                'Trigger ' . __METHOD__ . ' en PS vr. ' . _PS_VERSION_,
                PaymentLogger::DEBUG,
                0,
                __FILE__,
                __LINE__
            );
        }

        if (!$this->active) {
            return null;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return null;
        }

        if (!$this->isSetCredentials()) {
            PaymentLogger::log(
                $this->ll('You need to configure your PlacetoPay account before using this module.'),
                PaymentLogger::WARNING,
                6,
                __FILE__,
                __LINE__
            );

            return null;
        }

        $content = $this->displayBrandMessage();
        $action = $this->getUrl('redirect.php');
        $lastPendingTransaction = $this->getLastPendingTransaction($params['cart']->id_customer);

        if (!empty($lastPendingTransaction)) {
            $content .= $this->displayPendingPaymentMessage($lastPendingTransaction);
            $action = $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED
                ? $this->getUrl('redirect.php')
                : null;
        }

        if ($action && $this->getTransUnionMessage() == self::OPTION_ENABLED) {
            $content .= $this->displayTransUnionMessage();
        }

        $form = $this->generateForm($action, $content);

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->ll('Pay by PlacetoPay'))
            ->setAdditionalInformation('')
            ->setForm($form);

        return [$newOption];
    }

    /**
     * @param $action
     * @param $content
     * @return string
     */
    private function generateForm($action, $content)
    {
        $action = is_null($action)
            ? "onsubmit='return false;'"
            : "action='{$action}'";

        $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

        return "<form accept-charset='UTF-8' {$action} id='payment-form'>{$content}</form>";
    }

    /**
     * Last transaction pending to current costumer
     *
     * @param $customerId
     * @return mixed
     * @throws PaymentException
     */
    private function getLastPendingTransaction($customerId)
    {
        $status = PaymentStatus::PENDING;

        try {
            $result = Db::getInstance()->ExecuteS("
                SELECT p.* 
                FROM `{$this->tablePayment}` p
                    INNER JOIN `{$this->tableOrder}` o ON o.id_cart = p.id_order
                WHERE o.`id_customer` = {$customerId} 
                    AND p.`status` = {$status} 
                LIMIT 1
            ");
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 901);
        }

        if (!empty($result)) {
            $result = $result[0];
        }

        return $result;
    }

    /**
     * @return string
     */
    private function getUri()
    {
        $uri = null;
        $endpoints = PaymentUrl::getEndpointsTo($this->getCountry());

        if ($this->isCustomEnvironment()) {
            $uri = $this->getCustomConnectionUrl();
        } elseif (!empty($endpoints[$this->getEnvironment()])) {
            $uri = $endpoints[$this->getEnvironment()];
        }

        return $uri;
    }

    /**
     * @return string
     */
    private function getCountry()
    {
        $country = $this->getCurrentValueOf(self::COUNTRY);

        return empty($country)
            ? CountryCode::COLOMBIA
            : $country;
    }

    /**
     * @return string
     */
    private function getEnvironment()
    {
        $environment = $this->getCurrentValueOf(self::ENVIRONMENT);

        return empty($environment)
            ? Environment::TEST
            : $environment;
    }

    /**
     * @return string
     */
    private function getCustomConnectionUrl()
    {
        $customEnvironment = $this->getCurrentValueOf(self::CUSTOM_CONNECTION_URL);

        return empty($customEnvironment)
            ? null
            : $customEnvironment;
    }

    /**
     * @return bool
     */
    private function isProduction()
    {
        return $this->getEnvironment() === Environment::PRODUCTION;
    }

    /**
     * @return bool
     */
    private function isCustomEnvironment()
    {
        return $this->getEnvironment() === Environment::CUSTOM;
    }

    /**
     * @return string
     */
    private function getLogin()
    {
        return $this->getCurrentValueOf(self::LOGIN);
    }

    /**
     * @return string
     */
    private function getTranKey()
    {
        return $this->getCurrentValueOf(self::TRAN_KEY);
    }

    /**
     * @return string
     */
    private function getCompanyDocument()
    {
        return $this->getCurrentValueOf(self::COMPANY_DOCUMENT);
    }

    /**
     * @return string
     */
    private function getCompanyName()
    {
        return $this->getCurrentValueOf(self::COMPANY_NAME);
    }

    /**
     * @return string
     */
    private function getDescription()
    {
        return $this->getCurrentValueOf(self::DESCRIPTION);
    }

    /**
     * @return string
     */
    private function getEmailContact()
    {
        $emailContact = $this->getCurrentValueOf(self::EMAIL_CONTACT);

        return empty($emailContact)
            ? Configuration::get('PS_SHOP_EMAIL')
            : $emailContact;
    }

    /**
     * @return string
     */
    private function getTelephoneContact()
    {
        $telephoneContact = $this->getCurrentValueOf(self::TELEPHONE_CONTACT);

        return empty($telephoneContact)
            ? Configuration::get('PS_SHOP_PHONE')
            : $telephoneContact;
    }

    /**
     * @return bool
     */
    private function getTransUnionMessage()
    {
        return $this->getCurrentValueOf(self::CIFIN_MESSAGE);
    }

    /**
     * @return bool
     */
    private function getAllowBuyWithPendingPayments()
    {
        return (int)$this->getCurrentValueOf(self::ALLOW_BUY_WITH_PENDING_PAYMENTS);
    }

    /**
     * @return string
     */
    private function getShowOnReturn()
    {
        return $this->getCurrentValueOf(self::SHOW_ON_RETURN);
    }

    /**
     * @return bool
     */
    private function isShowOnReturnDetails()
    {
        return in_array($this->getShowOnReturn(), [self::SHOW_ON_RETURN_DEFAULT, self::SHOW_ON_RETURN_DETAILS]);
    }

    /**
     * @return int
     */
    private function getExpirationTimeMinutes()
    {
        $minutes = $this->getCurrentValueOf(self::EXPIRATION_TIME_MINUTES);

        return !is_numeric($minutes) || $minutes < self::EXPIRATION_TIME_MINUTES_MIN
            ? self::EXPIRATION_TIME_MINUTES_DEFAULT
            : $minutes;
    }

    /**
     * @return bool
     */
    private function getFillTaxInformation()
    {
        return $this->getCurrentValueOf(self::FILL_TAX_INFORMATION);
    }

    /**
     * @return bool
     */
    private function getStockReInject()
    {
        return $this->getCurrentValueOf(self::STOCK_REINJECT);
    }

    /**
     * @return string
     */
    private function getConnectionType()
    {
        $connectionType = $this->getCurrentValueOf(self::CONNECTION_TYPE);

        return empty($connectionType) || !in_array($connectionType, [
            self::CONNECTION_TYPE_SOAP,
            self::CONNECTION_TYPE_REST
        ])
            ? self::CONNECTION_TYPE_REST
            : $connectionType;
    }

    /**
     * @return bool
     */
    private function getFillBuyerInformation()
    {
        return $this->getCurrentValueOf(self::FILL_BUYER_INFORMATION);
    }

    /**
     * @return bool
     */
    private function getSkipResult()
    {
        return $this->getCurrentValueOf(self::SKIP_RESULT);
    }

    /**
     * @return string
     */
    private function getPaymentMethodsEnabled()
    {
        $paymentMethods = $this->getCurrentValueOf(self::PAYMENT_METHODS_ENABLED);

        if (is_array($paymentMethods)) {
            $paymentMethods = PaymentMethod::toString($paymentMethods);
        }

        return empty($paymentMethods)
            ? self::PAYMENT_METHODS_ENABLED_DEFAULT
            : $paymentMethods;
    }

    /**
     * @return mixed
     */
    private function getOrderState()
    {
        return Configuration::get(self::ORDER_STATE);
    }

    /**
     * @param string $page process.php
     * @param string $params Query string to add in URL, please include symbol (?), eg: ?var=foo
     * @return string
     */
    private function getUrl($page, $params = '')
    {
        $baseUrl = Context::getContext()->shop->getBaseURL(true);
        $url = $baseUrl . 'modules/' . getModuleName() . '/' . $page . $params;

        return $url;
    }

    /**
     * @return string
     */
    private function getPathScheduleTask()
    {
        return fixPath($this->getPathThisModule() . '/sonda.php');
    }

    /**
     * @return string
     */
    private function getPathThisModule()
    {
        return _PS_MODULE_DIR_ . getModuleName();
    }

    /**
     * @param null $cartId
     * @return Order
     * @throws PaymentException
     */
    private function getOrderByCartId($cartId = null)
    {
        if (versionComparePlaceToPay('1.7.1.0', '>=')) {
            $orderId = Order::getIdByCartId($cartId);
        } else {
            $orderId = Order::getOrderByCartId($cartId);
        }

        if (!$orderId) {
            throw new PaymentException(Tools::displayError(), 201);
        }

        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            throw new PaymentException(Tools::displayError(), 202);
        }

        return $order;
    }

    /**
     * Redirect to PlacetoPay Platform
     *
     * @param Cart $cart
     * @throws PaymentException
     */
    public function redirect(Cart $cart)
    {
        $lastPendingTransaction = $this->getLastPendingTransaction($cart->id_customer);

        if (!empty($lastPendingTransaction) && $this->getAllowBuyWithPendingPayments() == self::OPTION_DISABLED) {
            // @codingStandardsIgnoreLine
            $message = 'Payment not allowed, customer has payment pending and not allowed but with payment pending is disable';
            PaymentLogger::log($message, PaymentLogger::ERROR, 7, __FILE__, __LINE__);
            Tools::redirect('authentication.php?back=order.php');
        }

        $language = Language::getIsoById((int)($cart->id_lang));
        $customer = new Customer((int)($cart->id_customer));
        $currency = new Currency((int)($cart->id_currency));
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $deliveryAddress = new Address((int)($cart->id_address_delivery));
        $totalAmount = floatval($cart->getOrderTotal(true));
        $taxAmount = floatval($totalAmount - floatval($cart->getOrderTotal(false)));

        if (!Validate::isLoadedObject($customer)) {
            throw new PaymentException('Invalid customer', 301);
        }

        if (!Validate::isLoadedObject($invoiceAddress)
            || !Validate::isLoadedObject($deliveryAddress)) {
            throw new PaymentException('Invalid address', 302);
        }

        if (!Validate::isLoadedObject($currency)) {
            throw new PaymentException('Invalid currency', 303);
        }

        if (!CurrencyValidator::isValidCurrency($currency->iso_code)) {
            $message = sprintf('Currency ISO Code %s is not supported by PlacetoPay', $currency->iso_code);
            throw new PaymentException($message, 304);
        }

        $deliveryCountry = new Country((int)($deliveryAddress->id_country));
        $deliveryState = null;
        if ($deliveryAddress->id_state) {
            $deliveryState = new State((int)($deliveryAddress->id_state));
        }

        $urlOrderStatus = __PS_BASE_URI__
            . $this->getRedirectPageFromStatus(PaymentStatus::PENDING)
            . '?id_cart=' . $cart->id
            . '&id_module=' . $this->id
            . '&id_order=' . $this->currentOrder;

        try {
            $orderMessage = 'Success';
            $orderStatus = $this->getOrderState();
            $requestId = 0;
            $expiration = date('c', strtotime($this->getExpirationTimeMinutes() . ' minutes'));
            $ipAddress = (new RemoteAddress())->getIpAddress();

            // Create order in prestashop
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

            // After order create in validateOrder
            $reference = $this->currentOrderReference;
            $returnUrl = $this->getUrl('process.php', '?_=' . $this->reference($reference));

            // Request payment
            $request = [
                'locale' => ($language == 'en') ? 'en_US' : 'es_CO',
                'returnUrl' => $returnUrl,
                'noBuyerFill' => !(bool)$this->getFillBuyerInformation(),
                'skipResult' => (bool)$this->getSkipResult(),
                'ipAddress' => $ipAddress,
                'expiration' => $expiration,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'buyer' => [
                    'name' => $deliveryAddress->firstname,
                    'surname' => $deliveryAddress->lastname,
                    'email' => $customer->email,
                    'mobile' => (!empty($deliveryAddress->phone_mobile)
                        ? $deliveryAddress->phone_mobile
                        : $deliveryAddress->phone),
                    'address' => [
                        'country' => $deliveryCountry->iso_code,
                        'state' => (empty($deliveryState) ? null : $deliveryState->name),
                        'city' => $deliveryAddress->city,
                        'street' => $deliveryAddress->address1 . " " . $deliveryAddress->address2,
                    ]
                ],
                'payment' => [
                    'reference' => $reference,
                    'description' => sprintf($this->getDescription(), $reference),
                    'amount' => [
                        'currency' => $currency->iso_code,
                        'total' => $totalAmount,
                    ]
                ]
            ];

            if ($this->getFillTaxInformation() == self::OPTION_ENABLED && $taxAmount > 0) {
                // Add taxes
                $request['payment']['amount']['taxes'] = [
                    [
                        'kind' => 'valueAddedTax',
                        'amount' => $taxAmount,
                        'base' => $totalAmount - $taxAmount,
                    ]
                ];
            }

            $paymentMethods = $this->getPaymentMethodsEnabled();

            if (!empty($paymentMethods) && $paymentMethods != self::PAYMENT_METHODS_ENABLED_DEFAULT) {
                // Disable payment method except
                $request['paymentMethod'] = $paymentMethods;
            }

            if (isDebugEnable()) {
                PaymentLogger::log(print_r($request, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            $paymentRedirection = $this->instanceRedirection()->request($request);

            if (isDebugEnable()) {
                PaymentLogger::log(print_r($paymentRedirection, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            $orderMessage = $paymentRedirection->status()->message();

            if ($paymentRedirection->isSuccessful()) {
                $requestId = $paymentRedirection->requestId();
                $status = PaymentStatus::PENDING;
                // Redirect to payment:
                $redirectTo = $paymentRedirection->processUrl();
            } else {
                $totalAmount = 0;
                $status = PaymentStatus::FAILED;
                // Redirect to error:
                $redirectTo = $urlOrderStatus;

                $this->updateCurrentOrderWithError();

                PaymentLogger::log($orderMessage, PaymentLogger::WARNING, 0, __FILE__, __LINE__);
            }


            // Register payment request
            $this->insertPaymentPlaceToPay(
                $requestId,
                $cart->id,
                $cart->id_currency,
                $totalAmount,
                $status,
                $orderMessage,
                $ipAddress,
                $reference
            );

            if (isDebugEnable()) {
                $message = sprintf('[%d => %s] Redirecting flow to: %s', $status, $orderMessage, $redirectTo);
                PaymentLogger::log($message, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            // Redirect flow
            Tools::redirectLink($redirectTo);
        } catch (Exception $e) {
            $message = $e->getMessage();
            PaymentLogger::log($message, PaymentLogger::WARNING, 8, $e->getFile(), $e->getLine());

            Tools::redirect($urlOrderStatus);
        }
    }

    /**
     * Register payment
     *
     * @param $requestId
     * @param $orderId
     * @param $currencyId
     * @param $amount
     * @param $status
     * @param $message
     * @param $ipAddress
     * @param $reference
     * @return bool
     * @throws PaymentException
     */
    private function insertPaymentPlaceToPay(
        $requestId,
        $orderId,
        $currencyId,
        $amount,
        $status,
        $message,
        $ipAddress,
        $reference
    ) {
        // Default values
        $reason = '';
        $date = date('Y-m-d H:i:s');
        $reasonDescription = pSQL($message);
        $conversion = 1;
        $authCode = '000000';

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
                authcode,
                reference
            ) VALUES (
                '$orderId',
                '$currencyId',
                '$date',
                '$amount',
                '$status',
                '$reason',
                '$reasonDescription',
                '$conversion',
                '$ipAddress',
                '$requestId',
                '$authCode',
                '$reference'
            )
        ";

        try {
            Db::getInstance()->Execute($sql);
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 401);
        }

        return true;
    }

    /**
     * Process response from PlacetoPay Platform
     *
     * @param null $_reference
     * @throws PaymentException
     * @throws \Dnetix\Redirection\Exceptions\PlacetoPayException
     */
    public function process($_reference = null)
    {
        $paymentPlaceToPay = [];

        if (!is_null($_reference)) {
            // On returnUrl from redirection process
            $reference = $this->reference($_reference, true);
            $paymentPlaceToPay = $this->getPaymentPlaceToPayBy('reference', $reference);
        } elseif (!empty(file_get_contents("php://input"))) {
            // On resolve function called process
            $input = json_decode(file_get_contents("php://input"), 1);

            $notification = new Notification((array)$input, $this->getTranKey());

            if (!$notification->isValidNotification()) {
                if (isDebugEnable()) {
                    die('Change signature value in your request to: ' . $notification->makeSignature());
                }

                $message = 'Notification is not valid, process canceled. Input request:' . PHP_EOL . print_r($input, 1);

                throw new PaymentException($message, 501);
            }

            $requestId = (int)$input['requestId'];
            $paymentPlaceToPay = $this->getPaymentPlaceToPayBy('id_request', $requestId);
        }

        if (empty($paymentPlaceToPay)) {
            $error = 9;
            $message = sprintf('Payment _reference: [%s] not found', $_reference);

            if (isset($reference)) {
                $error = 10;
                $message = sprintf('Payment with reference: [%s] not found', $reference);
            } elseif (isset($requestId)) {
                $error = 11;
                $message = sprintf('Payment with id_request: [%s] not found', $requestId);
            }

            PaymentLogger::log($message, PaymentLogger::WARNING, $error, __FILE__, __LINE__);

            if (!empty($input)) {
                // Show status to reference in console
                die($message . PHP_EOL);
            }

            Tools::redirect('authentication.php?back=order.php');
        }

        $paymentId = $paymentPlaceToPay['id_payment'];
        $cartId = $paymentPlaceToPay['id_order'];
        $requestId = $paymentPlaceToPay['id_request'];
        $oldStatus = $paymentPlaceToPay['status'];

        $order = $this->getOrderByCartId($cartId);

        if (isDebugEnable()) {
            PaymentLogger::log(print_r($paymentPlaceToPay, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        if (!isDebugEnable() && $oldStatus != PaymentStatus::PENDING) {
            $message = sprintf(
                'Payment with reference: [%s] not is pending, current status is [%d=%s]',
                $order->reference,
                $oldStatus,
                implode('->', $this->getStatusDescription($oldStatus))
            );

            PaymentLogger::log($message, PaymentLogger::WARNING, 12, __FILE__, __LINE__);

            if (!empty($input)) {
                // Show status to reference in console
                die($message . PHP_EOL);
            }

            Tools::redirect('authentication.php?back=order.php');
        }

        $paymentRedirection = $this->instanceRedirection()->query($requestId);

        if (isDebugEnable()) {
            PaymentLogger::log(print_r($paymentRedirection, true), PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        if ($paymentRedirection->isSuccessful() && $order) {
            $newStatus = $this->getStatusPayment($paymentRedirection);

            if (isDebugEnable()) {
                $message = sprintf(
                    'Updating status to payment with reference: [%s] from [%d=%s] to [%d=%s]',
                    $order->reference,
                    $oldStatus,
                    implode('->', $this->getStatusDescription($oldStatus)),
                    $newStatus,
                    implode('->', $this->getStatusDescription($newStatus))
                );

                PaymentLogger::log($message, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            // Set status order in CMS
            $this->settleTransaction($paymentId, $newStatus, $order, $paymentRedirection);

            if (!empty($input)) {
                // Show status to reference in console
                die(sprintf(
                    'Payment with reference: [%s] status change from [%d=%s] to [%d=%s]',
                    $order->reference,
                    $oldStatus,
                    implode('->', $this->getStatusDescription($oldStatus)),
                    $newStatus,
                    implode('->', $this->getStatusDescription($newStatus))
                ));
            }

            $redirectTo = __PS_BASE_URI__
                . $this->getRedirectPageFromStatus($newStatus)
                . '?id_cart=' . $cartId
                . '&id_module=' . $this->id
                . '&id_order=' . $order->id
                . '&key=' . $order->secure_key;

            if (isDebugEnable()) {
                $message = sprintf('Redirecting flow to: [%s]', $redirectTo);
                PaymentLogger::log($message, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
            }

            // Redirect to confirmation page
            Tools::redirectLink($redirectTo);
        } elseif (!$paymentRedirection->isSuccessful()) {
            throw new PaymentException($paymentRedirection->status()->message(), 13);
        } elseif (!$order) {
            throw new PaymentException('Order not found: ' . $cartId, 14);
        } else {
            throw new PaymentException('Un-know error in process payment', 99);
        }
    }

    /**
     * @param $status
     * @return string
     */
    private function getRedirectPageFromStatus($status)
    {
        if ($status == PaymentStatus::APPROVED) {
            $redirectTo = self::PAGE_ORDER_CONFIRMATION;
        } else {
            $redirectTo = $this->isShowOnReturnDetails()
                ? self::PAGE_ORDER_DETAILS
                : self::PAGE_ORDER_HISTORY;
        }

        return $redirectTo;
    }

    /**
     * @param string $column You can any column from $this->tablePayment table
     * @param int $value
     * @return array|bool
     */
    private function getPaymentPlaceToPayBy($column, $value = null)
    {
        try {
            if (!empty($column) && !empty($value)) {
                $rows = Db::getInstance()->ExecuteS("
                SELECT *
                FROM  `{$this->tablePayment}`
                WHERE {$column} = '{$value}'
            ");
            }
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::WARNING, 15, $e->getFile(), $e->getLine());
        }

        return !empty($rows[0]) ? $rows[0] : false;
    }

    /**
     * @param RedirectInformation $response
     * @return int
     */
    private function getStatusPayment(RedirectInformation $response)
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
                // This is why it has been reject
                $status = PaymentStatus::REJECTED;
            } elseif ($lastPayment->status()->isFailed()) {
                // This is why it has been fail
                $status = PaymentStatus::FAILED;
            }
        } elseif ($response->status()->isRejected()) {
            // Canceled by user
            $status = PaymentStatus::REJECTED;
        }

        return $status;
    }

    /**
     * @param $paymentId
     * @param $status
     * @param Order $order
     * @param RedirectInformation $response
     * @throws PaymentException
     */
    private function settleTransaction($paymentId, $status, Order $order, RedirectInformation $response)
    {
        // Order not has been processed
        if ($order->getCurrentState() != (int)Configuration::get('PS_OS_PAYMENT')) {
            switch ($status) {
                case PaymentStatus::FAILED:
                case PaymentStatus::REJECTED:
                    if (in_array($order->getCurrentState(), [
                        Configuration::get('PS_OS_ERROR'),
                        Configuration::get('PS_OS_CANCELED')
                    ])) {
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

                    if (versionComparePlaceToPay('1.7.0.0', '<') && $this->getStockReinject() == self::OPTION_ENABLED) {
                        $products = $order->getProducts();
                        foreach ($products as $product) {
                            $order_detail = new \OrderDetail((int)($product['id_order_detail']));
                            \Product::reinjectQuantities($order_detail, $product['product_quantity']);
                        }
                    }
                    break;
                case PaymentStatus::DUPLICATE:
                case PaymentStatus::APPROVED:
                    // Order approved, change state
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
        $this->updateTransaction($paymentId, $status, $response);
    }

    /**
     * @param $paymentId
     * @param $status
     * @param $response
     * @return bool
     * @throws PaymentException
     */
    private function updateTransaction($paymentId, $status, RedirectInformation $response)
    {
        $date = pSQL($response->status()->date());
        $reason = pSQL($response->status()->reason());
        $reasonDescription = pSQL($response->status()->message());

        $bank = '';
        $franchise = '';
        $franchiseName = '';
        $authCode = '';
        $receipt = '';
        $conversion = '';
        $payerEmail = '';

        if (!empty($payment = $response->lastTransaction())
            && !empty($paymentStatus = $payment->status())
            && ($paymentStatus->isApproved() || $paymentStatus->isRejected() || $paymentStatus->isFailed())
        ) {
            $date = pSQL($paymentStatus->date());
            $reason = pSQL($paymentStatus->reason());
            $reasonDescription = pSQL($paymentStatus->message());

            if (!$paymentStatus->isFailed()) {
                $bank = pSQL($payment->issuerName());
                $franchise = pSQL($payment->paymentMethod());
                $franchiseName = pSQL($payment->paymentMethodName());
                $authCode = pSQL($payment->authorization());
                $receipt = pSQL($payment->receipt());
                $conversion = pSQL($payment->amount()->factor());
            }
        }

        if (!empty($request = $response->request())
            && !empty($payer = $request->payer())
            && !empty($email = $payer->email())) {
            $payerEmail = pSQL($email);
        }

        $sql = "
            UPDATE `{$this->tablePayment}` SET
                `status` = {$status},
                `date` = '{$date}',
                `reason` = '{$reason}',
                `reason_description` = '{$reasonDescription}',
                `franchise` = '{$franchise}',
                `franchise_name` = '{$franchiseName}',
                `bank` = '{$bank}',
                `authcode` = '{$authCode}',
                `receipt` = '{$receipt}',
                `conversion` = '{$conversion}',
                `payer_email` = '{$payerEmail}'
            WHERE `id_payment` = {$paymentId}
        ";

        try {
            Db::getInstance()->Execute($sql);
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 601);
        }

        return true;
    }

    /**
     * Show information response payment to customer
     *
     * @param $params
     * @return bool|mixed
     * @throws PaymentException
     */
    public function hookPaymentReturn($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(
                'Trigger ' . __METHOD__ . ' en PS vr. ' . _PS_VERSION_,
                PaymentLogger::DEBUG,
                0,
                __FILE__,
                __LINE__
            );
        }

        if (!$this->active) {
            return null;
        }

        $order = isset($params['objOrder'])
            ? $params['objOrder']
            : $params['order'];

        if ($order->module != getModuleName()) {
            return null;
        }

        switch ($this->getShowOnReturn()) {
            case self::SHOW_ON_RETURN_PSE_LIST:
                return $this->getPaymentPSEList($order->id_customer);
                break;
            case self::SHOW_ON_RETURN_DETAILS:
                return $this->getPaymentDetails($order);
                break;
            case self::SHOW_ON_RETURN_DEFAULT:
            default:
                return $this->getPaymentDetails($order);
                break;
        }
    }

    /**
     * @param Order $order
     * @return mixed
     * @throws PaymentException
     */
    private function getPaymentDetails(Order $order)
    {
        // Get information
        $transaction = $this->getTransactionInformation($order->id_cart);
        $cart = new Cart((int)$order->id_cart);
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $totalAmount = (float)($cart->getOrderTotal(true, Cart::BOTH));
        $taxAmount = $totalAmount - (float)($cart->getOrderTotal(false, Cart::BOTH));
        $payerName = '';
        $payerEmail = !empty($transaction['payer_email']) ? $transaction['payer_email'] : null;
        $transaction['tax'] = $taxAmount;

        // Customer data
        $customer = new Customer((int)($order->id_customer));

        if (Validate::isLoadedObject($customer)) {
            $payerName = empty($invoiceAddress)
                ? $customer->firstname . ' ' . $customer->lastname
                : $invoiceAddress->firstname . ' ' . $invoiceAddress->lastname;
            $payerEmail = isset($payerEmail) ? $payerEmail : $customer->email;
        }

        $attributes = [
                'company_document' => $this->getCompanyDocument(),
                'company_name' => $this->getCompanyName(),
                'payment_description' => sprintf($this->getDescription(), $transaction['reference']),
                'store_email' => $this->getEmailContact(),
                'store_phone' => $this->getTelephoneContact(),
                'transaction' => $transaction,
                'payer_name' => $payerName,
                'payer_email' => $payerEmail,
                'customer_id' => $cart->id_customer,
                'orderId' => $order->id,
                'logged' => (Context::getContext()->customer->isLogged() ? true : false),
            ] + $this->getStatusDescription($transaction['status']);

        $this->context->smarty->assign($attributes);

        return $this->display($this->getPathThisModule(), fixPath('/views/templates/front/response.tpl'));
    }

    /**
     * @param $status
     * @return array
     */
    private function getStatusDescription($status)
    {
        $description = [
            'status' => 'pending',
            'status_description' => $this->ll('Pending payment')
        ];

        switch ($status) {
            case PaymentStatus::APPROVED:
            case PaymentStatus::DUPLICATE:
                $description = [
                    'status' => 'ok',
                    'status_description' => $this->ll('Completed payment')
                ];
                break;
            case PaymentStatus::FAILED:
                $description = [
                    'status' => 'fail',
                    'status_description' => $this->ll('Failed payment')
                ];
                break;
            case PaymentStatus::REJECTED:
                $description = [
                    'status' => 'rejected',
                    'status_description' => $this->ll('Rejected payment')
                ];
                break;
        }

        return $description;
    }

    /**
     * @param $customerId
     * @return string
     */
    private function getPaymentPSEList($customerId)
    {
        $orders = self::getCustomerOrders($customerId);

        if ($orders) {
            foreach ($orders as &$order) {
                $myOrder = new Order((int)$order['id_order']);
                if (Validate::isLoadedObject($myOrder)) {
                    $order['virtual'] = $myOrder->isVirtual(false);
                }
            }
        }

        $this->context->smarty->assign([
            'orders' => $orders,
            'invoiceAllowed' => (int)Configuration::get('PS_INVOICE'),
            'reorderingAllowed' => !(bool)Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
            'slowValidation' => Tools::isSubmit('slowvalidation')
        ]);

        return $this->display($this->getPathThisModule(), fixPath('/views/templates/front/history.tpl'));
    }

    /**
     * Get customer orders
     *
     * @param $id_customer Customer id
     * @param bool $show_hidden_status Display or not hidden order statuses
     * @param Context|null $context
     * @return array
     */
    private function getCustomerOrders($id_customer, $show_hidden_status = false, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $sql = 'SELECT o.`id_order`, o.`id_currency`, o.`payment`, o.`invoice_number`, pp.`date` date_add, 
                      pp.`reference`, pp.`amount` total_paid, pp.`authcode` cus, 
                      (SELECT SUM(od.`product_quantity`) 
                      FROM `' . _DB_PREFIX_ . 'order_detail` od 
                      WHERE od.`id_order` = o.`id_order`) nb_products
        FROM `' . $this->tableOrder . '` o
            JOIN `' . $this->tablePayment . '` pp ON pp.id_order = o.id_cart
        WHERE o.`id_customer` = ' . (int)$id_customer .
            Shop::addSqlRestriction(Shop::SHARE_ORDER) . '
        GROUP BY o.`id_order`
        ORDER BY o.`date_add` DESC';

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!$res) {
            return [];
        }

        foreach ($res as $key => $val) {
            $res2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT os.`id_order_state`, osl.`name` AS order_state, os.`invoice`, os.`color` AS order_state_color
                FROM `' . _DB_PREFIX_ . 'order_history` oh
                LEFT JOIN `' . _DB_PREFIX_ . 'order_state` os ON (os.`id_order_state` = oh.`id_order_state`)
                INNER JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (
                    os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = ' . (int)$context->language->id . '
                )
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
     * @param $cartId
     * @param null $orderId
     * @return mixed
     * @throws PaymentException
     */
    private function getTransactionInformation($cartId, $orderId = null)
    {
        $id_order = (empty($cartId)
            ? "(SELECT `id_cart` FROM `{$this->tableOrder}` WHERE `id_order` = {$orderId})"
            : $cartId);

        try {
            $result = Db::getInstance()->ExecuteS(
                "SELECT * FROM `{$this->tablePayment}` WHERE `id_order` = {$id_order}"
            );
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 801);
        }

        if (!empty($result)) {
            $result = $result[0];

            if (empty($result['reason_description'])) {
                $result['reason_description'] = ($result['reason'] == '?-')
                    ? $this->ll('Processing transaction')
                    : $this->ll('No information');
            }

            if (empty($result['status'])) {
                $result['status_description'] = ($result['status'] == '')
                    ? $this->ll('Processing transaction')
                    : $this->ll('No information');
            }
        }

        return $result;
    }

    /**
     * Update status order in background
     * @param int $minutes
     */
    public function resolvePendingPayments($minutes = 12)
    {
        if ($this->isEnableShowSetup()) {
            echo $this->getSetup();
            $minutes = 0;
        }

        if (!isConsole() && !isDebugEnable()) {
            $message = sprintf(
                'Only from CLI (used SAPI: %s) is available execute this command: %s, aborted',
                php_sapi_name(),
                __FUNCTION__
            );

            PaymentLogger::log($message, PaymentLogger::WARNING, 16, __FILE__, __LINE__);

            Tools::redirect('authentication.php?back=order.php');
        }

        echo 'Begins ' . date('Ymd H:i:s') . '.' . breakLine();

        $date = date('Y-m-d H:i:s', time() - ($minutes * 60));
        $sql = "SELECT * 
            FROM `{$this->tablePayment}`
            WHERE `date` < '{$date}' 
              AND `status` = " . PaymentStatus::PENDING;

        if (isDebugEnable()) {
            PaymentLogger::log($sql, PaymentLogger::DEBUG, 0, __FILE__, __LINE__);
        }

        try {
            if ($result = Db::getInstance()->ExecuteS($sql)) {
                echo "Found (" . count($result) . ") payments pending." . breakLine(2);

                $paymentRedirection = $this->instanceRedirection();

                foreach ($result as $row) {
                    $reference = $row['reference'];
                    $requestId = (int)$row['id_request'];
                    $paymentId = (int)$row['id_payment'];
                    $cartId = (int)$row['id_order'];

                    echo "Processing reference: [{$reference}] (Request ID: {$requestId})." . breakLine();

                    $response = $paymentRedirection->query($requestId);
                    $status = $this->getStatusPayment($response);
                    $order = $this->getOrderByCartId($cartId);

                    if ($order) {
                        $this->settleTransaction($paymentId, $status, $order, $response);
                    }

                    echo sprintf(
                        'Payment with reference: [%s] is [%d=%s]' . breakLine(2),
                        $order->reference,
                        $status,
                        implode('->', $this->getStatusDescription($status))
                    );
                }
            } else {
                echo 'Not exists payments pending.' . breakLine();
            }
        } catch (PrestaShopDatabaseException $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 99, $e->getFile(), $e->getLine());
            echo 'Error: Module not installed' . breakLine();
        } catch (Exception $e) {
            PaymentLogger::log($e->getMessage(), PaymentLogger::ERROR, 99, $e->getFile(), $e->getLine());
            echo 'Error: ' . $e->getMessage() . breakLine();
        }

        echo 'Finished ' . date('Ymd H:i:s') . '.' . breakLine();
    }

    /**
     * @return bool
     */
    private function isEnableShowSetup()
    {
        $force = Tools::getValue('f', null);

        return !$this->isProduction()
        && !empty($force)
        && strlen($force) === 5
        && substr($this->getLogin(), -5) === $force
            ? true
            : false;
    }

    /**
     * @return string
     */
    private function getSetup()
    {
        $setup = $this->ll('Configuration') . breakLine();
        $setup .= sprintf('PHP [%s]', PHP_VERSION) . breakLine();
        $setup .= sprintf(
            'PrestaShop [%s] in %s mode' . breakLine(),
            _PS_VERSION_,
            isDebugEnable() ? 'DEBUG' : 'PRODUCTION'
        );
        $setup .= sprintf('Plugin [%s]', $this->getPluginVersion()) . breakLine();
        $setup .= sprintf('URL Base [%s]', $this->getUrl('')) . breakLine();
        $setup .= sprintf('%s [%s]', $this->ll('Country'), $this->getCountry()) . breakLine();
        $setup .= sprintf('%s [%s]', $this->ll('Environment'), $this->getEnvironment()) . breakLine();

        if ($this->isCustomEnvironment()) {
            $setup .= sprintf(
                '%s [%s]' . breakLine(),
                $this->ll('Custom connection URL'),
                $this->getCustomConnectionUrl()
            );
        }

        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Connection type'),
            $this->getConnectionType()
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Expiration time to pay'),
            $this->getExpirationTimeMinutes()
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Allow buy with pending payments?'),
            $this->getAllowBuyWithPendingPayments() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Fill TAX information?'),
            $this->getFillTaxInformation() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Fill buyer information?'),
            $this->getFillTaxInformation() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Skip result?'),
            $this->getSkipResult() ? $this->ll('Yes') : $this->ll('No')
        );
        $setup .= sprintf(
            '%s [%s]' . breakLine(),
            $this->ll('Payment methods enabled'),
            $this->getPaymentMethodsEnabled()
        );

        $setup .= breakLine();

        return $setup;
    }

    /**
     * Options to show when user return from payment
     */
    private function getListOptionShowOnReturn()
    {
        $options = [
            [
                'value' => self::SHOW_ON_RETURN_DEFAULT,
                'label' => $this->ll('PrestaShop View'),
            ],
            [
                'value' => self::SHOW_ON_RETURN_DETAILS,
                'label' => $this->ll('Payment Details'),
            ],
            [
                'value' => self::SHOW_ON_RETURN_PSE_LIST,
                'label' => $this->ll('PSE List'),
            ],
        ];

        return $options;
    }

    /**
     * @return array
     */
    private function getListOptionSwitch()
    {
        return [
            [
                'id' => 'active_on',
                'value' => self::OPTION_ENABLED,
                'label' => $this->ll('Yes'),
            ],
            [
                'id' => 'active_off',
                'value' => self::OPTION_DISABLED,
                'label' => $this->ll('No'),
            ]
        ];
    }

    /**
     * Options payment methods
     * @return array
     */
    private function getListOptionPaymentMethods()
    {
        $options = [];

        $options[] = [
            'value' => self::PAYMENT_METHODS_ENABLED_DEFAULT,
            'label' => $this->ll('All'),
        ];

        foreach (PaymentMethod::getPaymentMethodsAvailable($this->getCountry()) as $code => $name) {
            $options[] = [
                'value' => $code,
                'label' => $name,
            ];
        }

        return $options;
    }

    /**
     * @param string $name
     * @return mixed|string
     */
    private function getCurrentValueOf($name)
    {
        return Tools::getValue($name)
            ? Tools::getValue($name)
            : Configuration::get($name);
    }

    /**
     * @param $cart
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $string
     * @param bool $rollBack
     * @return string
     */
    private function reference($string, $rollBack = false)
    {
        return !$rollBack
            ? base64_encode($string)
            : base64_decode($string);
    }

    /**
     * @return bool
     */
    private function isSetCredentials()
    {
        return !empty($this->getLogin()) && !empty($this->getTranKey());
    }

    /**
     * Manage translations
     * @param $string
     * @return string
     */
    private function ll($string)
    {
        return $this->l($string, getModuleName());
    }

    /**
     * @param array $errors
     * @return mixed
     */
    private function showError(array $errors)
    {
        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            $errors = implode('<br>', $errors);
        }

        return $this->displayError($errors);
    }

    private function updateCurrentOrderWithError()
    {
        $history = new OrderHistory();
        $history->id_order = $this->currentOrder;
        $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
        $history->save();
    }

    /**
     * @return PaymentRedirection
     * @throws \Dnetix\Redirection\Exceptions\PlacetoPayException
     */
    private function instanceRedirection()
    {
        return new PaymentRedirection(
            $this->getLogin(),
            $this->getTranKey(),
            $this->getUri(),
            $this->getConnectionType()
        );
    }

    /**
     * @param $name
     * @return string
     */
    private function getNameInMultipleFormat($name)
    {
        return sprintf('%s[]', $name);
    }
}
