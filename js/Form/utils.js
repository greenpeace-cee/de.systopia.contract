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

export function getFormFields (fieldIDs) {
    const formFields = {};

    for (const fid of fieldIDs) {
        formFields[fid] = cj(`div.form-field div.content *[name=${fid}]`);
    }

    return formFields;
}

export function registerPaymentAdapter (name, adapter) {
    if (!window._PAYMENT_ADAPTERS_) {
        window._PAYMENT_ADAPTERS_ = {};
    }

    window._PAYMENT_ADAPTERS_[name] = adapter;
}
