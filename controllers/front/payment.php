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
 * @since 1.5.0
 */
class BankwirePaymentModuleFrontController extends ModuleFrontController
{
    /** @var bool $ssl */
    public $ssl = true;
    /** @var bool $display_column_left */
    public $display_column_left = false;
    /** @var BankWire $module */
    public $module;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        try {
            parent::initContent();
        } catch (PrestaShopException $e) {
            Tools::redirect('index.php?controller=order');
        }

        $cart = $this->context->cart;
        $currencyId = (int) $cart->id_currency;
        $langId = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        try {
            if (!count(Currency::checkPaymentCurrencies($this->module->id))) {
                Tools::redirect('index.php?controller=order');
            }
        } catch (PrestaShopException $e) {
            Tools::redirect('index.php?controller=order');
        }

        $availableCurrencies = Currency::getPaymentCurrencies($this->module->id, (int) $cart->id_shop);

        // Fetch currencies that have a bank account configured for the current shop
        $currenciesWithAccounts = Db::getInstance()->executeS(
            'SELECT DISTINCT c.id_currency, c.iso_code 
             FROM `' . _DB_PREFIX_ . 'currency` c
             JOIN `' . _DB_PREFIX_ . 'bankwire` b 
             ON c.id_currency = b.currency_id
             WHERE c.active = 1
             AND b.id_shop = ' . (int) $idShop
        );

        // Create a lookup array of currencies that have bank accounts
        $currencyIdsWithAccounts = array_column($currenciesWithAccounts, 'id_currency');

        // Filter available currencies to include only those with bank accounts
        $filteredCurrencies = array_filter($availableCurrencies, function ($currency) use ($currencyIdsWithAccounts) {
            return in_array($currency['id_currency'], $currencyIdsWithAccounts);
        });

        // Fetch bank accounts for the current shop, language, and currency
        $bankAccounts = Db::getInstance()->executeS(
            'SELECT b.id_bankwire, bl.account_holder, bl.account_details, bl.bank_address 
             FROM `' . _DB_PREFIX_ . 'bankwire` b
             LEFT JOIN `' . _DB_PREFIX_ . 'bankwire_lang` bl 
             ON b.id_bankwire = bl.id_bankwire 
             WHERE b.currency_id = ' . (int) $currencyId . '
             AND bl.id_lang = ' . (int) $langId . '
             AND b.id_shop = ' . (int) $idShop
        );

        try {
            $this->context->smarty->assign(
                [
                    'nbProducts'    => $cart->nbProducts(),
                    'cust_currency' => $currencyId,
                    'cust_lang'     => $langId,
                    'currencies'    => $filteredCurrencies,
                    'total'         => $cart->getOrderTotal(true, Cart::BOTH),
                    'this_path'     => $this->module->getPathUri(),
                    'this_path_bw'  => $this->module->getPathUri(),
                    'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
                    'bank_accounts' => $bankAccounts ?: [],
                ]
            );

            $this->setTemplate('payment_execution.tpl');
        } catch (Exception $e) {
            Logger::addLog("Bankwire module error: {$e->getMessage()}");
            Tools::redirect('index.php?controller=order');
        }
    }
}
