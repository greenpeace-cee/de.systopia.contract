<div class="crm-block crm-form-block">
    Confirm update {$activity_date} {$next_sched_contribution_date}

    <div class="crm-section" id="pause_until_update">
        <div class="label">{$form.pause_until_update.label}</div>
        <div class="content">{$form.pause_until_update.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
