const EXT_VARS = CRM.vars["de.systopia.contract"];

export class PaymentAdapter {
    cycleDays = [];
    frequencyOptions = {};

    constructor({ formType, formFields }) {
        this.formType = formType;
        this.formFields = formFields;
    }

    updateCycleDayField() {
        const cycleDayField = this.formFields["cycle_day"];
        let selectedCycleDay = parseInt(cycleDayField.val() || EXT_VARS.current_cycle_day);

        if (!this.cycleDays.includes(selectedCycleDay)) {
            selectedCycleDay = undefined;
        }

        cycleDayField.empty();
        cycleDayField.append("<option value=\"\">- none -</option>");

        for (const cycleDay of this.cycleDays) {
            cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

            if (selectedCycleDay === cycleDay) {
                cycleDayField.val(cycleDay);
            }
        }
    }

    updateFrequencyField() {
        const frequencyField = this.formFields["frequency"];
        const selectedFrequency = parseInt(frequencyField.val() ?? EXT_VARS.current_frequency);

        frequencyField.empty();

        const optionEntries = Object.entries(this.frequencyOptions)
            .sort(([valA], [valB]) => parseInt(valB) - parseInt(valA));

        for (const [value, label] of optionEntries) {
            frequencyField.append(`<option value="${value}">${label}</option>`);

            if (parseInt(value) === selectedFrequency) {
                frequencyField.val(value);
            }
        }
    }
}
