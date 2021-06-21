{literal}

<script>
    window.PaymentAdapters["eft"] = {};

    const EFT = window.PaymentAdapters["eft"];

    EFT.clearPaymentParameters = (formFields) => {
        formFields["amount"].val("");
        formFields["pa-eft-cycle_day"].val("1");
        formFields["frequency"].val("12");
    };

    EFT.fillPaymentParameters = (formFields) => {
        // Installment amount (amount)
        const amount = FormUtils.parseMoney(CRM.vars["de.systopia.contract"].current_amount);
        formFields["amount"].val(amount);

        // Cycle day (pa-eft-cycle_day)
        const cycle_day =
            CRM.vars["de.systopia.contract"].action === "revive"
            ? CRM.vars["de.systopia.contract/eft"].next_cycle_day
            : CRM.vars["de.systopia.contract"].current_cycle_day;

        formFields["pa-eft-cycle_day"].val(cycle_day);

        // Payment frequency (frequency)
        const frequency = FormUtils.mapFrequency(CRM.vars["de.systopia.contract"].current_frequency);
        formFields["frequency"].val(frequency);
    };

    EFT.onUpdate = (formFields) => {
        cj("span#currency").text(CRM.vars["de.systopia.contract"].default_currency);
    }

</script>

{/literal}
