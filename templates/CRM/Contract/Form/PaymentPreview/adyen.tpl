<div class="payment-preview" data-payment-adapter="adyen" style="display:none">
    Debitor name: <span id="debitor_name">{$contact.display_name}</span><br />
    Payment method: <span id="payment_method">Adyen</span><br />
    Payment instrument: <span id="payment_instrument"></span><br />
    Installment amount: <span id="installment"></span><br />
    Frequency: <span id="frequency"></span><br />
    Annual amount: <span id="annual"></span><br />
    Cycle day: <span id="cycle_day"></span><br />

    {if $parent_form == "create"}
        First regular debit: <span id="first_regular_debit"></span><br />
    {else}
        First debit after update: <span id="first_debit_after_update"></span><br />
    {/if}
</div>
