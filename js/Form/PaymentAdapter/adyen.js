import { registerPaymentAdapter } from "../utils.js";

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
            'payment_processor',
            'shopper_reference',
            'stored_payment_method_id',
        ];

        paymentTokenFields.forEach(fieldID => {
            const container = cj(`div.form-field#pa-adyen-${fieldID}`);
            useExistingToken ? container.hide() : container.show(200);
        });

        const tokenIDContainer = cj(`div.form-field#pa-adyen-payment_token_id`);
        useExistingToken ? tokenIDContainer.show() : tokenIDContainer.hide(200);

        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        // ...
    }
}

registerPaymentAdapter("adyen", new Adyen());
