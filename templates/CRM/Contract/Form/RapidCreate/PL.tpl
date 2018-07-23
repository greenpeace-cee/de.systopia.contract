{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-block crm-form-block">

  <h3>Create a new contact with associated contract and mandate</h3>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.gender_id.label}</div>
    <div class="content">{$form.gender_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.formal_title.label}</div>
    <div class="content">{$form.formal_title.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.first_name.label}</div>
    <div class="content">{$form.first_name.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.last_name.label}</div>
    <div class="content">{$form.last_name.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.phone.label}</div>
    <div class="content">{$form.phone.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.email.label}</div>
    <div class="content">{$form.email.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.postal_code.label}</div>
    <div class="content">{$form.postal_code.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.street_address.label}</div>
    <div class="content">{$form.street_address.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.city.label}</div>
    <div class="content">{$form.city.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.state_province_id.label}</div>
    <div class="content">{$form.state_province_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.country_id.label}</div>
    <div class="content">{$form.country_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.birth_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=birth_date}</div>
    <div class="clear"></div>
  </div>
  <hr/>

  <div class="crm-section">
    <div class="label">Payment Preview</div>
    <div class="content recurring-contribution-summary-text">None</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section payment-create">
    <div class="label">{$form.cycle_day.label}</div>
    <div class="content">{$form.cycle_day.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.iban.label}</div>
    <div class="content">{$form.iban.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.payment_amount.label}</div>
    <div class="content">{$form.payment_amount.html}&nbsp;<span id="payment_amount_currency"></span></div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.payment_frequency.label}</div>
    <div class="content">{$form.payment_frequency.html}</div>
    <div class="clear"></div>
  </div>

  <hr />
  <div class="crm-section">
    <div class="label">{$form.join_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=join_date}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.start_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=start_date}</div>
    <div class="clear"></div>
  </div>
  <hr />
  <div class="crm-section">
    <div class="label">{$form.campaign_id.label}</div>
    <div class="content">{$form.campaign_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_type_id.label}</div>
    <div class="content">{$form.membership_type_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.activity_medium.label}</div>
    <div class="content">{$form.activity_medium.html}</div>
    <div class="clear"></div>
  </div>
  <hr />
  <div class="crm-section">
    <div class="label">{$form.membership_reference.label}</div>
    <div class="content">{$form.membership_reference.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_venue.label}</div>
    <div class="content">{$form.membership_venue.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_ts_week.label}</div>
    <div class="content">{$form.membership_ts_week.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_dialoger.label}</div>
    <div class="content">{$form.membership_dialoger.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_channel.label}</div>
    <div class="content">{$form.membership_channel.html}</div>
    <div class="clear"></div>
  </div>

  <hr />

  <div class="crm-section">
    <div class="label">{$form.activity_details.label}</div>
    <div class="content">{$form.activity_details.html}</div>
    <div class="clear"></div>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.post_delivery_only_online.label}</div>
    <div class="content">{$form.post_delivery_only_online.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.interest.label}</div>
    <div class="content">{$form.interest.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.talk_topic.label}</div>
    <div class="content">{$form.talk_topic.html}</div>
    <div class="clear"></div>
  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.community_newsletter.label}</div>
    <div class="content">{$form.community_newsletter.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.groups.label}</div>
    <div class="content">{$form.groups.html}</div>
    <div class="clear"></div>
  </div>


  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>


{if $bic_lookup_accessible}
  {include file="CRM/Contract/Form/bic_lookup.tpl" location="bottom"}
{/if}

{literal}
<script type="text/javascript">
/**
 * update the payment info shown
 */
function updatePaymentSummaryText() {
  // render the current SEPA values
  var creditor        = CRM.vars['de.systopia.contract'].creditor;
  var cycle_day       = cj('[name=cycle_day]').val();
  var iban            = cj('[name=iban]').val();
  var installment     = parseMoney(cj('[name=payment_amount]').val());
  var freqency        = cj('[name=payment_frequency]').val();
  var freqency_label  = CRM.vars['de.systopia.contract'].frequencies[freqency];
  var start_date      = cj('[name=start_date]').val();
  var annual          = 0.0;
  var first_collection = nextCollectionDate(cycle_day, start_date);

  // caculcate the installment
  if (!isNaN(installment)) {
    annual = (installment.toFixed(2) * parseFloat(freqency)).toFixed(2);
  }

  // TODO: use template
  cj('.recurring-contribution-summary-text').html(
    // "Debitor name: " + debitor_name + "<br/>" +
    // "Debitor account: " + iban + "<br/>" +
    "Creditor name: " + creditor.name + "<br/>" +
    "Creditor account: " + creditor.iban + "<br/>" +
    "Payment method: SEPA Direct Debit<br/>" +
    "Frequency: " + freqency_label + "<br/>" +
    "Annual amount: " + annual + " " + creditor.currency + "<br/>" +
    "Installment amount: " + installment.toFixed(2) + " " + creditor.currency + "<br/>" +
    "Next debit: " + first_collection + "<br/>"
    );

  cj('#payment_amount_currency').text(creditor.currency);
}

// call once for the UI to adjust
cj(document).ready(function() {
  updatePaymentSummaryText();
  cj("div.payment-create").change(updatePaymentSummaryText);
  cj("#start_date").parent().parent().change(updatePaymentSummaryText);
});

</script>
{/literal}
