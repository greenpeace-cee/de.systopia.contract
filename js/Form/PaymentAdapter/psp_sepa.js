import {
    parseMoney,
    registerPaymentAdapter,
    updateCycleDayField,
    updateFrequencyField,
} from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/psp_sepa"];

class PSP {
    async confirmDialog (formFields) {
        const currency = EXT_VARS.default_currency;
        const amount = parseMoney(formFields["amount"].val());
        const frequency = parseInt(formFields["frequency"].val());
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const annualAmount = (amount * frequency).toFixed(2);
        const piField = formFields["pa-psp_sepa-payment_instrument"];
        const paymentInstrumentID = piField.val();
        const paymentInstrumentLabel = piField.find(`option[value=${paymentInstrumentID}]`).text();
        const creditorID = formFields["pa-psp_sepa-creditor"].val();
        const cycleDay = formFields["cycle_day"].val();
        const deferPaymentStart = formFields["defer_payment_start"]?.prop("checked");
        const startDate = (formFields["start_date"] || formFields["activity_date"]).val();

        const firstDebit = await this.nextCollectionDate({
            creditor_id: creditorID,
            cycle_day: cycleDay,
            defer_payment_start: deferPaymentStart,
            min_date: startDate,
        })

        return `
            <ul>
                <li>
                    We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b>
                    via <b>${paymentInstrumentLabel} (PSP)</b>
                </li>

                <li>The first debit is on <b>${firstDebit}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount}</b></li>
            </ul>
        `;
    }

    async nextCollectionDate ({ creditor_id, cycle_day, defer_payment_start, min_date }) {
        if (!creditor_id) return "";
        if (!cycle_day) return "";
        if (!min_date) return "";

        return await CRM.api3("Contract", "start_date", {
            creditor_id,
            cycle_day,
            defer_payment_start,
            membership_id: EXT_VARS.membership_id,
            min_date,
            payment_adapter: "psp_sepa",
            prev_recur_contrib_id: EXT_VARS.current_recurring,
        }).then(
            result => {
                if (result.is_error) console.error(result.error_message);
                return result?.values?.[0];
            },
            error => console.error(error.message),
        );
    }

    onFormChange (formFields) {
        // Creditor
        const selectedCreditor = formFields["pa-psp_sepa-creditor"].val();

        // Currency
        const currency = ADAPTER_VARS.currencies[selectedCreditor];
        cj("span#currency").text(currency);

        // Cycle days
        updateCycleDayField(formFields, ADAPTER_VARS.cycle_days[selectedCreditor] ?? [], EXT_VARS.current_cycle_day);

        // Payment frequencies
        updateFrequencyField(formFields, ADAPTER_VARS.payment_frequencies, EXT_VARS.current_frequency);

        // Payment instruments
        const paymentInstrumentField = formFields["pa-psp_sepa-payment_instrument"];
        const selectedPaymentInstrument = paymentInstrumentField.val();
        const paymentInstrumentOptions = ADAPTER_VARS.payment_instruments[selectedCreditor] || {};

        paymentInstrumentField.empty();
        paymentInstrumentField.append("<option value=\"\">- none -</option>");

        for (const [piValue, piLabel] of Object.entries(paymentInstrumentOptions)) {
            paymentInstrumentField.append(`<option value="${piValue}">${piLabel}</option>`);

            if (parseInt(selectedPaymentInstrument) === parseInt(piValue)) {
                paymentInstrumentField.val(piValue);
            }
        }

        // Payment preview
        this.updatePaymentPreview(formFields);
    }

    async updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=psp_sepa]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(EXT_VARS.debitor_name);

        // Creditor
        const pspCreditors = formFields["pa-psp_sepa-creditor"]
            .find("option")
            .map((_, opt) => ({ [opt.value]: opt.textContent }))
            .get()
            .reduce((result, item) => ({ ...result, ...item }), {});

        const creditorId = formFields["pa-psp_sepa-creditor"].val();
        paymentPreviewContainer.find("span#creditor").text(pspCreditors[creditorId]);

        // Payment instrument
        const paymentInstruments = formFields["pa-psp_sepa-payment_instrument"]
            .find("option")
            .get()
            .map(opt => ({ [opt.value]: opt.textContent }))
            .reduce((result, item) => ({ ...result, ...item }), {});

        const piId = formFields["pa-psp_sepa-payment_instrument"].val();
        paymentPreviewContainer.find("span#payment_instrument").text(paymentInstruments[piId]);

        // Account reference
        const accountReference = formFields["pa-psp_sepa-account_reference"].val();
        paymentPreviewContainer.find("span#account_reference").text(accountReference);

        // Account name
        const accountName = formFields["pa-psp_sepa-account_name"].val();
        paymentPreviewContainer.find("span#account_name").text(accountName);

        // Frequency
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(EXT_VARS.frequency_labels[frequency]);

        // Currency
        const currency = ADAPTER_VARS.currencies[creditorId] || EXT_VARS.default_currency;

        // Annual amount
        const amount = parseMoney(formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = formFields["cycle_day"].val() || "";
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);

        // Next debit
        const deferPaymentStart = formFields["defer_payment_start"]
            ? formFields["defer_payment_start"].prop("checked")
            : false;

        const startDate = formFields["start_date"]
            ? formFields["start_date"].val()
            : formFields["activity_date"].val();

        const nextDebit = await this.nextCollectionDate({
            creditor_id: creditorId,
            cycle_day: cycleDay,
            defer_payment_start: deferPaymentStart,
            min_date: startDate,
        });

        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }
}

registerPaymentAdapter("psp_sepa", new PSP());
