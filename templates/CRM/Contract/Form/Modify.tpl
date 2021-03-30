{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
| B. Endres (endres -at- systopia.de)                          |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

{foreach from=$payment_instrument_fields key=pi_name item=_}
    {include file="CRM/Contract/Form/PaymentInstrumentUtils/$pi_name.tpl"}
{/foreach}

<div class="crm-block crm-form-block">

    {* --- Payment fields --- *}

    <div class="crm-section form-field" id="payment_preview" data-payment-change="modify">
        <div class="label">
            <label>Payment preview</label>
        </div>

        <div class="content">
            {foreach from=$payment_instrument_fields key=pi_name item=_}
                {include file="CRM/Contract/Form/PaymentPreview/$pi_name.tpl"}
            {/foreach}
        </div>

        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="payment_change">
        <div class="label">{$form.payment_change.label}</div>
        <div class="content">{$form.payment_change.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="payment_instrument" data-payment-change="modify">
        <div class="label">{$form.payment_instrument.label}</div>
        <div class="content">{$form.payment_instrument.html}</div>
        <div class="clear"></div>
    </div>

    <hr />

    {foreach from=$payment_instrument_fields key=pi_name item=field_ids}
        {foreach from=$field_ids item=field_id}
            <div
                class="crm-section form-field"
                id="{$field_id}"
                data-payment-change="modify"
                data-payment-instrument="{$pi_name}"
            >
                <div class="label">{$form[$field_id].label}</div>
                <div class="content">{$form[$field_id].html}</div>
                <div class="clear"></div>
            </div>
        {/foreach}
    {/foreach}

    <hr data-payment-change="modify"/>

    {foreach from=$payment_instrument_fields key=pi_name item=_}
        {assign var="field_id" value="pi-$pi_name-cycle_day"}

        <div
            class="crm-section form-field"
            id="{$field_id}"
            data-payment-change="modify"
            data-payment-instrument="{$pi_name}"
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
        let PaymentInstrument = {};

        function initForm () {
            const paymentInstrumentFields = {/literal}{$payment_instrument_fields_json}{literal};

            const piFieldIds = Object.entries(paymentInstrumentFields).reduce(
                (result, [pi, ids]) => [ ...result, ...ids, `pi-${pi}-cycle_day` ],
                []
            );

            const formFieldIds = [
                "activity_date",
                "amount",
                "campaign_id",
                "frequency",
                "membership_type_id",
                "payment_change",
                "payment_instrument",
                ...piFieldIds,
            ];

            for (const fieldId of formFieldIds) {
                formFields[fieldId] = cj(`div.form-field div.content *[name=${fieldId}]`);

                if (fieldId === "payment_change" || fieldId === "payment_instrument") {
                    // Fill / clear payment fields when the value of payment_change changes
                    formFields["payment_change"].change(() => {
                        setPaymentInstrument();
                        showHideFormFields();
                        resetPaymentFields();
                        updatePaymentPreview();
                    });
                } else {
                    formFields[fieldId].change(() => {
                        setPaymentInstrument();
                        showHideFormFields();
                        updatePaymentPreview();
                    });
                }
            }

            setPaymentInstrument();
            showHideFormFields();
            updatePaymentPreview();
        }

        function resetPaymentFields () {
            const selectedPaymentChange = formFields["payment_change"].val();
            const selectedPaymentInstrument = formFields["payment_instrument"].val();
            const currentPaymentInstrument = CRM.vars["de.systopia.contract"].current_payment_instrument;

            if (selectedPaymentChange === "modify") {
                if (
                    selectedPaymentInstrument === currentPaymentInstrument
                    && PaymentInstrument.fillPaymentParameters
                ) {
                    // Fill in current payment parameters in case of same payment method
                    PaymentInstrument.fillPaymentParameters(formFields);
                } else if (PaymentInstrument.clearPaymentParameters) {
                    // Clear payment parameters in case of new payment method
                    PaymentInstrument.clearPaymentParameters(formFields);
                }
            }
        }

        function setPaymentInstrument () {
            const selectedPaymentInstrument = formFields["payment_instrument"].val();

            if (
                window._PAYMENT_INSTRUMENTS_
                && window._PAYMENT_INSTRUMENTS_[selectedPaymentInstrument]
            ) {
                PaymentInstrument = window._PAYMENT_INSTRUMENTS_[selectedPaymentInstrument];
            }
        }

        function showHideFormFields () {
            // Show only fields relevant to the currently selected payment change mode / instrument
            const selectedPaymentChange = formFields["payment_change"].val();
            const selectedPaymentInstrument = formFields["payment_instrument"].val();

            cj("*[data-payment-change], *[data-payment-instrument]").each((_, element) => {
                const change =
                    element.hasAttribute("data-payment-change")
                    ? element.getAttribute("data-payment-change")
                    : undefined;

                const instrument =
                    element.hasAttribute("data-payment-instrument")
                    ? element.getAttribute("data-payment-instrument")
                    : undefined;

                if (change !== undefined && change !== selectedPaymentChange) {
                    cj(element).hide(300);
                    return;
                }

                if (instrument !== undefined && instrument !== selectedPaymentInstrument) {
                    cj(element).hide(300);
                    return;
                }

                cj(element).show(300);
            });
        }

        function updatePaymentPreview () {
            if (PaymentInstrument.updatePaymentPreview) {
                PaymentInstrument.updatePaymentPreview(formFields);
            }
        }

        cj(document).ready(initForm);
    </script>
{/literal}
