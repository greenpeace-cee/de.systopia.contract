import { nextCollectionDate, parseMoney } from "../utils.js";
import { PaymentAdapter } from "./payment-adapter.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/adyen"];

export function createAdapter(formFields) {
    return new Adyen(formFields);
}

class Adyen extends PaymentAdapter {
    cycleDays = ADAPTER_VARS.cycle_days;
    frequencyOptions = ADAPTER_VARS.payment_frequencies;

    constructor(formFields) {
        super(formFields);
    }

    onFormChange () {
        // Cycle days
        this.updateCycleDayField();

        // Payment frequencies
        this.updateFrequencyField();

        if (EXT_VARS.action === "sign") {
            // Payment token fields
            const useExistingToken = this.formFields["pa-adyen-use_existing_token"].val() === '0';
            const paymentTokenFields = ADAPTER_VARS.payment_token_fields;

            paymentTokenFields.forEach(fieldID => {
                const container = cj(`div.form-field#pa-adyen-${fieldID}`);
                useExistingToken ? container.hide() : container.show();
            });

            const tokenIDContainer = cj(`div.form-field#pa-adyen-payment_token_id`);
            useExistingToken ? tokenIDContainer.show() : tokenIDContainer.hide();
        } else {
            // Debit before update warning
            const warning = cj("div.form-field#activity_date div#debit_before_update");
            const nextContribDate = new Date(EXT_VARS.next_sched_contribution_date);
            const scheduleDate = new Date(this.formFields["activity_date"].val());
            nextContribDate.getTime() < scheduleDate.getTime() ? warning.show() : warning.hide();
        }

        // Payment preview
        this.#updatePaymentPreview();
    }

    onSubmit() {
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

    async #getSelectedPaymentInstrument () {
        try {
            const useExistingToken = this.formFields["pa-adyen-use_existing_token"]?.val() === "1";

            if (EXT_VARS.action === "sign" && useExistingToken) {
                const piField = this.formFields["pa-adyen-payment_instrument_id"];
                const paymentInstrumentID = piField.val();

                return piField.find(`option[value=${paymentInstrumentID}]`).text();
            }

            const paymentTokenID = this.formFields["pa-adyen-payment_token_id"].val();

            if (!paymentTokenID) return "";

            const rcResult = await CRM.api4('ContributionRecur', 'get', {
              select: ["payment_instrument_id:label"],
              where: [["payment_token_id", "=", paymentTokenID]],
              limit: 1,
            });

            if (rcResult.length < 1) {
                throw new Error(
                    `No recurring contribution found for payment token with ID ${paymentTokenID}`
                );
            }

            return rcResult[0]["payment_instrument_id:label"];
        } catch (error) {
            console.error(error);

            return "";
        }
    }

    async #renderSummary() {
        const amount = parseMoney(this.formFields["amount"].val());
        const frequency = parseInt(this.formFields["frequency"].val());
        const annualAmount = amount * frequency;

        const currency = EXT_VARS.default_currency;
        const frequencyLabel = EXT_VARS.frequency_labels[frequency];
        const isNew = EXT_VARS.action === "sign";

        const paymentInstrument = await this.#getSelectedPaymentInstrument(this.formFields);

        const nextDebit = isNew
            ? await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                min_date: this.formFields["start_date"].val(),
                payment_adapter: "adyen",
            })
            : await nextCollectionDate({
                cycle_day: this.formFields["cycle_day"].val(),
                defer_payment_start: this.formFields["defer_payment_start"]?.prop("checked"),
                membership_id: EXT_VARS.membership_id,
                min_date: this.formFields["activity_date"].val(),
                payment_adapter: "adyen",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

        return `
            <ul style="margin:20px;padding:0px">
                <li>We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b> via <b>${paymentInstrument} (Adyen)</b></li>
                <li>The ${isNew ? "first" : "next"} debit is on <b>${nextDebit}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount.toFixed(2)}</b></li>
            </ul>
        `;
    }

    async #updatePaymentPreview () {
        const previewContainer = cj("div.payment-preview[data-payment-adapter=adyen]");

        // Payment instrument
        let paymentInstrument = await this.#getSelectedPaymentInstrument(this.formFields);
        previewContainer.find("span#payment_instrument").text(paymentInstrument);

        // Installment amount
        const amount = parseMoney(this.formFields["amount"].val());
        const currency = ADAPTER_VARS.default_currency;
        const installment = `${amount.toFixed(2)} ${currency}`;
        previewContainer.find("span#installment").text(installment);

        // Frequency
        const frequency = Number(this.formFields["frequency"].val());
        previewContainer.find("span#frequency").text(EXT_VARS.frequency_labels[frequency]);

        // Annual amount
        const annualAmount = amount * Number(frequency);
        previewContainer.find("span#annual").text(`${annualAmount.toFixed(2)} ${currency}`);

        // Cycle day
        const cycleDay = this.formFields["cycle_day"].val();
        previewContainer.find("span#cycle_day").text(cycleDay);

        if (EXT_VARS.action === "sign") {
            // First regular debit
            const firstRegularDebit = await nextCollectionDate({
                cycle_day: cycleDay,
                min_date: this.formFields["start_date"].val(),
                payment_adapter: "adyen",
            });

            previewContainer.find("span#first_regular_debit").text(firstRegularDebit ?? "");
        } else {
            // First debit after update
            const firstDebitAfterUpdate = await nextCollectionDate({
                cycle_day: cycleDay,
                defer_payment_start: this.formFields["defer_payment_start"].prop("checked"),
                membership_id: EXT_VARS.membership_id,
                min_date: this.formFields["activity_date"].val(),
                payment_adapter: "adyen",
                prev_recur_contrib_id: EXT_VARS.current_recurring,
            });

            previewContainer.find("span#first_debit_after_update").text(firstDebitAfterUpdate ?? "");
        }
    }
}
