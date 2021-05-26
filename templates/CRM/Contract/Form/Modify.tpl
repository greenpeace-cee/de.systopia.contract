{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
| B. Endres (endres -at- systopia.de)                          |
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

    {* --- Payment fields --- *}

    <div class="crm-section form-field" id="payment_preview" data-payment-change="modify">
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

    {foreach from=$payment_adapter_fields key=pa_name item=_}
        {assign var="field_id" value="pa-$pa_name-cycle_day"}

        <div
            class="crm-section form-field"
            id="{$field_id}"
            data-payment-change="modify"
            data-payment-adapter="{$pa_name}"
        >
            <div class="label">{$form[$field_id].label}</div>

            <div class="content">
                {$form[$field_id].html}
                <span>currently: <b>{$current_cycle_day}</b></span>
            </div>

            <div class="clear"></div>
        </div>
    {/foreach}

    <div class="crm-section form-field" id="recurring_contribution" data-payment-change="select_existing">
        <div class="label">
            {$form.recurring_contribution.label}
            <span class="crm-marker" title="{ts}This field is required.{/ts}">*</span>
        </div>

        <div class="content">{$form.recurring_contribution.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="amount" data-payment-change="modify">
        <div class="label">{$form.amount.label}</div>
        <div class="content">{$form.amount.html} {$currency}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="frequency" data-payment-change="modify">
        <div class="label">{$form.frequency.label}</div>
        <div class="content">{$form.frequency.html}</div>
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

{literal}
    <script>
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
                "amount",
                "campaign_id",
                "frequency",
                "membership_type_id",
                "payment_change",
                "payment_adapter",
                ...paFieldIds,
            ];

            for (const fieldId of formFieldIds) {
                formFields[fieldId] = cj(`div.form-field div.content *[name=${fieldId}]`);

                if (fieldId === "payment_change" || fieldId === "payment_adapter") {
                    formFields[fieldId].change(() => {
                        setPaymentAdapter();
                        resetPaymentFields();
                        updateForm();
                    });
                } else {
                    formFields[fieldId].change(() => {
                        setPaymentAdapter();
                        updateForm();
                    });
                }
            }

            setPaymentAdapter();
            updateForm();
        }

        function resetPaymentFields () {
            const selectedPaymentChange = formFields["payment_change"].val();
            const selectedPaymentAdapter = formFields["payment_adapter"].val();
            const currentPaymentAdapter = CRM.vars["de.systopia.contract"].current_payment_adapter;

            if (selectedPaymentChange === "modify") {
                if (
                    selectedPaymentAdapter === currentPaymentAdapter
                    && PaymentAdapter.fillPaymentParameters
                ) {
                    // Fill in current payment parameters in case of same payment method
                    PaymentAdapter.fillPaymentParameters(formFields);
                } else if (PaymentAdapter.clearPaymentParameters) {
                    // Clear payment parameters in case of new payment method
                    PaymentAdapter.clearPaymentParameters(formFields);
                }
            }
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

        function updateForm () {
            // Show only fields relevant to the currently selected payment change mode / adapter
            const selectedPaymentChange = formFields["payment_change"].val();
            const selectedPaymentAdapter= formFields["payment_adapter"].val();

            cj("*[data-payment-change], *[data-payment-adapter]").each((_, element) => {
                const change =
                    element.hasAttribute("data-payment-change")
                    ? element.getAttribute("data-payment-change")
                    : undefined;

                const adapter =
                    element.hasAttribute("data-payment-adapter")
                    ? element.getAttribute("data-payment-adapter")
                    : undefined;

                if (change !== undefined && change !== selectedPaymentChange) {
                    cj(element).hide(300);
                    return;
                }

                if (adapter !== undefined && adapter !== selectedPaymentAdapter) {
                    cj(element).hide(300);
                    return;
                }

                cj(element).show(300);
            });

            // Update payment preview
            if (PaymentAdapter.updatePaymentPreview) {
                PaymentAdapter.updatePaymentPreview(formFields);
            }

            // Call update callbacks of payment adapters
            if (PaymentAdapter.onUpdate) {
                PaymentAdapter.onUpdate(formFields);
            }
        }

        cj(document).ready(initForm);
    </script>
{/literal}
