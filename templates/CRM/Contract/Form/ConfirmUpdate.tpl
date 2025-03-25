<div class="crm-block crm-form-block">
    <ul style="margin:20px;padding:0px">
        <li>We will debit <b>{$currency} {$amount} {$frequency}</b> via <b>{$payment_instrument}</b></li>
        <li>The first debit after the update is on <b>{$first_debit_after_update}</b></li>
        <li>The total annual amount will be <b>{$currency} {$annual_amount}</b></li>
    </ul>

    <p>
        The update will be applied on <b>{$activity_date}</b> but the next scheduled
        debit is on <b>{$next_sched_contribution_date}</b>. Would you like to pause the
        contract until the update?
    </p>

    <div class="crm-section" id="pause_until_update">
        <div class="content" style="margin:0px">{$form.pause_until_update.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
