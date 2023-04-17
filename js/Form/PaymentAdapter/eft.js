import { registerPaymentAdapter } from "../utils.js";

class EFT {
    constructor() {
        const extVars = CRM.vars["de.systopia.contract"];
        const adapterVars = CRM.vars["de.systopia.contract/eft"];

        this.action = extVars.action;
        this.currentCycleDay = extVars.current_cycle_day;
        this.cycleDays = adapterVars.cycle_days;
        this.debitorName = extVars.debitor_name;
        this.defaultCurrency = adapterVars.default_currency;
        this.frequencies = extVars.frequencies;
        this.nextCycleDay = adapterVars.next_cycle_day;
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

        for (const cycleDay of cycleDayOptions) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (parseInt(selectedCycleDay) === parseInt(cycleDay)) {
                cycleDayField.val(cycleDay);
            }
        }

        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=eft]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(this.debitorName);

        // Frequency
        const frequencyMapping = this.frequencies;
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(frequencyMapping[frequency]);

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const currency = this.defaultCurrency;
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = formFields["cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);
    }
}

registerPaymentAdapter("eft", new EFT());
