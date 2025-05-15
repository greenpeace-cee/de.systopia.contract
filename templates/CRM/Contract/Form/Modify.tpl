{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
| B. Endres (endres -at- systopia.de)                          |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

{literal}

<script>
    const EXT_VARS = CRM.vars["de.systopia.contract"];

    import(`${EXT_VARS.ext_base_url}/js/Form/modify.js`).then(({ initForm }) => initForm());
</script>

{/literal}

<div class="crm-block crm-form-block">

    {* --- Payment fields --- *}

    <div class="crm-section form-field" id="payment_preview" data-payment-change="modify">
        <div class="label">
            <label>Payment preview</label>
        </div>

        <div class="content">
            {foreach from=$payment_adapter_fields key=pa_name item=_}
                {include file="CRM/Contract/Form/PaymentPreview/$pa_name.tpl" parent_form="Modify"}
            {/foreach}
        </div>

        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="payment_change">
        <div class="label">{$form.payment_change.label}</div>
        <div class="content">{$form.payment_change.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="payment_adapter" data-payment-change="modify">
        <div class="label">{$form.payment_adapter.label}</div>
        <div class="content">{$form.payment_adapter.html}</div>
        <div class="clear"></div>
    </div>

    {foreach from=$payment_adapter_fields key=pa_name item=field_ids}
        {foreach from=$field_ids item=field_id}
            <div
                class="crm-section form-field"
                id="{$field_id}"
                data-payment-change="modify"
                data-payment-adapter="{$pa_name}"
            >
                <div class="label">{$form[$field_id].label}</div>
                <div class="content">{$form[$field_id].html}</div>
                <div class="clear"></div>
            </div>
        {/foreach}
    {/foreach}

    <hr />

    <div class="crm-section form-field" id="recurring_contribution" data-payment-change="select_existing">
        <div class="label">
            {$form.recurring_contribution.label}
            <span class="crm-marker" title="{ts}This field is required.{/ts}">*</span>
        </div>

        <div class="content">{$form.recurring_contribution.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="cycle_day" data-payment-change="modify">
        <div class="label">{$form.cycle_day.label}</div>

        <div class="content">
            {$form.cycle_day.html}
            <span>currently: <b>{$current_cycle_day}</b></span>
        </div>

        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="amount" data-payment-change="modify">
        <div class="label">{$form.amount.label}</div>
        <div class="content">{$form.amount.html} <span id="currency">{$currency}</span></div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="frequency" data-payment-change="modify">
        <div class="label">{$form.frequency.label}</div>
        <div class="content">{$form.frequency.html}</div>
        <div class="clear"></div>
    </div>

    <div
        class="crm-section form-field"
        id="defer_payment_start"
        data-payment-change="modify"
        data-payment-adapter="adyen sepa_mandate"
    >
        <div class="label">
          {$form.defer_payment_start.label}
          {help id="defer_payment_start" file="CRM/Contract/Form/DeferPaymentStart.hlp"}
        </div>
        <div class="content">{$form.defer_payment_start.html}</div>
        <div class="clear"></div>
    </div>

    <hr data-payment-change="modify"/>

    {* --- Membership/campaign fields --- *}

    <div class="crm-section form-field" id="membership_type_id">
        <div class="label">{$form.membership_type_id.label}</div>
        <div class="content">{$form.membership_type_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="campaign_id">
        <div class="label">{$form.campaign_id.label}</div>
        <div class="content">{$form.campaign_id.html}</div>
        <div class="clear"></div>
    </div>

    <hr />

    {* --- Activity fields --- *}

    <div class="crm-section form-field" id="activity_date">
        <div id="debit_before_update" class="messages warning" style="display:none">
            This update will be applied <b>after</b> the next regular debit
            on <b>{$next_sched_contribution_date}</b>
        </div>

        <div class="label">
            {$form.activity_date.label}
            {help id="scheduling" file="CRM/Contract/Form/Scheduling.hlp"}
        </div>

        <div class="content">{$form.activity_date.html}</div>
        <div class="clear"></div>

        {if $default_to_minimum_change_date}
            <span class="content field-info">Default to minimum change date</span>
        {/if}
    </div>

    <div class="crm-section form-field" id="medium_id">
        <div class="label">{$form.medium_id.label}</div>
        <div class="content">{$form.medium_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="activity_details">
        <div class="label">{$form.activity_details.label}</div>
        <div class="content">{$form.activity_details.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
