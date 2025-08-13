{**
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
 *}
<div class="alert alert-info">
  <img
    src="{$module_dir|escape:'html':'UTF-8'}bankwire.svg"
    style="float:left; margin-right:15px;"
    width="77"
    height="45"
    alt="{l s='Bank wire' mod='bankwire'}"
  >

  <p>
    <strong>{l s='Accept bank transfers for customer orders:' mod='bankwire'}</strong>
    {l s='when a customer selects bank wire at checkout, the order status is set to Waiting for payment until you confirm receipt of funds.' mod='bankwire'}
    {l s='After you validate the transfer, you must manually change the order status and complete the order.' mod='bankwire'}
  </p>

  <div style="clear:both;"></div>
  <hr>

  <p><strong>{l s='Additional configuration required:' mod='bankwire'}</strong></p>

  <ol style="margin-top:8px;">
    <li>
      <strong>{l s='In Modules and Services > Payment:' mod='bankwire'}</strong>
      {l s='enable this payment method for the currencies, customer groups, countries, and carriers that should use it at checkout.' mod='bankwire'}
      {if $adminPaymentUrl}
        <a href="{$adminPaymentUrl|escape:'html':'UTF-8'}">{l s='Open menu.' mod='bankwire'}</a>
      {/if}

      <div class="alert alert-warning" style="margin-top:10px;">
        <strong>{l s='Note:' mod='bankwire'}</strong>
        {l s='If a currency is used in multiple countries, for example the Euro, enabling that currency alone is not sufficient. You must also enable each target country explicitly.' mod='bankwire'}
      </div>
    </li>

    <li>
      <strong>{l s='Add bank accounts in the module:' mod='bankwire'}</strong>
      <ul style="margin-top:8px;">
        <li>
          <strong>{l s='Currency:' mod='bankwire'}</strong>
          {l s='choose desired currency or ALL if you want to accept all transfers in one account and let your bank do the conversion.' mod='bankwire'}
          {if !$multistoreActive}
            <strong>{l s='There can be only one dedicated bank account per currency.' mod='bankwire'}</strong>
          {/if}
        </li>
        <li><strong>{l s='Account owner:' mod='bankwire'}</strong> {l s='your company name.' mod='bankwire'}</li>
        <li><strong>{l s='Details:' mod='bankwire'}</strong> {l s='all account details needed for the transfer, for example IBAN, BIC/SWIFT, etc.' mod='bankwire'}</li>
        <li><strong>{l s='Bank address:' mod='bankwire'}</strong> {l s='your bank address' mod='bankwire'}</li>
        {if $multistoreActive}
          <li>
            <strong>{l s='Shop association:' mod='bankwire'}</strong>
            {l s='choose per shop or per shop group restrictions for this bank account.' mod='bankwire'}
            <strong>{l s='There can be only one dedicated bank account per currency per shop.' mod='bankwire'}</strong>
          </li>
        {/if}
      </ul>
    </li>
  </ol>

  <p><strong>{l s='Typical configurations:' mod='bankwire'}</strong></p>
  <ul>
    <li>
      <strong>{l s='Single bank account for every currency:' mod='bankwire'}</strong>
      {l s='create one All currencies account. Useful if you have only one bank account. The bank will convert incoming transfers.' mod='bankwire'}
    </li>
    <li>
      <strong>{l s='Dedicated accounts per currency:' mod='bankwire'}</strong>
      {l s='create a dedicated account for each currency you accept if you have separate bank accounts in each currency (no conversion penalty receiving the money).' mod='bankwire'}
    </li>
    <li>
      <strong>{l s='Mixed setup:' mod='bankwire'}</strong>
      {l s='create accounts for the currencies you have dedicated bank accounts for and one All currencies account as a fallback. Orders in other FO-enabled currencies will use the All currencies account (and its bank conversion), so you do not lose orders.' mod='bankwire'}
    </li>
  </ul>
</div>

