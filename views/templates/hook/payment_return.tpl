{**
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
 *}

{if $status == 'ok'}
  <p>{l s='Your order on %s is complete.' sprintf=$shop_name mod='bankwire'}</p>
  <p>{l s='Please send us a bank wire with:' mod='bankwire'}</p>
  
  <ul>
    <li>{l s='Amount:' mod='bankwire'} <strong class="price">{$total_to_pay}</strong></li>
    <li>{l s='Name of account owner:' mod='bankwire'} 
      <strong>{$bankwireOwner|escape:'htmlall':'UTF-8'}</strong>
    </li>
    <li>{l s='Bank details:' mod='bankwire'} 
      <strong>{$bankwireDetails|escape:'htmlall':'UTF-8'}</strong>
    </li>
    <li>{l s='Bank name and address:' mod='bankwire'} 
      <strong>{$bankwireAddress|escape:'htmlall':'UTF-8'}</strong>
    </li>
  </ul>

  {if isset($reference)}
    <p>- {l s='Do not forget to insert your order reference %s in the subject of your bank wire.' sprintf=$reference mod='bankwire'}</p>
  {else}
    <p>- {l s='Do not forget to insert your order number #%d in the subject of your bank wire.' sprintf=$id_order mod='bankwire'}</p>
  {/if}

  <p>{l s='An email has been sent with this information.' mod='bankwire'}</p>
  <p><strong>{l s='Your order will be sent as soon as we receive payment.' mod='bankwire'}</strong></p>
  <p>{l s='If you have questions, comments, or concerns, please contact our' mod='bankwire'} 
    <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='expert customer support team' mod='bankwire'}</a>.
  </p>

{else}
  <p class="warning">
    {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our' mod='bankwire'} 
    <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='expert customer support team' mod='bankwire'}</a>.
  </p>
{/if}
