import { displayRelevantFormFields } from "./utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];

export async function initForm () {
    // Reference all relevant form fields in the DOM
    const formFields = Object.fromEntries([
        "activity_date",
        "amount",
        "campaign_id",
        "cycle_day",
        "defer_payment_start",
        "frequency",
        "membership_type_id",
        "payment_change",
        "payment_adapter",
        ...Object.values(EXT_VARS.payment_adapter_fields).flat(),
    ].map(name => [name, cj(`div.form-field div.content *[name=${name}]`)]));

    // Load & instantiate all available payment adapters
    const paymentAdapters = Object.fromEntries(
        await Promise.all(EXT_VARS.payment_adapters.map((adapterName) =>
            import(`${EXT_VARS.ext_base_url}/js/Form/PaymentAdapter/${adapterName}.js`)
                .then(({ createAdapter }) => createAdapter({ formType: "Modify", formFields }))
                .then(adapter => [adapterName, adapter])
    )));

    // Substitute the default 'Submit' button to trigger the 'onSubmit' hook
    // of the currently selected payment adapter
    const confirmButton = cj("button[data-identifier=_qf_Modify_submit]");
    const clonedButton = confirmButton.clone();
    confirmButton.hide();
    confirmButton.parent().append(clonedButton);

    clonedButton.on("click", () => {
        const selectedAdapter = formFields["payment_adapter"].val();

        paymentAdapters[selectedAdapter].onSubmit()
            .then((data) => {
                cj("input[type=hidden][name=pause_until_update]").val(data?.pause_until_update);
                confirmButton.click();
            })
            .catch(() => { /* Form submission cancelled */ });
    });

    // Trigger 'udpateForm' on every change of a form field
    Object.values(formFields).forEach(
        field => field.change(updateForm.bind(null, formFields, paymentAdapters))
    );

    updateForm(formFields, paymentAdapters);
}

function updateForm (formFields, paymentAdapters) {
    const selectedAdapter = formFields["payment_adapter"].val();

    // Show only fields relevant to the currently selected payment change mode / adapter
    displayRelevantFormFields({
        "data-payment-adapter": selectedAdapter,
        "data-payment-change": formFields["payment_change"].val(),
    });

    paymentAdapters[selectedAdapter].onFormChange();
}
