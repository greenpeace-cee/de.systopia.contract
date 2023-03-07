import { registerPaymentAdapter } from "../utils.js";

class PSP {
    constructor() {
        const extVars = CRM.vars["de.systopia.contract"];
        const adapterVars = CRM.vars["de.systopia.contract/psp_sepa"];

        this.action = extVars.action;
        this.currencies = adapterVars.currencies;
        this.currentCycleDay = extVars.current_cycle_day;
        this.cycleDays = adapterVars.cycle_days;
        this.debitorName = extVars.debitor_name;
        this.defaultCurrency = extVars.default_currency;
        this.frequencies = extVars.frequencies;
        this.graceEnd = extVars.grace_end;
        this.nextCycleDay = adapterVars.next_cycle_day;
        this.paymentInstruments = adapterVars.payment_instruments;
    }

    async nextCollectionDate ({ creditor_id = null, cycle_day = null, start_date = null }) {
        return await CRM.api3("Contract", "next_contribution_date", {
            creditor_id,
            cycle_day,
            payment_adapter: "psp_sepa",
            start_date,
        }).then(
            result => {
                if (result.is_error) console.error(result.error_message);
                return result?.values?.[0];
            },
            error => console.error(error.message),
        );
    }

    onFormChange (formFields) {
        const selectedCreditor = formFields["pa-psp_sepa-creditor"].val();

        // Currency
        const currency = this.currencies[selectedCreditor];
        cj("span#currency").text(currency);

        // Cycle days
        const cycleDayField = formFields["cycle_day"];
        const defaultCycleDay = this.action === "sign" ? this.nextCycleDay : this.currentCycleDay;
        const selectedCycleDay = cycleDayField.val() || defaultCycleDay;
        const cycleDayOptions = this.cycleDays[selectedCreditor] || {};

        cycleDayField.empty();
        cycleDayField.append("<option value=\"\">- none -</option>");

        for (const cycleDay of Object.values(cycleDayOptions)) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (parseInt(selectedCycleDay) === parseInt(cycleDay)) {
                cycleDayField.val(cycleDay);
            }
        }

        // Payment instruments
        const paymentInstrumentField = formFields["pa-psp_sepa-payment_instrument"];
        const selectedPaymentInstrument = paymentInstrumentField.val();
        const paymentInstrumentOptions = this.paymentInstruments[selectedCreditor] || {};

        paymentInstrumentField.empty();
        paymentInstrumentField.append("<option value=\"\">- none -</option>");

        for (const [piValue, piLabel] of Object.entries(paymentInstrumentOptions)) {
            paymentInstrumentField.append(`<option value="${piValue}">${piLabel}</option>`);

            if (parseInt(selectedPaymentInstrument) === parseInt(piValue)) {
                paymentInstrumentField.val(piValue);
            }
        }

        this.updatePaymentPreview(formFields);
    }

    async updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=psp_sepa]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(this.debitorName);

        // Creditor
        const pspCreditors = formFields["pa-psp_sepa-creditor"]
            .find("option")
            .map((_, opt) => ({ [opt.value]: opt.textContent }))
            .get()
            .reduce((result, item) => ({ ...result, ...item }), {});

        const creditorId = formFields["pa-psp_sepa-creditor"].val();
        paymentPreviewContainer.find("span#creditor").text(pspCreditors[creditorId]);

        // Payment instrument
        const paymentInstruments = formFields["pa-psp_sepa-payment_instrument"]
            .find("option")
            .get()
            .map(opt => ({ [opt.value]: opt.textContent }))
            .reduce((result, item) => ({ ...result, ...item }), {});

        const piId = formFields["pa-psp_sepa-payment_instrument"].val();
        paymentPreviewContainer.find("span#payment_instrument").text(paymentInstruments[piId]);

        // Account reference
        const accountReference = formFields["pa-psp_sepa-account_reference"].val();
        paymentPreviewContainer.find("span#account_reference").text(accountReference);

        // Account name
        const accountName = formFields["pa-psp_sepa-account_name"].val();
        paymentPreviewContainer.find("span#account_name").text(accountName);

        // Frequency
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(this.frequencies[frequency]);

        // Currency
        const currency = this.currencies[creditorId] || this.defaultCurrency;

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = formFields["cycle_day"].val() || "";
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);

        // Next debit
        const deferPaymentStart = formFields["defer_payment_start"]
            ? formFields["defer_payment_start"].prop("checked")
            : false;

        const graceEnd = this.action === "update" ? this.graceEnd : null;

        const startDate = formFields["start_date"]
            ? formFields["start_date"].val()
            : formFields["activity_date"].val();

        const nextDebit = await this.nextCollectionDate({
            creditor_id: creditorId,
            cycle_day: cycleDay,
            start_date: startDate,
        });

        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }
}

registerPaymentAdapter("psp_sepa", new PSP());
