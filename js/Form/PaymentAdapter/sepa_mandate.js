import { nextCollectionDate, parseMoney } from "../utils.js";
import { PaymentAdapter } from "./payment-adapter.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/sepa_mandate"];

export function createAdapter(formFields) {
    return new SEPA(formFields);
}

class SEPA extends PaymentAdapter {
    cycleDays = ADAPTER_VARS.cycle_days;
    frequencyOptions = ADAPTER_VARS.payment_frequencies;

    constructor(formFields) {
        super(formFields);
    }

    onFormChange () {
        // Currency
        cj("span#currency").text(ADAPTER_VARS.default_currency);

        // Cycle days
        this.updateCycleDayField();

        // Payment frequencies
        this.updateFrequencyField();

        if (EXT_VARS.action !== "sign") {
            // Debit before update warning
            const warning = cj("div.form-field#activity_date div#debit_before_update");
            const nextContribDate = new Date(EXT_VARS.next_sched_contribution_date);
            const scheduleDate = new Date(this.formFields["activity_date"].val());
            nextContribDate.getTime() < scheduleDate.getTime() ? warning.show() : warning.hide();
        }

        // Payment preview
        this.#updatePaymentPreview();
    }

    async onSubmit() {
        const activityTime = new Date(this.formFields["activity_date"]?.val() ?? 0).getTime();
        const nextContribTime = new Date(EXT_VARS.next_sched_contribution_date).getTime();

        if (EXT_VARS.action === "sign" || activityTime < nextContribTime) {
            return new Promise(async (resolve, reject) => {
                const message = await this.#renderSummary();

                CRM.confirm({
                    title: "Payment preview",
                    message,
                    options: { yes: "Confirm", no: "Edit" },
                })
                .on("crmConfirm:yes", resolve)
                .on("crmConfirm:no", reject);
            });
        } else {
            return new Promise((resolve, reject) => {
                CRM.loadForm("/civicrm/contract/confirm-update", {
                    dialog: { width: 600 },
                    ajaxForm: {
                        data: {
                            "activity_date": this.formFields["activity_date"].val(),
                            "next_sched_contribution_date": EXT_VARS.next_sched_contribution_date,
                        },
                    }
                })
                .on("crmFormSubmit", (event, ...submitted) => console.debug({submitted}))
                .on("crmFormCancel", reject)
                .on("crmFormSuccess", resolve);
            });
        }
    }

    async #renderSummary() {
        const amount = parseMoney(this.formFields["amount"].val());
        const frequency = parseInt(this.formFields["frequency"].val());
        const annualAmount = amount * frequency;

        const currency = EXT_VARS.default_currency;
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const isNew = EXT_VARS.action === "sign";

        const nextDebit = isNew
            ? await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                min_date: this.formFields["start_date"].val(),
                payment_adapter: "sepa_mandate",
            })
            : await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                defer_payment_start: this.formFields["defer_payment_start"]?.prop("checked"),
                membership_id: EXT_VARS.membership_id,
                min_date: this.formFields["activity_date"].val(),
                payment_adapter: "sepa_mandate",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

        return `
            <ul style="margin:20px;padding:0px">
                <li>We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b> via <b>SEPA Direct Debit</b></li>
                <li>The ${isNew ? "first" : "next"} debit will be on <b>${nextDebit}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount.toFixed(2)}</b></li>
            </ul>
        `;
    }

    async #updatePaymentPreview () {
        const previewContainer = cj("div.payment-preview[data-payment-adapter=sepa_mandate]");

        // Debitor name
        previewContainer.find("span#debitor_name").text(EXT_VARS.debitor_name);

        // Debitor account
        const iban = this.formFields["pa-sepa_mandate-iban"].val();
        previewContainer.find("span#iban").text(iban);

        // Creditor name
        const creditor = ADAPTER_VARS.creditor;
        previewContainer.find("span#creditor_name").text(creditor.name);

        // Creditor account
        previewContainer.find("span#creditor_iban").text(creditor.iban);

        // Frequency
        const frequency = Number(this.formFields["frequency"].val());
        previewContainer.find("span#frequency").text(EXT_VARS.frequency_labels[frequency]);

        // Annual amount
        const amount = parseMoney(this.formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${creditor.currency}`;
        previewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${creditor.currency}`;
        previewContainer.find("span#installment").text(installmentAmount);

        if (EXT_VARS.action === "sign") {
            // First regular debit
            const firstRegularDebit = await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                min_date: this.formFields["start_date"].val(),
                payment_adapter: "sepa_mandate",
            });

            previewContainer.find("span#first_regular_debit").text(firstRegularDebit ?? "");
        } else {
            // First debit after update
            const firstDebitAfterUpdate = await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                defer_payment_start: this.formFields["defer_payment_start"].prop("checked"),
                membership_id: EXT_VARS.membership_id,
                min_date: this.formFields["activity_date"].val(),
                payment_adapter: "sepa_mandate",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

            previewContainer.find("span#first_debit_after_update").text(firstDebitAfterUpdate ?? "");
        }
    }
}
