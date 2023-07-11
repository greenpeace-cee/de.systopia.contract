export function addConfirmDialog (confirmButton, formFields) {
    const clonedButton = confirmButton.clone();
    confirmButton.hide();
    confirmButton.parent().append(clonedButton);

    clonedButton.on("click", async () => {
        const adapter = getCurrentPaymentAdapter(formFields);
        const message = await adapter.confirmDialog(formFields);

        CRM.confirm({
            title: "Payment preview",
            message,
            options: {
                yes: "Confirm",
                no: "Edit",
            },
        }).on("crmConfirm:yes", () => {
            confirmButton.click();
        });
    });
}

export function displayRelevantFormFields (dataAttributes) {
    const selector = Object.keys(dataAttributes)
        .map(attr => `*[${attr}]`)
        .join(", ");

    cj(selector).each((_, element) => {
        for (const [attrName, expValue] of Object.entries(dataAttributes)) {
            if (!element.hasAttribute(attrName)) continue;

            const attrValues = element.getAttribute(attrName).split(" ");

            if (attrValues.includes(expValue)) continue;

            cj(element).hide(200);
            return;
        }

        cj(element).show(200);
    });
}

export function getCurrentPaymentAdapter (formFields) {
    const selectedPaymentAdapter = formFields["payment_adapter"].val();

    if (!window._PAYMENT_ADAPTERS_) return;
    if (!window._PAYMENT_ADAPTERS_[selectedPaymentAdapter]) return;
    return window._PAYMENT_ADAPTERS_[selectedPaymentAdapter];
}

export function getFormFields (fieldIDs) {
    const formFields = {};

    for (const fid of fieldIDs) {
        formFields[fid] = cj(`div.form-field div.content *[name=${fid}]`);
    }

    return formFields;
}

export function mapPaymentFrequency (frequency) {
    if (frequency === 1) return "annually";
    if (frequency === 2) return "semi-annually";
    if (frequency === 4) return "quarterly";
    if (frequency === 12) return "monthly";
    return "";
}

export function parseMoney(raw_value) {
    if (raw_value.length == 0) return 0.0;

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

export function registerPaymentAdapter (name, adapter) {
    if (!window._PAYMENT_ADAPTERS_) {
        window._PAYMENT_ADAPTERS_ = {};
    }

    window._PAYMENT_ADAPTERS_[name] = adapter;
}

export function updateCycleDayField (formFields, cycleDays, currentCycleDay) {
    const cycleDayField = formFields["cycle_day"];
    let selectedCycleDay = parseInt(cycleDayField.val() || currentCycleDay);

    if (!cycleDays.includes(selectedCycleDay)) {
        selectedCycleDay = undefined;
    }

    cycleDayField.empty();
    cycleDayField.append("<option value=\"\">- none -</option>");

    for (const cycleDay of cycleDays) {
        cycleDayField.append(`<option value="${cycleDay}">${cycleDay}</option>`);

        if (selectedCycleDay === cycleDay) {
            cycleDayField.val(cycleDay);
        }
    }
}
