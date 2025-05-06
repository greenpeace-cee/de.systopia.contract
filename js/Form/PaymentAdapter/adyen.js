import { displayRelevantFormFields, nextCollectionDate, parseMoney } from "../utils.js";
import { PaymentAdapter } from "./payment-adapter.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/adyen"];

export function createAdapter(parameters) {
    return new Adyen(parameters);
}

class Adyen extends PaymentAdapter {
    cycleDays = ADAPTER_VARS.cycle_days;
    frequencyOptions = ADAPTER_VARS.payment_frequencies;

    onFormChange () {
        // Cycle days
        this.updateCycleDayField();

        // Payment frequencies
        this.updateFrequencyField();

        // Payment token fields
        if (this.formType === "Create") {
            const createNewToken = this.formFields["pa-adyen-use_existing_token"].val() === "1";
            displayRelevantFormFields({ "data-new-adyen-token": createNewToken });
        }

        // Debit before update warning
        if (this.formType === "Modify") {
            const warning = cj("div.form-field#activity_date div#debit_before_update");
            const nextContribDate = new Date(EXT_VARS.next_sched_contribution_date);
            const scheduleDate = new Date(this.formFields["activity_date"].val());
            nextContribDate.getTime() < scheduleDate.getTime() ? warning.show() : warning.hide();
        }

        // Payment preview
        this.#updatePaymentPreview();
    }

    onSubmit() {
        return new Promise(async (resolve, reject) => {
            const {
                activityDate,
                amount,
                annualAmount,
                frequency,
                nextDebit,
                paymentInstrument,
            } = await this.#compileSummary();

            const currency = ADAPTER_VARS.default_currency;
            const frequencyLabel = EXT_VARS.frequency_labels[frequency];
            const nextSchedContributionDate = EXT_VARS.next_sched_contribution_date;

            const isNew = this.formType === "Create";
            const activityTime = new Date(activityDate ?? 0).getTime();
            const nextSchedContribTime = new Date(nextSchedContributionDate).getTime();

            if (isNew || activityTime < nextSchedContribTime) {
                const message = `
                    <ul style="list-style:inside;margin:20px;padding:0px">
                        <li>We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b> via <b>${paymentInstrument} (Adyen)</b></li>
                        <li>The first debit ${isNew ? "" : "after the update"} is on <b>${nextDebit}</b></li>
                        <li>The total annual amount will be <b>${currency} ${annualAmount.toFixed(2)}</b></li>
                    </ul>
                `;

                CRM.confirm({
                    title: "Payment preview",
                    message,
                    options: { yes: "Confirm", no: "Edit" },
                })
                .on("crmConfirm:yes", () => resolve())
                .on("crmConfirm:no", () => reject());
            } else {
                const urlParams = new URLSearchParams({
                    "activity_date": activityDate,
                    "amount": amount.toFixed(2),
                    "annual_amount": annualAmount.toFixed(2),
                    "currency": currency,
                    "first_debit_after_update": nextDebit,
                    "frequency": frequencyLabel,
                    "membership_id": EXT_VARS.membership_id,
                    "next_sched_contribution_date": nextSchedContributionDate,
                    "payment_instrument": `${paymentInstrument} (Adyen)`,
                });

                CRM.loadForm(`/civicrm/contract/confirm-update?${urlParams}`, {
                    dialog: { width: 600 },
                })
                .on("crmFormSubmit", (event, ...submitted) => resolve({
                    pause_until_update: submitted.find(({ name }) => name === "pause_until_update")?.value ?? "no",
                }))
                .on("crmFormCancel", () => reject());
            }
        });
    }

    async #compileSummary() {
        const activityDate = this.formFields["activity_date"]?.val();
        const amount = parseMoney(this.formFields["amount"].val());
        const cycleDay = this.formFields["cycle_day"].val();
        const frequency = parseInt(this.formFields["frequency"].val());
        const startDate = this.formFields["start_date"]?.val();
        const annualAmount = amount * frequency;

        const nextDebit = this.formType === "Create"
            ? await nextCollectionDate({
                cycle_day: cycleDay,
                min_date: startDate,
                payment_adapter: "adyen",
            })
            : await nextCollectionDate({
                cycle_day: cycleDay,
                defer_payment_start: this.formFields["defer_payment_start"]?.prop("checked"),
                membership_id: EXT_VARS.membership_id,
                min_date: activityDate,
                payment_adapter: "adyen",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

        const createNewToken = this.formFields["pa-adyen-use_existing_token"]?.val() === "1";
        const paymentInstrumentID = this.formFields["pa-adyen-payment_instrument_id"]?.val();
        const paymentTokenID = this.formFields["pa-adyen-payment_token_id"]?.val();

        const paymentInstrument = this.formType === "Create" && createNewToken
            ? ADAPTER_VARS.payment_instruments[paymentInstrumentID]
            : (ADAPTER_VARS.payment_tokens[paymentTokenID] ?? {})['contribution_recur.payment_instrument_id:label'];

        return {
            activityDate,
            amount,
            annualAmount,
            cycleDay,
            frequency,
            nextDebit,
            paymentInstrument,
            startDate,
        }
    }

    async #updatePaymentPreview () {
        const {
            activityDate,
            amount,
            annualAmount,
            cycleDay,
            frequency,
            nextDebit,
            paymentInstrument,
        } = await this.#compileSummary();

        const currency = ADAPTER_VARS.default_currency;
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const nextSchedContributionDate = EXT_VARS.next_sched_contribution_date;

        const previewContainer = cj("div.payment-preview[data-payment-adapter=adyen]");

        // Payment instrument
        previewContainer.find("div#payment_instrument span.value").text(paymentInstrument);

        // Installment amount
        previewContainer.find("div#installment span.value").text(`${amount.toFixed(2)} ${currency}`);

        // Frequency
        previewContainer.find("div#frequency span.value").text(frequencyLabel);

        // Annual amount
        previewContainer.find("div#annual span.value").text(`${annualAmount.toFixed(2)} ${currency}`);

        // Cycle day
        previewContainer.find("div#cycle_day span.value").text(cycleDay);

        if (this.formType === "Create") {
            // First regular debit
            previewContainer.find("div#first_regular_debit span.value").text(nextDebit ?? "");
        } else {
            // First debit after update
            previewContainer.find("div#first_debit_after_update span.value").text(nextDebit ?? "");

            // Next schduled debit (only if it's before the update)
            if (new Date(activityDate).getTime() > new Date(nextSchedContributionDate).getTime()) {
                previewContainer.find("div#next_sched_contribution_date").show();
            } else {
                previewContainer.find("div#next_sched_contribution_date").hide();
            }
        }
    }
}
