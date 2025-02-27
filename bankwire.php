<?php
/**
 * Copyright (C) 2017-2025 thirty bees
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
 * @copyright 2017-2025 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class BankWire
 */
class BankWire extends PaymentModule
{
    /**
     * BankWire constructor.
     */
    public function __construct()
    {
        $this->name = 'bankwire';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.11';
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
        $this->confirmUninstall = $this->l('Are you sure you want to remove this module?');

        $this->installDatabase();
    }

    private function installDatabase()
    {
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'bankwire` (
                `id_bankwire` INT(11) AUTO_INCREMENT PRIMARY KEY,
                `id_shop` INT(11) NOT NULL,
                `currency_id` INT(11) NOT NULL,
                INDEX (`id_shop`),
                INDEX (`currency_id`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ') && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'bankwire_lang` (
                `id_bankwire` INT(11) NOT NULL,
                `id_shop` INT(11) NOT NULL,
                `id_lang` INT(11) NOT NULL,
                `account_holder` VARCHAR(255) NOT NULL,
                `account_details` TEXT NOT NULL,
                `bank_address` TEXT NOT NULL,
                PRIMARY KEY (`id_bankwire`, `id_lang`, `id_shop`),
                INDEX (`id_bankwire`),
                INDEX (`id_shop`),
                INDEX (`id_lang`),
                FOREIGN KEY (`id_bankwire`) REFERENCES `'._DB_PREFIX_.'bankwire` (`id_bankwire`) ON DELETE CASCADE
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentEU') &&
            $this->registerHook('paymentReturn') &&
            $this->installDatabase();
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'bankwire_lang`') &&
            Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'bankwire`');
    }

    public function getContent()
    {
        if (Tools::getIsset('deletebankwire') && Tools::getValue('id_bankwire')) {
            $this->deleteBankAccount((int) Tools::getValue('id_bankwire'));
            Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
        }

        if (Tools::isSubmit('submitBankWire')) {
            $this->saveBankAccount();
        }

        return $this->displayInfos() . $this->renderBankAccountsTable() . $this->renderForm() . $this->displayFooter();
    }
    
    public function hookDisplayPayment($params)
    {
        if (!$this->active) {
            return '';
        }

        $currencyId = (int) $params['cart']->id_currency;
        $langId = (int) $this->context->language->id;

        $bankAccount = $this->getBankAccountByCurrency($currencyId, $langId);

        if (!$bankAccount) {
            return '';
        }

        $this->smarty->assign([
            'this_path'     => $this->_path,
            'this_path_bw'  => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'bank_accounts' => [$bankAccount],
            'cust_lang'     => $langId,
        ]);

        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return [];
        }

        $currencyId = (int) $params['cart']->id_currency;
        $langId = (int) $this->context->language->id;

        $bankAccount = $this->getBankAccountByCurrency($currencyId, $langId);

        if (!$bankAccount) {
            return [];
        }

