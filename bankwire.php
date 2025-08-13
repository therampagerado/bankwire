<?php
/**
 * Copyright (C) 2017-2024 thirty bees
 * Copyright (C) 2007-2016 PrestaShop SA
 *
 * thirty bees is an extension to the PrestaShop software by PrestaShop SA.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/classes/BankwireAccount.php';

/**
 * Class BankWire
 */
class BankWire extends PaymentModule
{
    // @codingStandardsIgnoreStart
    /** @var string $moduleHtml */
    protected $moduleHtml = '';
    // @codingStandarsdIgnoreEnd

    /**
     * BankWire constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'bankwire';
        $this->tab = 'payments_gateways';
        $this->version = '2.1.0';
        $this->author = 'thirty bees';
        $this->need_instance = 1;
        $this->controllers = ['payment', 'validation'];
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Bankwire Module');
        $this->description = $this->l('Accept payments for your products via bank wire transfer.');
        $this->tb_versions_compliancy = '> 1.0.0';
        $this->tb_min_version = '1.0.0';
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        if (!count(BankwireAccount::getCurrenciesByShop($this->context->shop->id))) {
            $this->warning = $this->l('No bank account has been defined for this shop.');
        }
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    public function install()
    {
        if (!parent::install() || !$this->installDb()) {
            return false;
        }

        $this->registerHook('displayPayment');
        $this->registerHook('displayPaymentEU');
        $this->registerHook('paymentReturn');

        return true;
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        if (!parent::uninstall() || !$this->uninstallDb()) {
            return false;
        }

        return true;
    }

    /**
     * Install database tables
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function installDb()
    {
        $sql = [];
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'bankwire_account` (
            `id_bankwire_account` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_currency` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_bankwire_account`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'bankwire_account_lang` (
            `id_bankwire_account` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `owner` VARCHAR(255) NOT NULL,
            `details` TEXT NOT NULL,
            `address` TEXT NOT NULL,
            PRIMARY KEY (`id_bankwire_account`,`id_lang`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'bankwire_account_shop` (
            `id_bankwire_account` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id_bankwire_account`,`id_shop`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        foreach ($sql as $s) {
            if (!Db::getInstance()->execute($s)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove database tables
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function uninstallDb()
    {
        $sql = [];
        $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'bankwire_account_shop`';
        $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'bankwire_account_lang`';
        $sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'bankwire_account`';
        foreach ($sql as $s) {
            if (!Db::getInstance()->execute($s)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        $this->moduleHtml .= $this->displayBankwire();

        if (Tools::isSubmit('submitBankwireAccount')) {
            $this->processAccount();
        }

        if (Tools::isSubmit('deletebankwire_account')) {
            $account = new BankwireAccount((int) Tools::getValue('id_bankwire_account'));
            $account->delete();
        }

        if (Tools::isSubmit('addbankwire_account') || Tools::isSubmit('updatebankwire_account')) {
            $this->moduleHtml .= $this->renderAccountForm();
        } else {
            $this->moduleHtml .= $this->renderAccountList();
        }

        return $this->moduleHtml;
    }

    /**
     * @return string
     * @throws Exception
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function displayBankwire()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    /**
     * Render list of accounts
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function renderAccountList()
    {
        $idLang = (int) $this->context->language->id;
        $shops = Shop::getContextListShopID();
        $shopIds = implode(',', array_map('intval', $shops));
        $accounts = Db::getInstance()->executeS('SELECT a.id_bankwire_account, c.iso_code AS currency, al.owner
            FROM '._DB_PREFIX_.'bankwire_account a
            INNER JOIN '._DB_PREFIX_.'bankwire_account_shop s ON (a.id_bankwire_account = s.id_bankwire_account AND s.id_shop IN ('.$shopIds.'))
            INNER JOIN '._DB_PREFIX_.'currency c ON (c.id_currency = a.id_currency)
            INNER JOIN '._DB_PREFIX_.'bankwire_account_lang al ON (a.id_bankwire_account = al.id_bankwire_account AND al.id_lang = '.$idLang.')
            GROUP BY a.id_bankwire_account');

        $fields_list = [
            'id_bankwire_account' => ['title' => $this->l('ID'), 'align' => 'center'],
            'currency'           => ['title' => $this->l('Currency')],
            'owner'              => ['title' => $this->l('Account owner')],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->identifier = 'id_bankwire_account';
        $helper->actions = ['edit', 'delete'];
        $helper->title = $this->l('Bank accounts');
        $helper->table = 'bankwire_account';
        $helper->no_link = true;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->toolbar_btn['new'] = [
            'href' => $helper->currentIndex.'&addbankwire_account',
            'desc' => $this->l('Add new account'),
        ];

        return $helper->generateList($accounts, $fields_list);
    }

    /**
     * Render account form
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function renderAccountForm()
    {
        $id = (int) Tools::getValue('id_bankwire_account');
        $account = new BankwireAccount($id);

        $currencies = Currency::getCurrencies(false, true, true);

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Bank account'),
                    'icon'  => 'icon-envelope',
                ],
                'input'  => [
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Currency'),
                        'name'    => 'id_currency',
                        'options' => [
                            'query' => $currencies,
                            'id'    => 'id_currency',
                            'name'  => 'name',
                        ],
                        'required' => true,
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Account owner'),
                        'name'     => 'owner',
                        'lang'     => true,
                        'required' => true,
                    ],
                    [
                        'type'     => 'textarea',
                        'label'    => $this->l('Details'),
                        'name'     => 'details',
                        'lang'     => true,
                        'required' => true,
                    ],
                    [
                        'type'     => 'textarea',
                        'label'    => $this->l('Bank address'),
                        'name'     => 'address',
                        'lang'     => true,
                        'required' => true,
                    ],
                    [
                        'type' => 'shop',
                        'label' => $this->l('Shop association'),
                        'name'  => 'checkBoxShopAsso_bankwire_account',
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = 'bankwire_account';
        $helper->identifier = 'id_bankwire_account';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->submit_action = 'submitBankwireAccount';
        $helper->default_form_language = (int) $this->context->language->id;

        $fields_value = [
            'id_currency' => $account->id_currency,
        ];
        $languages = $this->context->controller->getLanguages();
        foreach ($languages as $lang) {
            $fields_value['owner'][$lang['id_lang']] = isset($account->owner[$lang['id_lang']]) ? $account->owner[$lang['id_lang']] : '';
            $fields_value['details'][$lang['id_lang']] = isset($account->details[$lang['id_lang']]) ? $account->details[$lang['id_lang']] : '';
            $fields_value['address'][$lang['id_lang']] = isset($account->address[$lang['id_lang']]) ? $account->address[$lang['id_lang']] : '';
        }
        $shops = $account->id ? $account->getAssociatedShops() : Shop::getContextListShopID();
        $asso = [];
        foreach ($shops as $idShop) {
            if (is_array($idShop)) {
                $asso[$idShop['id_shop']] = true;
            } else {
                $asso[$idShop] = true;
            }
        }
        $fields_value['checkBoxShopAsso_bankwire_account'] = $asso;
        if ($account->id) {
            $fields_value['id_bankwire_account'] = $account->id;
        }

        $helper->tpl_vars = [
            'fields_value' => $fields_value,
            'languages'    => $languages,
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * Process form submission
     *
     * @throws PrestaShopException
     */
    protected function processAccount()
    {
        $id = (int) Tools::getValue('id_bankwire_account');
        $account = new BankwireAccount($id);
        $account->id_currency = (int) Tools::getValue('id_currency');

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $account->owner[$lang['id_lang']] = Tools::getValue('owner_'.$lang['id_lang']);
            $account->details[$lang['id_lang']] = Tools::getValue('details_'.$lang['id_lang']);
            $account->address[$lang['id_lang']] = Tools::getValue('address_'.$lang['id_lang']);
        }
        $shops = Tools::getValue('checkBoxShopAsso_bankwire_account');
        $account->id_shop_list = is_array($shops) ? $shops : [$this->context->shop->id];

