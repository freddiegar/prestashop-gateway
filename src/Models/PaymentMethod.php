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
use Dnetix\Redirection\Message\RedirectInformation;
use Exception;
use HelperForm;
use Language;
use Order;
use OrderHistory;
use OrderState;
use PaymentModule;
use PlacetoPay\Constants\CountryCode;
use PlacetoPay\Constants\Environment;
use PlacetoPay\Constants\PaymentStatus;
use PlacetoPay\Constants\PaymentUrl;
use PlacetoPay\Exceptions\PaymentException;
use PlacetoPay\Loggers\PaymentLogger;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Shop;
use State;
use Tools;
use Validate;

/**
 * Class PlaceToPayPaymentMethod
 * @property string currentOrderReference
 */
class PaymentMethod extends PaymentModule
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
    const STOCK_REINJECT = 'PLACETOPAY_STOCKREINJECT';

    const COUNTRY = 'PLACETOPAY_COUNTRY';
    const ENVIRONMENT = 'PLACETOPAY_ENVIRONMENT';
    const LOGIN = 'PLACETOPAY_LOGIN';
    const TRAN_KEY = 'PLACETOPAY_TRANKEY';
    const CONNECTION_TYPE = 'PLACETOPAY_CONNECTION_TYPE';

    const EXPIRATION_TIME_MINUTES_DEFAULT = 120; // 2 Hours
    const EXPIRATION_TIME_MINUTES_LIMIT = 241920; // 6 Months of 4 weeks

    const SHOW_ON_RETURN_DEFAULT = 'default';
    const SHOW_ON_RETURN_PSE_LIST = 'pse_list';
    const SHOW_ON_RETURN_DETAILS = 'details';

    const CONNECTION_TYPE_SOAP = 'soap';
    const CONNECTION_TYPE_REST = 'rest';

    const OPTION_ENABLED = '1';
    const OPTION_DISABLED = '0';

    const ORDER_STATE = 'PS_OS_PLACETOPAY';

    private $_html = '';
    private $_postErrors = array();

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
        $this->version = '3.0.0';
        $this->author = 'EGM Ingeniería sin Fronteras S.A.S';
        $this->tab = 'payments_gateways';
        $this->limited_countries = array('us', CountryCode::COLOMBIA, CountryCode::ECUADOR);
        $this->ps_versions_compliancy = array('min' => '1.6.0.0', 'max' => _PS_VERSION_);

        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->ll('Place to Pay');
        $this->description = $this->ll('Accept payments by credit cards and debits account');

        $this->confirmUninstall = $this->ll('Are you sure you want to uninstall?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->ll('No currency has been set for this module.');
        }

        if (!$this->isSetCredentials()) {
            $this->warning = $this->ll('You need to configure your Place to Pay account before using this module');
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
            case !$this->registerHook('paymentReturn');
                throw new PaymentException('error on install', 108);
                break;
        }

        $hookPaymentName = 'payment';
        if (versionComparePlaceToPay('1.7.0.0', '>=')) {
            $hookPaymentName = 'paymentOptions';
        }

        if (!$this->registerHook($hookPaymentName)) {
            throw new PaymentException('error on install', 109);
        }

        if (isDebugEnable()) {
            $message = sprintf('Hook %s was register on PS vr %s', $hookPaymentName, _PS_VERSION_);
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, $message));
        }

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

        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            Configuration::updateValue(self::STOCK_REINJECT, self::OPTION_ENABLED);
        }

        Configuration::updateValue(self::COUNTRY, CountryCode::COLOMBIA);
        Configuration::updateValue(self::ENVIRONMENT, Environment::TEST);
        Configuration::updateValue(self::LOGIN, '');
        Configuration::updateValue(self::TRAN_KEY, '');
        Configuration::updateValue(self::CONNECTION_TYPE, self::CONNECTION_TYPE_REST);

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
            || !Configuration::deleteByName(self::EMAIL_CONTACT)
            || !Configuration::deleteByName(self::TELEPHONE_CONTACT)
            || !Configuration::deleteByName(self::DESCRIPTION)

            || !Configuration::deleteByName(self::EXPIRATION_TIME_MINUTES)
            || !Configuration::deleteByName(self::SHOW_ON_RETURN)
            || !Configuration::deleteByName(self::CIFIN_MESSAGE)
            || !Configuration::deleteByName(self::ALLOW_BUY_WITH_PENDING_PAYMENTS)
            || !Configuration::deleteByName(self::FILL_TAX_INFORMATION)
            || !Configuration::deleteByName(self::FILL_BUYER_INFORMATION)

            || !Configuration::deleteByName(self::COUNTRY)
            || !Configuration::deleteByName(self::ENVIRONMENT)
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
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 601, $e->getMessage()));
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
        } catch (Exception $e) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 601, $e->getMessage()));
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
        } catch (Exception $e) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 601, $e->getMessage()));
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
        } catch (Exception $e) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 601, $e->getMessage()));
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
        } catch (Exception $e) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 601, $e->getMessage()));
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
                copy($this->getPathThisModule() . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png', _PS_IMG_DIR_ . 'os' . DIRECTORY_SEPARATOR . $orderState->id . '.gif');
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
        $this->_html .= $this->displayConfiguration();

        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            $this->_postValidation();
            if (count($this->_postErrors) == 0) {
                $this->_postProcess();
            } else {
                $this->_html .= $this->displayError($this->_postErrors);
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    /**
     * Validation data from post settings form
     */
    protected function _postValidation()
    {
        if (Tools::isSubmit('submitPlacetoPayConfiguration')) {
            // Company data
            if (!Tools::getValue(self::COMPANY_DOCUMENT)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Merchant ID'), $this->ll('is required.'));
            }
            if (!Tools::getValue(self::COMPANY_NAME)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Legal Name'), $this->ll('is required.'));
            }
            if (!Tools::getValue(self::EMAIL_CONTACT)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Email contact'), $this->ll('is required.'));
            } elseif (filter_var(Tools::getValue(self::EMAIL_CONTACT), FILTER_VALIDATE_EMAIL) === false) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Email contact'), $this->ll('is not valid.'));
            }
            if (!Tools::getValue(self::TELEPHONE_CONTACT)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Telephone contact'), $this->ll('is required.'));
            }
            if (!Tools::getValue(self::DESCRIPTION)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Payment description'), $this->ll('is required.'));
            }

            // Configuration Connection
            if (!Tools::getValue(self::COUNTRY)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Country'), $this->ll('is required.'));
            }
            if (!Tools::getValue(self::ENVIRONMENT)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Environment'), $this->ll('is required.'));
            }
            if (!Tools::getValue(self::LOGIN)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Login'), $this->ll('is required.'));
            }
            if (empty($this->getCurrentValueOf(self::TRAN_KEY)) && !Tools::getValue(self::TRAN_KEY)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Trankey'), $this->ll('is required.'));
            }
            if (!Tools::getValue(self::CONNECTION_TYPE)) {
                $this->_postErrors[] = sprintf('%s %s', $this->ll('Connection type'), $this->ll('is required.'));
            }
        }
    }

    /**
     * Update configuration vars
     */
    private function _postProcess()
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
            Configuration::updateValue(self::ALLOW_BUY_WITH_PENDING_PAYMENTS, Tools::getValue(self::ALLOW_BUY_WITH_PENDING_PAYMENTS));
            Configuration::updateValue(self::FILL_TAX_INFORMATION, Tools::getValue(self::FILL_TAX_INFORMATION));
            Configuration::updateValue(self::FILL_BUYER_INFORMATION, Tools::getValue(self::FILL_BUYER_INFORMATION));
            if (versionComparePlaceToPay('1.7.0.0', '<')) {
                Configuration::updateValue(self::FILL_BUYER_INFORMATION, Tools::getValue(self::FILL_BUYER_INFORMATION));
            }

            // Configuration Connection
            Configuration::updateValue(self::COUNTRY, Tools::getValue(self::COUNTRY));
            Configuration::updateValue(self::ENVIRONMENT, Tools::getValue(self::ENVIRONMENT));
            Configuration::updateValue(self::LOGIN, Tools::getValue(self::LOGIN));
            if (Tools::getValue(self::TRAN_KEY)) {
                // Value changed
                Configuration::updateValue(self::TRAN_KEY, Tools::getValue(self::TRAN_KEY));
            }
            Configuration::updateValue(self::CONNECTION_TYPE, Tools::getValue(self::CONNECTION_TYPE));
        }

        $this->_html .= $this->displayConfirmation($this->ll('Place to Pay settings updated'));
    }

    /**
     * Show configuration form
     *
     * @return string
     */
    private function displayConfiguration()
    {
        $this->smarty->assign(
            array(
                'version' => $this->getPluginVersion(),
                'url_notification' => $this->getUrl('process.php'),
                'schedule_task' => $this->getPathScheduleTask(),
                'is_set_credentials' => $this->isSetCredentials(),
            )
        );

        return $this->display($this->getPathThisModule(), DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'front' . DIRECTORY_SEPARATOR . 'setting.tpl');
    }

    /**
     * Show warning pending payment
     *
     * @param $lastPendingTransaction
     * @return string
     */
    private function displayPendingPaymentMessage($lastPendingTransaction)
    {
        $this->smarty->assign(
            array(
                'last_order' => isset($lastPendingTransaction['reference']) ? $lastPendingTransaction['reference'] : '########',
                'last_authorization' => isset($lastPendingTransaction['authcode']) ? $lastPendingTransaction['authcode'] : null,
                'telephone_contact' => $this->getTelephoneContact(),
                'email_contact' => $this->getEmailContact(),
                'allow_payment' => $this->getAllowBuyWithPendingPayments(),
            )
        );

        return $this->display($this->getPathThisModule(), DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'hook' . DIRECTORY_SEPARATOR . 'pending_payment.tpl');
    }

    /**
     * Show warning pending payment
     *
     * @return string
     */
    private function displayTransUnionMessage()
    {
        $this->smarty->assign(
            array(
                'site_name' => Configuration::get('PS_SHOP_NAME'),
                'company_name' => $this->getCompanyName(),
            )
        );

        return $this->display($this->getPathThisModule(), DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'hook' . DIRECTORY_SEPARATOR . 'message_payment.tpl');
    }

    /**
     * @return array
     */
    private function getConfigFieldsValues()
    {
        $compatibility_1_6 = array();

        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            $compatibility_1_6 = array(self::STOCK_REINJECT => $this->getStockReInject());
        }

        return array_merge(array(
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

            self::COUNTRY => $this->getCountry(),
            self::ENVIRONMENT => $this->getEnvironment(),
            self::LOGIN => $this->getLogin(),
            self::TRAN_KEY => $this->getTranKey(),
            self::CONNECTION_TYPE => $this->getConnectionType(),
        ), $compatibility_1_6);
    }

    /**
     * @return string
     */
    private function renderForm()
    {
        $compatibility_1_6 = null;

        if (versionComparePlaceToPay('1.7.0.0', '<')) {
            $compatibility_1_6 = array(
                'type' => 'switch',
                'label' => $this->ll('Re-inject stock on declination?'),
                'name' => self::STOCK_REINJECT,
                'is_bool' => true,
                'values' => $this->getListOptionSwitch(),
            );
        }

        $fieldsFormCompany = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->ll('Company data'),
                    'icon' => 'icon-building'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->ll('Merchant ID'),
                        'name' => self::COMPANY_DOCUMENT,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->ll('Legal Name'),
                        'name' => self::COMPANY_NAME,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->ll('Email contact'),
                        'name' => self::EMAIL_CONTACT,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->ll('Telephone contact'),
                        'name' => self::TELEPHONE_CONTACT,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->ll('Payment description'),
                        'name' => self::DESCRIPTION,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $fieldsFormSetup = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->ll('Configuration'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->ll('Expiration time to pay'),
                        'name' => self::EXPIRATION_TIME_MINUTES,
                        'options' => array(
                            'id' => 'value',
                            'name' => 'label',
                            'query' => $this->getListOptionExpirationMinutes(),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->ll('Show on payment return'),
                        'desc' => $this->ll('If you has PSE method payment in your commerce, set it in: PSE List.'),
                        'name' => self::SHOW_ON_RETURN,
                        'options' => array(
                            'id' => 'value',
                            'name' => 'label',
                            'query' => $this->getListOptionShowOnReturn(),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->ll('Enable TransUnion message?'),
                        'name' => self::CIFIN_MESSAGE,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->ll('Allow buy with pending payments?'),
                        'name' => self::ALLOW_BUY_WITH_PENDING_PAYMENTS,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->ll('Fill TAX information?'),
                        'name' => self::FILL_TAX_INFORMATION,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->ll('Fill buyer information?'),
                        'name' => self::FILL_BUYER_INFORMATION,
                        'is_bool' => true,
                        'values' => $this->getListOptionSwitch(),
                    ),
                    $compatibility_1_6,
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $fieldsFormConnection = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->ll('Configuration Connection'),
                    'icon' => 'icon-rocket'
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->ll('Country'),
                        'name' => self::COUNTRY,
                        'required' => true,
                        'options' => array(
                            'id' => 'value',
                            'name' => 'label',
                            'query' => array(
                                array(
                                    'value' => CountryCode::COLOMBIA,
                                    'label' => $this->ll('Colombia'),
                                ),
                                array(
                                    'value' => CountryCode::ECUADOR,
                                    'label' => $this->ll('Ecuador'),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->ll('Environment'),
                        'name' => self::ENVIRONMENT,
                        'required' => true,
                        'options' => array(
                            'id' => 'value',
                            'name' => 'label',
                            'query' => array(
                                array(
                                    'value' => Environment::PRODUCTION,
                                    'label' => $this->ll('Production'),
                                ),
                                array(
                                    'value' => Environment::TEST,
                                    'label' => $this->ll('Test'),
                                ),
                                array(
                                    'value' => Environment::DEVELOPMENT,
                                    'label' => $this->ll('Development'),
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->ll('Login'),
                        'name' => self::LOGIN,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->ll('Trankey'),
                        'name' => self::TRAN_KEY,
                        'required' => true,
                        'autocomplete' => 'off',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->ll('Connection type'),
                        'name' => self::CONNECTION_TYPE,
                        'required' => true,
                        'options' => array(
                            'id' => 'value',
                            'name' => 'label',
                            'query' => array(
                                array(
                                    'value' => self::CONNECTION_TYPE_SOAP,
                                    'label' => $this->ll('SOAP'),
                                ),
                                array(
                                    'value' => self::CONNECTION_TYPE_REST,
                                    'label' => $this->ll('REST'),
                                ),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

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
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper->generateForm(array($fieldsFormCompany, $fieldsFormSetup, $fieldsFormConnection));
    }

    /**
     * PrestaShop 1.6
     *
     * @param array $params
     * @return string
     */
    public function hookPayment($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, 'Trigger ' . __METHOD__ . ' en PS vr. ' . _PS_VERSION_));
        }

        if (!$this->active) {
            return null;
        }

        if (!$this->isSetCredentials()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, 'Error, set your credentials to used Place to Pay Payment Module'));
            return null;
        }

        $lastPendingTransaction = $this->getLastPendingTransaction($params['cart']->id_customer);

        if (!empty($lastPendingTransaction)) {
            $has_pending = true;
            $this->context->smarty->assign(array(
                'last_order' => $lastPendingTransaction['reference'],
                'last_authorization' => (string)$lastPendingTransaction['authcode'],
                'store_email' => $this->getEmailContact(),
                'store_phone' => $this->getTelephoneContact()
            ));
            $this->context->smarty->assign('payment_url', (

            $this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED
                ? $this->getUrl('redirect.php')
                : 'javascript:;')
            );
        } else {
            $has_pending = false;
            $this->context->smarty->assign('payment_url', $this->getUrl('redirect.php'));
        }

        $this->context->smarty->assign('has_pending', $has_pending);
        $this->context->smarty->assign('version', $this->getPluginVersion());
        $this->context->smarty->assign('site_name', Configuration::get('PS_SHOP_NAME'));
        $this->context->smarty->assign('cifin_message', $this->getTransUnionMessage());
        $this->context->smarty->assign('company_name', $this->getCompanyName());
        $this->context->smarty->assign('allow_payment', ($this->getAllowBuyWithPendingPayments() == self::OPTION_ENABLED || !$has_pending));

        return $this->display($this->getPathThisModule(), DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'hook_1_6' . DIRECTORY_SEPARATOR . 'payment.tpl');
    }

    /**
     * PrestaShop 1.7 or later
     * @param $params
     * @return array
     */
    public function hookPaymentOptions($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, 'Trigger ' . __METHOD__ . ' en PS vr. ' . _PS_VERSION_));
        }

        if (!$this->active) {
            return null;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return null;
        }

        if (!$this->isSetCredentials()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, 'Error, set your credentials to used Place to Pay Payment Module'));
            return null;
        }

        $content = '';
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

        $form = $this->_generateForm($action, $content);

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->ll('Pay by Place to Pay'))
            ->setAdditionalInformation($this->ll('Place to Pay secure web site will be displayed when you select this payment method.'))
            ->setForm(isUtf8($form) ? $form : utf8_decode($form));

        return array($newOption);
    }

    /**
     * @param $action
     * @param $content
     * @return string
     */
    private function _generateForm($action, $content)
    {
        if (is_null($action)) {
            $action = "onsubmit='return false;'";
        } else {
            $action = "action='{$action}'";
        }

        return "<form accept-charset='UTF-8' {$action} id='payment-form'>{$content}</form>";
    }

    /**
     * Last transaction pending to current costumer
     *
     * @param $customerId
     * @return mixed
     */
    private function getLastPendingTransaction($customerId)
    {
        $status = PaymentStatus::PENDING;

        $result = Db::getInstance()->ExecuteS("
            SELECT p.* 
            FROM `{$this->tablePayment}` p
                INNER JOIN `{$this->tableOrder}` o ON o.id_cart = p.id_order
            WHERE o.`id_customer` = {$customerId} 
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
        $uri = null;
        $endpoints = PaymentUrl::getEndpointsTo($this->getCountry());

        if (!empty($endpoints[$this->getEnvironment()])) {
            $uri = $endpoints[$this->getEnvironment()];
        }

        return $uri;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        $country = $this->getCurrentValueOf(self::COUNTRY);

        return empty($country)
            ? CountryCode::COLOMBIA
            : $country;
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        $env = $this->getCurrentValueOf(self::ENVIRONMENT);

        return empty($env)
            ? Environment::TEST
            : $env;
    }

    /**
     * @return mixed
     */
    public function getLogin()
    {
        return $this->getCurrentValueOf(self::LOGIN);
    }

    /**
     * @return mixed
     */
    public function getTranKey()
    {
        return $this->getCurrentValueOf(self::TRAN_KEY);
    }

    /**
     * @return mixed
     */
    public function getCompanyDocument()
    {
        return $this->getCurrentValueOf(self::COMPANY_DOCUMENT);
    }

    /**
     * @return mixed
     */
    public function getCompanyName()
    {
        return $this->getCurrentValueOf(self::COMPANY_NAME);
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->getCurrentValueOf(self::DESCRIPTION);
    }

    /**
     * @return mixed
     */
    public function getEmailContact()
    {
        $emailContact = $this->getCurrentValueOf(self::EMAIL_CONTACT);

        return empty($emailContact)
            ? Configuration::get('PS_SHOP_EMAIL')
            : $emailContact;
    }

    /**
     * @return mixed
     */
    public function getTelephoneContact()
    {
        $telephoneContact = $this->getCurrentValueOf(self::TELEPHONE_CONTACT);

        return empty($telephoneContact) ?
            Configuration::get('PS_SHOP_PHONE')
            : $telephoneContact;
    }

    /**
     * @return mixed
     */
    public function getTransUnionMessage()
    {
        return $this->getCurrentValueOf(self::CIFIN_MESSAGE);
    }

    /**
     * @return mixed
     */
    public function getAllowBuyWithPendingPayments()
    {
        return (int)$this->getCurrentValueOf(self::ALLOW_BUY_WITH_PENDING_PAYMENTS);
    }

    /**
     * @return mixed
     */
    public function getShowOnReturn()
    {
        return $this->getCurrentValueOf(self::SHOW_ON_RETURN);
    }

    /**
     * @return mixed
     */
    public function getExpirationTimeMinutes()
    {
        $minutes = $this->getCurrentValueOf(self::EXPIRATION_TIME_MINUTES);

        return !is_numeric($minutes) || $minutes < 10
            ? self::EXPIRATION_TIME_MINUTES_DEFAULT
            : $minutes;
    }

    /**
     * @return mixed
     */
    public function getFillTaxInformation()
    {
        return $this->getCurrentValueOf(self::FILL_TAX_INFORMATION);
    }

    /**
     * @return mixed
     */
    public function getStockReInject()
    {
        return $this->getCurrentValueOf(self::STOCK_REINJECT);
    }

    /**
     * @return mixed
     */
    public function getConnectionType()
    {
        $connectionType = $this->getCurrentValueOf(self::CONNECTION_TYPE);

        return empty($connectionType) || !in_array($connectionType, [self::CONNECTION_TYPE_SOAP, self::CONNECTION_TYPE_REST])
            ? self::CONNECTION_TYPE_REST
            : $connectionType;
    }

    /**
     * @return mixed
     */
    public function getFillBuyerInformation()
    {
        return $this->getCurrentValueOf(self::FILL_BUYER_INFORMATION);
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
    public function getUrl($page, $params = '')
    {

        $protocol = Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://';
        $domain = Configuration::get('PS_SHOP_DOMAIN_SSL') ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Tools::getHttpHost();

        return $protocol . $domain . __PS_BASE_URI__ . 'modules/' . getModuleName() . '/' . $page . $params;
    }

    /**
     * @return string
     */
    private function getPathScheduleTask()
    {
        return $this->getPathThisModule() . DIRECTORY_SEPARATOR . 'sonda.php';
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
     * Redirect to Place to pay Platform
     *
     * @param Cart $cart
     * @throws PaymentException
     */
    public function redirect(Cart $cart)
    {
        $lastPendingTransaction = $this->getLastPendingTransaction($cart->id_customer);

        if (!empty($lastPendingTransaction) && $this->getAllowBuyWithPendingPayments() == self::OPTION_DISABLED) {
            $message = 'Payment not allowed, customer has payment pending and not allowed but with payment pending is disable';
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 501, $message));
            Tools::redirect('authentication.php?back=order.php');
        }

        $language = Language::getIsoById((int)($cart->id_lang));
        $customer = new Customer((int)($cart->id_customer));
        $currency = new Currency((int)($cart->id_currency));
        $invoiceAddress = new Address((int)($cart->id_address_invoice));
        $deliveryAddress = new Address((int)($cart->id_address_delivery));
        $totalAmount = floatval($cart->getOrderTotal(true));
        $taxAmount = floatval($totalAmount - floatval($cart->getOrderTotal(false)));

        if (!Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($invoiceAddress)
            || !Validate::isLoadedObject($deliveryAddress)
            || !Validate::isLoadedObject($currency)
        ) {
            throw new PaymentException('invalid address or customer', 301);
        }

        $deliveryCountry = new Country((int)($deliveryAddress->id_country));
        $deliveryState = null;
        if ($deliveryAddress->id_state) {
            $deliveryState = new State((int)($deliveryAddress->id_state));
        }

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
            $returnUrl = $this->getUrl('process.php', '?_=' . $this->_reference($reference));

            // Request payment
            $request = [
                'locale' => ($language == 'en') ? 'en_US' : 'es_CO',
                'returnUrl' => $returnUrl,
                'noBuyerFill' => !(bool)$this->getFillBuyerInformation(),
                'ipAddress' => $ipAddress,
                'expiration' => $expiration,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'buyer' => [
                    'name' => $deliveryAddress->firstname,
                    'surname' => $deliveryAddress->lastname,
                    'email' => $customer->email,
                    'mobile' => (!empty($deliveryAddress->phone) ? $deliveryAddress->phone : $deliveryAddress->phone_mobile),
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

            if (isDebugEnable()) {
                PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, print_r($request, true)));
            }

            $transaction = (new PaymentRedirection($this->getLogin(), $this->getTranKey(), $this->getUri(), $this->getConnectionType()))->request($request);

            if (isDebugEnable()) {
                PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, print_r($transaction, true)));
            }

            if ($transaction->isSuccessful()) {
                $requestId = $transaction->requestId();
                $status = PaymentStatus::PENDING;
                // Redirect to payment:
                $redirectTo = $transaction->processUrl();
            } else {
                $status = PaymentStatus::FAILED;
                $totalAmount = 0;
                // Redirect to error:
                $redirectTo = __PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cart->id
                    . '&id_module=' . $this->id
                    . '&id_order=' . $this->currentOrder;

                $history = new OrderHistory();
                $history->id_order = $this->currentOrder;
                $history->changeIdOrderState(Configuration::get('PS_OS_ERROR'), $history->id_order);
                $history->save();
            }

            $orderMessage = $transaction->status()->message();

            // Register payment request
            $this->insertPaymentPlaceToPay($requestId, $cart->id, $cart->id_currency, $totalAmount, $status, $orderMessage, $ipAddress, $reference);

            if (isDebugEnable()) {
                $message = sprintf('[%d => %s] Redirecting flow to: %s', $status, $orderMessage, $redirectTo);
                PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, $message));
            }

            // Redirect flow
            Tools::redirectLink($redirectTo);
        } catch (Exception $e) {
            throw new PaymentException($e->getMessage(), 302);
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
    private function insertPaymentPlaceToPay($requestId, $orderId, $currencyId, $amount, $status, $message, $ipAddress, $reference)
    {
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
     * Process response from Place to Pay Platform
     *
     * @param null $_reference
     * @throws PaymentException
     */
    public function process($_reference = null)
    {
        $paymentPlaceToPay = array();

        if (!is_null($_reference)) {
            // On returnUrl from redirection process
            $reference = $this->_reference($_reference, true);
            $paymentPlaceToPay = $this->getPaymentPlaceToPayBy('reference', $reference);
        } elseif (!empty(file_get_contents("php://input"))) {
            // On resolve function called process
            $requestId = (int)(json_decode(file_get_contents("php://input")))->requestId;
            $paymentPlaceToPay = $this->getPaymentPlaceToPayBy('request_id', $requestId);
        }

        if (empty($paymentPlaceToPay)) {
            $message = sprintf('Payment place to pay not found, reference: [%s]', isset($reference) ? $reference : $_reference);
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 501, $message));
            Tools::redirect('authentication.php?back=order.php');
        }

        $paymentId = $paymentPlaceToPay['id_payment'];
        $cartId = $paymentPlaceToPay['id_order'];
        $requestId = $paymentPlaceToPay['id_request'];
        $oldStatus = $paymentPlaceToPay['status'];

        if (isDebugEnable()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, print_r($paymentPlaceToPay, true)));
        }

        if (!isDebugEnable() && $oldStatus != PaymentStatus::PENDING) {
            $message = sprintf('Payment # %d not is pending, current status is [%d]', $paymentId, $oldStatus);
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, $message));
            Tools::redirect('authentication.php?back=order.php');
        }

        $order = $this->getOrderByCartId($cartId);
        $response = (new PaymentRedirection($this->getLogin(), $this->getTranKey(), $this->getUri(), $this->getConnectionType()))->query($requestId);

        if (isDebugEnable()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, print_r($response, true)));
        }

        if ($response->isSuccessful() && $order) {
            $newStatus = $this->getStatusPayment($response);

            if (isDebugEnable()) {
                $message = sprintf('Updating status by payment # %d from [%d] to [%d]', $paymentId, $oldStatus, $newStatus);
                PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, $message));
            }

            // Set status order in CMS
            $this->settleTransaction($paymentId, $newStatus, $order, $response);

            if (!empty($_reference)) {
                // Redirect to confirmation page
                Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php'
                    . '?id_cart=' . $cartId
                    . '&id_module=' . $this->id
                    . '&id_order=' . $order->id
                    . '&key=' . $order->secure_key
                );
            } else {
                // Show status to reference in console
                die("{$order->reference} [{$newStatus}]" . PHP_EOL);
            }
        } elseif (!$response->isSuccessful()) {
            throw new PaymentException($response->status()->message(), 502);
        } elseif (!$order) {
            throw new PaymentException('Order not found: ' . $cartId, 503);
        } else {
            throw new PaymentException('Un-know error in process payment', 504);
        }
    }

    /**
     * @param string $column You can any column from $this->tablePayment table
     * @param int $value
     * @return bool|array
     */
    private function getPaymentPlaceToPayBy($column, $value = null)
    {
        if (!empty($column) && !empty($value)) {
            $rows = Db::getInstance()->ExecuteS("
                SELECT * 
                FROM  `{$this->tablePayment}` 
                WHERE {$column} = '{$value}'
            ");
        }

        return !empty($rows[0]) ? $rows[0] : false;
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
     */
    private function settleTransaction($paymentId, $status, Order $order, RedirectInformation $response)
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

                    if (versionComparePlaceToPay('1.7.0.0', '<') && $this->getStockReinject() == self::OPTION_ENABLED) {
                        $products = $order->getProducts();
                        foreach ($products as $product) {
                            $order_detail = new OrderDetail((int)($product['id_order_detail']));
                            Product::reinjectQuantities($order_detail, $product['product_quantity']);
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

        if ($response->isApproved() && !empty($response->lastTransaction())) {
            $payment = $response->lastTransaction();

            $date = pSQL($payment->status()->date());
            $reason = pSQL($payment->status()->reason());
            $reasonDescription = pSQL($payment->status()->message());

            $bank = pSQL($payment->issuerName());
            $franchise = pSQL($payment->paymentMethod());
            $franchiseName = pSQL($payment->paymentMethodName());
            $authCode = pSQL($payment->authorization());
            $receipt = pSQL($payment->receipt());
            $conversion = pSQL($payment->amount()->factor());
        }

        if ($response->isApproved() && !empty($response->request()->payer()) && !empty($response->request()->payer()->email())) {
            $payerEmail = pSQL($response->request()->payer()->email());
        }

        $sql = "
            UPDATE `{$this->tablePayment}` SET
                `date` = '{$date}',
                `status` = {$status},
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
     */
    public function hookPaymentReturn($params)
    {
        if (isDebugEnable()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, 'Trigger ' . __METHOD__ . ' en PS vr. ' . _PS_VERSION_));
        }

        if (!$this->active) {
            return null;
        }

        $order = isset($params['objOrder']) ? $params['objOrder'] : $params['order'];

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

        $attributes = array(
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
            ) + $this->getStatusDescription($transaction['status']);

        $this->context->smarty->assign($attributes);

        return $this->display($this->getPathThisModule(), DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'front' . DIRECTORY_SEPARATOR . 'response.tpl');
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

        $this->context->smarty->assign(array(
            'orders' => $orders,
            'invoiceAllowed' => (int)Configuration::get('PS_INVOICE'),
            'reorderingAllowed' => !(bool)Configuration::get('PS_DISALLOW_HISTORY_REORDERING'),
            'slowValidation' => Tools::isSubmit('slowvalidation')
        ));

        return $this->display($this->getPathThisModule(), DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'front' . DIRECTORY_SEPARATOR . 'history.tpl');
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

        $sql = 'SELECT o.`id_order`, o.`id_currency`, o.`payment`, o.`invoice_number`, pp.`date` date_add, pp.`reference`, pp.`amount` total_paid, pp.`authcode` cus, (SELECT SUM(od.`product_quantity`) FROM `' . _DB_PREFIX_ . 'order_detail` od WHERE od.`id_order` = o.`id_order`) nb_products
        FROM `' . $this->tableOrder . '` o
            JOIN `' . $this->tablePayment . '` pp ON pp.id_order = o.id_cart
        WHERE o.`id_customer` = ' . (int)$id_customer .
            Shop::addSqlRestriction(Shop::SHARE_ORDER) . '
        GROUP BY o.`id_order`
        ORDER BY o.`date_add` DESC';

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!$res) {
            return array();
        }

        foreach ($res as $key => $val) {
            $res2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT os.`id_order_state`, osl.`name` AS order_state, os.`invoice`, os.`color` AS order_state_color
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
     * @param $cartId
     * @param null $orderId
     * @return mixed
     */
    private function getTransactionInformation($cartId, $orderId = null)
    {

        $id_order = (empty($cartId)
            ? "(SELECT `id_cart` FROM `{$this->tableOrder}` WHERE `id_order` = {$orderId})"
            : $cartId);

        $result = Db::getInstance()->ExecuteS("SELECT * FROM `{$this->tablePayment}` WHERE `id_order` = {$id_order}");

        if (!empty($result)) {
            $result = $result[0];

            if (empty($result['reason_description'])) {
                $result['reason_description'] = ($result['reason'] == '?-') ? $this->ll('Processing transaction') : $this->ll('No information');
            }

            if (empty($result['status'])) {
                $result['status_description'] = ($result['status'] == '') ? $this->ll('Processing transaction') : $this->ll('No information');
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
    public function resolvePendingPayments($minutes)
    {
        if ($minutes == 0) {
            echo $this->ll('Configuration') . breakLine();
            echo sprintf('PHP [%s]', PHP_VERSION) . breakLine();
            echo sprintf('PrestaShop [%s]', _PS_VERSION_) . breakLine();
            echo sprintf('Plugin [%s]', $this->getPluginVersion()) . breakLine();
            echo sprintf('%s [%s]', $this->ll('Country'), $this->getCountry()) . breakLine();
            echo sprintf('%s [%s]', $this->ll('Environment'), $this->getEnvironment()) . breakLine();
            echo sprintf('%s [%s]', $this->ll('Connection type'), $this->getConnectionType()) . breakLine();
            echo sprintf('%s [%s]', $this->ll('Allow buy with pending payments?'), $this->getAllowBuyWithPendingPayments()) . breakLine(2);
        }
        echo 'Begins ' . date('Ymd H:i:s') . '.' . breakLine();

        $date = date('Y-m-d H:i:s', time() - ($minutes * 60));
        $sql = "SELECT * 
            FROM `{$this->tablePayment}`
            WHERE `date` < '{$date}' 
              AND `status` = " . PaymentStatus::PENDING;

        if (isDebugEnable()) {
            PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 0, $sql));
        }

        if ($result = Db::getInstance()->ExecuteS($sql)) {
            echo "Found (" . count($result) . ") payments pending." . breakLine(2);

            try {
                $place_to_pay = new PaymentRedirection($this->getLogin(), $this->getTranKey(), $this->getUri(), $this->getConnectionType());

                foreach ($result as $row) {
                    $reference = $row['reference'];
                    $requestId = (int)$row['id_request'];
                    $paymentId = (int)$row['id_payment'];
                    $cartId = (int)$row['id_order'];

                    echo "Processing {$reference}." . breakLine();

                    $response = $place_to_pay->query($requestId);
                    $status = $this->getStatusPayment($response);
                    $order = $this->getOrderByCartId($cartId);

                    if ($order) {
                        $this->settleTransaction($paymentId, $status, $order, $response);
                    }

                    echo sprintf('%s status is %s [%s]', $order->reference, implode('=', $this->getStatusDescription($status)), $status) . breakLine(2);
                }
            } catch (Exception $e) {
                PaymentLogger::log(sprintf("[%s:%d] => [%d]\n %s", __FILE__, __LINE__, 999, $e->getMessage()));
                echo 'Error: ' . $e->getMessage() . breakLine(2);
            }
        } else {
            echo 'Not exists payments pending.' . breakLine();
        }

        echo 'Finished ' . date('Ymd H:i:s') . '.' . breakLine();
    }

    /**
     *
     */
    private function getListOptionShowOnReturn()
    {
        $options = array(
            array(
                'value' => self::SHOW_ON_RETURN_DEFAULT,
                'label' => $this->ll('PrestaShop View'),
            ),
            array(
                'value' => self::SHOW_ON_RETURN_DETAILS,
                'label' => $this->ll('Payment Details'),
            ),
            array(
                'value' => self::SHOW_ON_RETURN_PSE_LIST,
                'label' => $this->ll('PSE List'),
            ),
        );

        return $options;
    }

    /**
     * Get expiration time minutes list
     *
     * @return array
     */
    private function getListOptionExpirationMinutes()
    {
        $options = array();
        $minutes = 10;
        $txtMinutes = $this->ll('minutes');
        $txtHours = $this->ll('hour(s)');
        $txtDays = $this->ll('day(s)');
        $txtWeeks = $this->ll('week(s)');
        $txtMonths = $this->ll('month(s)');

        while ($minutes <= self::EXPIRATION_TIME_MINUTES_LIMIT) {
            if ($minutes < 60) {
                $options[] = array(
                    'value' => $minutes,
                    'label' => sprintf('%d %s', $minutes, $txtMinutes),
                );
                $minutes += 10;
            } elseif ($minutes >= 60 && $minutes < 1440) {
                $options[] = array(
                    'value' => $minutes,
                    'label' => sprintf('%d %s', $minutes / 60, $txtHours),
                );
                $minutes += 60;
            } elseif ($minutes >= 1440 && $minutes < 10080) {
                $options[] = array(
                    'value' => $minutes,
                    'label' => sprintf('%d %s', $minutes / 1440, $txtDays),
                );
                $minutes += 1440;
            } elseif ($minutes >= 10080 && $minutes < 40320) {
                $options[] = array(
                    'value' => $minutes,
                    'label' => sprintf('%d %s', $minutes / 10080, $txtWeeks),
                );
                $minutes += 10080;
            } else {
                $options[] = array(
                    'value' => $minutes,
                    'label' => sprintf('%d %s', $minutes / 40320, $txtMonths),
                );
                $minutes += 40320;
            }
        }

        return $options;
    }

    /**
     * @return array
     */
    private function getListOptionSwitch()
    {
        return array(
            array(
                'id' => 'active_on',
                'value' => self::OPTION_ENABLED,
                'label' => $this->trans('Enabled', array(), 'Admin.Global'),
            ),
            array(
                'id' => 'active_off',
                'value' => self::OPTION_DISABLED,
                'label' => $this->trans('Disabled', array(), 'Admin.Global'),
            )
        );
    }

    /**
     * @param $name
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
    private function _reference($string, $rollBack = false)
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
}
