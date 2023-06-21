{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

{include file="CRM/Contract/Form/FormUtils.tpl"}

{literal}

<script>
    (async () => {
        const EXT_VARS = CRM.vars["de.systopia.contract"];
        const extBaseURL = EXT_VARS.ext_base_url;
        const paymentAdapters = Object.keys(EXT_VARS.payment_adapters);

        await Promise.all(paymentAdapters.map(
            adapter => import(`${extBaseURL}/js/Form/PaymentAdapter/${adapter}.js`)
        ));

        const { initForm } = await import(`${extBaseURL}/js/Form/create.js`);

        initForm();
    })();
</script>

{/literal}

<div class="crm-block crm-form-block">
    <div class="crm-section form-field" id="payment_preview" data-payment-option="create">
        <div class="label">
            <label>Payment preview</label>
        </div>

        <div class="content">
            {foreach from=$payment_adapter_fields key=pa_name item=_}
                {include file="CRM/Contract/Form/PaymentPreview/$pa_name.tpl"}
            {/foreach}
        </div>

        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="payment_option">
        <div class="label">{$form.payment_option.label}</div>
        <div class="content">{$form.payment_option.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="payment_adapter" data-payment-option="create">
        <div class="label">{$form.payment_adapter.label}</div>
        <div class="content">{$form.payment_adapter.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="existing_recurring_contribution" data-payment-option="select">
        <div class="label">
            {$form.existing_recurring_contribution.label}
            <span class="crm-marker" title="{ts}This field is required.{/ts}">*</span>
        </div>

        <div class="content">{$form.existing_recurring_contribution.html}</div>
        <div class="clear"></div>
    </div>

    {foreach from=$payment_adapter_fields key=pa_name item=field_ids}
        {foreach from=$field_ids item=field_id}
            <div
                class="crm-section form-field"
                id="{$field_id}"
                data-payment-option="create"
                data-payment-adapter="{$pa_name}"
            >
                <div class="label">{$form[$field_id].label}</div>
                <div class="content">{$form[$field_id].html}</div>
                <div class="clear"></div>
            </div>
        {/foreach}
    {/foreach}

    <hr />

    <div class="crm-section form-field" id="cycle_day" data-payment-option="create">
        <div class="label">{$form.cycle_day.label}</div>
        <div class="content">{$form.cycle_day.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="amount" data-payment-option="create">
        <div class="label">{$form.amount.label}</div>
        <div class="content">{$form.amount.html} <span id="currency">{$currency}</span></div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="frequency" data-payment-option="create">
        <div class="label">{$form.frequency.label}</div>
        <div class="content">{$form.frequency.html}</div>
        <div class="clear"></div>
    </div>

    <hr data-payment-option="create" />

    <div class="crm-section form-field" id="join_date">
        <div class="label">{$form.join_date.label}</div>
        <div class="content">{$form.join_date.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="start_date">
        <div class="label">{$form.start_date.label}</div>
        <div class="content">{$form.start_date.html}</div>
        <div class="clear"></div>
    </div>

    <hr />

    <div class="crm-section form-field" id="campaign_id">
        <div class="label">{$form.campaign_id.label}</div>
        <div class="content">{$form.campaign_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_type_id">
        <div class="label">{$form.membership_type_id.label}</div>
        <div class="content">{$form.membership_type_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="activity_medium">
        <div class="label">{$form.activity_medium.label}</div>
        <div class="content">{$form.activity_medium.html}</div>
        <div class="clear"></div>
    </div>

    <hr />

    <div class="crm-section form-field" id="membership_reference">
        <div class="label">{$form.membership_reference.label}</div>
        <div class="content">{$form.membership_reference.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_contract">
        <div class="label">{$form.membership_contract.label}</div>
        <div class="content">{$form.membership_contract.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_dialoger">
        <div class="label">{$form.membership_dialoger.label}</div>
        <div class="content">{$form.membership_dialoger.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_channel">
        <div class="label">{$form.membership_channel.label}</div>
        <div class="content">{$form.membership_channel.html}</div>
        <div class="clear"></div>
    </div>

    <hr />

    <div class="crm-section form-field" id="activity_details">
        <div class="label">{$form.activity_details.label}</div>
        <div class="content">{$form.activity_details.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
