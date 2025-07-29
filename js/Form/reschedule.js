const EXT_VARS = CRM.vars["de.systopia.contract"];

export async function initForm () {
    const activityDateField = cj(`div.form-field div.content *[name=activity_date]`);
    const datepickerField = activityDateField.parent().find("input.hasDatepicker");
    const minChangeDate = new Date(EXT_VARS.minimum_change_date);

    // Adjust for the right time zone
    minChangeDate.setTime(minChangeDate.getTime() + minChangeDate.getTimezoneOffset() * 60_000);

    datepickerField.datepicker(
        "option",
        "beforeShowDay",
        (date) => [date.getTime() >= minChangeDate.getTime(), ""]
    );

    datepickerField.focus();
}
