<?php
/*
Plugin Name: Flickr API
Plugin URI: http://wordpress.org/extend/plugins/flickr-api/
Description: Use a shortcode or widget to display flickr photos in your posts & pages.
Author: Robert O'Rourke
Version: 0.1.7
Author URI: http://sanchothefat.com/
*/

/*

Changelog

= 0.1.1 =
- fixed shortcode insert script
- added a notifcation and disabled UI stuff if no API key present

= 0.1.2 =
- fixed the 'fixed' shortcode output

= 0.1.3 =
* fixed problem with no automatic height on JS based flickr galleries

= 0.1.4 =
- fix jetpack conflict
- MAJOR CHANGE, shortcode is now 'flickrapi', please update your posts/pages

= 0.1.5 =
- improved loading time of JS galleries
- reduced chance of errors in galleria loading by better namespacing for localisation object

= 0.1.6 =
- fixed a bug including galleries
- added a cache time settings field

= 0.1.7 =
- fixed a bug with operator syntax thanks to Cliff Seal (http://logos-creative.com)

*/

if ( ! defined( 'FLICKR_API_PLUGIN_URL' ) )
    define( 'FLICKR_API_PLUGIN_URL', plugins_url( '', __FILE__ ) );

if ( ! defined( 'FLICKR_API_PLUGIN_BASE' ) )
    define( 'FLICKR_API_PLUGIN_BASE', basename( __FILE__ ) );

if ( ! defined( 'FLICKR_API_SHORTCODE' ) )
	define( 'FLICKR_API_SHORTCODE', 'flickrapi' );
	
if ( ! defined( 'FLICKR_API_CACHE_TIME' ) )
	define( 'FLICKR_API_CACHE_TIME', 3600 );

class Flickr_API_Plugin {

	var $flickr_api_codes = array();

	var $photo_extras = '';

	var $image_sizes = array();

	var $galleria_themes = array();

	function Flickr_API_Plugin() {

		$this->flickr_api_codes = array(
			1 	=> __( 'User not found. The specified user NSID was not a valid user.' ),
			100 => __( 'Invalid API Key. The API key passed was not valid or has expired.'),
			105 => __( 'Service currently unavailable. The requested service is temporarily unavailable.' ),
			111 => __( 'Format not found. The requested response format was not found.' ),
			112 => __( 'Method not found. The requested method was not found.' ),
			114 => __( 'Invalid SOAP envelope. The SOAP envelope send in the request could not be parsed.' ),
			115 => __( 'Invalid XML-RPC Method Call. The XML-RPC request document could not be parsed.' ),
			116 => __( 'Bad URL found. One or more arguments contained a URL that has been used for abuse on Flickr.' )
		);

		$this->photo_extras = implode( ', ', apply_filters( 'flickr_photo_extras', array(
			'description', 'license', 'date_upload', 'date_taken',
			'owner_name', 'icon_server', 'original_format',
			'last_update', 'geo', 'tags', 'machine_tags', 'o_dims',
			'views', 'media', 'path_alias',
			//'url_sq', 'url_t', 'url_s', 'url_m', 'url_z', 'url_l', 'url_o'
		) ) );

		$this->image_sizes = array(
			's' => __( 'Small square, 75x75' ),					// s	small square 75x75
			't' => __( 'Thumbnail, 100px on long side' ),		// t	thumbnail, 100 on longest side
			'm' => __( 'Medium Small, 240px on long side' ),	// m	small, 240 on longest side
			'-'  => __( 'Medium, 500px on long side' ),			// -	medium, 500 on longest side
			'z' => __( 'Medium Large, 640px on long side' ), 	// z	medium 640, 640 on longest side
			'b' => __( 'Large, 1024px on long side' ),			// b	large, 1024 on longest side*
			'o' => __( 'Original' )								// o	original image, either a jpg, gif or png, depending on source format
		);

		$this->galleria_themes = apply_filters( 'flickr_galleria_themes', array(
			FLICKR_API_PLUGIN_URL . '/galleria/themes/classic/galleria.classic.' . ( WP_DEBUG ? '' : 'min.' ) . 'js' => __( 'Classic' )
		) );

		// add settings
		add_action( 'admin_init', array( &$this, 'flickr_settings' ) );


		// if no API key then don't do this
		if ( ! get_option( 'flickr_api_key' ) ) {
			add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
			return;
		}

		add_action( 'wp', array( &$this, 'flickr_scripts' ) );

		// add meta box for machine tag

		/* Define the custom box */
		add_action( 'add_meta_boxes', array( &$this, 'flickr_box' ) );

		// media button
		add_action( 'media_buttons', array( &$this, 'flickr_shortcode_button' ), 10000 );

		// shortcode form
		add_action( 'admin_print_footer_scripts', array( &$this, 'flickr_box_form' ), 250000 );

		// dropdown ajax
		add_action( 'wp_ajax_flickr_get_dropdown', array( &$this, 'flickr_get_dropdown' ) );

	}


	//admin notice when no API key
	function admin_notices() {
		echo '<div id="flickr-api-no-key" class="updated fade"><p>' . sprintf( __( 'To start using the Flick API plugin you need to %s and then update the %s' ), '<a href="http://www.flickr.com/services/apps/by/">' . __( 'get an API key from Flickr' ) . '</a>', '<a href="options-media.php#flickr-options">' . __( 'plugin settings' ) . '</a>' ) . '.</p></div>';
	}


