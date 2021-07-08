{literal}

<script>
    window.PaymentAdapters["psp_sepa"] = {};

    const PSP = window.PaymentAdapters["psp_sepa"];

    PSP.vars = CRM.vars["de.systopia.contract/psp_sepa"];

    PSP.onUpdate = (formFields) => {
        const { action, current_cycle_day } = CRM.vars["de.systopia.contract"];

        const selectedCreditor = formFields["pa-psp_sepa-creditor"].val();

        // Currency
        const currency = PSP.vars.currencies[selectedCreditor];
        cj("span#currency").text(currency);

        // Cycle days
        const cycleDayField = formFields["cycle_day"];
        const defaultCycleDay = action === "sign" ? PSP.vars.next_cycle_day : current_cycle_day;
        const selectedCycleDay = cycleDayField.val() || defaultCycleDay;
        const cycleDayOptions = PSP.vars.cycle_days[selectedCreditor];

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
        const paymentInstrumentOptions = PSP.vars.payment_instruments[selectedCreditor];

        paymentInstrumentField.empty();
        paymentInstrumentField.append("<option value=\"\">- none -</option>");

        for (const [piValue, piLabel] of Object.entries(paymentInstrumentOptions)) {
            paymentInstrumentField.append(`<option value="${piValue}">${piLabel}</option>`);

            if (parseInt(selectedPaymentInstrument) === parseInt(piValue)) {
                paymentInstrumentField.val(piValue);
            }
        }
    };
</script>

{/literal}
