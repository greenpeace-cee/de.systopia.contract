<div class="crm-block crm-form-block">
    <div class="crm-section" id="cancel_reason">
        <div class="label">{$form.cancel_reason.label}</div>
        <div class="content">{$form.cancel_reason.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section" id="activity_date">
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

    <div class="crm-section" id="medium_id">
        <div class="label">{$form.medium_id.label}</div>
        <div class="content">{$form.medium_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section" id="note">
        <div class="label">{$form.notes.label}</div>
        <div class="content">{$form.notes.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
