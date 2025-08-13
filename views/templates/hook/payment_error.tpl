{* bankwire - payment error template *}
<p class="warning">
  {if isset($bankwireError)}{$bankwireError|escape:'htmlall':'UTF-8'}{else}{l s='Bank wire is not available for the selected currency.' mod='bankwire'}{/if}
</p>
