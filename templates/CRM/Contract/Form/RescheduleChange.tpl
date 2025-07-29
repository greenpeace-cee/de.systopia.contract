{literal}

<script>
    import("de.systopia.contract/Form/reschedule").then(({ initForm }) => initForm());
</script>

{/literal}

<div class="crm-block crm-form-block">
    <div class="crm-section form-field" id="activity_date">
        <div class="label">{$form.activity_date.label}</div>
        <div class="content">{$form.activity_date.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
