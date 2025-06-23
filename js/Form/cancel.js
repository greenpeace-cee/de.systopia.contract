import { formatDateYMD } from "de.systopia.contract/Form/utils";

const EXT_VARS = CRM.vars["de.systopia.contract"];

export function initForm() {
    // Reference all relevant form fields in the DOM
    const formFields = Object.fromEntries([
        "activity_date",
        "cancel_reason",
        "cancel_tags",
        "medium_id",
        "note",
    ].map(name => [name, cj(`div.form-field div.content *[name=${name}]`)]));

    // Substitute the default 'Submit' button to trigger the 'onSubmit' hook
    const confirmButton = cj("button[data-identifier=_qf_Cancel_submit]");
    const clonedButton = confirmButton.clone();
    confirmButton.hide();
    confirmButton.parent().append(clonedButton);

    clonedButton.on("click", () => onSubmit(formFields)
        .then(() => confirmButton.click())
        .catch(() => { /* Form submission cancelled */ })
    );

    // Trigger 'updateForm' on every change of a form field
    Object.values(formFields).forEach(field => field.change(updateForm.bind(null, formFields)));

    updateForm(formFields);
}

function onSubmit(formFields) {
    if (!EXT_VARS["next_sched_contribution_date"]) return Promise.resolve();

    const nextSchedContributionDate = new Date(EXT_VARS["next_sched_contribution_date"]);
    const activityDate = new Date(formFields["activity_date"].val());

    if (activityDate.getTime() <= nextSchedContributionDate.getTime()) return Promise.resolve();

    const message = `<p>
        The contract will be cancelled on <b>${formatDateYMD(activityDate)}</b>
        but the next scheduled debit is on <b>${formatDateYMD(nextSchedContributionDate)}</b>.
        Would you like to continue with your current inputs?
    </p>`;

    return new Promise((resolve, reject) => {
        CRM.confirm({
            title: "Confirm cancellation",
            message,
            options: { yes: "Confirm", no: "Edit" },
            width: 500,
        })
        .on("crmConfirm:yes", () => resolve())
        .on("crmConfirm:no", () => reject());
    });
}

function updateForm(formFields) {
    if (EXT_VARS["next_sched_contribution_date"]) {
        const nextSchedContributionDate = new Date(EXT_VARS["next_sched_contribution_date"]);
        const activityDate = new Date(formFields["activity_date"].val());
        const debitBeforeChangeWarning = cj(`div.form-field div#debit_before_change`);

        if (activityDate.getTime() > nextSchedContributionDate.getTime()) {
            debitBeforeChangeWarning.show();
        } else {
            debitBeforeChangeWarning.hide();
        }
    }
}
