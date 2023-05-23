import { registerPaymentAdapter, updateCycleDayField } from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/eft"];

class EFT {
    onFormChange (formFields) {
        // Currency
        cj("span#currency").text(ADAPTER_VARS.default_currency);

        // Cycle days
        updateCycleDayField(formFields, ADAPTER_VARS.cycle_days, EXT_VARS.current_cycle_day);

        // Payment preview
        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=eft]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(EXT_VARS.debitor_name);

        // Frequency
        const frequencyMapping = EXT_VARS.frequencies;
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(frequencyMapping[frequency]);

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const currency = ADAPTER_VARS.default_currency;
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
