/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


CRM.$(function($) {

  var contractStatuses = CRM.vars['de.systopia.contract'].contractStatuses;

  // # Link to membership create should be replaced with contract create #
  $('a[href="/civicrm/contact/view/membership?reset=1&action=add&cid=' + CRM.vars['de.systopia.contract'].cid + '&context=membership"]')
  .attr("href", "/civicrm/contract/create?cid=" + CRM.vars['de.systopia.contract'].cid);

  // Add a membership-review tr with appropriate colspan under each row to
  // receive the contract history
  $( "#memberships tr.crm-membership, #inactive-memberships tr.crm-membership" ).each( function(){
    var elementId = $(this).attr('id');
    var membershipId = elementId.substr(elementId.indexOf("_") + 1);
    decorateRow(membershipId);

    // update/create the review link
    var link = $(document).find('#crm-membership-review-link_' + membershipId);
    if (link.length == 0) {
      var queryURL = CRM.url('civicrm/contract/review', 'reset=&snippet=1&id=' + membershipId);
      link = " <a class='toggle-review' id='crm-membership-review-link_" + membershipId + "' href='" + queryURL + "'>" + getReviewLinkText(membershipId) + "</a>";
      $(this).after("<tr class='crm-membership crm-membership-review odd odd-row' id='crm-membership-review_" + membershipId + "'><td colspan='" + $(this).find('td').length + "'></td></tr>");
      $(this).find('td.crm-membership-status').append(link);
    }
  });

  // hide the .membership-review tr by default
  $( "tr.crm-membership-review" ).hide();

  function getReviewLinkText(membershipId){
    if(contractStatuses[membershipId].needs_review > 0){
      return 'needs review';
    }else if(contractStatuses[membershipId].scheduled > 0) {
      return 'scheduled modifications';
    }
    return 'review';
  }

  function decorateRow(membershipId){
    var contractStatus = contractStatuses[membershipId];
    var membershipRow = $(document).find('#crm-membership_' + membershipId);

    if(contractStatus.needs_review > 0){
      membershipRow.addClass('needs-review');
    }else{
      membershipRow.removeClass('needs-review');
    }

    if(contractStatus.scheduled > 0){
      membershipRow.addClass('scheduled');
    }else{
      membershipRow.removeClass('scheduled');
    }

    if(contractStatus.failed > 0){
      membershipRow.addClass('failed');
    }else{
      membershipRow.removeClass('failed');
    }
  }

  function toggleReviewPane(membershipId){
    var reviewRow = $(document).find('#crm-membership-review_' + membershipId);
    var reviewLink = $(document).find('#crm-membership-review-link_' + membershipId);
    if(reviewRow.is(":visible")){
      reviewRow.hide();
      reviewLink.html(getReviewLinkText(membershipId));
    }else{
      var queryURL = CRM.url('civicrm/contract/review', 'reset=&snippet=1&id=' + membershipId);
      reviewRow.find('td').load(queryURL, function(){
        reviewRow.show();
        reviewLink.html('hide');
      });
    }
  }

  function updateReviewPane(membershipId){
    var reviewRow = $(document).find('#crm-membership-review_' + membershipId);
    var queryURL = CRM.url('civicrm/contract/review', 'reset=&snippet=1&id=' + membershipId);
    reviewRow.find('td').load(queryURL);
  }

  function updateMembershipRow(membershipId){
    CRM.api3('Contract', 'get_open_modification_counts', {
      "id": membershipId
    }).done(function(result) {
      contractStatuses[membershipId] = result.values;
      decorateRow(membershipId);
    });
  }

  // We have to ensure the listeners are only added once (this seems like a
  // fairly mad way to do that but anyhow...)

  if(CRM.vars['de.systopia.contract'].listenersLoaded !== true){

    // Clicking on the toggle-review link toggles the membership-review tr
    $(document).on( 'click', '.toggle-review', function(e){
      e.preventDefault();
      var elementId = $(this).attr('id');
      var membershipId = elementId.substr(elementId.indexOf("_") + 1);
      toggleReviewPane(membershipId);
    });

    // Modification edit activities open in a pop up
    $(document).on( 'click', '.edit-activity', CRM.popup);

    // When modification edit activity pop ups are closed, update the appropriate
    // membership review pane and membership row
    $(document).on( 'crmPopupFormSuccess', '.edit-activity', function(){
      var elementId = $(this).parents('.crm-membership-review').attr('id');
      var membershipId = elementId.substr(elementId.indexOf("_") + 1);
      updateReviewPane(membershipId);
      updateMembershipRow(membershipId);
    });

    $(document).on( 'crmPopupFormSuccess', '.action-item', function(){
      var elementId = $(this).parents('.crm-membership').attr('id');
      if (elementId) {
        var membershipId = elementId.substr(elementId.indexOf("_") + 1);
        // TODO: anything to do here?
      }
    });

    CRM.vars['de.systopia.contract'].listenersLoaded = true;
  }
});
