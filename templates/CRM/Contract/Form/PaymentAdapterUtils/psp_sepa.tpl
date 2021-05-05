{literal}

<script>
    if (window._PAYMENT_ADAPTERS_ === undefined) {
        window._PAYMENT_ADAPTERS_ = {};
    }

    if (!Object.keys(window._PAYMENT_ADAPTERS_).includes("psp_sepa")) {
        window._PAYMENT_ADAPTERS_["psp_sepa"] = {};
    }

    const PSPSEPA = window._PAYMENT_ADAPTERS_["psp_sepa"];

</script>

{/literal}
