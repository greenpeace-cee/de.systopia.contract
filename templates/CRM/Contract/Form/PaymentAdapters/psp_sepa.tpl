{literal}

<script>
    window.PaymentAdapters["psp_sepa"] = {};

    const PSP = window.PaymentAdapters["psp_sepa"];

    PSP.clearPaymentParameters = (formFields) => {
        formFields["amount"].val("");
        formFields["frequency"].val("12");
    };

    PSP.fillPaymentParameters = (formFields) => {
        // Installment amount (amount)
        const amount = FormUtils.parseMoney(CRM.vars["de.systopia.contract"].current_amount);
        formFields["amount"].val(amount);

        // Payment frequency (frequency)
        const frequency = FormUtils.mapFrequency(CRM.vars["de.systopia.contract"].current_frequency);
        formFields["frequency"].val(frequency);
    };
</script>

{/literal}
