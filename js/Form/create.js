import { displayRelevantFormFields, getFormFields } from "./utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];

let formFields = null;
let adapter = null;

export function initForm () {
    const paymentAdapterFields = EXT_VARS.payment_adapter_fields;
    const paFieldIds = Object.values(paymentAdapterFields).flat();

    const formFieldIDs = [
        "activity_details",
        "activity_medium",
        "amount",
        "campaign_id",
        "cycle_day",
        "end_date",
        "existing_recurring_contribution",
        "frequency",
        "join_date",
        "membership_channel",
        "membership_contract",
        "membership_dialoger",
        "membership_reference",
        "membership_type_id",
        "payment_option",
        "payment_adapter",
        "start_date",
        ...paFieldIds,
    ];

    formFields = getFormFields(formFieldIDs);

    Object.values(formFields).forEach(field => field.change(updateForm));

    updateForm();
}

function updateForm () {
    // Show only fields relevant to the currently selected payment option / adapter
    const selectedPaymentOption = formFields["payment_option"].val();
    const selectedPaymentAdapter = formFields["payment_adapter"].val();

    displayRelevantFormFields({
        "data-payment-option": selectedPaymentOption,
        "data-payment-adapter": selectedPaymentAdapter,
    });

    if (!window._PAYMENT_ADAPTERS_) return;
    if (!window._PAYMENT_ADAPTERS_[selectedPaymentAdapter]) return;
    
    adapter = window._PAYMENT_ADAPTERS_[selectedPaymentAdapter];
    adapter.onFormChange(formFields);
}
