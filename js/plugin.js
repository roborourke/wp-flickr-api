/**
 * Imagesloaded plugin: https://github.com/desandro/imagesloaded
 */
(function(c,n){var k="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";c.fn.imagesLoaded=function(l){function m(){var b=c(h),a=c(g);d&&(g.length?d.reject(e,b,a):d.resolve(e));c.isFunction(l)&&l.call(f,e,b,a)}function i(b,a){b.src===k||-1!==c.inArray(b,j)||(j.push(b),a?g.push(b):h.push(b),c.data(b,"imagesLoaded",{isBroken:a,src:b.src}),o&&d.notifyWith(c(b),[a,e,c(h),c(g)]),e.length===j.length&&(setTimeout(m),e.unbind(".imagesLoaded")))}var f=this,d=c.isFunction(c.Deferred)?c.Deferred():
0,o=c.isFunction(d.notify),e=f.find("img").add(f.filter("img")),j=[],h=[],g=[];e.length?e.bind("load.imagesLoaded error.imagesLoaded",function(b){i(b.target,"error"===b.type)}).each(function(b,a){var e=a.src,d=c.data(a,"imagesLoaded");if(d&&d.src===e)i(a,d.isBroken);else if(a.complete&&a.naturalWidth!==n)i(a,0===a.naturalWidth||0===a.naturalHeight);else if(a.readyState||a.complete)a.src=k,a.src=e}):m();return d?d.promise(f):f}})(jQuery);

(function($){

    $(document).ready(function(){

		function flickr_api( method, args, cache, callback ) {
			return $.post( flickrapi.ajaxurl, {
				action: 'flick_api',
				method: method,
				args: args,
				cache: cache
			}, callback, 'json' );
		}

		// leave it to the theme author if not set
		if ( flickrapi.display == '' )
			return;

		if ( flickrapi.display == 'slideshow' ) {

			$( '.flickr-gallery' ).each( function( i, item ) {

				$( this ).imagesLoaded( function() {

					var mheight = 0;

					$( 'img', this ).each( function() {
						if ( $( this ).height() > mheight )
							mheight = $( this ).height();
					} );

					$( this )
						.height( mheight )
						.attr( 'id', 'flickr-gallery-' + i )
						.append( '<div class="cycle-nav"><a class="prev" href="#prev">Prev</a> <a class="next" href="#next">Next</a></div>' )
						.find( '.figure' ).css( { opacity: 0 } ).wrapAll( '<div class="cycle-wrap" style="overflow:hidden;" />' ).end()
						.find( '.cycle-wrap' )
						.cycle( {
							fx: 'fade',
							fit: 0,
							speed: 1000,
							timeout: 6000,
							sync: 1,
							prev: '#flickr-gallery-' + i + ' .prev',
							next: '#flickr-gallery-' + i + ' .next'
						} );
				} );

			} );

			return;
		}


		// only proceed if we have galleria loaded
		if ( Galleria ) {

			// initialize flickr plugin

			Galleria.loadTheme( flickrapiGalleria.theme );

			$( '.flickr-gallery' ).each( function() {

				$( this ).imagesLoaded( function() {

					var mheight = 0;

					$( 'img', this ).each( function() {
						if ( $( this ).height() > mheight )
							mheight = $( this ).height();
					} );

					$( this ).height( mheight ).galleria( {
						dataConfig: function( img ) {
							return {
								title: $( img ).attr( 'alt' ),
								description: $( img ).next( '.caption' ).html()
							};
						}
					} );

				} );

			} );

		}

    });

})(jQuery);
