{literal}

<script>
    function mapFrequency ({ interval, unit }) {
        if (interval === "1" && unit === "month") return "12";
        if (interval === "3" && unit === "month") return "4";
        if (interval === "6" && unit === "month") return "2";
        if (interval === "12" && unit === "month") return "1";
        if (interval === "1" && unit === "year") return "1";

        return "1";
    };

    if (window._PAYMENT_ADAPTERS_ === undefined) {
        window._PAYMENT_ADAPTERS_ = {};
    }

    if (!Object.keys(window._PAYMENT_ADAPTERS_).includes("sepa_mandate")) {
        window._PAYMENT_ADAPTERS_["sepa_mandate"] = {};
    }

    const SEPAMandate = window._PAYMENT_ADAPTERS_["sepa_mandate"];

    SEPAMandate.parseMoney = (raw_value) => {
        if (raw_value.length == 0) {
            return 0.0;
        }

        // find out if there's a problem with ','
        var stripped_value = raw_value.replace(' ', '');

        if (stripped_value.includes(',')) {
            // if there are at least three digits after the ','
            //  it's a thousands separator
            if (stripped_value.match('#,\d{3}#')) {
                // it's a thousands separator -> just strip
                stripped_value = stripped_value.replace(',', '');
            } else {
                // it has to be interpreted as a decimal
                // first remove all other decimals
                stripped_value = stripped_value.replace('.', '');
                stripped_value = stripped_value.replace(',', '.');
            }
        }

        return parseFloat(stripped_value);
    }


    SEPAMandate.clearPaymentParameters = (formFields) => {
        formFields["amount"].val("");
        formFields["pa-sepa_mandate-cycle_day"].val("1");
        formFields["frequency"].val("12");
        formFields["pa-sepa_mandate-iban"].val("");
    };

    SEPAMandate.fillPaymentParameters = (formFields) => {
        // Installment amount (amount)
        const amount = SEPAMandate.parseMoney(CRM.vars["de.systopia.contract"].current_amount);
        formFields["amount"].val(amount);

        // Cycle day (pi-sepa_mandate-cycle_day)
        const cycle_day =
            CRM.vars["de.systopia.contract"].action === "revive"
            ? CRM.vars["de.systopia.contract/sepa_mandate"].next_cycle_day
            : CRM.vars["de.systopia.contract"].current_cycle_day;

        formFields["pa-sepa_mandate-cycle_day"].val(cycle_day);

        // Payment frequency (frequency)
        const frequency = mapFrequency(CRM.vars["de.systopia.contract"].current_frequency);
        formFields["frequency"].val(frequency);

        // IBAN (payment-sepa_mandate-iban)
        const iban = CRM.vars["de.systopia.contract/sepa_mandate"].current_iban;
        formFields["pa-sepa_mandate-iban"].val(iban);
    };
</script>

{/literal}
