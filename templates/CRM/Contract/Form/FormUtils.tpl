{literal}

<script>
    window.FormUtils = {
        mapFrequency: ({ interval, unit }) => {
            if (interval === "1" && unit === "month") return "12";
            if (interval === "3" && unit === "month") return "4";
            if (interval === "6" && unit === "month") return "2";
            if (interval === "12" && unit === "month") return "1";
            if (interval === "1" && unit === "year") return "1";

            return "1";
        },

        parseMoney: (raw_value) => {
            if (raw_value.length == 0) {
                return 0.0;
            }

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
        },
    };
</script>

{/literal}