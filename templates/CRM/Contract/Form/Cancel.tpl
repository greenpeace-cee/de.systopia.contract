{literal}

<script>
    import("de.systopia.contract/Form/cancel").then(({ initForm }) => initForm());
</script>

{/literal}

<div class="crm-block crm-form-block">
    <div class="crm-section form-field" id="cancel_reason">
        <div class="label">{$form.cancel_reason.label}</div>
        <div class="content">{$form.cancel_reason.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="cancel_tags">
        <div class="label">{$form.cancel_tags.label}</div>
        <div class="content">{$form.cancel_tags.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="activity_date">
        <div id="debit_before_change" class="messages warning" style="display:none">
            This contract will be cancelled <b>after</b> the next regular debit
            on <b>{$next_sched_contribution_date}</b>
        </div>

        <div class="label">
            {$form.activity_date.label}
            {help title="Scheduling updates" id="scheduling" file="CRM/Contract/Form/Scheduling.hlp"}
        </div>

        <div class="content">{$form.activity_date.html}</div>
        <div class="clear"></div>

        {if $default_to_minimum_change_date}
            <span class="content field-info">Default to minimum change date</span>
        {/if}
    </div>

    <div class="crm-section form-field" id="medium_id">
        <div class="label">{$form.medium_id.label}</div>
        <div class="content">{$form.medium_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section form-field" id="note">
        <div class="label">{$form.note.label}</div>
        <div class="content">{$form.note.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
