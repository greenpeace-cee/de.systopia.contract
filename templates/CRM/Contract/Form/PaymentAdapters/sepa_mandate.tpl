{literal}

<script>
    window.PaymentAdapters["sepa_mandate"] = {};

    const SEPAMandate = window.PaymentAdapters["sepa_mandate"];

    SEPAMandate.clearPaymentParameters = (formFields) => {
        formFields["amount"].val("");
        formFields["pa-sepa_mandate-cycle_day"].val("1");
        formFields["frequency"].val("12");
        formFields["pa-sepa_mandate-iban"].val("");
    };

    SEPAMandate.fillPaymentParameters = (formFields) => {
        // Installment amount (amount)
        const amount = FormUtils.parseMoney(CRM.vars["de.systopia.contract"].current_amount);
        formFields["amount"].val(amount);

        // Cycle day (pa-sepa_mandate-cycle_day)
        const cycle_day =
            CRM.vars["de.systopia.contract"].action === "revive"
            ? CRM.vars["de.systopia.contract/sepa_mandate"].next_cycle_day
            : CRM.vars["de.systopia.contract"].current_cycle_day;

        formFields["pa-sepa_mandate-cycle_day"].val(cycle_day);

        // Payment frequency (frequency)
        const frequency = FormUtils.mapFrequency(CRM.vars["de.systopia.contract"].current_frequency);
        formFields["frequency"].val(frequency);

        // IBAN (payment-sepa_mandate-iban)
        const iban = CRM.vars["de.systopia.contract/sepa_mandate"].current_iban;
        formFields["pa-sepa_mandate-iban"].val(iban);
    };

    SEPAMandate.onUpdate = (formFields) => {
        cj("span#currency").text(CRM.vars["de.systopia.contract"].default_currency);
    }
</script>

{/literal}
