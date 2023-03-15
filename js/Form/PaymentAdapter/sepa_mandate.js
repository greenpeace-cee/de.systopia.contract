import { registerPaymentAdapter, updateCycleDayField } from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/sepa_mandate"];

class SEPA {
    async nextCollectionDate ({ cycle_day, start_date }) {
        if (!cycle_day) return "";
        if (!start_date) return "";

        return await CRM.api3("Contract", "next_contribution_date", {
            cycle_day,
            payment_adapter: "sepa_mandate",
            recurring_contribution_id: EXT_VARS.current_recurring,
            start_date,
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
        paymentPreviewContainer.find("span#frequency").text(EXT_VARS.frequencies[frequency]);

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
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
            start_date: startDate,
        });

        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }
}

registerPaymentAdapter("sepa_mandate", new SEPA());
