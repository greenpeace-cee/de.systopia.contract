import { displayRelevantFormFields } from "de.systopia.contract/Form/utils";

const EXT_VARS = CRM.vars["de.systopia.contract"];

export async function initForm () {
    // Reference all relevant form fields in the DOM
    const formFields = Object.fromEntries([
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
        ...Object.values(EXT_VARS.payment_adapter_fields).flat(),
    ].map(name => [name, cj(`div.form-field div.content *[name=${name}]`)]));

    // Load & instantiate all available payment adapters
    const paymentAdapters = Object.fromEntries(
        await Promise.all(EXT_VARS.payment_adapters.map((adapterName) =>
            import(`de.systopia.contract/Form/PaymentAdapter/${adapterName}`)
                .then(({ createAdapter }) => createAdapter({ formType: "Create", formFields }))
                .then(adapter => [adapterName, adapter])
    )));

    const confirmButton = cj("button[data-identifier=_qf_Create_submit]");
    const clonedButton = confirmButton.clone();
    confirmButton.hide();
    confirmButton.parent().append(clonedButton);

    clonedButton.on("click", () => {
        const selectedAdapter = formFields["payment_adapter"].val();

        paymentAdapters[selectedAdapter].onSubmit()
            .then(() => confirmButton.click())
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

    // Show only fields relevant to the currently selected payment option / adapter
    displayRelevantFormFields({
        "data-payment-adapter": selectedAdapter,
        "data-payment-option": formFields["payment_option"].val(),
    });

    paymentAdapters[selectedAdapter].onFormChange();
}
