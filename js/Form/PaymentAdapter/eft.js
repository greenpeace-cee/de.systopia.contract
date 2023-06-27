import {
    mapPaymentFrequency,
    parseMoney,
    registerPaymentAdapter,
    updateCycleDayField,
} from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/eft"];

class EFT {
    async confirmDialog (formFields) {
        const currency = EXT_VARS.default_currency;
        const amount = parseMoney(formFields["amount"].val());
        const frequency = parseInt(formFields["frequency"].val());
        const frequencyLabel = mapPaymentFrequency(frequency);
        const annualAmount = (amount * frequency).toFixed(2);
        const cycleDay = formFields["cycle_day"].val();
        const startDate = (formFields["start_date"] || formFields["activity_date"]).val();

        const firstDebit = await this.nextCollectionDate({
            cycle_day: cycleDay,
            min_date: startDate,
        })

        return `
            <ul>
                <li>
                    We will receive <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b>
                    via <b>EFT</b>
                </li>

                <li>The next payment is on <b>${firstDebit}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount}</b></li>
            </ul>
        `;
    }

    async nextCollectionDate ({ cycle_day, min_date }) {
        if (!cycle_day) return "";
        if (!min_date) return "";

        return await CRM.api3("Contract", "start_date", {
            cycle_day,
            defer_payment_start: false,
            membership_id: EXT_VARS.membership_id,
            min_date,
            payment_adapter: "eft",
            prev_recur_contrib_id: EXT_VARS.current_recurring,
        }).then(
            result => {
                if (result.is_error) console.error(result.error_message);
                return result?.values?.[0];
            },
            error => console.error(error.message),
        );
    }

    onFormChange (formFields) {
        // Currency
        cj("span#currency").text(ADAPTER_VARS.default_currency);

        // Cycle days
        updateCycleDayField(formFields, ADAPTER_VARS.cycle_days, EXT_VARS.current_cycle_day);

        // Payment preview
        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=eft]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(EXT_VARS.debitor_name);

        // Frequency
        const frequencyMapping = EXT_VARS.frequencies;
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(frequencyMapping[frequency]);

        // Annual amount
        const amount = parseMoney(formFields["amount"].val());
        const currency = ADAPTER_VARS.default_currency;
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = formFields["cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);
    }
}

registerPaymentAdapter("eft", new EFT());
