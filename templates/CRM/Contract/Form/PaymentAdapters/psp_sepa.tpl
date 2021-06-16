{literal}

<script>
    window.PaymentAdapters["psp_sepa"] = {};

    const PSP = window.PaymentAdapters["psp_sepa"];

    PSP.clearPaymentParameters = (formFields) => {
        formFields["amount"].val("");
        formFields["frequency"].val("12");
        formFields["pa-psp_sepa-account_reference"].val("");
        formFields["pa-psp_sepa-account_name"].val("");
    };

    PSP.fillPaymentParameters = (formFields) => {
        // Cycle day
        const cycleDay = CRM.vars["de.systopia.contract"].current_cycle_day;
        formFields["pa-psp_sepa-cycle_day"].val(cycleDay);

        // Installment amount (amount)
        const amount = FormUtils.parseMoney(CRM.vars["de.systopia.contract"].current_amount);
        formFields["amount"].val(amount);

        // Payment frequency (frequency)
        const frequency = FormUtils.mapFrequency(CRM.vars["de.systopia.contract"].current_frequency);
        formFields["frequency"].val(frequency);

        // Payment instrument (pa-psp_sepa-payment_instrument)
        const paymentInstrument = CRM.vars["de.systopia.contract/psp_sepa"].current_payment_instrument;
        formFields["pa-psp_sepa-payment_instrument"].val(paymentInstrument);

        // Account reference (pa-psp_sepa-account_reference)
        const accountReference = CRM.vars["de.systopia.contract/psp_sepa"].current_account_reference;
        formFields["pa-psp_sepa-account_reference"].val(accountReference);

        // Account name (pa-psp_sepa-account_name)
        const accountName = CRM.vars["de.systopia.contract/psp_sepa"].current_account_name;
        formFields["pa-psp_sepa-account_name"].val(accountName);

        // Creditor (pa-psp_sepa-creditor)
        const creditorId = CRM.vars["de.systopia.contract/psp_sepa"].current_creditor;
        formFields["pa-psp_sepa-creditor"].val(creditorId);
    };

    PSP.onUpdate = (formFields) => {
        // Update options for cycle days
        const cycleDayField = formFields["pa-psp_sepa-cycle_day"];
        let previousCreditor = cycleDayField.attr("data-psp-creditor");
        const selectedCreditor = formFields["pa-psp_sepa-creditor"].val();

        if (previousCreditor !== selectedCreditor) {
            cycleDayField.attr("data-psp-creditor", selectedCreditor);
            cycleDayField.empty();
            cycleDayField.append("<option></option>");

            const cycleDays = CRM.vars["de.systopia.contract/psp_sepa"].cycle_days[selectedCreditor];

            for (const cycleDay of Object.values(cycleDays)) {
                cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);
            }
        }

        // Update options for payment instruments
        const paymentInstrumentField = formFields["pa-psp_sepa-payment_instrument"];
        previousCreditor = paymentInstrumentField.attr("data-psp-creditor");

        if (previousCreditor !== selectedCreditor) {
            paymentInstrumentField.attr("data-psp-creditor", selectedCreditor);
            paymentInstrumentField.empty();

            const paymentInstruments = CRM.vars["de.systopia.contract/psp_sepa"].payment_instruments[selectedCreditor];

            for (const [piValue, piLabel] of Object.entries(paymentInstruments)) {
                paymentInstrumentField.append(`<option value="${piValue}">${piLabel}</option>`);
            }
        }
    };
</script>

{/literal}
