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

/**
 * @since 1.5.0
 */
class BankwireValidationModuleFrontController extends ModuleFrontController
{
    /** @var BankWire $module */
    public $module;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;

        if (!Validate::isLoadedObject($cart) ||
            $cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Ensure the payment method is still available
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'bankwire') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $currencyId = (int) $currency->id;
        $langId = (int) $this->context->language->id;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $idShop = (int) $this->context->shop->id;

        // Fetch the bank account for the current currency, language, and shop
        $bankAccount = Db::getInstance()->getRow(
            'SELECT bl.account_holder, bl.account_details, bl.bank_address
             FROM `' . _DB_PREFIX_ . 'bankwire` b
             LEFT JOIN `' . _DB_PREFIX_ . 'bankwire_lang` bl 
             ON b.id_bankwire = bl.id_bankwire 
             WHERE b.currency_id = ' . (int) $currencyId . '
             AND bl.id_lang = ' . (int) $langId . '
             AND b.id_shop = ' . (int) $idShop
        );

        if (!$bankAccount) {
            die($this->module->l('No bank account is available for this currency and shop.', 'validation'));
        }

        $extra_mail_vars = [
            '{bankwire_owner}'   => pSQL($bankAccount['account_holder']),
            '{bankwire_details}' => nl2br(pSQL($bankAccount['account_details'])),
            '{bankwire_address}' => nl2br(pSQL($bankAccount['bank_address'])),
        ];

        // Validate the order
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_BANKWIRE'),
            $total,
            $this->module->displayName,
            null,
            $extra_mail_vars,
            (int) $currencyId,
            false,
            $cart->secure_key
        );

        // Redirect to order confirmation page
        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart='.$cart->id.
            '&id_module='.$this->module->id.
            '&id_order='.$this->module->currentOrder.
            '&key='.$customer->secure_key
        );
    }
}
