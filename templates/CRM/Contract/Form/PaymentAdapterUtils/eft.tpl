{literal}

<script>
    if (window._PAYMENT_ADAPTERS_ === undefined) {
        window._PAYMENT_ADAPTERS_ = {};
    }

    if (!Object.keys(window._PAYMENT_ADAPTERS_).includes("eft")) {
        window._PAYMENT_ADAPTERS_["eft"] = {};
    }

    const EFT = window._PAYMENT_ADAPTERS_["eft"];

</script>

{/literal}
