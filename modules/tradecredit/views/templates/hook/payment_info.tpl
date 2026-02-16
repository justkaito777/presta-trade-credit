{**
 * Payment option additional information template
 * Displayed on checkout when customer selects Trade Credit
 *}

<div class="trade-credit-info">
    <p>
        <strong>{l s='Dostępny kredyt kupiecki:' d='Modules.Tradecredit.Shop'}</strong>
        <span class="trade-credit-amount">{$available_credit} {$currency_sign}</span>
    </p>
    <p>
        <strong>{l s='Kwota zamówienia:' d='Modules.Tradecredit.Shop'}</strong>
        <span>{$cart_total} {$currency_sign}</span>
    </p>

    {if !$has_enough_credit}
        <div class="alert alert-danger" role="alert">
            <p>
                <i class="material-icons">warning</i>
                {l s='Niewystarczający kredyt kupiecki. Nie możesz złożyć zamówienia z tą metodą płatności.' d='Modules.Tradecredit.Shop'}
            </p>
        </div>
    {else}
        <div class="alert alert-success" role="alert">
            <p>
                <i class="material-icons">check_circle</i>
                {l s='Masz wystarczający kredyt kupiecki do opłacenia tego zamówienia.' d='Modules.Tradecredit.Shop'}
            </p>
        </div>
    {/if}
</div>

<style>
    .trade-credit-info {
        padding: 10px 0;
    }
    .trade-credit-info p {
        margin-bottom: 5px;
    }
    .trade-credit-amount {
        color: #4cbb6c;
        font-weight: bold;
        font-size: 1.1em;
    }
    .trade-credit-info .alert {
        margin-top: 10px;
    }
    .trade-credit-info .material-icons {
        vertical-align: middle;
        font-size: 18px;
        margin-right: 5px;
    }
</style>
