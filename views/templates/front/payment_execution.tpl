{**
 * Copyright (C) 2017-2025 thirty bees
 * Copyright (C) 2007-2016 Prestashop SA
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
 * Prestashop is an internationally registered trademark of PrestaShop SA.
 *}

{capture name=path}
  <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='bankwire'}">{l s='Checkout' mod='bankwire'}</a>
  <span class="navigation-pipe">{$navigationPipe}</span>{l s='Bank-wire payment' mod='bankwire'}
{/capture}

{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='bankwire'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
  <p class="warning">{l s='Your shopping cart is empty.' mod='bankwire'}</p>
{else}
  <h3>{l s='Bank-wire payment' mod='bankwire'}</h3>
  <form action="{$link->getModuleLink('bankwire', 'validation', [], true)|escape:'html'}" method="post">
    <p>
      <img src="{$this_path_bw}bankwire.jpg" alt="{l s='Bank wire' mod='bankwire'}" width="86" height="49" style="float:left; margin: 0px 10px 5px 0px;"/>
      {l s='You have chosen to pay by bank wire.' mod='bankwire'}
      <br/><br/>
      {l s='Here is a short summary of your order:' mod='bankwire'}
    </p>
    <p style="margin-top:20px;">
      - {l s='The total amount of your order is' mod='bankwire'}
      <span id="amount" class="price">{displayPrice price=$total}</span>
      {if $use_taxes == 1}
        {l s='(tax incl.)' mod='bankwire'}
      {/if}
    </p>

    {* Ensure only bank accounts for the selected language are displayed *}
    {if isset($bank_accounts) && $bank_accounts|@count > 0}
      <p>
        {l s='Please choose a bank account for the transfer:' mod='bankwire'}
        <br/>
        <select id="bank_account" name="bank_account">
          {foreach from=$bank_accounts item=account}
            {if $account.id_lang == $cust_lang && $account.id_shop == $shop_id}
              <option value="{$account.id_bankwire}">
                {$account.account_holder|escape:'html'} - {$account.bank_address|escape:'html'}
              </option>
            {/if}
          {/foreach}
        </select>
      </p>

      {* Show bank account details dynamically *}
      <div id="bank_account_details">
        {foreach from=$bank_accounts item=account}
          {if $account.id_lang == $cust_lang && $account.id_shop == $shop_id}
            <p>
              <strong>{l s='Account Holder' mod='bankwire'}:</strong> <span id="account_holder">{$account.account_holder|escape:'html'}</span><br/>
              <strong>{l s='Bank Address' mod='bankwire'}:</strong> <span id="bank_address">{$account.bank_address|escape:'html'}</span><br/>
              <strong>{l s='Account Details' mod='bankwire'}:</strong> <span id="account_details">{$account.account_details|escape:'html'}</span><br/>
            </p>
            {break}
          {/if}
        {/foreach}
      </div>

      {* JavaScript to update bank details dynamically when selecting an account *}
      <script>
        document.addEventListener("DOMContentLoaded", function() {
            var select = document.getElementById("bank_account");
            select.addEventListener("change", function() {
                var selectedId = this.value;
                var accounts = {$bank_accounts|json_encode nofilter};

                accounts.forEach(function(account) {
                    if (account.id_bankwire == selectedId && account.id_lang == {$cust_lang}) {
                        document.getElementById("account_holder").textContent = account.account_holder;
                        document.getElementById("bank_address").textContent = account.bank_address;
                        document.getElementById("account_details").textContent = account.account_details;
                    }
                });
            });
        });
      </script>
    {else}
      <p class="warning">{l s='No bank account is available for the selected currency and language. Please choose another payment method.' mod='bankwire'}</p>
    {/if}

    {if isset($bank_accounts) && $bank_accounts|@count > 0}
      <p>
        {l s='Bank wire account information will be displayed on the next page.' mod='bankwire'}
        <br/><br/>
        <b>{l s='Please confirm your order by clicking "I confirm my order".' mod='bankwire'}</b>
      </p>

      <p class="cart_navigation" id="cart_navigation">
        <input type="submit" value="{l s='I confirm my order' mod='bankwire'}" class="exclusive_large"/>
        <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}" class="button_large">{l s='Other payment methods' mod='bankwire'}</a>
      </p>
    {/if}
  </form>
{/if}
