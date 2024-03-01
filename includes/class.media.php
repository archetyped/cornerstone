<?php

/**
 * Core properties/methods for Media management
 * @package Cornerstone
 * @subpackage Media
 * @author Archetyped
 */
class CNR_Media extends CNR_Base {
	
	var $var_field = 'field';
	
	var $var_action = 'action';

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->var_field = $this->add_prefix($this->var_field);
		$this->var_action = $this->add_prefix($this->var_action);
	}
	
	/* Methods */
	
	function register_hooks() {
		parent::register_hooks();
		
		//Register media placeholder handler
		cnr_register_placeholder_handler('media', $this->m('content_type_process_placeholder_media'));
		
		//Register field types
		add_action('cnr_register_field_types', $this->m('register_field_types'));
		
		//Register/Modify content types
//		add_action('cnr_register_content_types', $this->m('register_content_types'));
		
		//Register handler for custom media requests
		add_action('media_upload_cnr_field_media', $this->m('field_upload_media'));
		
		//Display 'Set as...' button in media item box
		add_filter('attachment_fields_to_edit', $this->m('attachment_fields_to_edit'), 11, 2);
		
		//Add form fields to upload form (to pass query vars along with form submission)
		add_action('pre-html-upload-ui', $this->m('attachment_html_upload_ui'));
		
		//Display additional meta data for media item (dimensions, etc.)
		//add_filter('media_meta', $this->m('media_meta'), 10, 2);
		
		//Modifies media upload query vars so that request is routed through plugin
		add_filter('admin_url', $this->m('media_upload_url'), 10, 2);
		
		//Adds admin menus for content types
		add_action('cnr_admin_menu_type', $this->m('type_admin_menu'));
		
		//Modify tabs in upload popup for fields
		add_filter('media_upload_tabs', $this->m('field_upload_tabs'));
	}
	
	/**
	 * Register media-specific field types
	 */
	function register_field_types($field_types) {
		/**
		 * @var CNR_Content_Utilities
		 */
		global $cnr_content_utilities;
		
		// Media (Base)
		$media = new CNR_Field_Type('media', 'base_closed');
		$media->set_description('Media Item');
		$media->set_property('title', 'Select Media');
		$media->set_property('button','Select Media');
		$media->set_property('remove', 'Remove Media');
		$media->set_property('set_as', 'media');
		$media->set_layout('form', '{media}');
		$media->set_layout('display', '{media format="display"}');
		$media->set_layout('display_url', '{media format="display" type="url"}');
		$media->add_script( array('add', 'edit-item', 'post-new.php', 'post.php', 'media-upload-popup'), $this->add_prefix('script_media'), $this->util->get_file_url('js/lib.media.js'), array($this->add_prefix('core'), $this->add_prefix('admin')));
		$cnr_content_utilities->register_field($media);

		// Image
		$image = new CNR_Field_Type('image', 'media');
		$image->set_description('Image');
		$image->set_parent('media');
		$image->set_property('title', 'Select Image');
		$image->set_property('button', 'Select Image');
		$image->set_property('remove', 'Remove Image');
		$image->set_property('set_as', 'image');
		$cnr_content_utilities->register_field($image);
	}
	
	/**
	 * Media placeholder handler
	 * @param string $ph_output Value to be used in place of placeholder
	 * @param CNR_Field $field Field containing placeholder
	 * @param array $placeholder Current placeholder @see CNR_Field::parse_layout for structure of $placeholder array
	 * @param string $layout Layout to build
	 * @param array $data Extended data for field (Default: null)
	 * @return string Value to use in place of current placeholder
	 */
	function content_type_process_placeholder_media($ph_output, $field, $placeholder, $layout, $data) {
		global $post_ID, $temp_ID;
		$attr_default = array('format' => 'form', 'type' => 'html', 'id' => '', 'class' => '');
		$attr = wp_parse_args($placeholder['attributes'], $attr_default);
		//Get media ID
		$post_media = $field->get_data();
		
		//Format output based on placeholder attribute
		switch ( strtolower($attr['format']) ) {
			case 'form':
				$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
				$media_upload_iframe_src = "media-upload.php";
				$media_id = $field->get_id(array('format' => true));
				$media_name = $media_id;
				$query = array (
								'post_id'			=> $uploading_iframe_ID,
								'type'				=> $this->add_prefix('field_media'),
								$this->var_action	=> 'true',
								$this->var_field	=> $media_id,
								'cnr_set_as'		=> $field->get_property('set_as'),
								'TB_iframe'			=> 'true'
								);
				$media_upload_iframe_src = apply_filters('image_upload_iframe_src', $media_upload_iframe_src . '?' . http_build_query($query));
				
				//Get Attachment media URL
				$post_media_valid = get_post($post_media);
				$post_media_valid = ( isset($post_media_valid->post_type) && 'attachment' == $post_media_valid->post_type ) ? true : false;

				//Start output
				ob_start();
			?>
			<div id="<?php echo "$media_name-data"; ?>" class="media_data">
			<?php
				if ($post_media_valid) {
					//Add media preview 
			?>
					{media format="display" id="<?php echo "$media_name-frame"; ?>" class="media_frame"}
					{media format="display" type="link" target="_blank" id="<?php echo "$media_name-link"; ?>" class="media_link"}
					<input type="hidden" name="<?php echo "$media_name"?>" id="<?php echo "$media_name"?>" value="<?php echo $post_media ?>" />
			<?php
				}
			?>
			</div>
			<?php
				//Add media action options (upload, remove, etc.)
			?>
					<div class="actions buttons">
						<a href="<?php echo "$media_upload_iframe_src" ?>" id="<?php echo "$media_name-lnk"?>" class="thickbox button" title="{title}" onclick="return false;">{button}</a>
						<span id="<?php echo "$media_name-options"?>" class="options <?php if (!$post_media_valid) : ?> options-default <?php endif; ?>">
						or <a href="#" title="Remove media" class="del-link" id="<?php echo "$media_name-option_remove"?>" onclick="CNR.media.doAction(this); return false;">{remove}</a>
						 <span id="<?php echo "$media_name-remove_confirmation"?>" class="confirmation remove-confirmation confirmation-default">Are you sure? <a href="#" id="<?php echo "$media_name-remove"?>" class="delete" onclick="return CNR.media.doAction(this);">Remove</a> or <a href="#" id="<?php echo "$media_name-remove_cancel"?>" onclick="return CNR.media.doAction(this);">Cancel</a></span>
						</span>
					</div>
			<?php
				//Output content
				$ph_output = ob_get_clean();
				break;
			case 'display':
				//Add placeholder attributes to attributes from function call
				
				//Remove attributes used by system
				$type = $attr['type'];
				$attr_system = array('format', 'type');
				foreach ( $attr_system as $key ) {
					unset($attr[$key]);
				}
				$data = wp_parse_args($data, $attr);
				$ph_output = $this->get_media_output($post_media, $type, $data);
				break;
		}
		return $ph_output;
	}
	
	/**
	 * Handles upload of Post media on post edit form
	 * @return void 
	 */
	function field_upload_media() {
		$errors = array();
		$id = 0;
		
		//Process media selection
		if ( isset($_POST['setmedia']) ) {
			/* Send image data to main post edit form and close popup */
			//Get Attachment ID
			$field_var = $this->add_prefix('field');
			$args = new stdClass();
			$keys = array_keys($_POST['setmedia']);
			$args->id = esc_attr( array_shift($keys) );
			unset($keys);
			$args->field = '';
			if ( isset($_REQUEST['attachments'][$args->id][$this->var_field]) )
				$args->field = $_REQUEST['attachments'][$args->id][$this->var_field];
			elseif ( isset($_REQUEST[$this->var_field]) )
				$args->field = $_REQUEST[$this->var_field];
			$args->field = esc_attr( $args->field );
			$a = get_post($args->id);
			if ( ! empty($a) ) {
				$args->url = wp_get_attachment_url($a->ID);
				$args->type = get_post_mime_type($a->ID);
				$icon = !wp_attachment_is_image($a->ID);
				$args->preview = wp_get_attachment_image_src($a->ID, '', $icon);
				$args->preview = ( ! $args->preview ) ? '' : $args->preview[0];
			}
			//Update parent window (JS)
			$js_out = "var win = window.dialogArguments || opener || parent || top; win.CNR.media.setPostMedia(" . json_encode($args) . ");";
			echo $this->util->build_script_element($js_out, 'media_upload', false);
			exit;
		}
		
		//Handle HTML upload
		if ( isset($_POST['html-upload']) && !empty($_FILES) ) {
			$id = media_handle_upload('async-upload', $_REQUEST['post_id']);
			//Clear uploaded files
			unset($_FILES);
			if ( is_wp_error($id) ) {
				$errors['upload_error'] = $id;
				$id = false;
			}
		}
		
		//Display default UI
					
		//Determine media type
		$type = ( isset($_REQUEST['type']) ) ? esc_attr( $_REQUEST['type'] ) : 'cnr_field_media';
		//Determine UI to use (disk or URL upload)
		$upload_form = ( isset($_GET['tab']) && 'type_url' == $_GET['tab'] ) ? 'media_upload_type_url_form' : 'media_upload_type_form';
		//Load UI
		return wp_iframe( $upload_form, $type, $errors, $id );
	}
	
	/**
	 * Modifies array of form fields to display on Attachment edit form
	 * Array items are in the form:
	 * 'key' => array(
	 * 				  'label' => "Label Text",
	 * 				  'value' => Value
	 * 				  )
	 * 
	 * @return array Form fields to display on Attachment edit form 
	 * @param array $form_fields Associative array of Fields to display on form (@see get_attachment_fields_to_edit())
	 * @param object $attachment Attachment post object
	 */
	function attachment_fields_to_edit($form_fields, $attachment) {
		
		if ( $this->is_custom_media() ) {
			$post = get_post($attachment);
			//Clear all form fields
			$form_fields = array();
			//TODO Display custom buttons based on mime type defined in content type's field
			$set_as = 'media';
			$qvar = 'cnr_set_as';
			//Get set as text from request
			if ( isset($_REQUEST[$qvar]) && !empty($_REQUEST[$qvar]) )
				$set_as = $_REQUEST[$qvar];
			elseif ( ( strpos($_SERVER['PHP_SELF'], 'async-upload.php') !== false || isset($_POST['html-upload']) ) && ($ref = wp_get_referer()) && strpos($ref, $qvar) !== false && ($ref = parse_url($ref)) && isset($ref['query']) ) {
				//Get set as text from referer (for async uploads)
				$qs = array();
				parse_str($ref['query'], $qs);
				if ( isset($qs[$qvar]) )
					$set_as = $qs[$qvar];
			}
			//Add "Set as Image" button to form fields array
			$set_as = 'Set as ' . $set_as;
			$field = array(
							'label'		=> '',
							'input'		=> 'html',
							'html'		=> '<input type="submit" class="button" value="' . esc_attr( $set_as ) . '" name="setmedia[' . $post->ID . ']" />'
							);
			$form_fields['buttons'] = $field;
			//Add field ID value as hidden field (if set)
			if ( isset($_REQUEST[$this->var_field]) ) {
				$field = array(
							'input'	=> 'hidden',
							'value'	=> $_REQUEST[$this->var_field]
							);
				$form_fields[$this->var_field] = $field;
			}
		}
		return $form_fields;
	}
	
	/**
	 * Checks if value represents a valid media item
	 * @param int|object $media Attachment ID or Object to check
	 * @return bool TRUE if item is media, FALSE otherwise
	 */
	function is_media($media) {
		$media = get_post($media);
		return ( ! empty($media) && 'attachment' == $media->post_type );	
	}
	
	/**
	 * Checks whether current media upload/selection request is initiated by the plugin
	 */
	function is_custom_media() {
		$ret = false;
		$action = $this->var_action;
		$upload = false;
		if (isset($_REQUEST[$action]))
			$ret = true;
		elseif (isset($_SERVER['HTTP_REFERER']) ) {
			$qs = array();
			$ref = parse_url($_SERVER['HTTP_REFERER']);
			if ( isset($ref['query']) )
				parse_str($ref['query'], $qs);
			if (array_key_exists($action, $qs))
				$ret = true;
		}
		
		return $ret;
	}
	
	/**
	 * Add HTML Media upload form
	 * @return void
	 */
	function attachment_html_upload_ui() {
		$vars = array ($this->var_action, $this->var_field);
		foreach ( $vars as $var ) {
			if ( isset($_REQUEST[$var]) )
				echo '<input type="hidden" name="' . esc_attr( $var ) . '" id="' . esc_attr( $var ) . '" value="' . esc_attr( $_REQUEST[$var] ) . '" />';
		}
	}
	
	/**
	 * Adds additional media meta data to media item display
	 * @param object $meta Meta data to display
	 * @param object $post Attachment post object
	 * @return string Meta data to display
	 */
	function media_meta($meta, $post) {
		if ($this->is_custom_media() && wp_attachment_is_image($post->ID)) {
			//Get attachment image info
			$img = wp_get_attachment_image_src($post->ID, '');
			if (is_array($img) && count($img) > 2) {
				//Add image dimensions to output
				$meta .= sprintf('<div>%dpx&nbsp;&times;&nbsp;%dpx</div>', $img[1], $img[2]);
			}
		}
		return $meta;
	}
	
	/**
	 * Modifies media upload URL to work with CNR attachments
	 * @param string $url Full admin URL
	 * @param string $path Path part of URL
	 * @return string Modified media upload URL
	 */
	function media_upload_url($url, $path) {
		if (strpos($path, 'media-upload.php') === 0) {
			//Get query vars
			$qs = parse_url($url);
			$qs = ( isset($qs['query']) ) ? $qs['query'] : '';
			$q = array();
			parse_str($qs, $q);
			//Check for tab variable
			if (isset($q['tab'])) {
				//Replace tab value
				$q['cnr_tab'] = $q['tab'];
				$q['tab'] = 'type';
			}
			//Rebuild query string
			$qs_upd = build_query($q);
			//Update query string on URL
			$url = str_replace($qs, $qs_upd, $url);
		}
		return $url;
	}
	
	/*-** Field-Specific **-*/
	
	/**
	 * Removes URL tab from media upload popup for fields
	 * Fields currently only support media stored @ website
	 * @param array $default_tabs Media upload tabs
	 * @see media_upload_tabs() for full $default_tabs array
	 * @return array Modified tabs array
	 */
	function field_upload_tabs($default_tabs) {
		if ( $this->is_custom_media() )
			unset($default_tabs['type_url']);
		return $default_tabs;
	}
	
	/*-** Post Attachments **-*/
	
	/**
	 * Retrieves matching attachments for post
	 * @param object|int $post Post object or Post ID (Default: current global post)
	 * @param array $args (optional) Associative array of query arguments
	 * @see get_posts() for query arguments
	 * @return array|bool Array of post attachments (FALSE on failure)
	 */
	function post_get_attachments($post = null, $args = '', $filter_special = true) {
		if (!$this->util->check_post($post))
			return false;
		global $wpdb;
		
		//Default arguments
		$defaults = array(
						'post_type'			=>	'attachment',
						'post_parent'		=>	(int) $post->ID,
						'numberposts'		=>	-1
						);
		
		$args = wp_parse_args($args, $defaults);
		
		//Get attachments
		$attachments = get_children($args);
		
		//Filter special attachments
		if ( $filter_special ) {
			$start = '[';
			$end = ']';
			$removed = false;
			foreach ( $attachments as $i => $a ) {
				if ( $start == substr($a->post_title, 0, 1) && $end == substr($a->post_title, -1) ) {
					unset($attachments[$i]);
					$removed = true;
				}
			}
			if ( $removed )
				$attachments = array_values($attachments);
		}
		
		//Return attachments
		return $attachments;
	}
	
	/**
	 * Retrieve the attachment's path
	 * Path = Full URL to attachment - site's base URL
	 * Useful for filesystem operations (e.g. file_exists(), etc.)
	 * @param object|id $post Attachment object or ID
	 * @return string Attachment path
	 */
	function get_attachment_path($post = null) {
		if (!$this->util->check_post($post))
			return '';
		//Get Attachment URL
		$url = wp_get_attachment_url($post->ID);
		//Replace with absolute path
		$path = str_replace(get_bloginfo('wpurl') . '/', ABSPATH, $url);
		return $path;
	}
	
	/**
	 * Retrieves filesize of an attachment
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 * @return int|string Filesize in bytes (@see filesize()) or as formatted string based on parameters
	 */
	function get_attachment_filesize($post = null, $formatted = true) {
		$size = 0;
		if (!$this->util->check_post($post))
			return $size;
		//Get path to attachment
		$path = $this->get_attachment_path($post);
		//Get file size
		if (file_exists($path))
			$size = filesize($path);
		if ($size > 0 && $formatted) {
			$size = (int) $size;
			$label = 'b';
			$format = "%s%s";
			//Format file size
			if ($size >= 1024 && $size < 102400) {
				$label = 'kb';
				$size = intval($size/1024);
			}
			elseif ($size >= 102400) {
				$label = 'mb';
				$size = round(($size/1024)/1024, 1);
			}
			$size = sprintf($format, $size, $label);
		}
		
		return $size;
	}
	
	/**
	 * Prints the attachment's filesize 
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 */
	function the_attachment_filesize($post = null, $formatted = true) {
		echo $this->get_attachment_filesize($post, $formatted);
	}
	
	/**
	 * Build output for media item
	 * Based on media type and output type parameter
	 * @param int|obj $media Media object or ID
	 * @param string $type (optional) Output type (Default: source URL)
	 * @return string Media output
	 */
	function get_media_output($media, $type = 'url', $attr = array()) {
		$ret = '';
		$media = get_post($media);
		//Continue processing valid media items
		if ( $this->is_media($media) ) {
			//URL - Same for all attachments
			if ( 'url' == $type ) {
				$ret = wp_get_attachment_url($media->ID);
			} elseif ( 'link' == $type ) {
				$ret = $this->get_link($media, $attr);
			} else {
				//Determine media type
				$mime = get_post_mime_type($media);
				$mime_main = substr($mime, 0, strpos($mime, '/'));
				
				//Pass to handler for media type + output type
				$handler = implode('_', array('get', $mime_main, 'output'));
				if ( method_exists($this, $handler))
					$ret = $this->{$handler}($media, $type, $attr);
				else {
					//Default output if no handler exists
					$ret = $this->get_image_output($media, $type, $attr);
				}
			}
		}
		
		
		return apply_filters($this->add_prefix('get_media_output'), $ret, $media, $type);
	}
	
	/**
	 * Build HTML for displaying media
	 * Output based on media type (image, video, etc.)
	 * @param int|obj $media (Media object or ID)
	 * @return string HTML for media
	 */
	function get_media_html($media) {
		$out = '';
		return $out;
	}
	
	function get_link($media, $attr = array()) {
		$ret = '';
		$media = get_post($media);
		if ( $this->is_media($media) ) {
			$attr['href'] = wp_get_attachment_url($media->ID);
			$text = ( isset($attr['text']) ) ? $attr['text'] : basename($attr['href']);
			unset($attr['text']);
			//Build attribute string
			$attr = wp_parse_args($attr, array('href' => ''));
			$attr_string = $this->util->build_attribute_string($attr);
			$ret = '<a ' . $attr_string . '>' . $text . '</a>';
		}
		return $ret;
	}
	
	/**
	 * Builds output for image attachments
	 * @param int|obj $media Media object or ID
	 * @param string $type Output type
	 * @return string Image output
	 */
	function get_image_output($media, $type = 'html', $attr = array()) {
		$ret = '';
		$icon = !wp_attachment_is_image($media->ID);
		
		//Get image properties
		$attr = wp_parse_args($attr, array('alt' => trim(strip_tags( $media->post_excerpt ))));
		list($attr['src'], $attribs['width'], $attribs['height']) = wp_get_attachment_image_src($media->ID, '', $icon);
			
		switch ( $type ) {
			case 'html' :
				//Remove attributes that must not be empty
				$attr_nonempty = array('id');
				foreach ( $attr_nonempty as $key ) {
					if ( !isset($attr[$key]) || empty($attr[$key]) )
						unset($attr[$key]);	
				}
				$attr_str = $this->util->build_attribute_string($attr);
				$ret = '<img ' . $attr_str . ' />';
				break;
		}
		
		return $ret;
	}
	
	/**
	 * Build HTML IMG element of an Image
	 * @param array $image Array of image properties
	 * 	0:	Source URI
	 * 	1:	Width
	 * 	2:	Height
	 * @return string HTML IMG element of specified image
	 */
	function get_image_html($image, $attributes = '') {
		$ret = '';
		if (is_array($image) && count($image) >= 3) {
			//Build attribute string
			if (is_array($attributes)) {
				$attribs = '';
				$attr_format = '%s="%s"';
				foreach ($attributes as $attr => $val) {
					$attribs .= sprintf($attr_format, $attr, esc_attr($val));
				}
				$attributes = $attribs;
			}
			$format = '<img src="%1$s" width="%2$d" height="%3$d" ' . $attributes . ' />';
			$ret = sprintf($format, $image[0], $image[1], $image[2]);
		}
		return $ret;
	}
	
	/**
	 * Registers admin menus for content types
	 * @param CNR_Content_Type $type Content Type Instance
	 * 
	 * @global CNR_Content_Utilities $cnr_content_utilities
	 */
	function type_admin_menu($type) {
		global $cnr_content_utilities;
		$u =& $cnr_content_utilities;
		
		//Add Menu
		$parent_page = $u->get_admin_page_file($type->id);
		$menu_page = $u->get_admin_page_file($type->id, 'extra');
		$this->util->add_submenu_page($parent_page, __('Extra Menu'), __('Extra Menu'), 8, $menu_page, $this->m('type_admin_page'));
	}
	
	function type_admin_page() {
		global $title;
		?>
		<div class="wrap">
			<?php screen_icon('edit'); ?>
			<h2><?php esc_html_e($title); ?></h2>
			<p>
			This is an extra menu for a specific content type added via a plugin hook
			</p>
		</div>
		<?php
	}
}