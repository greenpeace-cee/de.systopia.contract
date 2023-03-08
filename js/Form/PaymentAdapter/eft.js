import { registerPaymentAdapter, updateCycleDayField } from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/eft"];

class EFT {
    constructor() {
        this.action = EXT_VARS.action;
        this.currentCycleDay = EXT_VARS.current_cycle_day;
        this.cycleDays = ADAPTER_VARS.cycle_days;
        this.debitorName = EXT_VARS.debitor_name;
        this.defaultCurrency = ADAPTER_VARS.default_currency;
        this.frequencies = EXT_VARS.frequencies;
    }

    onFormChange (formFields) {
        // Currency
        cj("span#currency").text(this.defaultCurrency);

        // Cycle days
        updateCycleDayField(formFields, this.cycleDays, this.currentCycleDay);

        // Payment preview
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
