<?php

require_once 'class.base.php';
require_once 'class.content-types.php';

$cnr_media =& new CNR_Media();
$cnr_media->register_hooks();

/**
 * Core properties/methods for Media management
 * @package Cornerstone
 * @subpackage Media
 * @author SM
 */
class CNR_Media extends CNR_Base {
	
	/**
	 * Legacy Constructor
	 */
	function CNR_Media() {
		$this->__construct($id);
	}
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}
	
	/* Methods */
	
	function register_hooks() {
		//Register media placeholder handler
		cnr_register_placeholder_handler('media', $this->m('content_type_process_placeholder_media'));

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
		
		//Modify tabs in upload popup for fields
		add_filter('media_upload_tabs', $this->m('field_upload_tabs'));
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
		$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
		$media_upload_iframe_src = "media-upload.php";
		$img_id = $field->get_id(true);
		$image_name = $img_id;
		$query = array (
						'post_id'			=> $uploading_iframe_ID,
						'type'				=> 'cnr_field_media',
						'cnr_action'		=> 'true',
						'cnr_field'			=> $img_id,
						'cnr_set_as'		=> $field->get_property('set_as'),
						'TB_iframe'			=> 'true'
						);
		$image_upload_iframe_src = apply_filters('image_upload_iframe_src', $media_upload_iframe_src . '?' . http_build_query($query));
		//$image_title = __('Set Post ' . ucwords(strtolower($img)));
		$post_image = $field->get_data();
		//Get Attachment Image URL
		$post_image_src = ( ((int) $post_image) > 0 ) ? wp_get_attachment_image_src($post_image, '') : false;
		if (!$post_image_src)
			$post_image = false;
		else
			$post_image_src = $post_image_src[0];
		
		//Start output
		ob_start();
?>
	<?php
		if ($post_image) { 
	?>
			<img id="<?php echo "$image_name-frame"?>" src="<?php echo $post_image_src ?>" class="image_frame" />
			<input type="hidden" name="<?php echo "$image_name"?>" id="<?php echo "$image_name"?>" value="<?php echo $post_image ?>" />
	<?php
		}
	?>
			<div class="buttons">
				<a href="<?php echo "$image_upload_iframe_src" ?>" id="<?php echo "$image_name-lnk"?>" class="thickbox button" title="{title}" onclick="return false;">{button}</a>
				<span id="<?php echo "$image_name-options"?>" class="options <?php if (!$post_image) : ?> options-default <?php endif; ?>">
				or <a href="#" title="Remove Image" class="del-link" id="<?php echo "$image_name-option_remove"?>" onclick="postImageAction(this); return false;">{remove}</a>
				 <span id="<?php echo "$image_name-remove_confirmation"?>" class="confirmation remove-confirmation confirmation-default">Are you sure? <a href="#" id="<?php echo "$image_name-remove"?>" class="delete" onclick="return postImageAction(this);">Remove</a> or <a href="#" id="<?php echo "$image_name-remove_cancel"?>" onclick="return postImageAction(this);">Cancel</a></span>
				</span>
			</div>
	<?php
		//Output content
		$ph_output = ob_get_clean();
		
		return $ph_output;
	}
	
	/**
	 * Handles upload of Post image on post edit form
	 * @return 
	 */
	function field_upload_media() {
		$errors = array();
		$id = 0;
		
		//Process image selection
		if ( isset($_POST['setimage']) ) {
			/* Send image data to main post edit form and close popup */
			//Get Attachment ID
			$attachment_id = array_keys($_POST['setimage']);
			$attachment_id = array_shift($attachment_id); 
		 	//Get Attachment Image URL
			$src = wp_get_attachment_image_src($attachment_id, '');
			if (!$src)
				$src = '';
			else
				$src = $src[0];
			//Build JS Arguments string
			$args = "'$attachment_id', '$src'";
			//$this->debug->print_message($_REQUEST);
			$type = '';
			if ( isset($_REQUEST['attachments'][$attachment_id]['cnr_field']) )
				$type = $_REQUEST['attachments'][$attachment_id]['cnr_field'];
			elseif ( isset($_REQUEST['cnr_field']) )
				$type = $_REQUEST['cnr_field'];
			$type = ( !empty($type) ) ? ", '" . $type . "'" : '';
			$args .= $type;
			?>
			<script type="text/javascript">
			/* <![CDATA[ */
			var win = window.dialogArguments || opener || parent || top;
			win.setPostImage(<?php echo $args; ?>);
			/* ]]> */
			</script>
			<?php
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
		$type = ( isset($_REQUEST['type']) ) ? $_REQUEST['type'] : 'cnr_field_media';
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
		
		if ($this->is_custom_media()) {
			//$this->debug->print_message('Request', $_REQUEST, 'Post', $_POST);
			$post =& get_post($attachment);
			//Clear all form fields
			$form_fields = array();
			if ( substr($post->post_mime_type, 0, 5) == 'image' ) {
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
								'input'		=> 'html',
								'html'		=> '<input type="submit" class="button" value="' . $set_as . '" name="setimage[' . $post->ID . ']" />'
								);
				$form_fields['buttons'] = $field;
				//Add field ID value as hidden field (if set)
				if (isset($_REQUEST['cnr_field'])) {
					$field = array(
									'input'	=> 'hidden',
									'value'	=> $_REQUEST['cnr_field']
									);
					$form_fields['cnr_field'] = $field;
				}
			}
		}
		return $form_fields;
	}
	
	/**
	 * Checks whether current media upload/selection request is initiated by the plugin
	 */
	function is_custom_media() {
		$ret = false;
		$action = 'cnr_action';
		$upload = false;
		if (isset($_REQUEST[$action]))
			$ret = true;
		else {
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
		$vars = array ('cnr_action', 'cnr_field');
		foreach ( $vars as $var ) {
			if ( isset($_REQUEST[$var]) )
				echo '<input type="hidden" name="' . $var . '" id="' . $var . '" value="' . esc_attr($_REQUEST[$var]) . '" />';
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
}
?>