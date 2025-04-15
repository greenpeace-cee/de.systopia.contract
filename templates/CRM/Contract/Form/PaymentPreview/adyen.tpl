<div class="payment-preview" data-payment-adapter="adyen" style="display:none">
    <div id="debitor_name">Debitor name: <span class="value">{$contact.display_name}</span></div>
    <div id="payment_method">Payment method: <span class="value">Adyen</span></div>
    <div id="payment_instrument">Payment instrument: <span class="value"></span></div>
    <div id="installment">Installment amount: <span class="value"></span></div>
    <div id="frequency">Frequency: <span class="value"></span></div>
    <div id="annual">Annual amount: <span class="value"></span></div>
    <div id="cycle_day">Cycle day: <span class="value"></span></div>

    {if $parent_form == "Create"}
        <div id="first_regular_debit">First regular debit: <span class="value"></span></div>
    {else}
        <div id="next_sched_contribution_date">Next scheduled debit: <span class="value">{$next_sched_contribution_date}</span></div>
        <div id="first_debit_after_update">First debit after update: <span class="value"></span></div>
    {/if}
</div>
