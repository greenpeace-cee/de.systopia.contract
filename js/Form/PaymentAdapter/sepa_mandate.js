import { registerPaymentAdapter } from "../utils.js";

class SEPA {
    constructor() {
        const extVars = CRM.vars["de.systopia.contract"];
        const adapterVars = CRM.vars["de.systopia.contract/sepa_mandate"];

        this.action = extVars.action;
        this.creditor = adapterVars.creditor;
        this.currentCycleDay = extVars.current_cycle_day;
        this.cycleDays = adapterVars.cycle_days;
        this.debitorName = extVars.debitor_name;
        this.defaultCreditorGrace = adapterVars.default_creditor_grace;
        this.defaultCreditorNotice = adapterVars.default_creditor_notice;
        this.defaultCurrency = adapterVars.default_currency;
        this.frequencies = extVars.frequencies;
        this.graceEnd = extVars.grace_end;
        this.nextCycleDay = adapterVars.next_cycle_day;
        this.nextInstallmentDate = adapterVars.next_installment_date;
    }

    nextCollectionDate (cycle_day, start_date, grace_end, defer_payment_start) {
        cycle_day = parseInt(cycle_day);

        if (cycle_day < 1 || cycle_day > 30) {
            alert("Illegal cycle day detected: " + cycle_day);
            return "Error";
        }

        // earliest contribution date is: max(now+notice, start_date, grace_end)

        // first: calculate the earliest possible collection date
        var notice = parseInt(this.defaultCreditorNotice);
        var grace  = parseInt(this.defaultCreditorGrace);
        var earliest_date = defer_payment_start ? new Date(this.nextInstallmentDate) : new Date();

        // see https://stackoverflow.com/questions/6963311/add-days-to-a-date-object
        earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + Math.max(0, (notice-grace)) * 86400000));

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

        return earliest_date.getFullYear() + '-' + month + '-' + day;
    }

    onFormChange (formFields) {
        // Currency
        cj("span#currency").text(this.defaultCurrency);

        // Cycle days
        const cycleDayField = formFields["cycle_day"];
        const defaultCycleDay = this.action === "sign" ? this.nextCycleDay : this.currentCycleDay;
        const selectedCycleDay = cycleDayField.val() || defaultCycleDay;
        const cycleDayOptions = this.cycleDays;

        cycleDayField.empty();
        cycleDayField.append("<option value=\"\">- none -</option>");

        for (const cycleDay of Object.values(cycleDayOptions)) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (parseInt(selectedCycleDay) === parseInt(cycleDay)) {
                cycleDayField.val(cycleDay);
            }
        }

        this.updatePaymentPreview(formFields);
    }

    updatePaymentPreview (formFields) {
        const paymentPreviewContainer = cj("div.payment-preview[data-payment-adapter=sepa_mandate]");

        // Debitor name
        paymentPreviewContainer.find("span#debitor_name").text(this.debitorName);

        // Debitor account
        const iban = formFields["pa-sepa_mandate-iban"].val();
        paymentPreviewContainer.find("span#iban").text(iban);

        // Creditor name
        const creditor = this.creditor;
        paymentPreviewContainer.find("span#creditor_name").text(creditor.name);

        // Creditor account
        paymentPreviewContainer.find("span#creditor_iban").text(creditor.iban);

        // Frequency
        const frequency = Number(formFields["frequency"].val());
        paymentPreviewContainer.find("span#frequency").text(this.frequencies[frequency]);

        // Annual amount
        const amount = FormUtils.parseMoney(formFields["amount"].val());
        const annualAmount = `${(amount * frequency).toFixed(2)} ${creditor.currency}`;
        paymentPreviewContainer.find("span#annual").text(annualAmount);

        // Installment amount
        const installmentAmount = `${amount.toFixed(2)} ${creditor.currency}`;
        paymentPreviewContainer.find("span#installment").text(installmentAmount);

        // Next debit
        const deferPaymentStart = formFields["defer_payment_start"]
            ? formFields["defer_payment_start"].prop("checked")
            : false;

        const cycleDay = formFields["cycle_day"].val();

        const startDate = formFields["start_date"]
            ? formFields["start_date"].val()
            : formFields["activity_date"].val();

        const graceEnd = this.action === "update" ? this.graceEnd : null;
        const nextDebit = this.nextCollectionDate(cycleDay, startDate, graceEnd, deferPaymentStart);
        paymentPreviewContainer.find("span#next_debit").text(nextDebit);
    }
}

registerPaymentAdapter("sepa_mandate", new SEPA());
