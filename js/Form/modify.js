import { displayRelevantFormFields, getFormFields } from "./utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];

let formFields = null;
let adapter = null;

export function initForm () {
    const paymentAdapterFields = EXT_VARS.payment_adapter_fields;
    const paFieldIds = Object.values(paymentAdapterFields).flat();

    const formFieldIDs = [
        "activity_date",
        "amount",
        "campaign_id",
        "cycle_day",
        "defer_payment_start",
        "frequency",
        "membership_type_id",
        "payment_change",
        "payment_adapter",
        ...paFieldIds,
    ];

    formFields = getFormFields(formFieldIDs);

    Object.values(formFields).forEach(field => field.change(updateForm));

    const selectedPaymentAdapter =
        formFields["payment_adapter"].val()
        || EXT_VARS.current_payment_adapter;

    formFields["payment_adapter"].val(selectedPaymentAdapter);

    updateForm();
}

function updateForm () {
    // Show only fields relevant to the currently selected payment change mode / adapter
    const selectedPaymentChange = formFields["payment_change"].val();
    const selectedPaymentAdapter = formFields["payment_adapter"].val();

    displayRelevantFormFields({
        "data-payment-change": selectedPaymentChange,
        "data-payment-adapter": selectedPaymentAdapter,
    });

    if (!window._PAYMENT_ADAPTERS_) return;
    if (!window._PAYMENT_ADAPTERS_[selectedPaymentAdapter]) return;
    
    adapter = window._PAYMENT_ADAPTERS_[selectedPaymentAdapter];
    adapter.onFormChange(formFields);
}
