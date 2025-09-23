{literal}

<script>
    import("de.systopia.contract/Form/edit-membership").then(({ initForm }) => initForm());
</script>

{/literal}

<div class="crm-block crm-form-block">
    <div class="crm-section form-field" id="membership_reference">
        <div class="label">{$form.membership_reference.label}</div>
        <div class="content">{$form.membership_reference.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_contract">
        <div class="label">{$form.membership_contract.label}</div>
        <div class="content">{$form.membership_contract.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_dialoger">
        <div class="label">{$form.membership_dialoger.label}</div>
        <div class="content">{$form.membership_dialoger.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_channel">
        <div class="label">{$form.membership_channel.label}</div>
        <div class="content">{$form.membership_channel.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="membership_referrer">
        <div class="label">{$form.membership_referrer.label}</div>
        <div class="content">{$form.membership_referrer.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="contract_file">
        <div class="label">{$form.contract_file.label}</div>
        <div class="content">{$form.contract_file.html}</div>

        {if $contract_file != null}
            <div class="content attachment">
                Attached file:
                <a href="{$contract_file.url}">{$contract_file.file_name}</a>
                <a
                    class="icon delete-icon"
                    id="delete-attachment"
                    title="Delete attachment"
                    data-file-id="{$contract_file.id}"
                    data-file-name="{$contract_file.file_name}"
                    href="#">
                </a>
            </div>
        {/if}

        <div class="clear"></div>
    </div>

    <hr />

    <div class="crm-section form-field" id="join_date">
        <div class="label">{$form.join_date.label}</div>
        <div class="content">{$form.join_date.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="start_date">
        <div class="label">{$form.start_date.label}</div>
        <div class="content">{$form.start_date.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
