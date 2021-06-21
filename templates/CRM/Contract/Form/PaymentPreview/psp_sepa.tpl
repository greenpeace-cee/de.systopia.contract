<div class="payment-preview" data-payment-adapter="psp_sepa">
    Debitor name: <span id="debitor_name"></span><br />
    Payment method: <span id="payment_method">PSP</span><br />
    Creditor: <span id="creditor"></span><br />
    Payment instrument: <span id="payment_instrument"></span><br />
    Account reference: <span id="account_reference"></span><br />
    Account name: <span id="account_name"></span><br />
    Frequency: <span id="frequency"></span><br />
    Annual amount: <span id="annual"></span><br />
    Installment amount: <span id="installment"></span><br />
    Cycle day: <span id="cycle_day"></span><br />
    Next debit: <span id="next_debit"></span><br />
</div>

{literal}

<script>
    const PSP = window.PaymentAdapters["psp_sepa"];

    PSP.updatePaymentPreview = (formFields) => {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=psp_sepa]");

        // Debitor name
        const debitorName = CRM.vars["de.systopia.contract"].debitor_name;
        paymentPreviewContainer.find("span#debitor_name").text(debitorName);

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
        const frequencyMapping = CRM.vars["de.systopia.contract"].frequencies;
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(frequencyMapping[frequency]);

        // Currency
        const currency =
            CRM.vars["de.systopia.contract/psp_sepa"].currencies[creditorId]
            || CRM.vars["de.systopia.contract"].default_currency;

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = formFields["pa-psp_sepa-cycle_day"].val() || "";
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);

        // Next debit
        const action = CRM.vars["de.systopia.contract"].action;
        const graceEnd = action === "update" ? CRM.vars["de.systopia.contract"].grace_end : null;
        const startDate = formFields["start_date"] ? formFields["start_date"].val() : null;
        const nextDebit = PSP.nextCollectionDate({ creditorId, cycleDay, graceEnd, startDate });
        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    };

    PSP.nextCollectionDate = ({ creditorId, cycleDay, graceEnd, startDate }) => {
        if (!cycleDay) return "";

        let date = new Date();

        const grace = parseInt(CRM.vars["de.systopia.contract/psp_sepa"].grace_days[creditorId]);
        const notice = parseInt(CRM.vars["de.systopia.contract/psp_sepa"].notice_days[creditorId]);
        const millisecondsInADay = 24 * 60 * 60 * 1000;

        date.setTime(date.getTime() + (notice - grace) * millisecondsInADay);

        if (startDate && new Date(startDate).getTime() > date.getTime()) {
            date = new Date(startDate);
        }

        if (graceEnd && new Date(graceEnd).getTime() > date.getTime()) {
            date = new Date(graceEnd);
        }

        const cycleDay_int = parseInt(cycleDay, 10);

        if (cycleDay_int < date.getDate()) {
            let i = 31;

            while (i > 0 && date.getDate() !== cycleDay_int) {
                date.setTime(date.getTime() + millisecondsInADay);
                i--;
            }
        } else {
            date.setDate(cycleDay_int);
        }

        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString(10).padStart(2, "0");
        const day = date.getDate().toString(10).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }
</script>

{/literal}
