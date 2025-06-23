import { formatDateYMD, nextCollectionDate, parseMoney } from "de.systopia.contract/Form/utils";
import { PaymentAdapter } from "de.systopia.contract/Form/PaymentAdapter/payment-adapter";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/sepa_mandate"];

export function createAdapter(parameters) {
    return new SEPA(parameters);
}

class SEPA extends PaymentAdapter {
    cycleDays = ADAPTER_VARS.cycle_days;
    frequencyOptions = ADAPTER_VARS.payment_frequencies;

    isAllowedScheduleDate(date, options = {}) {
        const minDate = new Date(ADAPTER_VARS.minimum_change_date ?? Date.now());

        // Reject dates in the past/before the minimum change date
        if (date.getTime() < minDate.setHours(0, 0, 0, 0)) return false;

        // Reject Saturdays/Sundays
        if (date.getDay() < 1 || date.getDay() > 5) return false;

        // Reject bank holidays
        if (ADAPTER_VARS.bank_holidays.includes(formatDateYMD(date))) return false;

        // Find the next cycle day
        const oneDayMilliseconds = 24 * 60 * 60 * 1000;

        let nextCycleDay = new Date(date);
        let safetyCounter = 0;

        while (nextCycleDay.getDate() !== options?.cycleDay && safetyCounter++ < 31) {
            nextCycleDay = new Date(nextCycleDay.getTime() + oneDayMilliseconds);
        }

        // No suitable date found within the next 30 days
        if (safetyCounter > 30) return false;

        // Walk back from the next cycle day to the next allowed schedule date
        const noticeMilliseconds = ADAPTER_VARS.notice_days * oneDayMilliseconds;
        let scheduleDate = new Date(nextCycleDay.getTime() - noticeMilliseconds);

        while (
            scheduleDate.getDay() < 1
            || scheduleDate.getDay() > 5
            || ADAPTER_VARS.bank_holidays.includes(formatDateYMD(scheduleDate))
        ) {
            scheduleDate = new Date(scheduleDate.getTime() - oneDayMilliseconds);
        }

        // Compare the given date to the next allowed schedule date
        return formatDateYMD(date) === formatDateYMD(scheduleDate);
    }

    onFormChange () {
        // Currency
        cj("span#currency").text(ADAPTER_VARS.creditor.currency);

        // Cycle days
        this.updateCycleDayField();

        // Payment frequencies
        this.updateFrequencyField();

        // Allowed schedule dates
        if (this.formType === "Modify") {
            const cycleDay = parseInt(this.formFields["cycle_day"].val());
            const datepickerField = this.formFields["activity_date"].parent().find("input.hasDatepicker");
            const selectedScheduleDate = new Date(this.formFields["activity_date"].val());

            if (!this.isAllowedScheduleDate(selectedScheduleDate, { cycleDay })) {
                datepickerField.datepicker("setDate", undefined);
                this.formFields["activity_date"].val(undefined);
            }

            datepickerField.datepicker(
                "option",
                "beforeShowDay",
                (date) => [this.isAllowedScheduleDate(date, { cycleDay }), ""]
            );
        }

        // Debit before update warning
        if (this.formType === "Modify" && EXT_VARS.next_sched_contribution_date) {
            const warning = cj("div.form-field#activity_date div#debit_before_update");
            const nextContribDate = new Date(EXT_VARS.next_sched_contribution_date);
            const scheduleDate = new Date(this.formFields["activity_date"].val() || 0);
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
            } = await this.#compileSummary();

            const currency = ADAPTER_VARS.creditor.currency;
            const frequencyLabel = EXT_VARS.frequency_labels[frequency];
            const nextSchedContributionDate = EXT_VARS.next_sched_contribution_date;

            const isNew = this.formType === "Create";
            const activityTime = new Date(activityDate ?? 0).getTime();
            const nextSchedContribTime = new Date(nextSchedContributionDate).getTime();

            if (isNew || !nextSchedContributionDate || activityTime < nextSchedContribTime) {
                const message = `
                    <ul style="list-style:inside;margin:20px;padding:0px">
                        <li>We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b> via <b>SEPA Direct Debit</b></li>
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
                    "payment_instrument": "SEPA Direct Debit",
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
        const iban = this.formFields["pa-sepa_mandate-iban"].val();
        const startDate = this.formFields["start_date"]?.val();
        const annualAmount = amount * frequency;

        const nextDebit = this.formType === "Create"
            ? await nextCollectionDate({
                cycle_day: cycleDay,
                payment_adapter: "sepa_mandate",
                min_date: formatDateYMD(new Date(Math.max(
                    new Date(startDate).getTime(),
                    new Date(ADAPTER_VARS.minimum_change_date).getTime() + (24 * 60 * 60 * 1000),
                ))),
            })
            : await nextCollectionDate({
                cycle_day: cycleDay,
                defer_payment_start: this.formFields["defer_payment_start"]?.prop("checked"),
                membership_id: EXT_VARS.membership_id,
                min_date: activityDate,
                payment_adapter: "sepa_mandate",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

        return {
            activityDate,
            amount,
            annualAmount,
            cycleDay,
            frequency,
            iban,
            nextDebit,
            startDate,
        };
    }

    async #updatePaymentPreview () {
        const {
            activityDate,
            amount,
            annualAmount,
            cycleDay,
            frequency,
            iban,
            nextDebit,
        } = await this.#compileSummary();

        const creditor = ADAPTER_VARS.creditor;
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const nextSchedContributionDate = EXT_VARS.next_sched_contribution_date;

        const previewContainer = cj("div.payment-preview[data-payment-adapter=sepa_mandate]");

        // Debitor account
        previewContainer.find("div#iban span.value").text(iban);

        // Creditor name
        previewContainer.find("div#creditor_name span.value").text(creditor.name);

        // Creditor account
        previewContainer.find("div#creditor_iban span.value").text(creditor.iban);

        // Frequency
        previewContainer.find("div#frequency span.value").text(frequencyLabel);

        // Annual amount
        previewContainer.find("div#annual span.value").text(`${annualAmount.toFixed(2)} ${creditor.currency}`);

        // Installment amount
        previewContainer.find("div#installment span.value").text(`${amount.toFixed(2)} ${creditor.currency}`);

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
