{literal}

<script>
    window.PaymentAdapters["eft"] = {};

    const EFT = window.PaymentAdapters["eft"];

    EFT.vars = CRM.vars["de.systopia.contract/eft"];

    EFT.onUpdate = (formFields) => {
        const { action, current_cycle_day } = CRM.vars["de.systopia.contract"];

        // Currency
        cj("span#currency").text(EFT.vars.default_currency);

        // Cycle days
        const cycleDayField = formFields["cycle_day"];
        const defaultCycleDay = action === "sign" ? EFT.vars.next_cycle_day : current_cycle_day;
        const selectedCycleDay = cycleDayField.val() || defaultCycleDay;
        const cycleDayOptions = EFT.vars.cycle_days;

        cycleDayField.empty();
        cycleDayField.append("<option value=\"\">- none -</option>");

        for (const cycleDay of cycleDayOptions) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (parseInt(selectedCycleDay) === parseInt(cycleDay)) {
                cycleDayField.val(cycleDay);
            }
        }
    };

</script>

{/literal}