        foreach ($account->id_shop_list as $idShop) {
            if (BankwireAccount::existsForCurrency($account->id_currency, $idShop, $id)) {
                $this->moduleHtml .= $this->displayError($this->l('An account already exists for this currency and shop.'));
                return;
            }
        }

        if ($account->id) {
            $account->update();
        } else {
            $account->add();
        }

        $this->moduleHtml .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * @return string
     * @throws Exception
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookPayment()
    {
        if (!$this->active) {
            return '';
        }

        $account = BankwireAccount::getByCurrency($this->context->cart->id_currency, $this->context->shop->id, $this->context->language->id);
        if (!$account) {
            $this->smarty->assign('bankwireError', $this->l('Bank wire is not available for the selected currency.'));
            return $this->display(__FILE__, 'payment_error.tpl');
        }

        $this->smarty->assign(
            [
                'this_path'     => $this->_path,
                'this_path_bw'  => $this->_path,
                'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            ]
        );

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * @return array|string
     * @throws PrestaShopException
     */
    public function hookDisplayPaymentEU()
    {
        if (!$this->active) {
            return '';
        }

        $account = BankwireAccount::getByCurrency($this->context->cart->id_currency, $this->context->shop->id, $this->context->language->id);
        if (!$account) {
            return '';
        }

        return [
            'cta_text' => $this->l('Pay by Bank Wire'),
            'logo'     => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/bankwire.jpg'),
            'action'   => $this->context->link->getModuleLink($this->name, 'validation', [], true),
        ];
    }

    /**
     * @param array $params
     *
     * @return string
     * @throws Exception
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookPaymentReturn($params)
    {
        if (!isset($params) || !isset($params['objOrder']) || !$params['objOrder'] instanceof Order || !$this->active) {
            return '';
        }

        try {
            $state = $params['objOrder']->getCurrentState();
            if (in_array($state, [Configuration::get('PS_OS_BANKWIRE'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')])) {
                $account = BankwireAccount::getByCurrency($params['currencyObj']->id, $this->context->shop->id, $this->context->language->id);
                $this->smarty->assign(
                    [
                        'total_to_pay'    => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                        'bankwireDetails' => $account ? nl2br($account['details']) : '',
                        'bankwireAddress' => $account ? nl2br($account['address']) : '',
                        'bankwireOwner'   => $account ? $account['owner'] : '',
                        'status'          => 'ok',
                        'id_order'        => $params['objOrder']->id,
                    ]
                );
                if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                    $this->smarty->assign('reference', $params['objOrder']->reference);
                }
            } else {
                $this->smarty->assign('status', 'failed');
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Bankwire module error: {$e->getMessage()}");

            return '';
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }
}