	function flickr_scripts() {
		global $post;

		if ( ! is_admin() ) {

			$display = get_option( 'flickr_display' );

			if ( $display == 'galleria' ) {
				wp_enqueue_script( 'galleria', FLICKR_API_PLUGIN_URL . "/galleria/galleria-1.2.5.min.js", array( 'jquery' ), '1.2.5' );
				$theme_urls = array_keys( $this->galleria_themes );
				$theme = get_option( 'flickr_galleria_theme', $theme_urls[ 0 ] );
				wp_localize_script( 'galleria', 'flickrapiGalleria', array(
					'theme' => $theme
				) );
			}

			if ( $display == 'slideshow' ) {
				wp_enqueue_script( 'jquery-cycle-lite', FLICKR_API_PLUGIN_URL . '/js/jquery.cycle.lite.1.1.min.js', array( 'jquery' ), '1.1' );
			}

			wp_enqueue_script( 'flickr-api-plugin', FLICKR_API_PLUGIN_URL . '/js/plugin.js', array( 'jquery' ) );
			wp_localize_script( 'flickr-api-plugin', 'flickrapi', array(
			    'apikey' 	=> get_option( 'flickr_api_key' ),
			    'baseurl' 	=> FLICKR_API_PLUGIN_URL,
			    'ajaxurl' 	=> admin_url( 'admin-ajax.php' ),
			    'display' 	=> $display,
				'shortcode' => FLICKR_API_SHORTCODE
			) );

		}
	}


	// get the first image attached to the current post
	function flickr_get_attachments( $size = 'thumbnail' ) {
		global $post;
		return get_children( array( 'post_parent' => $post->ID, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'ASC', 'orderby' => 'menu_order ID' ) );
	}


	function settings_link( $links, $file ) {
		if ( $file ==  plugin_basename( __FILE__ ) )
			array_unshift( $links, '<a href="options-media.php#flickr-options">' . __( "Settings" ) . '</a>' );
		return $links;
	}


	/* Adds a box to the main column on the Post and Page edit screens */
	function flickr_box() {
		// add for photos only
		foreach ( get_post_types() as $post_type )
			add_meta_box( 'flickr', __( 'Flickr' ), array( &$this, 'flickr_machinetag_html' ), $post_type, 'side' );
	}

	/* Prints the box content */
	function flickr_machinetag_html() {
		$post_id = intval( $_GET['post'] );
		?>
		<p>
		    <?php _e( 'Use the following machine tag in flickr to attach images to this post.' ); ?>
		</p>
		<div class="tag">
		    <code class="flickr-machinetag"><?php echo get_option( 'flickr_machinetag', preg_replace( "/([a-z0-9-]+)(\.[a-z]{2,4}){1,2}$/si", "$1", preg_replace( "/^http:\/\/(w{3}.)?/", "", home_url() ) ) ); ?>:p=<?php echo $post_id; ?></code>
		</div>
		<?php
	}

	function flickr_shortcode_button() {
		?><a class="thickbox" href="#TB_inline?width=640&amp;height=557&amp;inlineId=flickr-form" title="<?php _e( 'Insert a Flickr Gallery' ); ?>"><img src="<?php echo FLICKR_API_PLUGIN_URL . '/gfx/flickr-media-button.png' ?>" alt="<?php _e( 'Flickr Gallery' ); ?>" /></a><?php
	}


	function flickr_box_form(  ) {

		$gets = array(
			'gallery' => __( 'Gallery' ),
			'photoset' => __( 'Photoset' ),
			'machinetagged' => __( 'Machine tagged images for this post' ),
			'tags' => __( 'Tags' ),
			'collection' => __( 'Collection' ),
			'favourites' => __( 'Favourites' ),
			'group' => __( 'Group' ),
			'photostream' => __( 'Photostream' )
		);

	?>
	<div id="flickr-form" style="display:none;">
		<div id="flickr-form-inner">
			<p>
				<label for="flickr-user"><?php _e( 'Username to get photos for' ); ?></label>
				<input type="text" name="flickr_user" class="flickr-user widefat" id="flickr-user" value="<?php esc_attr_e( get_option( 'flickr_username' ) ); ?>" />
			</p>

			<fieldset>
				<legend for="flickr-get"><?php _e( "Select where you want to get images from on Flickr" ); ?></legend>
				<ul>
				<?php foreach( $gets as $get => $label ) { ?>
					<li><label for="flickr-<?php echo $get; ?>"><input class="flickr-get" value="<?php echo $get; ?>" type="radio" id="flickr-<?php echo $get; ?>" name="flickr_get" /> <?php _e( $label ); ?></label></li>
				<?php } ?>
				</ul>
			</fieldset>

			<div class="flickr-dropdown-box" data-id="flickr-select" data-name="flickr_select"></div>

			<p>
				<label for="flickr-size"><?php _e( 'Image size' ); ?></label>
				<select id="flickr-size" name="flickr_size" class="flickr-size widefat">
					<?php foreach( $this->image_sizes as $size => $desc ) { ?>
					<option value="<?php esc_attr_e( $size ); ?>" <?php if ( $size == 'z' ) echo ' selected="selected"'; ?>><?php esc_html_e( $desc ); ?></option>
					<?php } ?>
				</select>
			</p>

			<p>
				<label for="flickr-link"><?php _e( 'Link options' ); ?></label>
				<select id="flickr-link" name="flickr_link" class="flickr-link widefat">
					<option value=""><?php _e( 'No link' ); ?></option>
					<option value="flickr"><?php _e( 'Flickr photo page' ) ?></option>
					<optgroup label="<?php _e( 'Link to another image size' ); ?>">
					<?php foreach( $this->image_sizes as $size => $desc ) { ?>
						<option value="<?php esc_attr_e( $size ); ?>"><?php esc_html_e( $desc ); ?></option>
					<?php } ?>
					</optgroup>
				</select>
			</p>

			<p>
				<label for="flickr-count"><?php _e( 'Number of photos to show' ); ?></label>
				<input type="text" name="flickr_count" class="flickr-count widefat" id="flickr-count" value="100" />
			</p>

			<p>
				<a class="button" id="insert-flickr-gallery" href="#insert-flickr"><?php _e( 'Insert Flickr gallery' ); ?></a>
			</p>
		</div>
	</div>
	    <?php
	}


