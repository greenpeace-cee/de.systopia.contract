<div class="payment-preview" data-payment-adapter="psp_sepa">
    Debitor name: <span id="debitor_name"></span><br />
    Payment method: <span id="payment_method">PSP</span><br />
    Creditor: <span id="creditor"></span><br />
    Label: <span id="label"></span><br />
    Payment instrument: <span id="payment_instrument"></span><br />
    Account reference: <span id="account_reference"></span><br />
    Account name: <span id="account_name"></span><br />
    Frequency: <span id="frequency"></span><br />
    Annual amount: <span id="annual"></span><br />
    Installment amount: <span id="installment"></span><br />
    Cycle day: <span id="cycle_day"></span><br />
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

        // Label
        const label = formFields["pa-psp_sepa-label"].val();
        paymentPreviewContainer.find("span#label").text(label);

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

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const currency = CRM.vars["de.systopia.contract/eft"].default_currency;
        const annualAmount = `${(amount * frequency).toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Cycle day
        const cycleDay = formFields["pa-psp_sepa-cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);
    };
</script>

{/literal}
