{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
| B. Endres (endres -at- systopia.de)                          |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-block crm-form-block">
    <div class="crm-section payment-modify">
      <div class="label">{$form.contract_modification_reviewers.label}</div>
      <div class="content">{$form.contract_modification_reviewers.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section payment-modify">
        <div class="label">{$form.contract_domain.label}</div>
        <div class="content">{$form.contract_domain.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

</div>
