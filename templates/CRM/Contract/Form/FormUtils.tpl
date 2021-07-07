{literal}

<script>
    window.FormUtils = {
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