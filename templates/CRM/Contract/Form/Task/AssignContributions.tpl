{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-section">
  <div class="label">{$form.contract_id.label}</div>
  <div class="content">{$form.contract_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
    <div class="label">{$form.reassign.label} <a onclick='CRM.help("{ts domain="de.systopia.contract"}Re-Assign{/ts}", {literal}{"id":"id-re-assign","file":"CRM\/Contract\/Form\/Task\/AssignContributions"}{/literal}); return false;' href="#" title="{ts domain="de.systopia.contract"}Help{/ts}" class="helpicon">&nbsp;</a></td></div>
    <div class="content">{$form.reassign.html}</div>
    <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.adjust_financial_type.label} <a onclick='CRM.help("{ts domain="de.systopia.contract"}Adjust Financial Type{/ts}", {literal}{"id":"id-adjust-financial-type","file":"CRM\/Contract\/Form\/Task\/AssignContributions"}{/literal}); return false;' href="#" title="{ts domain="de.systopia.contract"}Help{/ts}" class="helpicon">&nbsp;</a></td></div>
  <div class="content">{$form.adjust_financial_type.html}&nbsp;<span class="membership-financial-type">(unknown)</span></div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.adjust_campaign.label} <a onclick='CRM.help("{ts domain="de.systopia.contract"}Adjust Campaign{/ts}", {literal}{"id":"id-adjust-campaign","file":"CRM\/Contract\/Form\/Task\/AssignContributions"}{/literal}); return false;' href="#" title="{ts domain="de.systopia.contract"}Help{/ts}" class="helpicon">&nbsp;</a></td></div>
  <div class="content">{$form.adjust_campaign.html}&nbsp;<span class="membership-campaign">(unknown)</span></div>
  <div class="clear"></div>
</div>

<div class="crm-section non-sepa-contract-only">
  <div class="label">{$form.assign_mode.label} <a onclick='CRM.help("{ts domain="de.systopia.contract"}Assign to Recurring Contribution{/ts}", {literal}{"id":"id-assign-mode","file":"CRM\/Contract\/Form\/Task\/AssignContributions"}{/literal}); return false;' href="#" title="{ts domain="de.systopia.contract"}Help{/ts}" class="helpicon">&nbsp;</a></td></div>
  <div class="content">{$form.assign_mode.html}</div>
  <div class="clear"></div>
</div>

{include file="CRM/common/formButtons.tpl" location="bottom"}


<script type="text/javascript">
var contracts = {$contracts};
{literal}

/*******************************
 *   campaign changed handler  *
 ******************************/
cj("#contract_id").change(function() {
  // rebuild segment list
  var contract = contracts[cj("#contract_id").val()];
  // show financial type
  cj("span.membership-financial-type").html("(" + contract['financial_type'] + ")");
  // show campaign
  if (contract['rcur_campaign'] === null) {
    cj("span.membership-campaign").html("(Contribution campaign will be removed)");
  } else {
    cj("span.membership-campaign").html("(" + contract['rcur_campaign'] + ")");
  }


  // show non-sepa options
  if (contract['sepa_mandate_id']) {
      cj("div.non-sepa-contract-only").hide(300);
  } else {
      cj("div.non-sepa-contract-only").show(300);
  }
});

// fire off event once
cj("#contract_id").change();
{/literal}
</script>
