export function displayRelevantFormFields (dataAttributes) {
    const selector = Object.keys(dataAttributes)
        .map(attr => `*[${attr}]`)
        .join(", ");

    cj(selector).each((_, element) => {
        for (const [attrName, expValue] of Object.entries(dataAttributes)) {
            if (!element.hasAttribute(attrName)) continue;

            const attrValues = element.getAttribute(attrName).split(" ");

            if (attrValues.includes(expValue.toString())) continue;

            cj(element).hide();
            return;
        }

        cj(element).show();
    });
}

export function formatDateYMD(date) {
    const year = date.getFullYear().toString(10);
    const month = (date.getMonth() + 1).toString(10).padStart(2, "0");
    const day = date.getDate().toString(10).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

export function nextCollectionDate(params) {
    return CRM.api3("Contract", "start_date", params).then(
        result => result?.values?.at(0),
        error => console.error(error.message),
    );
}

export function parseMoney(value) {
    value = value.replaceAll(" ", "");

    if (value.length < 1) return 0.0;

    if (!value.includes(",")) return parseFloat(value);

    if (value.match(/,\d{3}/)) {
        // The comma is a thousands separator and can be removed
        return parseFloat(value.replaceAll(",", ""));
    } else {
        // The comma has to be interpreted as a decimal point
        return parseFloat(value.replaceAll(".", "").replaceAll(",", "."));
    }
}
