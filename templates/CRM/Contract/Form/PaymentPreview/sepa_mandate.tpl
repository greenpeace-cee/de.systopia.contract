<div class="payment-preview" data-payment-adapter="sepa_mandate">
    Debitor name: <span id="debitor_name"></span><br />
    Debitor account: <span id="iban"></span><br />
    Creditor name: <span id="creditor_name"></span><br />
    Creditor account: <span id="creditor_iban"></span><br />
    Payment method: <span id="payment_method">SEPA Direct Debit</span><br />
    Frequency: <span id="frequency"></span><br />
    Annual amount: <span id="annual"></span><br />
    Installment amount: <span id="installment"></span><br />
    Next debit: <span id="next_debit"></span><br />
</div>

{literal}

<script>
    const SEPAMandate = window.PaymentAdapters["sepa_mandate"];

    SEPAMandate.updatePaymentPreview = (formFields) => {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=sepa_mandate]");

        // Debitor name
        const debitorName = CRM.vars["de.systopia.contract"].debitor_name;
        paymentPreviewContainer.find("span#debitor_name").text(debitorName);

        // Debitor account
        const iban = formFields["pi-sepa_mandate-iban"].val();
        paymentPreviewContainer.find("span#iban").text(iban);

        // Creditor name
        const creditor = CRM.vars["de.systopia.contract/sepa_mandate"].creditor;
        paymentPreviewContainer.find("span#creditor_name").text(creditor.name);

        // Creditor account
        paymentPreviewContainer.find("span#creditor_iban").text(creditor.iban);

        // Frequency
        const frequencyMapping = CRM.vars["de.systopia.contract"].frequencies;
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(frequencyMapping[frequency]);

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${creditor.currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${creditor.currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Next debit
        const action = CRM.vars["de.systopia.contract"].action;
        const cycleDay = formFields["pi-sepa_mandate-cycle_day"].val();
        const startDate = formFields["activity_date"].val();
        const graceEnd = action === "update" ? CRM.vars["de.systopia.contract"].grace_end : null;
        const nextDebit = nextCollectionDate(cycleDay, startDate, graceEnd);
        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }

    function nextCollectionDate(cycle_day, start_date, grace_end) {
        cycle_day = parseInt(cycle_day);

        if (cycle_day < 1 || cycle_day > 30) {
            alert("Illegal cycle day detected: " + cycle_day);
            return "Error";
        }

        // earliest contribution date is: max(now+notice, start_date, grace_end)

        // first: calculate the earliest possible collection date
        var notice = parseInt(CRM.vars['de.systopia.contract/sepa_mandate'].default_creditor_notice);
        var grace  = parseInt(CRM.vars['de.systopia.contract/sepa_mandate'].default_creditor_grace);
        var earliest_date = new Date();
        // see https://stackoverflow.com/questions/6963311/add-days-to-a-date-object
        earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + (notice-grace) * 86400000));

        // then: take start date into account
        if (start_date) {
            start_date = new Date(start_date);

            if (start_date.getTime() > earliest_date.getTime()) {
                earliest_date = start_date;
            }
        }

        // then: take grace period into account
        if (grace_end) {
            grace_end = new Date(grace_end);

            if (grace_end.getTime() > earliest_date.getTime()) {
                earliest_date = grace_end;
            }
        }

        // now move to the next cycle day
        var safety_check = 65; // max two months

        while (earliest_date.getDate() != cycle_day && safety_check > 0) {
            // advance one day
            earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + 86400000));
            safety_check = safety_check - 1;
        }

        if (safety_check == 0) {
            console.log("Error, cannot cycle to day " + cycle_day);
        }

        // format to YYYY-MM-DD. Don't use toISOString() (timezone mess-up)
        var month = earliest_date.getMonth() + 1;
        month = month.toString();

        if (month.length == 1) {
            month = '0' +  month;
        }

        var day = earliest_date.getDate().toString();

        if (day.length == 1) {
            day = '0' + day;
        }

        // console.log(earliest_date.getFullYear() + '-' + month + '-' + day);
        return earliest_date.getFullYear() + '-' + month + '-' + day;
    }
</script>

{/literal}