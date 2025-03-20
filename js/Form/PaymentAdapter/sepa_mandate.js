import {
    parseMoney,
    registerPaymentAdapter,
    updateCycleDayField,
    updateFrequencyField,
} from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/sepa_mandate"];

class SEPA {
    async confirmDialog (formFields) {
        const currency = EXT_VARS.default_currency;
        const amount = parseMoney(formFields["amount"].val());
        const frequency = parseInt(formFields["frequency"].val());
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const annualAmount = (amount * frequency).toFixed(2);
        const cycleDay = formFields["cycle_day"].val();
        const deferPaymentStart = formFields["defer_payment_start"]?.prop("checked");
        const startDate = (formFields["start_date"] || formFields["activity_date"]).val();

        const firstDebit = await this.nextCollectionDate({
            cycle_day: cycleDay,
            defer_payment_start: deferPaymentStart,
            min_date: startDate,
        })

        return `
            <ul>
                <li>
                    We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b>
                    via <b>SEPA Direct Debit</b>
                </li>

                <li>The first debit is on <b>${firstDebit}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount}</b></li>
            </ul>
        `;
    }

    async nextCollectionDate ({ cycle_day, defer_payment_start, min_date }) {
        if (!cycle_day) return "";
        if (!min_date) return "";

        return await CRM.api3("Contract", "start_date", {
            cycle_day,
            defer_payment_start,
            membership_id: EXT_VARS.membership_id,
            min_date,
            payment_adapter: "sepa_mandate",
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
        // Currency
        cj("span#currency").text(ADAPTER_VARS.default_currency);

        // Cycle days
        updateCycleDayField(formFields, ADAPTER_VARS.cycle_days, EXT_VARS.current_cycle_day);

        // Payment frequencies
        updateFrequencyField(formFields, ADAPTER_VARS.payment_frequencies, EXT_VARS.current_frequency);

        // Payment preview
        this.updatePaymentPreview(formFields);
    }

    async updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj(
            "div.payment-preview[data-payment-adapter=sepa_mandate]"
        );

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(EXT_VARS.debitor_name);

        // Debitor account
        const iban = formFields["pa-sepa_mandate-iban"].val();
        paymentPreviewContainer.find("span#iban").text(iban);

        // Creditor name
        const creditor = ADAPTER_VARS.creditor;
        paymentPreviewContainer.find("span#creditor_name").text(creditor.name);

        // Creditor account
        paymentPreviewContainer.find("span#creditor_iban").text(creditor.iban);

        // Frequency
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(EXT_VARS.frequency_labels[frequency]);

        // Annual amount
        const amount = parseMoney(formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${creditor.currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${creditor.currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Next debit
        const deferPaymentStart = formFields["defer_payment_start"]
            ? formFields["defer_payment_start"].prop("checked")
            : false;

        const cycleDay = formFields["cycle_day"].val();

        const startDate = formFields["start_date"]
            ? formFields["start_date"].val()
            : formFields["activity_date"].val();

        const nextDebit = await this.nextCollectionDate({
            cycle_day: cycleDay,
            defer_payment_start: deferPaymentStart,
            min_date: startDate,
        });

        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }
}

registerPaymentAdapter("sepa_mandate", new SEPA());
