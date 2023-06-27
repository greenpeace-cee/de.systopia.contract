import {
    addConfirmDialog,
    displayRelevantFormFields,
    getCurrentPaymentAdapter,
    getFormFields,
} from "./utils.js";

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

    const confirmButton = cj("button[data-identifier=_qf_Create_submit]");
    addConfirmDialog(confirmButton, formFields);

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

    adapter = getCurrentPaymentAdapter(formFields);
    adapter.onFormChange(formFields);
}
