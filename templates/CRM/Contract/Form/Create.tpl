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
    window.PaymentAdapters = {};
</script>

{/literal}

{foreach from=$payment_adapter_fields key=pa_name item=_}
    {include file="CRM/Contract/Form/PaymentAdapters/$pa_name.tpl"}
{/foreach}

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

    {foreach from=$payment_adapter_fields key=pa_name item=_}
        {assign var="field_id" value="pa-$pa_name-cycle_day"}

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

    <div class="crm-section form-field" id="amount" data-payment-option="create">
        <div class="label">{$form.amount.label}</div>
        <div class="content">{$form.amount.html} {$currency}</div>
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

    <div class="crm-section form-field" id="end_date">
        <div class="label">{$form.end_date.label}</div>
        <div class="content">{$form.end_date.html}</div>
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

{literal}

<script type="text/javascript">

    const formFields = {};
    let PaymentAdapter = {};

    function initForm () {
        const paymentAdapterFields = {/literal}{$payment_adapter_fields_json}{literal};

        const paFieldIds = Object.entries(paymentAdapterFields).reduce(
            (result, [pa, ids]) => [ ...result, ...ids, `pa-${pa}-cycle_day` ],
            []
        );

        const formFieldIds = [
            "activity_date",
            "activity_details",
            "activity_medium",
            "amount",
            "campaign_id",
            "end_date",
            "existing_recurring_contribution",
            "frequency",
            "join_date",
            "membership_channel",
            "membership_contract",
            "membership_dialoger",
            "membership_reference",
            "membership_type_id",
            "payment_option",
            "payment_adapter",
            "start_date",
            ...paFieldIds,
        ];

        for (const fieldId of formFieldIds) {
            formFields[fieldId] = cj(`div.form-field div.content *[name=${fieldId}]`);

            formFields[fieldId].change(() => {
                setPaymentAdapter();
                showHideFormFields();
                updatePaymentPreview();
            });
        }

        setPaymentAdapter();
        showHideFormFields();
        updatePaymentPreview();
    }

    function setPaymentAdapter () {
        const selectedPaymentAdapter = formFields["payment_adapter"].val();

        if (
            window.PaymentAdapters
            && window.PaymentAdapters[selectedPaymentAdapter]
        ) {
            PaymentAdapter = window.PaymentAdapters[selectedPaymentAdapter];
        }
    }

    function showHideFormFields () {
        // Show only fields relevant to the currently selected payment option / adapter
        const selectedPaymentOption = formFields["payment_option"].val();
        const selectedPaymentAdapter = formFields["payment_adapter"].val();

        cj("*[data-payment-option], *[data-payment-adapter]").each((_, element) => {
            const option =
                element.hasAttribute("data-payment-option")
                ? element.getAttribute("data-payment-option")
                : undefined;

            const adapter =
                element.hasAttribute("data-payment-adapter")
                ? element.getAttribute("data-payment-adapter")
                : undefined;

            if (option !== undefined && option !== selectedPaymentOption) {
                cj(element).hide(300);
                return;
            }

            if (adapter !== undefined && adapter !== selectedPaymentAdapter) {
                cj(element).hide(300);
                return;
            }

            cj(element).show(300);
        });
    }

    function updatePaymentPreview () {
        if (PaymentAdapter.updatePaymentPreview) {
            PaymentAdapter.updatePaymentPreview(formFields);
        }
    }

    cj(document).ready(initForm);
</script>

{/literal}
