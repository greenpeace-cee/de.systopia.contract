<div class="payment-preview" data-payment-adapter="psp_sepa">
    Payment preview PSP
</div>

{literal}

<script>
    const PSP = window.PaymentAdapters["psp_sepa"];

    PSP.updatePaymentPreview = (formFields) => {
        // ...
    };
</script>

{/literal}
