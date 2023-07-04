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

    const confirmButton = cj("button[data-identifier=_qf_Modify_submit]");
    addConfirmDialog(confirmButton, formFields);

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

    adapter = getCurrentPaymentAdapter(formFields);
    adapter.onFormChange(formFields);
}
