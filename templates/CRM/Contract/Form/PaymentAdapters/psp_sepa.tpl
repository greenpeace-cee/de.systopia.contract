{literal}

<script>
    window.PaymentAdapters["psp_sepa"] = {};

    const PSP = window.PaymentAdapters["psp_sepa"];

    PSP.clearPaymentParameters = (formFields) => {
        formFields["amount"].val("");
        formFields["frequency"].val("12");
        formFields["pa-psp_sepa-label"].val("");
        formFields["pa-psp_sepa-account_reference"].val("");
        formFields["pa-psp_sepa-account_name"].val("");
    };

    PSP.fillPaymentParameters = (formFields) => {
        // Installment amount (amount)
        const amount = FormUtils.parseMoney(CRM.vars["de.systopia.contract"].current_amount);
        formFields["amount"].val(amount);

        // Payment frequency (frequency)
        const frequency = FormUtils.mapFrequency(CRM.vars["de.systopia.contract"].current_frequency);
        formFields["frequency"].val(frequency);
    };

    PSP.onUpdate = (formFields) => {
        // Update options for cycle days
        const cycleDayField = formFields["pa-psp_sepa-cycle_day"];
        const previousCreditor = cycleDayField.attr("data-psp-creditor");
        const selectedCreditor = formFields["pa-psp_sepa-creditor"].val();

        if (previousCreditor === selectedCreditor) return;

        cycleDayField.attr("data-psp-creditor", selectedCreditor);
        cycleDayField.empty();

        const cycleDays = CRM.vars["de.systopia.contract/psp_sepa"].cycle_days[selectedCreditor];

        for (const cycleDay of Object.values(cycleDays)) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);
        }
    };
</script>

{/literal}
