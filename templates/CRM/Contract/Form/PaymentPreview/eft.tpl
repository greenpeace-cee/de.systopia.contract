<div class="payment-preview" data-payment-adapter="eft">
    Debitor name: <span id="debitor_name"></span><br />
    Payment method: <span id="payment_method">EFT</span><br />
    Frequency: <span id="frequency"></span><br />
    Annual amount: <span id="annual"></span><br />
    Installment amount: <span id="installment"></span><br />
    Cycle day: <span id="cycle_day"></span><br />
</div>

{literal}

<script>
    const EFT = window.PaymentAdapters["eft"];

    EFT.updatePaymentPreview = (formFields) => {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=eft]");

        // Debitor name
        const debitorName = CRM.vars["de.systopia.contract"].debitor_name;
        paymentPreviewContainer.find("span#debitor_name").text(debitorName);

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
        const cycleDay = formFields["cycle_day"].val();
        paymentPreviewContainer.find("span#cycle_day").text(cycleDay);
    };
</script>

{/literal}
