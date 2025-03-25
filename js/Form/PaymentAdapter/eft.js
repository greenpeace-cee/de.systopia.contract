import { nextCollectionDate, parseMoney } from "../utils.js";
import { PaymentAdapter } from "./payment-adapter.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/eft"];

export function createAdapter(formFields) {
    return new EFT(formFields);
}

class EFT extends PaymentAdapter {
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

        // Payment preview
        this.#updatePaymentPreview();
    }

    onSubmit() {
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
    }

    async #renderSummary() {
        const amount = parseMoney(this.formFields["amount"].val());
        const frequency = parseInt(this.formFields["frequency"].val());
        const annualAmount = amount * frequency;

        const currency = EXT_VARS.default_currency;
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const isNew = EXT_VARS.action === "sign";

        const nextPayment = isNew
            ? await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                min_date: this.formFields["start_date"].val(),
                payment_adapter: "eft",
            })
            : await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                membership_id: EXT_VARS.membership_id,
                min_date: this.formFields["activity_date"].val(),
                payment_adapter: "eft",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

        return `
            <ul style="margin:20px;padding:0px">
                <li>We will receive <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b> via <b>EFT</b></li>
                <li>The ${isNew ? "first" : "next"} payment will be received on <b>${nextPayment}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount}</b></li>
            </ul>
        `;
    }

    #updatePaymentPreview () {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=eft]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(EXT_VARS.debitor_name);

        // Frequency
        const frequency = Number(this.formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(EXT_VARS.frequency_labels[frequency]);

        // Annual amount
        const amount = parseMoney(this.formFields["amount"].val());
        const currency = ADAPTER_VARS.default_currency;
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = this.formFields["cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);
    }
}
