import { registerPaymentAdapter } from "../utils.js";

class Adyen {
    constructor() {
        const extVars = CRM.vasr["de.systopia.contract"];
        const adapterVars = CRM.vars["de.systopia.contract/adyen"];

        this.defaultCurrency = adapterVars.default_currency;
    }

    onFormChange (formFields) {
        // ...

        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        // ...
    }
}

registerPaymentAdapter("adyen", new Adyen());
