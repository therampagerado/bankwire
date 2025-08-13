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
        try {
            parent::initContent();
        } catch (PrestaShopException $e) {
        }

        $cart = $this->context->cart;
        try {
            $currencies = [];
            foreach (BankwireAccount::getCurrenciesByShop((int) $cart->id_shop) as $row) {
                $currencies[] = Currency::getCurrencyInstance($row['id_currency']);
            }

            if (!BankwireAccount::getByCurrency($cart->id_currency, $cart->id_shop)) {
                Tools::redirect('index.php?controller=order&step=3');
            }

            $this->context->smarty->assign(
                [
                    'nbProducts'    => $cart->nbProducts(),
                    'cust_currency' => $cart->id_currency,
                    'currencies'    => $currencies,
                    'total'         => $cart->getOrderTotal(true, Cart::BOTH),
                    'this_path'     => $this->module->getPathUri(),
                    'this_path_bw'  => $this->module->getPathUri(),
                    'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
                ]
            );

            $this->setTemplate('payment_execution.tpl');
        } catch (Exception $e) {
            Logger::addLog("Bankwire module error: {$e->getMessage()}");
        }
    }
}