	function flickr_get_dropdown( $get = '', $username = 'me', $id = '', $name = '', $selected = '' ) {

		if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'flickr_get_dropdown' ) {
			$get 		= sanitize_key( $_POST[ 'fget' ] );
			$username 	= sanitize_text_field( $_POST[ 'username' ] );
			$id 		= sanitize_text_field( $_POST[ 'id' ] );
			$name 		= sanitize_key( $_POST[ 'name' ] );
		}

		switch( $get ) {
			case 'gallery':
				$data = flickr_get_galleries( $username );
				if ( is_object( $data ) && count( $data->galleries->gallery ) ) { ?>
					<div class="gallery">
						<label for="<?php echo $id; ?>"><?php _e( 'Select a gallery' ); ?></label>
						<select name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="flickr-select widefat">
							<?php foreach( $data->galleries->gallery as $gallery ) { ?>
							<option <?php selected( $selected, $gallery->id ); ?> value="<?php esc_attr_e( $gallery->id ); ?>"><?php echo $gallery->title->_content;  ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } else { ?>
					<div class="flickr-none">
						<?php _e( 'No galleries found for that user' ); ?>
					</div>
				<?php }
				break;
			case 'photoset':
				$data = flickr_get_photosets( $username );
				if ( is_object( $data ) && count( $data->photosets->photoset ) ) { ?>
					<div class="photoset">
						<label for="<?php echo $id; ?>"><?php _e( 'Select a photoset' ); ?></label>
						<select name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="flickr-select widefat">
							<?php foreach( $data->photosets->photoset as $set ) { ?>
							<option <?php selected( $selected, $set->id ); ?> value="<?php esc_attr_e( $set->id ); ?>"><?php echo $set->title->_content; ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } else { ?>
					<div class="flickr-none">
						<?php _e( 'No photosets found for that user' ); ?>
					</div>
				<?php }
				break;
			case 'tags':
				$data = flickr_get_tags( $username );
				$selected = explode( ', ', $selected );
				if ( count( $data->who->tags->tag ) ) { ?>
					<div class="tag">
						<label for="<?php echo $id; ?>"><?php _e( 'Select one or more tags' ); ?></label>
						<select name="<?php echo $name; ?>[]" id="<?php echo $id; ?>" multiple="multiple" class="flickr-select widefat flickr-tag-select">
							<?php foreach( $data->who->tags->tag as $tag ) { ?>
							<option<?php echo in_array( $tag->_content, $selected ) ? ' selected="selected"' : ''; ?> value="<?php esc_attr_e( $tag->_content ); ?>"><?php echo $tag->_content;  ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } else { ?>
					<div class="flickr-none">
						<?php _e( 'No tags found for that user' ); ?>
					</div>
				<?php }
				break;
			case 'collection':
				$data = flickr_get_collections( $username );
				if ( count( $data->collections->collection ) ) { ?>
					<div class="collection">
						<label for="<?php echo $id; ?>"><?php _e( 'Select a collection' ); ?></label>
						<select name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="flickr-select widefat">
							<?php foreach( $data->collections->collection as $collection ) { ?>
							<option <?php selected( $selected, $collection->id ); ?> value="<?php esc_attr_e( $collection->id ); ?>"><?php echo $collection->title->_content; ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } else { ?>
					<div class="flickr-none">
						<?php _e( 'No collections found for that user' ); ?>
					</div>
				<?php }
				break;
			case 'group':
				$data = flickr_get_groups( $username );
				if ( count( $data->groups->group ) ) { ?>
					<div class="group">
						<label for="<?php echo $id; ?>"><?php _e( 'Select a group' ); ?></label>
						<select name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="flickr-select widefat">
							<?php foreach( $data->groups->group as $group ) { ?>
							<option <?php selected( $selected, $group->nsid ); ?> value="<?php esc_attr_e( $group->nsid ); ?>"><?php echo $group->name; ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } else { ?>
					<div class="flickr-none">
						<?php _e( 'No groups found for that user' ); ?>
					</div>
				<?php }
				break;
			default:
				return false;
				break;
		}

		if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'flickr_get_dropdown' )
			die;

	}

	// Settings API stuff
	function flickr_settings() {

		// admin scripts
		wp_enqueue_style( 'chosen', FLICKR_API_PLUGIN_URL . '/js/chosen/chosen.css' );
		wp_register_script( 'chosen', FLICKR_API_PLUGIN_URL . '/js/chosen/chosen.jquery.min.js', array( 'jquery' ) );

		wp_enqueue_style( 'flickr-api-plugin-admin', FLICKR_API_PLUGIN_URL . '/css/admin.css' );
		wp_enqueue_script( 'flickr-api-plugin-admin', FLICKR_API_PLUGIN_URL . '/js/admin.js', array( 'jquery', 'chosen' ) );
		wp_localize_script( 'flickr-api-plugin-admin', 'flickrapi', array(
			'shortcode' => FLICKR_API_SHORTCODE
		) );

		// settings fields
		add_settings_section( 'flickr', __('Flickr'), array( &$this, 'flickr_settings_form' ), 'media' );

		add_settings_field( 'flickr_api_key', __( 'API Key' ), array( &$this, 'flickr_api_key_field' ), 'media', 'flickr' );
		register_setting( 'media', 'flickr_api_key', array( &$this, 'flickr_save_api' ) );

		add_settings_field( 'flickr_api_secret', __( 'Secret' ), array( &$this, 'flickr_api_secret_field' ), 'media', 'flickr' );
		register_setting( 'media', 'flickr_api_secret', 'sanitize_text_field' );

		add_settings_field( 'flickr_username', __( 'Username' ), array( &$this, 'flickr_username_field' ), 'media', 'flickr' );
		register_setting( 'media', 'flickr_username', 'sanitize_text_field' );

		add_settings_field( 'flickr_nsid', __( 'NSID' ), array( &$this, 'flickr_nsid_field' ), 'media', 'flickr' );
		// register_setting( 'media', 'flickr_nsid', 'sanitize_text_field' );

		add_settings_field( 'flickr_machinetag', __( 'Machine Tag Base' ), array( &$this, 'flickr_machinetag_field' ), 'media', 'flickr' );
		register_setting( 'media', 'flickr_machinetag', 'sanitize_text_field' );

		add_settings_field( 'flickr_display', __( 'Default gallery display' ), array( &$this, 'flickr_display_field' ), 'media', 'flickr' );
		register_setting( 'media', 'flickr_display', 'sanitize_text_field' );
		register_setting( 'media', 'flickr_galleria_theme', 'esc_url_raw' );

		add_settings_field( 'flickr_api_cache_time', __( 'Cache time (seconds)' ), array( &$this, 'flickr_cache_time_field' ), 'media', 'flickr' );
		register_setting( 'media', 'flickr_api_cache_time', 'intval' );
		
		// settings link on plugin page
		add_filter( 'plugin_action_links', array( &$this, 'settings_link' ), 10, 2 );

	}

	function flickr_settings_form() { ?>
		<p><?php _e( 'Please enter your API key and your flickr username. The API secret key field is optional.' ); ?></p><?php
	}

	function flickr_save_api( $value ) {
		global $flickr_api_codes;

		// check api key
		$test = json_decode( flickr_api( 'flickr.test.echo', array( 'api_key' => $value ), false ) );

		if ( isset( $test ) && $test->stat == 'fail' ) {
			// set error message
			add_settings_error( 'flickr_api_key', $test->code, $flickr_api_codes[ $test->code ] );
			return sanitize_text_field( $value );
		}

		// get user id for api key / username
		$username = isset( $_POST[ 'flickr_username' ] ) ? sanitize_text_field( $_POST[ 'flickr_username' ] ) : get_option( 'flickr_username' );
		if ( $username && ! empty( $username ) ) {
			$nsid = flickr_get_user_nsid( $username );

			if ( is_wp_error( $nsid ) )
				return sanitize_text_field( $value );

			if ( ! get_option( 'flickr_nsid' ) )
				add_option( 'flickr_nsid', '' );
			update_option( 'flickr_nsid', $nsid );
		}

		return sanitize_text_field( $value );
	}

	function flickr_api_key_field() { ?>
	    <input class="regular-text code" type="text" size="50" name="flickr_api_key" value="<?php esc_attr_e( get_option( 'flickr_api_key' ) ); ?>" /><?php
	}

	function flickr_api_secret_field() { ?>
	    <input class="regular-text code" type="password" size="50" name="flickr_api_secret" value="<?php esc_attr_e( get_option( 'flickr_api_secret' ) ); ?>" /><?php
	}

	function flickr_username_field() { ?>
	    <input type="text" size="50" name="flickr_username" value="<?php esc_attr_e( get_option( 'flickr_username' ) ); ?>" /><?php
	}

	function flickr_nsid_field() { ?>
	    <input type="text" size="50" disabled="disabled" name="flickr_nsid" value="<?php esc_attr_e( get_option( 'flickr_nsid' ) ); ?>" /><?php
	}

	function flickr_machinetag_field() { ?>
	    <input type="text" size="50" name="flickr_machinetag" value="<?php esc_attr_e( get_option( 'flickr_machinetag', preg_replace( "/([a-z0-9-]+)(\.[a-z]{2,4}){1,2}$/si", "$1", preg_replace( "/^http:\/\/(w{3}.)?/", "", home_url() ) ) ) ); ?>" /><?php
	}
	
	function flickr_cache_time_field() { ?>
	    <input type="text" size="10" name="flickr_api_cache_time" value="<?php esc_attr_e( get_option( 'flickr_api_cache_time', FLICKR_API_CACHE_TIME ) ); ?>" /><?php
	}

	function flickr_display_field() {
		$display = get_option( 'flickr_display', '' );
		$theme_urls = array_keys( $this->galleria_themes );
		$galleria_theme = get_option( 'flickr_galleria_theme', $theme_urls[ 0 ] );
		?>
		<p><label for="flickr-display-none"><input id="flickr-display-none" type="radio" name="flickr_display" value="" <?php checked( '', $display ); ?> /> <?php _e( 'Plain HTML (No styling)' ); ?></label></p>
		<p><label for="flickr-display-slideshow"><input id="flickr-display-slideshow" type="radio" name="flickr_display" value="slideshow" <?php checked( 'slideshow', $display ); ?> /> <?php _e( 'Simple slide show' ); ?></label></p>
		<p><label for="flickr-display-galleria"><input id="flickr-display-galleria" type="radio" name="flickr_display" value="galleria" <?php checked( 'galleria', $display ); ?> /> <?php _e( 'Galleria' ); ?></label>
			<select name="flickr_galleria_theme">
				<?php foreach( $this->galleria_themes as $url => $theme ) { ?>
				<option <?php selected( $galleria_theme, $url ); ?> value="<?php echo $url; ?>"><?php echo $theme; ?></option>
				<?php } ?>
			</select>
		</p>
		<?php
	}

}


$flickr_api_plugin = new Flickr_API_Plugin();
function flickr_api_plugin() {
	global $flickr_api_plugin;
	return $flickr_api_plugin;
}


class flickr_api {

	function __construct() {

	}

	function get() {

	}

}


add_shortcode( FLICKR_API_SHORTCODE, 'flickr_api_shortcode' );
function flickr_api_shortcode( $atts ) {
	extract( shortcode_atts( array(
		'get' => false,
		'id' => '',
		'user' => 'me',
		'size' => '',
		'count' => '',
		'link' => ''
	), $atts ) );

	return get_flickr_photos( $get, $id, $user, $size, $count, $link );
}

function get_flickr_photos( $get = '', $id = '', $user ='me', $size = '', $count = 100, $link = '' ) {
	global $post;

	$output = '';

	if ( $size == '-' ) $size = '';

	switch( $get ) {
	    case 'photostream':
		$output = flickr_get_photostream_photos( $user, $size, $count, $link );
		break;
	    case 'favourites':
		$output = flickr_get_favourite_photos( $user, $size, $count, $link );
		break;
	    case 'machinetagged':
		$output = flickr_get_tag_photos( get_option( 'flickr_machinetag', preg_replace( "/([a-z0-9-]+)(\.[a-z]{2,4}){1,2}$/si", "$1", preg_replace( "/^http:\/\/(w{3}.)?/", "", home_url() ) ) ) . ':p=' . $post->ID, $size, $count, $link );
		break;
	    case 'tags':
		$output = flickr_get_tag_photos( $id, $size, $count, $link );
		break;
	    case 'gallery':
		$output = flickr_get_gallery_photos( $id, $size, $count, $link );
		break;
	    case 'photoset':
		$output = flickr_get_photoset_photos( $id, $size, $count, $link );
		break;
	    case 'collection':
		$output = flickr_get_collection_photos( $id, $size, $count, $link );
		break;
	    case 'group':
		$output = flickr_get_group_photos( $id, $size, $count, $link );
		break;
	    default:
		break;
	}

	return $output;
}


// flickr API calls (using REST)
function flickr_api( $method = '', $args = array(), $cache = true ) {
	global $flickr_api;

	// crap out if no api key
	if ( false === ( $api_key = get_option( 'flickr_api_key' ) ) || empty( $method ) )
		return false;

	// build the API parameters
	$params = wp_parse_args( apply_filters( 'flickr_api_args', $args, $method ), array(
		'api_key'		=> $api_key,
		'format'		=> 'json',	// get json because people may want to use the data in javascript. Some how.
		'nojsoncallback' 	=> 1,
		'method' 		=> $method
	) );

	// this is how we cache json responses in transients
	$args_hash = 'flickr_' . md5( serialize( array( $method, $args ) ) );

	// turn cache off in debug mode
	if ( WP_DEBUG )
		$cache = false;
	
	// check cache and fetch if empty
	if ( ! $cache || false === ( $response = get_transient( $args_hash ) ) ) {

		$url = "http://api.flickr.com/services/rest/";
		$response = wp_remote_post( $url, array(
			'body' => $params
		) );

		if ( is_wp_error( $response ) )
			return $response;

		$response = $response[ 'body' ];

		// if not an error code set the transient
		$rsp = json_decode( $response );
		if ( $rsp->stat == 'ok' ) {
			set_transient( $args_hash, $response, FLICKR_API_CACHE_TIME ); // cache the json
		} elseif ( isset( $rsp->code ) ) {
			$error = new WP_Error( $rsp->code, $flickr_api->flickr_api_codes[ $rsp->code ], $rsp );
		}
	}

	// check response status
	return $response;
}


add_action('wp_ajax_flickr_api', 'flickr_ajax_api');
add_action('wp_ajax_nopriv_flickr_api', 'flickr_ajax_api');
function flickr_ajax_api() {

	$method = sanitize_key( $_REQUEST[ 'method' ] );
	$args 	= $_REQUEST[ 'args' ];
	$cache 	= (bool)$_REQUEST[ 'cache' ];

	echo flickr_api( $method, $args, $cache );

	die();
}

function flickr_photo_extras() {
	global $flickr_api_plugin;
	return $flickr_api_plugin->photo_extras;
}

/*
 * Retrieve certain photos from flickr and create output including error if it is
 *
 * @param $get (String) possible values are 'user', 'set', 'gallery', 'group', 'search' or 'tags'
 * @param $args (Array|String) array containing extra data to search on eg. a user within a group or a string value of a user id, group id, search term etc...
 */

function flickr_get_photos( $photos, $size = '', $link = '', $show_description = false, $format = 'jpg', $echo = false ) {

	// size guide
	// s	small square 75x75
	// t	thumbnail, 100 on longest side
	// m	small, 240 on longest side
	// -	medium, 500 on longest side
	// z	medium 640, 640 on longest side
	// b	large, 1024 on longest side*
	// o	original image, either a jpg, gif or png, depending on source format

	if ( $size == '-' ) $size = '';

	if ( is_string( $photos ) )
		$photos = json_decode( $photos );

	// photosets don't follow standard photo response
	if ( isset( $photos->photoset ) )
		$data = $photos->photoset->photo;

	// standard photo response
	if ( isset( $photos->photos ) )
		$data = $photos->photos->photo;

	if ( ! isset( $data ) || ! is_array( $data ) ) {
		// create error
		return false;
	}

	// start the reactor
	ob_start();
	?>
	<div class="gallery flickr-gallery image-size-<?php esc_attr_e( $size ); ?>"><?php
	if ( count( $data ) ) { foreach( $data as $photo ) {

		$url = '';
		if ( is_string( $link ) && ! empty( $link ) ) {
			if ( $link == 'flickr' )
				$url = flickr_get_photo_page_url( $photo );
			else
				$url = flickr_get_photo_url( $photo, $link );
		}

		?>
		<div class="figure">
			<?php do_action( 'flickr_photo_before', $photo, $size ); ?>
			<?php if ( ! empty( $url ) ) echo '<a href="'. $url .'">'; ?>
			<img class="flickr-photo size-<?php esc_attr_e( $size ); ?>" src="<?php echo flickr_get_photo_url( $photo, $size ); ?>" alt="<?php esc_attr_e( $photo->title ); ?>" />
			<?php if ( ! empty( $url ) ) echo '</a>'; ?>
			<?php if ( $show_description && isset( $photo->description ) && ! empty( $photo->description->_content ) ) { ?>
			<p class="caption"><?php echo $photo->description->_content; ?></p>
			<?php } ?>
			<?php do_action( 'flickr_photo_after', $photo, $size ); ?>
		</div><?php
	} } ?>
	</div><?php

	$html = ob_get_clean();

	if ( $echo )
		echo $html;
	return $html;

}


// URL builders
function flickr_get_photo_url( $photo, $size, $format = 'jpg' ) {
	if ( ! is_object( $photo ) )
		return '';

	// the format is pretty important!
	if ( ! $format || empty( $format ) )
		$format = 'jpg';

	if ( $size == '-' ) $size = '';

	if ( ! empty( $size ) ) $size = "_$size";

	if ( $size == '_o' )
		return "http://farm{$photo->farm}.static.flickr.com/{$photo->server}/{$photo->id}_{$photo->originalsecret}$size.{$photo->originalformat}";
	return "http://farm{$photo->farm}.static.flickr.com/{$photo->server}/{$photo->id}_{$photo->secret}$size.jpg";
}

function flickr_get_photo_page_url( $photo ) {
	if ( ! is_object( $photo ) )
		return '';
	return "http://www.flickr.com/photos/{$photo->owner}/{$photo->id}";
}

function flickr_get_photostream_url( $user_id ) {
	if ( preg_match( "/@/", $user_id ) )
		return "http://www.flickr.com/photos/{$user_id}/";
	return "http://www.flickr.com/photos/" . flickr_get_user_nsid( $user_id ) . '/';
}

function flickr_get_profile_url( $user_id ) {
	if ( preg_match( "/@/", $user_id ) )
		return "http://www.flickr.com/people/{$user_id}/";
	return "http://www.flickr.com/people/" . flickr_get_user_nsid( $user_id ) . '/';
}

function flickr_get_photosets_url( $user_id ) {
	return flickr_get_photostream_url( $user_id ) . 'sets/';
}

function flickr_get_photoset_url( $user_id, $photoset_id ) {
	return flickr_get_photosets_url( $user_id ) . $photoset_id;
}

if ( ! function_exists( 'base_encode' ) ) {
	function base_encode($num, $alphabet) {
		$base_count = strlen($alphabet);
		$encoded = '';
		while ($num >= $base_count) {
			$div = $num/$base_count;
			$mod = ($num-($base_count*intval($div)));
			$encoded = $alphabet[$mod] . $encoded;
			$num = intval($div);
		}

		if ($num) $encoded = $alphabet[$num] . $encoded;

		return $encoded;
	}
}

function flickr_get_short_photo_url( $photo ) {
	if ( is_object( $photo ) )
		$photo = $photo->id;
	return "http://flic.kr/p/" . base_encode( 58, $photo );
}


// get and cache flickr nsid's by default and by username
function flickr_get_user_nsid( $username = 'me' ) {

	// get username option if exists and not supplied to function
	if ( $username == 'me' && false === ( $username = get_option( 'flickr_username' ) ) )
		return false;

	// if we have the nsid already
	if ( false != ( $nsid = get_option( "flickr_nsid_$username" ) ) )
		return $nsid;

	// if we get to this point make a request
	$userinfo = json_decode( flickr_api( 'flickr.people.findByUsername', array( 'username' => $username ) ) );

	if ( $userinfo->stat == 'ok' ) {
		$nsid = $userinfo->user->nsid;

		// set nsid option
		add_option( "flickr_nsid_$username", $nsid );
		return $nsid;
	}

	return false;
}


// get lists
function flickr_search( $text = '', $count = 100, $args = array( ) ) {

	$search = flickr_api( 'flickr.photos.search', wp_parse_args( $args, array(
		'text' => $text,
		'per_page' => $count,
		'extras' => flickr_photo_extras()
	) ) );

	return json_decode( $search );

}

	function flickr_get_photostream_photos( $user = 'me', $size = 'm', $count = 100, $link = '', $format = 'jpg', $output = 'html' ) {

		if ( empty( $user ) )
			return false;

		$photos = flickr_api( 'flickr.photos.search', array(
			'user_id' => flickr_get_user_nsid( $user ),
			'per_page' => $count,
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			return flickr_get_photos( $photos, $size, $link );

		} elseif ( $output == 'array' ) {

			return json_decode( $photos );

		}

		return $photos;

	}

function flickr_get_galleries( $user = 'me' ) {

	$galleries = flickr_api( 'flickr.galleries.getList', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $galleries );

}
	function flickr_get_gallery_photos( $gallery = '', $size = 'm', $count = 100, $link = '', $format = 'jpg', $output = 'html' ) {

		if ( empty( $gallery ) )
			return false;

		$photos = flickr_api( 'flickr.galleries.getPhotos', array(
			'gallery_id' => $gallery,
			'per_page' => $count,
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			return flickr_get_photos( $photos, $size, $link );

		} elseif ( $output == 'array' ) {

			return json_decode( $photos );

		}

		return $photos;

	}


function flickr_get_photosets( $user = 'me' ) {

	$photosets = flickr_api( 'flickr.photosets.getList', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $photosets );

}
	function flickr_get_photoset_photos( $photoset = '', $size = 'm', $count = 100, $link = '', $format = 'jpg', $output = 'html' ) {

		if ( empty( $photoset ) )
			return false;

		$photos = flickr_api( 'flickr.photosets.getPhotos', array(
			'photoset_id' => $photoset,
			'per_page' => $count,
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			return flickr_get_photos( $photos, $size, $link );

		} elseif ( $output == 'array' ) {

			return json_decode( $photos );

		}

		return $photos;

	}

function flickr_get_contacts( $user = 'me' ) {

	$contacts = flickr_api( 'flickr.contacts.getPublicList', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $contacts );

}


function flickr_get_collections( $user = 'me' ) {

	$collections = flickr_api( 'flickr.collections.getTree', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $collections );

}
	function flickr_get_collection_photos( $collection = '', $size = 'm', $count = 100, $link = '', $username = 'me', $format = 'jpg', $output = 'html' ) {

		if ( empty( $collection ) )
			return false;

		$data = flickr_api( 'flickr.collections.getTree', array(
			'collection_id' => $collection,
			'user_id' => flickr_get_user_nsid( $username ),
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			ob_start();

			echo '<div class="collection">';
			echo '<h2 class="collection-title">'. $data->collections->collection->title .'</h2>';
			echo '<p class="collection-description">'. $data->collections->collection->description .'</p>';

			foreach( $data->collections->collection->set as $set ) {
				echo '<div class="set">';
				echo '<h3 class="set-title">'. $set->title .'</h3>';
				echo '<p class="set-description">'. $set->title .'</p>';
				echo flickr_get_photoset_photos( $set->id, $size, $count, $link );
				echo '</div>';
			}

			echo '</div>';

			$html = ob_get_clean();

			return $html;

		} elseif ( $output == 'array' ) {

			return json_decode( $data );

		}

		return $data;

	}

function flickr_get_tags( $user = 'me' ) {

	$tags = flickr_api( 'flickr.tags.getListUser', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $tags );

}
	function flickr_get_tag_photos( $tags = '', $size = 'm', $count = 100, $link = '', $format = 'jpg', $username = 'me', $output = 'html' ) {

		if ( empty( $tags ) )
			return false;

		if ( is_array( $tags ) )
			$tags = implode( ', ', $tags );

		$photos = flickr_api( 'flickr.photos.search', array(
			'user_id' => flickr_get_user_nsid( $username ),
			'tags' => $tags,
			'per_page' => $count,
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			return flickr_get_photos( $photos, $size, $link );

		} elseif ( $output == 'array' ) {

			return json_decode( $photos );

		}

		return $photos;

	}

function flickr_get_favourites( $user = 'me' ) {

	$favourites = flickr_api( 'flickr.favorites.getPublicList', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $favourites );

}
	function flickr_get_favourite_photos( $username = 'me', $size = 'm', $count = 100, $link = '', $format = 'jpg', $output = 'html' ) {

		if ( empty( $username ) )
			return false;

		$photos = flickr_api( 'flickr.favorites.getPublicList', array(
			'user_id' => flickr_get_user_nsid( $username ),
			'per_page' => $count,
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			return flickr_get_photos( $photos, $size, $link );

		} elseif ( $output == 'array' ) {

			return json_decode( $photos );

		}

		return $photos;

	}

function flickr_get_groups( $user = 'me' ) {

	$groups = flickr_api( 'flickr.people.getPublicGroups', array(
		'user_id' => flickr_get_user_nsid( $user )
	) );

	return json_decode( $groups );

}
	function flickr_get_group_photos( $group_id = '', $size = 'm', $count = 100, $link = '', $format = 'jpg', $output = 'html' ) {

		if ( empty( $group_id ) )
			return false;

		$photos = flickr_api( 'flickr.photos.search', array(
			'group_id' => $group_id,
			'per_page' => $count,
			'extras' => flickr_photo_extras()
		) );

		if ( $output == 'html' ) {

			return flickr_get_photos( $photos, $size, $link );

		} elseif ( $output == 'array' ) {

			return json_decode( $photos );

		}

		return $photos;

	}



/**
 * Flickr Widget
 */

add_action( 'widgets_init', create_function( '', 'register_widget("Flickr_API_Widget");' ) );
class Flickr_API_Widget extends WP_Widget {

	var $image_sizes;

	function Flickr_API_Widget() {
		parent::WP_Widget( /* Base ID */'flickr_photos', /* Name */'Flickr Photos', array( 'description' => 'Displays a selection of photos from Flickr' ) );


		$this->image_sizes = array(
			's' => __( 'Small square, 75x75' ),			// s	small square 75x75
			't' => __( 'Thumbnail, 100px on long side' ),		// t	thumbnail, 100 on longest side
			'm' => __( 'Medium Small, 240px on long side' ),	// m	small, 240 on longest side
			'-'  => __( 'Medium, 500px on long side' ),		// -	medium, 500 on longest side
			'z' => __( 'Medium Large, 640px on long side' ), 	// z	medium 640, 640 on longest side
			'b' => __( 'Large, 1024px on long side' ),		// b	large, 1024 on longest side*
			'o' => __( 'Original' )					// o	original image, either a jpg, gif or png, depending on source format
		);
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters( 'widget_title', $instance[ 'title' ] );

		if ( empty( $instance[ 'get' ] ) )
			return '';

		echo $before_widget;

		if ( $title )
			echo $before_title . $title . $after_title;

		echo get_flickr_photos( $instance[ 'get' ], $instance[ 'id' ], $instance[ 'user' ], $instance[ 'size' ], $instance[ 'count' ], $instance[ 'link' ] );

		echo $after_widget;
	}

	function form( $instance ) {

		$title 	= esc_attr( $instance[ 'title' ] );
		$user 	= esc_attr( $instance[ 'user' ] );
		$size 	= esc_attr( $instance[ 'size' ] );
		$id 	= esc_attr( $instance[ 'id' ] );
		$get 	= esc_attr( $instance[ 'get' ] );
		$count 	= intval( $instance[ 'count' ] );
		$link 	= esc_attr( $instance[ 'link' ] );

		$fgets = array(
			'gallery' => __( 'Gallery' ),
			'photoset' => __( 'Photoset' ),
			'machinetagged' => __( 'Machine tagged for current post' ),
			'tags' => __( 'Tags' ),
			'collection' => __( 'Collection' ),
			'favourites' => __( 'Favourites' ),
			'group' => __( 'Group' ),
			'photostream' => __( 'Photostream' )
		);

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'user' ); ?>"><?php _e( 'Username to get photos for' ); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'user' ); ?>" class="flickr-user widefat" id="<?php echo $this->get_field_id( 'user' ); ?>" value="<?php empty( $user ) ? esc_attr_e( get_option( 'flickr_username' ) ) : esc_attr_e( $user ); ?>" />
		</p>

		<fieldset class="flickr-choices">
			<legend><?php _e( "Select what you want to get from images from on Flickr" ); ?></legend>
			<ul>
			<?php foreach( $fgets as $fget => $label ) { ?>
				<li><label for="<?php echo $this->get_field_id( "get-$fget" ); ?>"><input class="flickr-get" value="<?php echo $fget; ?>" type="radio" id="<?php echo $this->get_field_id( "get-$fget" ); ?>" name="<?php echo $this->get_field_name( 'get' ); ?>" <?php checked( $fget, $get ); ?> /> <?php _e( $label ); ?></label></li>
			<?php } ?>
			</ul>
		</fieldset>

		<div class="flickr-dropdown-box" data-id="<?php echo $this->get_field_id( 'id' ); ?>" data-name="<?php echo $this->get_field_name( 'id' ); ?>">
			<?php
			if ( isset( $get ) && ! empty( $get ) ) {
				echo Flickr_API_Plugin::flickr_get_dropdown( $get, $user, $this->get_field_id( 'id' ), $this->get_field_name( 'id' ), $id );
			}
			?>
		</div>

		<p>
			<label for="<?php echo $this->get_field_id( 'size' ); ?>"><?php _e( 'Image size' ); ?></label>
			<select id="<?php echo $this->get_field_id( 'size' ); ?>" name="<?php echo $this->get_field_name( 'size' ); ?>" class="widefat flickr-size">
				<?php foreach( $this->image_sizes as $s => $desc ) { ?>
				<option <?php selected( $s, $size ); ?> value="<?php esc_attr_e( $s ); ?>"><?php esc_html_e( $desc ); ?></option>
				<?php } ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'link' ); ?>"><?php _e( 'Link options' ); ?></label>
			<select id="<?php echo $this->get_field_id( 'link' ); ?>" name="<?php echo $this->get_field_name( 'link' ); ?>" class="widefat flickr-link">
				<option <?php selected( '', $link ); ?> value=""><?php _e( 'No link' ); ?></option>
				<option <?php selected( 'flickr', $link ); ?> value="flickr"><?php _e( 'Flickr photo page' ) ?></option>
				<optgroup label="<?php _e( 'Link to another image size' ); ?>">
				<?php foreach( $this->image_sizes as $s => $desc ) { ?>
					<option <?php selected( $s, $link ); ?> value="<?php esc_attr_e( $s ); ?>"><?php esc_html_e( $desc ); ?></option>
				<?php } ?>
				</optgroup>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php _e( 'Number of images' ); ?></label>
			<input type="text" name="<?php echo $this->get_field_name( 'count' ); ?>" class="flickr-count widefat" id="<?php echo $this->get_field_id( 'count' ); ?>" value="<?php echo $count ? $count : 100; ?>" />
		</p>

		<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance[ 'title' ] 	= strip_tags( $new_instance[ 'title' ] );
		$instance[ 'get' ] 	= sanitize_key( $new_instance[ 'get' ] );
		$instance[ 'user' ] 	= sanitize_text_field( $new_instance[ 'user' ] );
		$instance[ 'size' ] 	= sanitize_key( $new_instance[ 'size' ] );
		$instance[ 'count' ] 	= intval( $new_instance[ 'count' ] );
		$instance[ 'id' ] 	= is_array( $new_instance[ 'id' ] ) ? implode( ', ', $new_instance[ 'id' ] ) : sanitize_text_field( $new_instance[ 'id' ] );
		$instance[ 'link' ] 	= sanitize_key( $new_instance[ 'link' ] );
		return $instance;
	}

}


?>
