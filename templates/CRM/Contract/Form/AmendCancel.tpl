<div class="crm-block crm-form-block">
    {if $activity['id'] != $membership['most_recent_activity_id'] || $membership['status_id:name'] != 'Cancelled'}
        <div class="messages warning">
            This is not the most recent activity for this contract!
        </div>
    {/if}

    <div class="crm-section" id="cancel_reason">
        <div class="label">{$form.cancel_reason.label}</div>
        <div class="content">{$form.cancel_reason.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section" id="cancel_tags">
        <div class="label">{$form.cancel_tags.label}</div>
        <div class="content">{$form.cancel_tags.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section" id="medium_id">
        <div class="label">{$form.medium_id.label}</div>
        <div class="content">{$form.medium_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section" id="details">
        <div class="label">{$form.details.label}</div>
        <div class="content">{$form.details.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>
