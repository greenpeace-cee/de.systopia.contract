CRM.$(function ($) {
  $('[id^="membership_general"] div.crm-accordion-body').prepend('' +
    '<table class="crm-info-panel">' +
      '<tr>' +
        '<td class="label">' + ts('Membership scan') + '</td>' +
        '<td class="html-adjust">' +
          '<a href="CONTRACT_FILE_DOWNLOAD" target="_blank">' + ts('Download') + '</a>' +
        '</td>' +
      '</tr>' +
    '</table>'
  );
});
