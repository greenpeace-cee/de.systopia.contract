import {
    getPaymentInstrumentLabel,
    mapPaymentFrequency,
    parseMoney,
    registerPaymentAdapter,
    updateCycleDayField,
} from "../utils.js";

const EXT_VARS = CRM.vars["de.systopia.contract"];
const ADAPTER_VARS = CRM.vars["de.systopia.contract/adyen"];

class Adyen {
    async confirmDialog (formFields) {
        const currency = EXT_VARS.default_currency;
        const amount = parseMoney(formFields["amount"].val());
        const frequency = parseInt(formFields["frequency"].val());
        const frequencyLabel = mapPaymentFrequency(frequency);
        const annualAmount = (amount * frequency).toFixed(2);
        const paymentInstrumentID = formFields["pa-adyen-payment_instrument_id"].val();
        const paymentInstrumentLabel = await getPaymentInstrumentLabel(paymentInstrumentID);
        const cycleDay = formFields["cycle_day"].val();
        const deferPaymentStart = formFields["defer_payment_start"]?.prop("checked");
        const startDate = (formFields["start_date"] || formFields["activity_date"]).val();

        const firstDebit = await this.nextCollectionDate({
            cycle_day: cycleDay,
            defer_payment_start: deferPaymentStart,
            min_date: startDate,
        })

        return `
            <ul>
                <li>
                    We will debit <b>${currency} ${amount.toFixed(2)} ${frequencyLabel}</b>
                    via <b>${paymentInstrumentLabel} (Adyen)</b>
                </li>

                <li>The first debit is on <b>${firstDebit}</b></li>
                <li>The total annual amount will be <b>${currency} ${annualAmount}</b></li>
            </ul>
        `;
    }

    async nextCollectionDate ({ cycle_day, defer_payment_start, min_date }) {
        if (!cycle_day) return "";
        if (!min_date) return "";

        return await CRM.api3("Contract", "start_date", {
            cycle_day,
            defer_payment_start,
            membership_id: EXT_VARS.membership_id,
            min_date,
            payment_adapter: "adyen",
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
        // Cycle days
        updateCycleDayField(formFields, ADAPTER_VARS.cycle_days, EXT_VARS.current_cycle_day);

        // Payment token fields
        if (EXT_VARS.action === "sign") {
            const useExistingToken = formFields["pa-adyen-use_existing_token"].val() === '0';
            const paymentTokenFields = ADAPTER_VARS.payment_token_fields;

            paymentTokenFields.forEach(fieldID => {
                const container = cj(`div.form-field#pa-adyen-${fieldID}`);
                useExistingToken ? container.hide() : container.show();
            });

            const tokenIDContainer = cj(`div.form-field#pa-adyen-payment_token_id`);
            useExistingToken ? tokenIDContainer.show() : tokenIDContainer.hide();
        }

        // Payment preview
        this.updatePaymentPreview(formFields);
    }

    async updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=adyen]");

        // Payment instrument
        const piField = formFields["pa-adyen-payment_instrument_id"];
        const selectedPIValue = piField.val();
        const selectedPILabel = piField.find(`option[value=${selectedPIValue}]`).text();
        paymentPreviewContainer.find("span#payment_instrument").text(selectedPILabel);

        // Installment amount
        const amount = parseMoney(formFields["amount"].val());
        const currency = ADAPTER_VARS.default_currency;
        const installment = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installment);

        // Frequency
        const freqField = formFields["frequency"];
        const selectedFreqValue = freqField.val();
        const selectedFreqLabel = freqField.find(`option[value=${selectedFreqValue}]`).text();
        paymentPreviewContainer.find("span#frequency").text(selectedFreqLabel);

        // Annual amount
        const annualAmount = amount * Number(selectedFreqValue);
        paymentPreviewContainer.find("span#annual").text(`${annualAmount.toFixed(2)} ${currency}`);

        // Cycle day
        const cycleDay = formFields["cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);

        // Next debit
        const startDate = formFields["start_date"]
            ? formFields["start_date"].val()
            : formFields["activity_date"].val();

        const nextDebit = await this.nextCollectionDate({
            cycle_day: cycleDay,
            min_date: startDate,
        });

        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }
}

registerPaymentAdapter("adyen", new Adyen());
