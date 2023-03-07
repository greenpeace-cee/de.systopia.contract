import { registerPaymentAdapter } from "../utils.js";

class SEPA {
    constructor() {
        const extVars = CRM.vars["de.systopia.contract"];
        const adapterVars = CRM.vars["de.systopia.contract/sepa_mandate"];

        this.action = extVars.action;
        this.creditor = adapterVars.creditor;
        this.currentCycleDay = extVars.current_cycle_day;
        this.cycleDays = adapterVars.cycle_days;
        this.debitorName = extVars.debitor_name;
        this.defaultCurrency = adapterVars.default_currency;
        this.frequencies = extVars.frequencies;
        this.graceEnd = extVars.grace_end;
        this.nextCycleDay = adapterVars.next_cycle_day;
    }

    async nextCollectionDate ({ cycle_day = null, start_date = null }) {
        return await CRM.api3("Contract", "next_contribution_date", {
            cycle_day,
            payment_adapter: "sepa_mandate",
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
        cj("span#currency").text(this.defaultCurrency);

        // Cycle days
        const cycleDayField = formFields["cycle_day"];
        const defaultCycleDay = this.action === "sign" ? this.nextCycleDay : this.currentCycleDay;
        const selectedCycleDay = cycleDayField.val() || defaultCycleDay;
        const cycleDayOptions = this.cycleDays;

        cycleDayField.empty();
        cycleDayField.append("<option value=\"\">- none -</option>");

        for (const cycleDay of Object.values(cycleDayOptions)) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (parseInt(selectedCycleDay) === parseInt(cycleDay)) {
                cycleDayField.val(cycleDay);
            }
        }

        this.updatePaymentPreview(formFields);
    }

    async updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=sepa_mandate]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(this.debitorName);

        // Debitor account
        const iban = formFields["pa-sepa_mandate-iban"].val();
        paymentPreviewContainer.find("span#iban").text(iban);

        // Creditor name
        const creditor = this.creditor;
        paymentPreviewContainer.find("span#creditor_name").text(creditor.name);

        // Creditor account
        paymentPreviewContainer.find("span#creditor_iban").text(creditor.iban);

        // Frequency
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(this.frequencies[frequency]);

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
