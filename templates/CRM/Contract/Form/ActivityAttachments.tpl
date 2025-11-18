{literal}

<script>
    import("de.systopia.contract/Form/manage-attachments").then(({ initForm }) => initForm());
</script>

{/literal}

<div class="crm-block crm-form-block">
    <ul id="activity-attachments">
        {foreach from=$files item=file}
            <li data-file-id="{$file.id}">
                <a href="{$file.url}">{$file.name}</a>
                <a
                    class="icon delete-icon delete-attachment"
                    title="Delete attachment"
                    data-file-id="{$file.id}"
                    data-file-name="{$file.name}"
                    >
                </a>
            </li>
        {/foreach}
    </ul>

    <div class="crm-section form-field" id="attachment">
        <div class="label">{$form.attachment.label}</div>
        <div class="content">{$form.attachment.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