        return [
            'cta_text' => $this->l('Pay by Bank Wire'),
            'logo'     => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/bankwire.jpg'),
            'action'   => $this->context->link->getModuleLink($this->name, 'validation', [], true),
        ];
    }

    public function hookPaymentReturn($params)
    {
        $order = isset($params['order']) ? $params['order'] : (isset($params['objOrder']) ? $params['objOrder'] : null);

        if (!$this->active || !$order) {
            return '';
        }

        $currencyId = (int) $order->id_currency;
        $langId = (int) $this->context->language->id;

        // Fetch bank account details for the exact language
        $bankAccount = Db::getInstance()->getRow(
            'SELECT bl.account_holder, bl.account_details, bl.bank_address 
             FROM `'._DB_PREFIX_.'bankwire` b
             LEFT JOIN `'._DB_PREFIX_.'bankwire_lang` bl ON b.id_bankwire = bl.id_bankwire 
             WHERE b.currency_id = '.(int) $currencyId.'
             AND bl.id_lang = '.(int) $langId
        );

        if (!$bankAccount) {
            return '';
        }

        $this->smarty->assign([
            'total_to_pay'    => Tools::displayPrice($order->getOrdersTotalPaid(), new Currency($currencyId), false),
            'bankwireOwner'   => htmlspecialchars($bankAccount['account_holder']),
            'bankwireDetails' => nl2br(htmlspecialchars($bankAccount['account_details'])),
            'bankwireAddress' => nl2br(htmlspecialchars($bankAccount['bank_address'])),
            'status'          => 'ok',
            'id_order'        => $order->id,
        ]);

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    private function saveBankAccount()
    {
        $idBankwire = (int) Tools::getValue('id_bankwire');
        $currencyId = (int) Tools::getValue('currency_id');
        $isAllShops = Shop::getContext() === Shop::CONTEXT_ALL;
        $errors = [];

        if (!$currencyId) {
            $errors[] = $this->l('You must select a currency.');
        }

        $languages = Language::getLanguages(false);
        $bankwireData = [];

        foreach ($languages as $lang) {
            $langId = (int) $lang['id_lang'];
            $accountHolder = trim(Tools::getValue('account_holder_' . $langId));
            $accountDetails = trim(Tools::getValue('account_details_' . $langId));
            $bankAddress = trim(Tools::getValue('bank_address_' . $langId));

            if (empty($accountHolder) || empty($accountDetails) || empty($bankAddress)) {
                $errors[] = sprintf(
                    $this->l('All fields must be filled for the language: %s.'),
                    $lang['name']
                );
            } else {
                $bankwireData[$langId] = [
                    'account_holder' => pSQL($accountHolder),
                    'account_details' => pSQL($accountDetails),
                    'bank_address' => pSQL($bankAddress),
                ];
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->context->controller->errors[] = $error;
            }
            return;
        }

        $shopIds = $isAllShops ? Shop::getShops(true, null, true) : [(int) $this->context->shop->id];

        foreach ($shopIds as $shopId) {
            $existingId = Db::getInstance()->getValue(
                'SELECT id_bankwire FROM `' . _DB_PREFIX_ . 'bankwire` 
                 WHERE currency_id = ' . (int) $currencyId . ' 
                 AND id_shop = ' . (int) $shopId
            );

            if ($existingId) {
                Db::getInstance()->update('bankwire', [
                    'currency_id' => $currencyId,
                ], 'id_bankwire = ' . (int) $existingId);
                $idBankwire = $existingId;
            } else {
                Db::getInstance()->insert('bankwire', [
                    'currency_id' => $currencyId,
                    'id_shop' => $shopId,
                ]);
                $idBankwire = Db::getInstance()->Insert_ID();
            }

            foreach ($bankwireData as $langId => $data) {
                $existingLang = Db::getInstance()->getValue(
                    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'bankwire_lang` 
                     WHERE id_bankwire = ' . (int) $idBankwire . ' 
                     AND id_lang = ' . (int) $langId . '
                     AND id_shop = ' . (int) $shopId
                );

                if ($existingLang) {
                    Db::getInstance()->update('bankwire_lang', $data, 
                        'id_bankwire = ' . (int) $idBankwire . ' 
                        AND id_lang = ' . (int) $langId . ' 
                        AND id_shop = ' . (int) $shopId
                    );
                } else {
                    Db::getInstance()->insert('bankwire_lang', array_merge([
                        'id_bankwire' => $idBankwire,
                        'id_lang' => $langId,
                        'id_shop' => $shopId,
                    ], $data));
                }
            }
        }

        Tools::redirectAdmin(
            AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
        );
    }

    private function deleteBankAccount($id)
    {
        if ($id > 0) {
            Db::getInstance()->delete('bankwire', 'id_bankwire = ' . (int)$id);
        }
    }

    private function renderBankAccountsTable()
    {
        $isAllShops = Shop::getContext() === Shop::CONTEXT_ALL;
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->employee->id_lang;

        $query = 'SELECT DISTINCT b.id_bankwire, c.iso_code AS currency_iso, 
                         bl.account_holder, bl.account_details, bl.bank_address';

        if ($isAllShops) {
            $query .= ', (SELECT GROUP_CONCAT(id_shop SEPARATOR ", ") FROM `' . _DB_PREFIX_ . 'bankwire` WHERE id_bankwire = b.id_bankwire) as shop_ids';
        }

        $query .= ' FROM `' . _DB_PREFIX_ . 'bankwire` b
                    INNER JOIN `' . _DB_PREFIX_ . 'bankwire_lang` bl 
                    ON b.id_bankwire = bl.id_bankwire 
                    AND bl.id_lang = ' . (int) $idLang . '
                    INNER JOIN `' . _DB_PREFIX_ . 'currency` c 
                    ON b.currency_id = c.id_currency';

        if (!$isAllShops) {
            $query .= ' WHERE b.id_shop = ' . (int) $idShop;
        }

        $query .= ' ORDER BY b.id_bankwire';

        $bankAccounts = Db::getInstance()->executeS($query);

        $fields_list = [
            'id_bankwire' => ['title' => $this->l('ID'), 'align' => 'center'],
            'currency_iso' => ['title' => $this->l('Currency')],
            'account_holder' => ['title' => $this->l('Account Holder')],
            'account_details' => ['title' => $this->l('Account Details')],
            'bank_address' => ['title' => $this->l('Bank Address')],
        ];

        if ($isAllShops) {
            $fields_list['shop_ids'] = ['title' => $this->l('Shops')];
        }

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->identifier = 'id_bankwire';
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;
        $helper->title = $this->l('Bank Accounts');
        $helper->table = 'bankwire';
        $helper->listTotal = count($bankAccounts);
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        return $helper->generateList($bankAccounts, $fields_list);
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
    
    private function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBankWire';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getBankWireFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getBankWireForm()]);
    }

    private function getBankWireForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Bank wire account details'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'hidden',
                        'name' => 'id_bankwire',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Currency'),
                        'name' => 'currency_id',
                        'options' => [
                            'query' => $this->getAvailableCurrencies(),
                            'id' => 'id_currency',
                            'name' => 'iso_code',
                        ],
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Account holder'),
                        'name' => 'account_holder',
                        'lang' => true,
                        'required' => true,
                        'desc' => '<em>' . $this->l('Enter the full legal name of the account holder, such as the company or individual name associated with the bank account.') . '</em>',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Account details'),
                        'name' => 'account_details',
                        'lang' => true,
                        'required' => true,
                        'desc' => '<em>' . $this->l('Provide the IBAN, SWIFT/BIC, or any other required banking details applicable in your region to facilitate accurate transactions.') . '</em>',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Bank name and address'),
                        'name' => 'bank_address',
                        'lang' => true,
                        'required' => true,
                        'desc' => '<em>' . $this->l('Specify the bank\'s official name along with the complete branch address to ensure seamless payment processing.') . '</em>',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    private function getBankWireFormValues()
    {
        $idBankwire = (int)Tools::getValue('id_bankwire');
        $bankAccount = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'bankwire` WHERE id_bankwire = ' . (int)$idBankwire);
        
        $languages = Language::getLanguages(false);
        $fields_value = [
            'id_bankwire' => $idBankwire,
            'currency_id' => $bankAccount['currency_id'] ?? '',
        ];

        foreach ($languages as $lang) {
            $langId = (int)$lang['id_lang'];
            $langData = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'bankwire_lang` WHERE id_bankwire = ' . (int)$idBankwire . ' AND id_lang = ' . (int)$langId);
            
            $fields_value['account_holder'][$langId] = $langData['account_holder'] ?? '';
            $fields_value['account_details'][$langId] = $langData['account_details'] ?? '';
            $fields_value['bank_address'][$langId] = $langData['bank_address'] ?? '';
        }

        return $fields_value;
    }

    private function getAvailableCurrencies()
    {
        return Db::getInstance()->executeS(
            'SELECT DISTINCT id_currency, iso_code 
             FROM `'._DB_PREFIX_.'currency` 
             WHERE active = 1 
             ORDER BY iso_code ASC'
        );
    }


    private function getBankAccountByCurrency($currencyId, $langId)
    {
        $idShop = (int) $this->context->shop->id;
        $cacheId = 'bankwire_account_'.$currencyId.'_'.$langId.'_'.$idShop;

        if (!Cache::isStored($cacheId)) {
            $result = Db::getInstance()->getRow(
                'SELECT b.id_bankwire, b.currency_id, bl.account_holder, bl.account_details, bl.bank_address
                 FROM `'._DB_PREFIX_.'bankwire` b
                 LEFT JOIN `'._DB_PREFIX_.'bankwire_lang` bl 
                 ON b.id_bankwire = bl.id_bankwire
                 WHERE b.currency_id = ' . (int) $currencyId . '
                 AND bl.id_lang = ' . (int) $langId . '
                 AND b.id_shop = ' . (int) $idShop
            );
            Cache::store($cacheId, $result);
        }

        return Cache::retrieve($cacheId);
    }
    
    private function getAvailableBankCurrencies()
    {
        $currencies = Db::getInstance()->executeS(
            'SELECT DISTINCT c.id_currency, c.iso_code 
             FROM `'._DB_PREFIX_.'bankwire` b
             INNER JOIN `'._DB_PREFIX_.'currency` c ON b.currency_id = c.id_currency 
             WHERE c.active = 1 
             ORDER BY c.iso_code ASC'
        );

        return $currencies;
    }
    
    public function displayInfos()
    {
        $paymentConfigLink = $this->context->link->getAdminLink('AdminPayment');
        $multistoreMessage = '';

        if (Shop::isFeatureActive()) {
            $multistoreMessage = '<p>' . $this->l('This module fully supports Multistore mode. To apply the same settings across all shops, make changes in the \'All shops\' context. Alternatively, switch to a specific shop\'s context to configure individual settings.') . '</p>';
        }

        return '
            <div class="alert alert-info">
                <img src="' . $this->_path . 'bankwire.jpg" style="float:left; margin-right:15px;" width="86" height="49">
                <p><strong>' . $this->l('This module enables secure payments via bank wire.') . '</strong></p>
                <p>' . $this->l('To activate the module, configure at least one bank account in the desired currency and ensure all required fields are completed. Currently only one account per currency is supported.') . '</p>
                <p>' . $this->l('The payment option will only be available at checkout when a customer selects a currency for which a corresponding bank account has been set up.') . '</p>
                <p><strong>' . $this->l('Important:') . '</strong> ' . $this->l('For the module to function correctly, it must also be configured in the') . ' 
                    <a href="' . $paymentConfigLink . '" target="_blank">' . $this->l('Payment settings') . '</a>. 
                </p>
                <p>' . $this->l('If you do not maintain separate accounts for each currency and rely on your bank to apply exchange rates for incoming transfers, ensure the same bank details are provided for all enabled currencies in the Payment settings.') . '</p>
                <p>' . $this->l('When a customer selects bank wire as the payment method, the order status will automatically update to') . ' 
                    <strong>' . $this->l('Awaiting bank wire payment') . '</strong>.
                </p>
                <p>' . $this->l('You must manually confirm the order upon receipt of the bank transfer.') . '</p>
                ' . $multistoreMessage . '
            </div>';
    }
    
    public function displayFooter()
    {
        $githubUrl = 'https://github.com/thirtybees/bankwire';
        $githubIcon = '<img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png" alt="GitHub" width="16" height="16" style="vertical-align: middle; margin-left: 5px;">';

        return '
            <div class="alert alert-info" style="text-align: right; padding: 10px; min-height: 50px;">
                <p style="display: inline-block; margin-right: 10px;">
                    ' . $this->l('For the module roadmap and future development suggestions, please visit') . ' 
                    <a href="' . $githubUrl . '" target="_blank" style="font-weight: bold;">the Bankwire Module\'s official GitHub repository.</a>
                </p>
                <a href="' . $githubUrl . '" target="_blank">' . $githubIcon . '</a>
            </div>';
    }
}
