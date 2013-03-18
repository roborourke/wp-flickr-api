=== Flickr API ===
Contributors: sanchothefat
Tags: flickr, gallery, galleries, photos, images, slideshow, galleria, widget, shortcode
Requires at least: 3.0
Tested up to: 3.5.1
Stable tag: 0.1.7

A comprehensive Flickr plugin that makes it easy to show off your images in style.

== Description ==

The Flickr API plugin provides tools for displaying your flickr galleries, sets, photostream or favourites and more using a shortcode in posts and pages or as a widget. You can choose the size of image you want, whether it should be linked to another image size or back to flickr or not linked at all, how many images to show and more...

There are some built in options for displaying your images as a simple slideshow or using the Galleria jquery plugin. Alternatively you can choose to style the output yourself and use your own javascript.

For developers the plugin also gives you an easy method for calling and caching API responses both in PHP and javascript, and lots of useful tools for working with those responses.

= Usage =

You will need to get an API key from flickr to use this plugin. Under the 'You' menu look for 'Your Apps'. Click to get a key and then go to the media settings screen to add your API key and user name. The plugin will automatically determine your NSID so you never need to look this up.

To use the plugin either generate a shortcode using the media upload/insert button and selecting what you want to get or use the Flickr widget provided.

= For developers =

The plugin exposes its methods for your use in themes as template tags but the main one you may find useful is `flickr_api()`. This is a general function for calling any API method with the parameters you specify.

`<?php
$response = json_decode( flickr_api( $method, $params, $cache ) );
?>`

All responses are in JSON format so you will need to use `json_decode()` to use the response in PHP.

`@param $method: (string) 	This is the API method to call
@param $params: (array)  	Additional arguments to pass into the call such as user_id, photoset_id, gallery_id, text, tags etc...
@param $cache : (bool)	 	Whether or not to cache the response based on the arguments passed in`

You can use the API via javascript as well:

`<script>
var photos = flickr_api( method, params, cache );
</script>`

**NSID lookup:**

`<?php
$nsid = flickr_get_user_nsid( $username );
?>`

Just pass in the Flickr username of the person to get the NSID for.


= Filters/Hooks =

**flickr_galleria_themes**

You can enable the choice of custom or purchased galleria themes by extending the themes array. Useful if you want your theme to have a choice of galleria theme beyond the 'classic' style.

`<?php
add_filter( 'flickr_galleria_themes', 'my_galleria_themes' );
function my_galleria_themes( $themes ) {
    $themes[ /* full or relative url to theme js file */ ] = __( 'Theme Name' );
    return $themes;
}
?>`



== Changelog ==

= 0.1.7 =
* fixed a bug with linking to different sized photos in the output. Thanks to Cliff Seal (http://logos-creative.com) for the bug report!

= 0.1.6 =
* fixed bug with getting galleries, thanks to @strawbleu for the bug report
* added a settings field for the cache time. Defaults to 1 hour

= 0.1.5 =
* improved loading time of JS galleries
* reduced chance of errors in galleria loading by better namespacing for localisation object

= 0.1.4 =
* fixed conflict with jetpack shortcodes
* MAJOR UPDATE - change all your shortcodes to '[flickrapi ...]' and not '[flickr ...]'

= 0.1.3 =
* fixed problem with no automatic height on JS based flickr galleries

= 0.1.2 =
* fixed the 'fixed' shortcode output

= 0.1 =
* Initial alpha version