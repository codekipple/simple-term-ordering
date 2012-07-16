jQuery('table.widefat tbody th, table.widefat tbody td').css('cursor','move');

jQuery("table.widefat tbody").sortable({
  items: 'tr:not(.inline-edit-row)',
  cursor: 'move',
  axis: 'y',
  containment: 'table.widefat',
  scrollSensitivity: 40,
  helper: function(e, ui) {
    ui.children().each(function() { jQuery(this).width(jQuery(this).width()); });
    return ui;
  },
  start: function(event, ui) {
    if ( ! ui.item.hasClass('alternate') ) ui.item.css( 'background-color', '#ffffff' );
    ui.item.children('td,th').css('border-bottom-width','0');
    ui.item.css( 'outline', '1px solid #dfdfdf' );
  },
  stop: function(event, ui) {
    ui.item.removeAttr('style');
    ui.item.children('td,th').css('border-bottom-width','1px');
  },
  update: function(event, ui) {
    jQuery('table.widefat tbody th, table.widefat tbody td').css('cursor','default');
    jQuery("table.widefat tbody").sortable('disable');

    var ID = ui.item.find('.check-column input').val(); // this is the id
    var taxonomy = jQuery(this).parents('form').find('input[name$="taxonomy"]').val();
    var termparent = ui.item.find('.parent').html();   // term parent

    var prevID = ui.item.prev().find('.check-column input').val();
    var nextID = ui.item.next().find('.check-column input').val();

    // can only sort in same tree

    var prevtermparent = undefined;
    if ( prevID != undefined ) {
      var prevtermparent = ui.item.prev().find('.parent').html()
      if ( prevtermparent != termparent) prevID = undefined;
    }

    var nexttermparent = undefined;
    if ( nextID != undefined ) {
      nexttermparent = ui.item.next().find('.parent').html();
      if ( nexttermparent != termparent) nextID = undefined;
    }

    // if previous and next not at same tree level, or next not at same tree level and the previous is the parent of the next, or just moved item beneath its own children
    if ( ( prevID == undefined && nextID == undefined ) || ( nextID == undefined && nexttermparent == prevID ) || ( nextID != undefined && prevtermparent == ID ) ) {
      jQuery("table.widefat tbody").sortable('cancel');
      alert( simple_term_ordering_l10n.RepositionTree );
      jQuery('table.widefat tbody th, table.widefat tbody td').css('cursor','move');
      jQuery("table.widefat tbody").sortable('enable');
      return;
    }

    // show spinner
    ui.item.find('.check-column input').hide().after('<img alt="processing" src="images/wpspin_light.gif" class="waiting" style="margin-left: 6px;" />');

    // go do the sorting stuff via ajax
    jQuery.post( ajaxurl, { action: 'simple_term_ordering', id: ID, previd: prevID, nextid: nextID, taxonomy: taxonomy }, function(response){
      if ( response == 'children' ) window.location.reload();
      else {
        ui.item.find('.check-column input').show().siblings('img').remove();
        jQuery('table.widefat tbody th, table.widefat tbody td').css('cursor','move');
        jQuery("table.widefat tbody").sortable('enable');
      }
    });

    // fix cell colors
    jQuery( 'table.widefat tbody tr' ).each(function(){
      var i = jQuery('table.widefat tbody tr').index(this);
      if ( i%2 == 0 ) jQuery(this).addClass('alternate');
      else jQuery(this).removeClass('alternate');
    });
  }
});