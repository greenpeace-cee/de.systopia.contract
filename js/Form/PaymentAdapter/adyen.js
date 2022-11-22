import { parseMoney, registerPaymentAdapter } from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/adyen"];

class Adyen {
    constructor() {
        this.defaultCurrency = ADAPTER_VARS.default_currency;
    }

    onFormChange (formFields) {
        const useExistingToken = formFields["pa-adyen-use_existing_token"].val() === '0';

        const paymentTokenFields = [
            'account_number',
            'billing_first_name',
            'billing_last_name',
            'email',
            'expiry_date',
            'ip_address',
            'payment_processor_id',
            'shopper_reference',
            'stored_payment_method_id',
        ];

        paymentTokenFields.forEach(fieldID => {
            const container = cj(`div.form-field#pa-adyen-${fieldID}`);
            useExistingToken ? container.hide() : container.show();
        });

        const tokenIDContainer = cj(`div.form-field#pa-adyen-payment_token_id`);
        useExistingToken ? tokenIDContainer.show() : tokenIDContainer.hide();

        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=adyen]");

        // Payment instrument
        const piField = formFields["pa-adyen-payment_instrument_id"];
        const selectedPIValue = piField.val();
        const selectedPILabel = piField.find(`option[value=${selectedPIValue}]`).text();
        paymentPreviewContainer.find("span#payment_instrument").text(selectedPILabel);

        // Installment amount
        const amount = parseMoney(formFields["amount"].val());
        const currency = this.defaultCurrency;
        const installment = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installment);

        // Frequency
        const freqField = formFields["frequency"];
        const selectedFreqValue = freqField.val();
        const selectedFreqLabel = freqField.find(`option[value=${selectedFreqValue}]`).text();
        paymentPreviewContainer.find("span#frequency").text(selectedFreqLabel);

        // Annual amount
        const annualAmount = amount * Number(selectedFreqValue);
        paymentPreviewContainer.find("span#annual").text(`${annualAmount.toFixed(2)} ${currency}`);

        // Cycle day
        const cycleDay = formFields["cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);
    }
}

registerPaymentAdapter("adyen", new Adyen());
