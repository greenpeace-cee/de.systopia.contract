import { nextCollectionDate, parseMoney } from "../utils.js";
import { PaymentAdapter } from "./payment-adapter.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/eft"];

export function createAdapter(parameters) {
    return new EFT(parameters);
}

class EFT extends PaymentAdapter {
    cycleDays = ADAPTER_VARS.cycle_days;
    frequencyOptions = ADAPTER_VARS.payment_frequencies;

    isAllowedScheduleDate(date, options = {}) {
        return true;
    }

    onFormChange () {
        // Currency
        cj("span#currency").text(ADAPTER_VARS.default_currency);

        // Cycle days
        this.updateCycleDayField();

        // Payment frequencies
        this.updateFrequencyField();

        // Allowed schedule dates
        if (this.formType === "Modify") {
            this.formFields["activity_date"]
                .parent()
                .find("input.hasDatepicker")
                .datepicker("option", "beforeShowDay", (date) => [this.isAllowedScheduleDate(date), ""]);
        }

        // Payment preview
        this.#updatePaymentPreview();
    }

    onSubmit() {
        return new Promise(async (resolve, reject) => {
            const { amount, annualAmount, frequency, nextPayment } = await this.#compileSummary();

            const currency = ADAPTER_VARS.default_currency;
            const frequencyLabel = EXT_VARS.frequency_labels[frequency];

            const isNew = this.formType === "Create";

            const message = `
                <ul style="list-style:inside;margin:20px;padding:0px">
                    <li>We will receive <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b> via <b>EFT</b></li>
                    <li>The first payment ${isNew ? "" : "after the update"} will be received on <b>${nextPayment}</b></li>
                    <li>The total annual amount will be <b>${currency} ${annualAmount}</b></li>
                </ul>
            `;

            CRM.confirm({
                title: "Payment preview",
                message,
                options: { yes: "Confirm", no: "Edit" },
            })
            .on("crmConfirm:yes", () => resolve())
            .on("crmConfirm:no", () => reject());
        });
    }

    async #compileSummary() {
        const activityDate = this.formFields["activity_date"]?.val();
        const amount = parseMoney(this.formFields["amount"].val());
        const cycleDay = this.formFields["cycle_day"].val();
        const frequency = parseInt(this.formFields["frequency"].val());
        const startDate = this.formFields["start_date"]?.val();
        const annualAmount = amount * frequency;

        const nextPayment = this.formType === "Create"
            ? await nextCollectionDate({
                cycle_day: cycleDay,
                min_date: startDate,
                payment_adapter: "eft",
            })
            : await nextCollectionDate({
                cycle_day: cycleDay,
                membership_id: EXT_VARS.membership_id,
                min_date: activityDate,
                payment_adapter: "eft",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

        return {
            activityDate,
            amount,
            annualAmount,
            cycleDay,
            frequency,
            nextPayment,
            startDate,
        };
    }

    async #updatePaymentPreview () {
        const { amount, annualAmount, cycleDay, frequency } = await this.#compileSummary();

        const currency = ADAPTER_VARS.default_currency;
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];

        const previewContainer = cj("div.payment-preview[data-payment-adapter=eft]");

        // Frequency
        previewContainer.find("div#frequency span.value").text(frequencyLabel);

        // Annual amount
        previewContainer.find("div#annual span.value").text(`${annualAmount.toFixed(2)} ${currency}`);

        // Installment amount
        previewContainer.find("div#installment span.value").text(`${amount.toFixed(2)} ${currency}`);

        // Cycle day
        previewContainer.find("div#cycle_day span.value").text(cycleDay);
    }
}
