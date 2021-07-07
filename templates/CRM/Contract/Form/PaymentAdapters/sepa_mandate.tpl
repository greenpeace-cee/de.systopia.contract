{literal}

<script>
    window.PaymentAdapters["sepa_mandate"] = {};

    const SEPA = window.PaymentAdapters["sepa_mandate"];

    SEPA.vars = CRM.vars["de.systopia.contract/sepa_mandate"];

    SEPA.onUpdate = (formFields) => {
        const { action, current_cycle_day } = CRM.vars["de.systopia.contract"];

        // Currency
        cj("span#currency").text(SEPA.vars.default_currency);

        // Cycle days
        const cycleDayField = formFields["cycle_day"];
        const defaultCycleDay = action === "sign" ? SEPA.vars.next_cycle_day : current_cycle_day;
        const selectedCycleDay = cycleDayField.val() || defaultCycleDay;
        const cycleDayOptions = CRM.vars["de.systopia.contract/sepa_mandate"].cycle_days;

        cycleDayField.empty();
        cycleDayField.append("<option value=\"\">- none -</option>");

        for (const cycleDay of Object.values(cycleDayOptions)) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (parseInt(selectedCycleDay) === parseInt(cycleDay)) {
                cycleDayField.val(cycleDay);
            }
        }
    }
</script>

{/literal}
