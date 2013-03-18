;( function( $ ) {

    $( document ).ready( function() {

	var win = window.dialogArguments || opener || parent || top;

	function esc_attr( str ) {
		return str.replace( /"/g, '\"' );
	}

	$( '.flickr-machinetag' ).click( function() {
	    $( this ).select();
	} );

	// shortcode insert
	$( '#insert-flickr-gallery' ).live( 'click', function() {
		var flickr_get = $( '.flickr-get:checked' ).val(),
		    flickr_id = $( '.flickr-select' ).length ? $( '.flickr-select' ).val() : '',
		    flickr_user = $( '.flickr-user' ).val(),
		    flickr_size = $( '.flickr-size' ).val(),
			flickr_link = $( '.flickr-link' ).val(),
		    flickr_count = '' + parseInt( $( '.flickr-count' ).val(), 10 ),
		    shortcode = '';

		// handle multiselect
		if ( flickr_id.constructor == Array )
		    flickr_id = flickr_id.join(', ');

		// medium image size is default
		if ( flickr_size == '-' )
			flickr_size = false;

		shortcode = '[' + flickrapi.shortcode +
			( flickr_user ? ' user="' + esc_attr( flickr_user ) + '"' : '' ) +
			( flickr_get ? ' get="' + esc_attr( flickr_get ) + '"' : '' ) +
			( flickr_id ? ' id="' + esc_attr( flickr_id ) + '"' : '' ) +
			( flickr_size ? ' size="' + esc_attr( flickr_size ) + '"' : '' ) +
			( flickr_link ? ' link="' + esc_attr( flickr_link ) + '"' : '' ) +
			( flickr_count ? ' count="' + esc_attr( flickr_count ) + '"' : '' ) +
		    ']';

		if ( '' != flickr_get && '' != flickr_user )
			win.send_to_editor( shortcode );

		tb_remove();
		return false;
	} );

	// switch
	$( '.flickr-get' ).live( 'click', function() {
		var $parent = $( this ).parents( '.widget' ).length ? $( this ).parents( '.widget' ) : $( this ).parents( '#flickr-form-inner' );

	    if ( $( '.flickr-get:checked', $parent ).val().match( /gallery|photoset|tags|collection|group/ ) ) {

			$.post( ajaxurl, {
				action: 'flickr_get_dropdown',
				fget: $( '.flickr-get:checked', $parent ).val(),
				username: $( '.flickr-user', $parent ).val(),
				id: $( '.flickr-dropdown-box', $parent ).attr( 'data-id' ),
				name: $( '.flickr-dropdown-box', $parent ).attr( 'data-name' )
			}, function( data ) {
				$( '.flickr-dropdown-box', $parent ).html( data );
				$( '.flickr-select', $parent ).chosen();
			} );

	    } else {

			$( '.flickr-dropdown-box', $parent ).html( '' );

	    }

	} );

	if ( pagenow == 'widgets' ) {

	    $( '#widgets-right .widget:has(.flickr-dropdown-box), #widgets-left .widget-holder-wrap:not(#available-widgets) .widget:has(.flickr-dropdown-box)' ).live( 'mouseover.chosen', function() {
		if ( ! $( '.chzn-container', this ).length )
		    $( '.flickr-dropdown-box select' ).chosen();
	    } );

	}

    } );

} )( jQuery );
