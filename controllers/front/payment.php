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

/**
 * @since 1.5.0
 */
class BankwirePaymentModuleFrontController extends ModuleFrontController
{
    // @codingStandardsIgnoreStart
    /** @var bool $ssl */
    public $ssl = true;
    /** @var bool $display_column_left */
    public $display_column_left = false;
    /** @var BankWire $module */
    public $module;
    // @codingStandardsIgnoreEnd

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $cart = $this->context->cart;

        // Require at least a specific account or ALL account
        if (!BankwireAccount::getByCurrency((int)$cart->id_currency, (int)$cart->id_shop)) {
            Tools::redirect('index.php?controller=order&step=3');
        }

        $rows = BankwireAccount::getCurrenciesByShop((int)$cart->id_shop);
        $hasAll = false;
        foreach ($rows as $r) {
            if ((int)$r['id_currency'] === BankwireAccount::CURRENCY_ALL) { $hasAll = true; break; }
        }

        $currencies = [];
        if ($hasAll) {
            // Present only the cart currency to keep the tpl text coherent
            $c = Currency::getCurrencyInstance((int)$cart->id_currency);
            $currencies[] = [
                'id_currency' => (int)$c->id,
                'name'        => $c->name ?: $c->iso_code,
            ];
        } else {
            // Legacy shape: array of arrays
            foreach ($rows as $row) {
                $c = Currency::getCurrencyInstance((int)$row['id_currency']);
                if (Validate::isLoadedObject($c)) {
                    $currencies[] = [
                        'id_currency' => (int)$c->id,
                        'name'        => $c->name ?: $c->iso_code,
                    ];
                }
            }
        }

        $this->context->smarty->assign([
            'nbProducts'    => (int)$cart->nbProducts(),
            'cust_currency' => (int)$cart->id_currency,
            'currencies'    => $currencies,
            'total'         => (float)$cart->getOrderTotal(true, Cart::BOTH),
            'use_taxes'     => (int) Configuration::get('PS_TAX'),
            'this_path'     => $this->module->getPathUri(),
            'this_path_bw'  => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
        ]);

        $this->setTemplate('payment_execution.tpl');
    }
}
