<div class="crm-block crm-form-block">
    <div class="crm-section" id="resume_date">
        <div class="label">{$form.resume_date.label}</div>
        <div class="content">{$form.resume_date.html}</div>
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
        <div class="label">{$form.note.label}</div>
        <div class="content">{$form.note.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
