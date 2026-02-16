{**
 * Payment return template
 * Displayed on order confirmation page
 *}

<div class="trade-credit-confirmation">
    <p>
        {l s='Twoje zamówienie zostało opłacone kredytem kupieckim.' d='Modules.Tradecredit.Shop'}
    </p>
    <p>
        <strong>{l s='Pozostały kredyt kupiecki:' d='Modules.Tradecredit.Shop'}</strong>
        <span class="trade-credit-remaining">{$available_credit} {$currency_sign}</span>
    </p>
</div>

<style>
    .trade-credit-confirmation {
        padding: 10px 0;
    }
    .trade-credit-remaining {
        color: #4cbb6c;
        font-weight: bold;
        font-size: 1.1em;
    }
</style>
