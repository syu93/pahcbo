jQuery( function( $ ) {

  $( 'body' ).on( 'change', '.menu-item-if-menu-enable', function() {
    $( this ).closest( '.if-menu-enable' ).next().toggle( $( this ).prop( 'checked' ) );
  } );

  $( '.wrap' ).on( 'click', '.if-menu-notice button', function() {
    $.post( ajaxurl, { action: 'if_menu_hide_notice' }, function( response ) {
      if ( response != 1 ) {
        alert( 'If Menu: Error trying to hide the notice - ' + response );
      }
    } );
  } );

} );
