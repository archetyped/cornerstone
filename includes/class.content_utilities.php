<?php
/**
 * Utilities for Content Type functionality
 * @package Cornerstone
 * @subpackage Content Types
 * @author Archetyped
 */
class CNR_Content_Utilities extends CNR_Base {

	/**
	 * Array of hooks called
	 * @var array
	 */
	var $hooks_processed = array();
	
	/**
	 * Initialize content type functionality
	 */
	function init() {
		$this->register_hooks();
	}

	/**
	 * Registers hooks for content types
	 * @todo 2010-07-30: Check hooks for 3.0 compatibility
	 */
	function register_hooks() {
		//Register types
		add_action('init', $this->m('register_types'));
		add_action('init', $this->m('add_hooks'), 11);
		
		//Enqueue scripts for fields in current post type
		add_action('admin_enqueue_scripts', $this->m('enqueue_files'));
		
		//Add menus
		//add_action('admin_menu', $this->m('admin_menu'));

		//Build UI on post edit form
		add_action('do_meta_boxes', $this->m('admin_do_meta_boxes'), 10, 3);

		//Get edit link for items
		//add_filter('get_edit_post_link', $this->m('get_edit_item_url'), 10, 3);

		//add_action('edit_form_advanced', $this->m('admin_page_edit_form'));

		//Save Field data/Content type
		add_action('save_post', $this->m('save_item_data'), 10, 2);

		//Modify post query for content type compatibility
		add_action('pre_get_posts', $this->m('pre_get_posts'), 20);
	}

	/**
	 * Initialize fields and content types
	 */
	function register_types() {
		//Global variables
		global $cnr_field_types, $cnr_content_types;
		
		/* Field Types */

		//Base
		$base = new CNR_Field_Type('base');
		$base->set_description('Default Element');
		$base->set_property('tag', 'span');
		$base->set_property('class', '', 'attr');
		$base->set_layout('form', '<{tag} name="{field_name}" id="{field_id}" {properties ref_base="root" group="attr"} />');
		$base->set_layout('label', '<label for="{field_id}">{label}</label>');
		$base->set_layout('display', '{data format="display"}');
		$this->register_field($base);

		//Base closed
		$base_closed = new CNR_Field_Type('base_closed');
		$base_closed->set_parent('base');
		$base_closed->set_description('Default Element (Closed Tag)');
		$base_closed->set_layout('form_start', '<{tag} id="{field_id}" name="{field_name}" {properties ref_base="root" group="attr"}>');
		$base_closed->set_layout('form_end', '</{tag}>');
		$base_closed->set_layout('form', '{form_start ref_base="layout"}{data}{form_end ref_base="layout"}');
		$this->register_field($base_closed);

		//Input
		$input = new CNR_Field_Type('input');
		$input->set_parent('base');
		$input->set_description('Default Input Element');
		$input->set_property('tag', 'input');
		$input->set_property('type', 'text', 'attr');
		$input->set_property('value', CNR_Field::USES_DATA, 'attr');
		$this->register_field($input);
		
		//Text input
		$text = new CNR_Field_Type('text', 'input');
		$text->set_description('Text Box');
		$text->set_property('size', 15, 'attr');
		$text->set_property('label');
		$text->set_layout('form', '{label ref_base="layout"} {inherit}');
		$this->register_field($text);
		
		//Checkbox
		$checkbox = new CNR_Field_Type('checkbox', 'input');
		$checkbox->set_description('Checkbox');
		$checkbox->set_property('type', 'checkbox', 'attr');
		$checkbox->set_property('label');
		$checkbox->set_property('checked', '', 'attr');
		$checkbox->set_layout('form', '{inherit} {label ref_base="layout"}');
		$this->register_field($checkbox);
		
		//Textarea
		$ta = new CNR_Field_Type('textarea', 'base_closed');
		$ta->set_property('tag', 'textarea');
		$ta->set_property('cols', 40, 'attr');
		$ta->set_property('rows', 3, 'attr');
		$this->register_field($ta);
		
		//Rich Text
		$rt = new CNR_Field_Type('richtext', 'textarea');
		$rt->set_layout('form', '<div class="rt_container">{rich_editor}</div>');
		$this->register_field($rt);

		//Location
		$location = new CNR_Field_Type('location');
		$location->set_description('Geographic Coordinates');
		$location->set_element('latitude', 'text', array( 'size' => 3, 'label' => 'Latitude' ));
		$location->set_element('longitude', 'text', array( 'size' => 3, 'label' => 'Longitude' ));
		$location->set_layout('form', '<span>{latitude ref_base="elements"}</span>, <span>{longitude ref_base="elements"}</span>');
		$this->register_field($location);

		//Phone
		$phone = new CNR_Field_Type('phone');
		$phone->set_description('Phone Number');
		$phone->set_element('area', 'text', array( 'size' => 3 ));
		$phone->set_element('prefix', 'text', array( 'size' => 3 ));
		$phone->set_element('suffix', 'text', array( 'size' => 4 ));
		$phone->set_layout('form', '({area ref_base="elements"}) {prefix ref_base="elements"} - {suffix ref_base="elements"}');
		$this->register_field($phone);

		//Hidden
		$hidden = new CNR_Field_Type('hidden');
		$hidden->set_parent('input');
		$hidden->set_description('Hidden Field');
		$hidden->set_property('type', 'hidden');
		$this->register_field($hidden);

		//Span
		$span = new CNR_Field_Type('span');
		$span->set_description('Inline wrapper');
		$span->set_parent('base_closed');
		$span->set_property('tag', 'span');
		$span->set_property('value', 'Hello there!');
		$this->register_field($span);

		//Select
		$select = new CNR_Field_Type('select');
		$select->set_description('Select tag');
		$select->set_parent('base_closed');
		$select->set_property('tag', 'select');
		$select->set_property('tag_option', 'option');
		$select->set_property('options', array());
		$select->set_layout('form', '{label ref_base="layout"} {form_start ref_base="layout"}{loop data="properties.options" layout="option" layout_data="option_data"}{form_end ref_base="layout"}');
		$select->set_layout('option', '<{tag_option} value="{data_ext id="option_value"}">{data_ext id="option_text"}</{tag_option}>');
		$select->set_layout('option_data', '<{tag_option} value="{data_ext id="option_value"}" selected="selected">{data_ext id="option_text"}</{tag_option}>');		
		$this->register_field($select);

		//Enable plugins to modify (add, remove, etc.) field types
		do_action_ref_array('cnr_register_field_types', array(&$cnr_field_types));

		//Content Types
		
		//Enable plugins to add/remove content types
		do_action_ref_array('cnr_register_content_types', array(&$cnr_content_types));

		//Enable plugins to modify content types after they have all been registered
		do_action_ref_array('cnr_content_types_registered', array(&$cnr_content_types));
	}

	/**
	 * Add content type to global array of content types
	 * @param CNR_Content_Type $ct Content type to register
	 * @global array $cnr_content_types Content types array
	 */
	function register_content_type(&$ct) {
		//Add content type to CNR array
		if ( $this->is_content_type($ct) && !empty($ct->id) ) {
			global $cnr_content_types;
			$cnr_content_types[$ct->id] =& $ct;
		}
		//WP Post Type Registration
		global $wp_post_types;
		if ( !empty($ct->id) && !isset($wp_post_types[$ct->id]) )
			register_post_type($ct->id, $this->build_post_type_args($ct));
	}
	
	/**
	 * Generates arguments array for WP Post Type Registration
	 * @param CNR_Content_Type $ct Content type being registered
	 * @return array Arguments array
	 * @todo Enable custom taxonomies
	 */
	function build_post_type_args(&$ct) {
		//Setup labels
		
		//Build labels
		$labels = array (
			'name'				=> _( $ct->get_title(true) ),
			'singular_name'		=> _( $ct->get_title(false) ),
			'all_items'			=> sprintf( _( 'All %s' ), $ct->get_title(true) ),
		);
		
		//Action labels
		$item_actions = array(
			'add_new'				=> 'Add New %s',
			'edit'					=> 'Edit %s',
			'new'					=> 'New %s',
			'view'					=> 'View %s',
			'search'				=> array('Search %s', true),
			'not_found'				=> array('No %s found', true, false),
			'not_found_in_trash'	=> array('No %s found in Trash', true, false)
		);

		foreach ( $item_actions as $key => $val ) {
			$excluded = false;
			$plural = false;
			if ( is_array($val) ) {
				if ( count($val) > 1 && true == $val[1] ) {
					$plural = true;
				}
				if ( count($val) > 2 && false == $val[2] )
					$excluded = true;
				$val = $val[0];
			}
			$title = ( $plural ) ? $labels['name'] : $labels['singular_name'];
			if ( $excluded )
				$item = $key;
			else {
				$item = $key . '_item' . ( ( $plural ) ? 's' : '' );
			}
			$labels[$item] = sprintf($val, $title);
		}
		
		//Setup args
		$args = array (
			'labels'				=> $labels,
			'description'			=> $ct->get_description(),
			'public'				=> true,
			'capability_type'		=> 'post',
			'rewrite'				=> array( 'slug' => strtolower($labels['name']) ),
			'has_archive'			=> true,
			'hierarchical'			=> false,
			'menu_position'			=> 5,
			'supports'				=> array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions'),
			'taxonomies'			=> get_object_taxonomies('post'),
			'show_in_rest'			=> true,
		);
		
		return $args;
	}

	/**
	 * Add field type to global array of field types
	 * @param CNR_Field_Type $field Field to register
	 * 
	 * @global array $cnr_field_types Field types array
	 */
	function register_field(&$field) {
		if ( $this->is_field($field) && !empty($field->id) ) {
			global $cnr_field_types;
			$cnr_field_types[$field->id] =& $field;
		}
	}

	/*-** Helpers **-*/

	/**
	 * Checks whether an object is a valid content type instance
	 * @param obj $ct Object to evaluate
	 * @return bool TRUE if object is a valid content type instance, FALSE otherwise
	 */
	function is_content_type(&$ct) {
		return is_a($ct, 'cnr_content_type');
	}

	/**
	 * Checks whether an object is a valid field instance
	 * @param obj $field Object to evaluate
	 * @return bool TRUE if object is a valid field instance, FALSE otherwise
	 */
	function is_field(&$field) {
		return is_a($field, 'cnr_field_type');
	}

	/*-** Handlers **-*/

	/**
	 * Modifies query parameters to include custom content types
	 * Adds custom content types to default post query so these items are retrieved as well
	 * @param WP_Query $q Reference to WP_Query object being used to perform posts query
	 * @see WP_Query for reference
	 */
	function pre_get_posts($q) {
		$pt =& $q->query_vars['post_type'];
		/* Do not continue processing if:
		 * > In admin section
		 * > Not main query (or CNR-initiated query)
		 * > Single object requested
		 * > More than one post type is already specified
		 * > Post type other than 'post' is supplied
		 */
		if ( is_admin()
		|| ( !$q->is_main_query() && !isset($q->query_vars[$this->get_prefix()]) )
		|| $q->is_singular()
		|| ( is_array($pt)
			&& ( count($pt) > 1 
				|| 'post' != $pt[0] )
			)
		|| !in_array($pt, array('post', null))
		) {
			return false;
		}
		
		$default_types = $this->get_default_post_types();
		$custom_types = array_diff(array_keys($this->get_types()), $default_types);
		if ( !count($custom_types) )
			return false;
		//Wrap post type in array
		if ( empty($pt) || is_null($pt) )
			$pt = array('post');
		if ( !is_array($pt) )
			$pt = array($pt);
		//Add custom types to query
		foreach ( $custom_types as $type ) {
			$pt[] = $type;
		}
	}
	
	/**
	 * Retrieves current context (content type, action)
	 * @return array Content Type and Action of current request
	 */
	function get_context() {
		$post = false;
		if ( isset($GLOBALS['post']) && !is_null($GLOBALS['post']) )
			$post = $GLOBALS['post'];
		elseif ( isset($_REQUEST['post_id']) )
			$post = $_REQUEST['post_id'];
		elseif ( isset($_REQUEST['post']) )
			$post = $_REQUEST['post'];
		elseif ( isset($_REQUEST['post_type']) )
			$post = $_REQUEST['post_type'];
		//Get action
		$action = $this->util->get_action();
		if ( empty($post) )
			$post = $this->get_page_type();
		//Get post's content type
		$ct =& $this->get_type($post);
		
		return array(&$ct, $action);
	}
	
	/**
	 * Enqueues files for fields in current content type
	 * @param string $page Current context
	 */
	function enqueue_files($page = null) {
		list($ct, $action) = $this->get_context();
		$file_types = array('scripts' => 'script', 'styles' => 'style');
		//Get content type fields
		foreach ( $ct->fields as $field ) {
			//Enqueue scripts/styles for each field
			foreach ( $file_types as $type => $func_base ) {
				$deps = $field->{"get_$type"}();
				foreach ( $deps as $handle => $args ) {
					//Confirm context
					if ( in_array('all', $args['context']) || in_array($page, $args['context']) || in_array($action, $args['context']) ) {
						$this->enqueue_file($func_base, $args['params']);
					}
				}
			}
		}
	}
	
	/**
	 * Add plugin hooks for fields used in current request
	 */
	function add_hooks() {
		list($ct, $action) = $this->get_context();
		//Iterate through content type fields and add hooks from fields
		foreach ( $ct->fields as $field ) {
			//Iterate through hooks added to field
			$hooks = $field->get_hooks(); 
			foreach ( $hooks as $tag => $callback ) {
				//Iterate through function callbacks added to tag
				foreach ( $callback as $id => $args ) {
					//Check if hook/function was already processed
					if ( isset($this->hooks_processed[$tag][$id]) )
						continue;
					//Add hook/function to list of processed hooks 
					if ( !isset($this->hooks_processed[$tag]) || !is_array($this->hooks_processed[$tag]) )
						$this->hooks_processed[$tag] = array($id => true);
					//Add hook to WP
					call_user_func_array('add_filter', $args);
				}
			}
		}
	}

	/**
	 * Enqueues files
	 * @param string $type Type of file to enqueue (script or style)
	 * @param array $args (optional) Arguments to pass to enqueue function
	 */
	function enqueue_file($type = 'script', $args = array()) {
		$func = 'wp_enqueue_' . $type;
		if ( function_exists($func) ) {
			call_user_func_array($func, $args);
		}
	}

	/**
	 * Add admin menus for content types
	 * @deprecated Not needed for 3.0+
	 */
	function admin_menu() {
		global $cnr_content_types;

		$pos = 21;
		foreach ( $cnr_content_types as $id => $type ) {
			if ( $this->is_default_post_type($id) )
				continue;
			$page = $this->get_admin_page_file($id);
			$callback = $this->m('admin_page');
			$access = 8;
			$pos += 1;
			$title = $type->get_title(true);
			if ( !empty($title) ) {
				//Main menu
				add_menu_page($type->get_title(true), $type->get_title(true), $access, $page, $callback, '', $pos);
				//Edit
				add_submenu_page($page, __('Edit ' . $type->get_title(true)), __('Edit'), $access, $page, $callback);
				$hook = get_plugin_page_hookname($page, $page);
				add_action('load-' . $hook, $this->m('admin_menu_load_plugin'));
				//Add
				$page_add = $this->get_admin_page_file($id, 'add');
				add_submenu_page($page, __('Add New ' . $type->get_title()), __('Add New'), $access, $page_add, $callback);
				$hook = get_plugin_page_hook($page_add, $page);
				add_action('load-' . $hook, $this->m('admin_menu_load_plugin'));
				//Hook for additional menus
				$menu_hook = 'cnr_admin_menu_type';
				//Type specific
				do_action_ref_array($menu_hook . '_' . $id, array(&$type));
				//General
				do_action_ref_array($menu_hook, array(&$type));
			}
		}
	}

	/**
	 * Load data for plugin admin page prior to admin-header.php is loaded
	 * Useful for enqueueing scripts/styles, etc.
	 */
	function admin_menu_load_plugin() {
		//Get Action
		global $editing, $post, $post_ID, $p;
		$action = $this->util->get_action();
		if ( isset($_GET['delete_all']) )
			$action = 'delete_all';
		if ( isset($_GET['action']) && 'edit' == $_GET['action'] && ! isset($_GET['bulk_edit']))
			$action = 'manage';
		switch ( $action ) {
			case 'delete_all' :
			case 'edit' :
				//Handle bulk actions
				//Redirect to edit.php for processing

				//Build query string
				$qs = $_GET;
				unset($qs['page']);
				$edit_uri = admin_url('edit.php') . '?' . build_query($qs);
				wp_redirect($edit_uri);
				break;
			case 'edit-item' :
				wp_enqueue_script('admin_comments');
				enqueue_comment_hotkeys_js();
				//Get post being edited
				if ( empty($_GET['post']) ) {
					wp_redirect("post.php"); //TODO redirect to appropriate manage page
					exit();
				}
				$post_ID = $p = (int) $_GET['post'];
				$post = get_post($post_ID);
				if ( !current_user_can('edit_post', $post_ID) )
					wp_die( __('You are not allowed to edit this item') );

				if ( $last = wp_check_post_lock($post->ID) ) {
					add_action('admin_notices', '_admin_notice_post_locked');
				} else {
					wp_set_post_lock($post->ID);
					$locked = true;
				}
				//Continue on to add case
			case 'add'	:
				$editing = true;
				wp_enqueue_script('autosave');
				wp_enqueue_script('post');
				if ( user_can_richedit() )
					wp_enqueue_script('editor');
				add_thickbox();
				wp_enqueue_script('media-upload');
				wp_enqueue_script('word-count');
				add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 25 );
				wp_enqueue_script('quicktags');
				wp_enqueue_script($this->add_prefix('edit_form'), $this->util->get_file_url('js/lib.admin.edit_form.js'), array('jquery', 'postbox'), false, true);
				break;
			default		:
				wp_enqueue_script( $this->add_prefix('inline-edit-post') );
		}
	}

	/**
	 * Build admin page file name for the specified post type
	 * @param string|CNR_Content_Type $type Content type ID or object
	 * @param string $action Action to build file name for
	 * @param bool $sep_action Whether action should be a separate query variable (Default: false)
	 * @return string Admin page file name
	 */
	function get_admin_page_file($type, $action = '', $sep_action = false) {
		if ( isset($type->id) )
			$type = $type->id;
		$page = $this->add_prefix('post_type_' . $type);
		if ( !empty($action) ) {
			if ( $sep_action )
				$page .= '&action=';
			else
				$page .= '-';

			$page .= $action;
		}
		return $page;
	}

	/**
	 * Determine content type based on URL query variables
	 * Uses $_GET['page'] variable to determine content type
	 * @return string Content type of page (NULL if no type defined by page)
	 */
	function get_page_type() {
		$type = null;
		//Extract type from query variable
		if ( isset($_GET['page']) ) {
			$type = $_GET['page'];
			$prefix = $this->add_prefix('post_type_');
			//Remove plugin page prefix
			if ( ($pos = strpos($type, $prefix)) === 0 )
				$type = substr($type, strlen($prefix));
			//Remove action (if present)
			if ( ($pos = strrpos($type, '-')) && $pos !== false )
				$type = substr($type, 0, $pos);
		}
		return $type;
	}

	/**
	 * Populate administration page for content type
	 */
	function admin_page() {
		$prefix = $this->add_prefix('post_type_');
		if ( strpos($_GET['page'], $prefix) !== 0 )
			return false;

		//Get action
		$action = $this->util->get_action('manage');
		//Get content type
		$type =& $this->get_type($this->get_page_type());
		global $title, $parent_file, $submenu_file;
		$title = $type->get_title(true);
		//$parent_file = $prefix . $type->id;
		//$submenu_file = $parent_file;

		switch ( $action ) {
			case 'edit-item' :
			case 'add' :
				$this->admin_page_edit($type, $action);
				break;
			default :
				$this->admin_page_manage($type, $action);
		}
	}

	/**
	 * Queries content items for admin management pages
	 * Also retrieves available post status for specified content type
	 * @see wp_edit_posts_query
	 * @param CNR_Content_Type|string $type Content type instance or ID
	 * @return array All item statuses and Available item status
	 */
	function admin_manage_query($type = 'post') {
		global $wp_query;
		$q = array();
		//Get post type
		if ( ! is_a($type, 'CNR_Content_Type') ) {
			$type = $this->get_type($type);
		}
		$q = array('post_type' => $type->id);
		$g = $_GET;
		//Date
		$q['m']   = isset($g['m']) ? (int) $g['m'] : 0;
		//Category
		$q['cat'] = isset($g['cat']) ? (int) $g['cat'] : 0;
		$post_stati  = array(	//	array( adj, noun )
					'publish' => array(_x('Published', 'post'), __('Published posts'), _n_noop('Published <span class="count">(%s)</span>', 'Published <span class="count">(%s)</span>')),
					'future' => array(_x('Scheduled', 'post'), __('Scheduled posts'), _n_noop('Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>')),
					'pending' => array(_x('Pending Review', 'post'), __('Pending posts'), _n_noop('Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>')),
					'draft' => array(_x('Draft', 'post'), _x('Drafts', 'manage posts header'), _n_noop('Draft <span class="count">(%s)</span>', 'Drafts <span class="count">(%s)</span>')),
					'private' => array(_x('Private', 'post'), __('Private posts'), _n_noop('Private <span class="count">(%s)</span>', 'Private <span class="count">(%s)</span>')),
					'trash' => array(_x('Trash', 'post'), __('Trash posts'), _n_noop('Trash <span class="count">(%s)</span>', 'Trash <span class="count">(%s)</span>')),
				);

		$post_stati = apply_filters('post_stati', $post_stati);

		$avail_post_stati = get_available_post_statuses('post');

		//Status
		if ( isset($g['post_status']) && in_array( $g['post_status'], array_keys($post_stati) ) ) {
			$q['post_status'] = $g['post_status'];
			$q['perm'] = 'readable';
		} else {
			unset($q['post_status']);
		}

		//Order
		if ( isset($q['post_status']) && 'pending' === $q['post_status'] ) {
			$q['order'] = 'ASC';
			$q['orderby'] = 'modified';
		} elseif ( isset($q['post_status']) && 'draft' === $q['post_status'] ) {
			$q['order'] = 'DESC';
			$q['orderby'] = 'modified';
		} else {
			$q['order'] = 'DESC';
			$q['orderby'] = 'date';
		}

		//Pagination
		$posts_per_page = (int) get_user_option( 'edit_per_page', 0, false );
		if ( empty( $posts_per_page ) || $posts_per_page < 1 )
			$posts_per_page = 15;
		if ( isset($g['paged']) && (int) $g['paged'] > 1 )
			$q['paged'] = (int) $g['paged'];
		$q['posts_per_page'] = apply_filters( 'edit_posts_per_page', $posts_per_page );
		//Search
		$q[s] = ( isset($g['s']) ) ? $g[s] : '';
		$wp_query->query($q);

		return array($post_stati, $avail_post_stati);
	}

	/**
	 * Counts the number of items in the specified content type
	 * @see wp_count_posts
	 * @param CNR_Content_Type|string $type Content Type instance or ID
	 * @param string $perm Permission level for items (e.g. readable)
	 * @return array Associative array of item counts by post status (published, draft, etc.)
	 */
	function count_posts( $type, $perm = '' ) {
		global $wpdb;

		$user = wp_get_current_user();

		if ( !is_a($type, 'CNR_Content_Type') )
			$type = $this->get_type($type);
		$type_val = $type->get_meta_value();
		$type = $type->id;
		$cache_key = $type;

		//$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
		$query = "SELECT p.post_status, COUNT( * ) as num_posts FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.id WHERE m.meta_key = '" . $this->get_type_meta_key() . "' AND m.meta_value = '$type_val'";
		if ( 'readable' == $perm && is_user_logged_in() ) {
			//TODO enable check for custom post types "read_private_{$type}s"
			if ( !current_user_can("read_private_posts") ) {
				$cache_key .= '_' . $perm . '_' . $user->ID;
				$query .= " AND (p.post_status != 'private' OR ( p.post_author = '$user->ID' AND p.post_status = 'private' ))";
			}
		}
		$query .= ' GROUP BY p.post_status';

		$count = wp_cache_get($cache_key, 'counts');
		if ( false !== $count )
			return $count;

		$count = $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );

		$stats = array( 'publish' => 0, 'private' => 0, 'draft' => 0, 'pending' => 0, 'future' => 0, 'trash' => 0 );
		foreach( (array) $count as $row_num => $row ) {
			$stats[$row['post_status']] = $row['num_posts'];
		}

		$stats = (object) $stats;
		wp_cache_set($cache_key, $stats, 'counts');

		return $stats;
	}

	/**
	 * Builds management page for items of a specific custom content type
	 * @param CNR_Content_Type $type Content Type to manage
	 * @param string $action Current action
	 * 
	 * @global string $title
	 * @global string $parent_file
	 * @global string $plugin_page
	 * @global string $page_hook
	 * @global WP_User $current_user
	 * @global WP_Query $wp_query
	 * @global wpdb $wpdb
	 * @global WP_Locale $wp_locale
	 */
	function admin_page_manage($type, $action) {
		if ( !current_user_can('edit_posts') )
			wp_die(__('You do not have sufficient permissions to access this page.'));

		global $title, $parent_file, $plugin_page, $page_hook, $current_user, $wp_query, $wpdb, $wp_locale;
		$title = __('Edit ' . $type->get_title(true));
		$admin_path = ABSPATH . 'wp-admin/'; 

		//Pagination
		if ( ! isset($_GET['paged']) )
			$_GET['paged'] = 1;

		$add_url = $this->get_admin_page_url($type->id, 'add');
		$is_trash = isset($_GET['post_status']) && $_GET['post_status'] == 'trash';
		//User posts
		$user_posts = false;
		if ( !current_user_can('edit_others_posts') ) {
			$user_posts_count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id = p.id WHERE m.meta_key = '_cnr_post_type' AND m.meta_value = %s AND p.post_status != 'trash' AND p.post_author = %d", $type->get_meta_value(), $current_user->ID) );
			$user_posts = true;
			if ( $user_posts_count && empty($_GET['post_status']) && empty($_GET['all_posts']) && empty($_GET['author']) )
				$_GET['author'] = $current_user->ID;
		}
		//Get content type items
		list($post_stati, $avail_post_stati) = $this->admin_manage_query($type->id);
		?>
		<div class="wrap">
		<?php screen_icon('edit'); ?>
		<h2><?php echo esc_html( $title ); ?> <a href="<?php echo $add_url; ?>" class="button add-new-h2"><?php echo esc_html_x('Add New', 'post'); ?></a> <?php
		if ( isset($_GET['s']) && $_GET['s'] )
			printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', esc_html( get_search_query() ) ); ?>
		</h2>
		<?php /* Action messages here: saved, trashed, etc. */ ?>
		<form id="posts-filter" action="<?php echo admin_url('admin.php'); ?>" method="get">
		<?php if ( isset($_GET['page']) ) { ?>
		<input type="hidden" name="page" id="page" value="<?php esc_attr_e($_GET['page']); ?>" />
		<?php } ?>
		<ul class="subsubsub">
		<?php 
		/* Status links */
		if ( empty($locked_post_status) ) :
			$status_links = array();
			$num_posts = $this->count_posts($type, 'readable');
			$class = '';
			$allposts = '';
			$curr_page = $_SERVER['PHP_SELF'] . '?page=' . $_GET['page'];
			if ( $user_posts ) {
				if ( isset( $_GET['author'] ) && ( $_GET['author'] == $current_user->ID ) )
					$class = ' class="current"';
				$status_links[] = "<li><a href='$curr_page&author=$current_user->ID'$class>" . sprintf( _nx( 'My Posts <span class="count">(%s)</span>', 'My Posts <span class="count">(%s)</span>', $user_posts_count, 'posts' ), number_format_i18n( $user_posts_count ) ) . '</a>';
				$allposts = '?all_posts=1';
			}

			$total_posts = array_sum( (array) $num_posts ) - $num_posts->trash;
			$class = empty($class) && empty($_GET['post_status']) ? ' class="current"' : '';
			$status_links[] = "<li><a href='$curr_page{$allposts}'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

			foreach ( $post_stati as $status => $label ) {
				$class = '';

				if ( !in_array( $status, $avail_post_stati ) )
					continue;

				if ( empty( $num_posts->$status ) )
					continue;

				if ( isset($_GET['post_status']) && $status == $_GET['post_status'] )
					$class = ' class="current"';

				$status_links[] = "<li><a href='$curr_page&post_status=$status'$class>" . sprintf( _n( $label[2][0], $label[2][1], $num_posts->$status ), number_format_i18n( $num_posts->$status ) ) . '</a>';
			}
			echo implode( " |</li>\n", $status_links ) . '</li>';
			unset( $status_links );
		endif;
		?>
		</ul>
		<p class="search-box">
			<label class="screen-reader-text" for="post-search-input"><?php _e('Search Posts', 'cornerstone'); ?>:</label>
			<input type="text" id="post-search-input" name="s" value="<?php the_search_query(); ?>" />
			<input type="submit" value="<?php esc_attr_e('Search Posts', 'cornerstone'); ?>" class="button" />
		</p>
		<?php 
		if ( have_posts() ) {
		?>
		<div class="tablenav">
		<?php 
		$page_links = paginate_links( array(
			'base'		=> add_query_arg( 'paged', '%#%' ),
			'format'	=> '',
			'prev_text'	=> __('&laquo;'),
			'next_text'	=> __('&raquo;'),
			'total'		=> $wp_query->max_num_pages,
			'current'	=> $_GET['paged']
		));
		?>
		<div class="alignleft actions">
		<select name="action">
			<option value="-1" selected="selected"><?php _e('Bulk Actions', 'cornerstone'); ?></option>
			<?php if ( $is_trash ) { ?>
			<option value="untrash"><?php _e('Restore', 'cornerstone'); ?></option>
			<?php } else { ?>
			<option value="edit"><?php _e('Edit', 'cornerstone'); ?></option>
			<?php } if ( $is_trash || !EMPTY_TRASH_DAYS ) { ?>
			<option value="delete"><?php _e('Delete Permanently', 'cornerstone'); ?></option>
			<?php } else { ?>
			<option value="trash"><?php _e('Move to Trash', 'cornerstone'); ?></option>
			<?php } ?>
		</select>
		<input type="submit" value="<?php esc_attr_e('Apply', 'cornerstone'); ?>" name="doaction" id="doaction" class="button-secondary action" />
		<?php wp_nonce_field('bulk-posts'); ?>

		<?php // view filters
		if ( !is_singular() ) {
		$arc_query = "SELECT DISTINCT YEAR(post_date) AS yyear, MONTH(post_date) AS mmonth FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id = p.ID WHERE m.meta_key = '" . $this->get_type_meta_key() . "' AND m.meta_value = '" . $type->get_meta_value() . "' ORDER BY post_date DESC";

		$arc_result = $wpdb->get_results( $arc_query );

		$month_count = count($arc_result);

		if ( $month_count && !( 1 == $month_count && 0 == $arc_result[0]->mmonth ) ) {
		$m = isset($_GET['m']) ? (int)$_GET['m'] : 0;
		?>
		<select name='m'>
		<option<?php selected( $m, 0 ); ?> value='0'><?php _e('Show all dates', 'cornerstone'); ?></option>
		<?php
		foreach ($arc_result as $arc_row) {
			if ( $arc_row->yyear == 0 )
				continue;
			$arc_row->mmonth = zeroise( $arc_row->mmonth, 2 );

			if ( $arc_row->yyear . $arc_row->mmonth == $m )
				$default = ' selected="selected"';
			else
				$default = '';

			echo "<option$default value='" . esc_attr("$arc_row->yyear$arc_row->mmonth") . "'>";
			echo $wp_locale->get_month($arc_row->mmonth) . " $arc_row->yyear";
			echo "</option>\n";
		}
		?>
		</select>
		<?php } 

		$dropdown_options = array('show_option_all' => __('View all categories'), 'hide_empty' => 0, 'hierarchical' => 1,
			'show_count' => 0, 'orderby' => 'name', 'selected' => $cat);
		wp_dropdown_categories($dropdown_options);
		do_action('restrict_manage_posts');
		?>
		<input type="submit" id="post-query-submit" value="<?php esc_attr_e('Filter', 'cornerstone'); ?>" class="button-secondary" />
		<?php } 

		if ( $is_trash && current_user_can('edit_others_posts') ) { ?>
		<input type="submit" name="delete_all" id="delete_all" value="<?php esc_attr_e('Empty Trash', 'cornerstone'); ?>" class="button-secondary apply" />
		<?php } ?>
		</div>

		<?php if ( $page_links ) { ?>
		<div class="tablenav-pages"><?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s', 
			number_format_i18n( ( $_GET['paged'] - 1 ) * $wp_query->query_vars['posts_per_page'] + 1 ),
			number_format_i18n( min( $_GET['paged'] * $wp_query->query_vars['posts_per_page'], $wp_query->found_posts ) ),
			number_format_i18n( $wp_query->found_posts ),
			$page_links
		); echo $page_links_text; ?></div>
		<?php } //page links ?>
		<div class="clear"></div>
		</div>
		<?php
			include ($admin_path . 'edit-post-rows.php');
		} else { //have_posts() ?>
		<div class="clear"></div>
		<p><?php
		if ( $is_trash )
			_e('No posts found in the trash', 'cornerstone');
		else
			_e('No posts found', 'cornerstone');
		?></p>
		<?php } ?>
		</form>
		<?php inline_edit_row('post'); ?>
		<div id="ajax-response"></div>
		<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Build admin edit page for custom type item
	 * @param CNR_Content_Type $type Content type being edited
	 * @param string $action Current action (add, edit, manage, etc.)
	 */
	function admin_page_edit($type, $action) {
		global $title, $hook_suffix, $parent_file, $screen_layout_columns, $post, $post_ID, $p;
		$screen_layout_columns = 2;
		//TODO Add default icon for content type
		$parent_file = 'edit.php'; //Makes screen_icon() use edit icon on post edit form
		switch ( $action ) {
			case 'edit-item' :
				$title = 'Edit';
				$post = get_post_to_edit($post_ID);
				break;
			default :
				$title = 'Add New';
				$post = get_default_post_to_edit();
				break;	
		}
		$title = __($title . ' ' . $type->get_title());
		$admin_path = ABSPATH . 'wp-admin/';
		include ($admin_path . 'edit-form-advanced.php');
	}

	/**
	 * Adds hidden field declaring content type on post edit form
	 * @deprecated no longer needed for WP 3.0+
	 */
	function admin_page_edit_form() {
		global $post, $plugin_page;
		if ( empty($post) || !$post->ID ) {
			$type = $this->get_type($post);
			if ( ! empty($type) && ! empty($type->id) ) {
			?>
			<input type="hidden" name="cnr[content_type]" id="cnr[content_type]" value="<?php echo $type->id; ?>" />
			<?php
			}
		}
	}

	/**
	 * Adds meta boxes for post's content type
	 * Each group in content type is a separate meta box
	 * @param string $type Type of item meta boxes are being build for (post, page, link)
	 * @param string $context Location of meta box (normal, advanced, side)
	 * @param object $post Post object
	 */
	function admin_do_meta_boxes($type, $context, $post) {
		//Validate $type. Should be 'post','page', or a custom post type for our purposes
		if ( in_array($type, array_merge(array_keys($this->get_types()), array('post', 'page'))) ) {
			//Get content type definition
			$ct =& $this->get_type($post);
			//Pass processing to content type instance
			$ct->admin_do_meta_boxes($type, $context, $post);
		}
	}

	/**
	 * Saves field data submitted for current post
	 * @param int $post_id ID of current post
	 * @param object $post Post object
	 */
	function save_item_data($post_id, $post) {
		if ( empty($post_id) || empty($post) || !isset($_POST['cnr']) || !is_array($_POST['cnr']) )
			return false;
		$pdata = $_POST['cnr'];
		
		if ( isset($pdata['attributes']) && is_array($pdata['attributes']) && isset($pdata['fields_loaded']) && is_array($pdata['fields_loaded']) ) {
			
			$prev_data = (array) $this->get_item_data($post_id);
			
			//Remove loaded fields from prev data
			$prev_data = array_diff_key($prev_data, $pdata['fields_loaded']);
			
			//Get current field data
			$curr_data = $pdata['attributes'];
						
			//Merge arrays together (new data overwrites old data)
			if ( is_array($prev_data) && is_array($curr_data) ) {
				$curr_data = array_merge($prev_data, $curr_data);
			}
			
			//Save to database
			update_post_meta($post_id, $this->get_fields_meta_key(), $curr_data);
		}
		//Save content type
		if ( isset($_POST['cnr']['content_type']) ) {
			$type = $_POST['cnr']['content_type'];
			$saved_type = get_post_meta($post_id, $this->get_type_meta_key(), true);
			if ( is_array($saved_type) )
				$saved_type = implode($saved_type);
			if ( $type != $saved_type ) {
				//Continue processing if submitted content type is different from previously-saved content type (or no type was previously set)
				update_post_meta($post_id, $this->get_type_meta_key(), array($type));
			}
		}
	}

	/*-** Helpers **-*/

	/**
	 * Get array of default post types
	 * @return array Default post types
	 */
	function get_default_post_types() {
		return array('post', 'page', 'attachment', 'revision', 'nav_menu');
	}

	/**
	 * Checks if post's post type is a standard WP post type
	 * @param mixed $post_type Post type (default) or post ID/object to evaluate
	 * @see CNR_Content_Utilities::get_type() for possible parameter values
	 * @return bool TRUE if post is default type, FALSE if it is a custom type
	 */
	function is_default_post_type($post_type) {
		if ( !is_string($post_type) ) {
			$post_type = $this->get_type($post_type);
			$post_type = $post_type->id;
		}
		return in_array($post_type, $this->get_default_post_types());
	}

	/**
	 * Checks if specified content type has been defined
	 * @param string|CNR_Content_Type $type Content type ID or object
	 * @return bool TRUE if content type exists, FALSE otherwise
	 * 
	 * @uses array $cnr_content_types
	 */
	function type_exists($type) {
		global $cnr_content_types;
		if ( ! is_scalar($type) ) {
			if ( is_a($type, 'CNR_Content_Type') )
				$type = $type->id;
			else
				$type = null;
		}
		return ( isset($cnr_content_types[$type]) );
	}

	/**
	 * Retrieves content type definition for specified content item (post, page, etc.)
	 * If content type does not exist, a new instance object will be created and returned
	 * > New content types are automatically registered (since we are looking for registered types when using this method)
	 * @param string|object $item Post object, or item type (string)
	 * @return CNR_Content_Type Reference to matching content type, empty content type if no matching type exists
	 * 
	 * @uses array $cnr_content_types
	 */
	function &get_type($item) {
		//Return immediately if $item is a content type instance
		if ( is_a($item, 'CNR_Content_Type') )
			return $item;

		$type = null;

		if ( is_string($item) )
			$type = $item;

		if ( !$this->type_exists($type) ) {
			$post = $item;

			//Check if $item is a post (object or ID)
			if ( $this->util->check_post($post) && isset($post->post_type) ) {
				$type = $post->post_type;
			}
		}
		global $cnr_content_types;
		if ( $this->type_exists($type) ) {
			//Retrieve content type from global array
			$type =& $cnr_content_types[$type];
		} else {
			//Create new empty content type if it does not already exist
			$type = new CNR_Content_Type($type);
			//Automatically register newly initialized content type if it extends an existing WP post type
			if ( $this->is_default_post_type($type->id) )
				$type->register();
		}

		return $type;
	}
	
	/**
	 * Retrieve content types
	 * @return Reference to content types array
	 */
	function &get_types() {
		return $GLOBALS['cnr_content_types'];
	}

	/**
	 * Retrieve meta key for post fields
	 * @return string Fields meta key
	 */
	function get_fields_meta_key() {
		return $this->util->make_meta_key('fields');
	}

	/**
	 * Retrieve meta key for post type
	 * @return string Post type meta key
	 */
	function get_type_meta_key() {
		return $this->util->make_meta_key('post_type');
	}

	/**
	 * Checks if post contains specified field data
	 * @param Object $post (optional) Post to check data for
	 * @param string $field (optional) Field ID to check for
	 * @return bool TRUE if data exists, FALSE otherwise
	 */
	function has_item_data($item = null, $field = null) {
		$ret = $this->get_item_data($item, $field, 'raw', null);
		if ( is_scalar($ret) )
			return ( !empty($ret) || $ret === 0 );
		if ( is_array($ret) ) {
			foreach ( $ret as $key => $val ) {
				if ( !empty($val) || $val === 0 )
					return true;
			}
		}
		return false;
	}

	/**
	 * Retrieve specified field data from content item (e.g. post)
	 * Usage Examples:
	 * get_item_data($post_id, 'field_id')
	 *  - Retrieves field_id data from global $post object
	 *  - Field data is formatted using 'display' layout of field
	 *  
	 * get_item_data($post_id, 'field_id', 'raw')
	 *  - Retrieves field_id data from global $post object
	 *  - Raw field data is returned (no formatting)
	 *  
	 * get_item_data($post_id, 'field_id', 'display', $post_id)
	 *  - Retrieves field_id data from post matching $post_id
	 *  - Field data is formatted using 'display' layout of field
	 *  
	 * get_item_data($post_id, 'field_id', null)
	 *  - Retrieves field_id data from post matching $post_id
	 *  - Field data is formatted using 'display' layout of field
	 *    - The default layout is used when no valid layout is specified
	 *
	 * get_item_data($post_id)
	 *  - Retrieves full data array from post matching $post_id
	 *  
	 * @param int|object $item(optional) Content item to retrieve field from (Default: null - global $post object will be used)
	 * @param string $field ID of field to retrieve
	 * @param string $layout(optional) Layout to use when returning field data (Default: display)
	 * @param array $attr (optional) Additional attributes to pass along to field object (e.g. for building layout, etc.)
	 * @see CNR_Field_Type::build_layout for more information on attribute usage
	 * @return mixed Specified field data 
	 */
	function get_item_data($item = null, $field = null, $layout = null, $default = '', $attr = null) {
		$ret = $default;

		//Get item
		$item = get_post($item);

		if ( !isset($item->ID) )
			return $ret;

		//Get item data
		$data = get_post_meta($item->ID, $this->get_fields_meta_key(), true);

		//Get field data

		//Set return value to data if no field specified
		if ( empty($field) || !is_string($field) )
			$ret = $data;
		//Stop if no valid field specified
		if ( !isset($data[$field]) ) {
			//TODO Check $item object to see if specified field exists (e.g. title, post_status, etc.)
			return $ret;
		}

		$ret = $data[$field];

		//Initialize layout value
		$layout_def = 'display';

		if ( !is_scalar($layout) || empty($layout) )
			$layout = $layout_def;

		$layout = strtolower($layout);

		//Check if raw data requested
		if ( 'raw' == $layout )
			return $ret;

		/* Build specified layout */

		//Get item's content type
		$ct =& $this->get_type($item);
		$ct->set_data($data);

		//Get field definition
		$fdef =& $ct->get_field($field);

		//Validate layout
		if ( !$fdef->has_layout($layout) )
			$layout = $layout_def;
		
		//Build layout
		$fdef->set_caller($ct);
		$ret = $fdef->build_layout($layout, $attr);
		$fdef->clear_caller();

		//Return formatted value
		return $ret;
	}

	/**
	 * Prints an item's field data
	 * @see CNR_Content_Utilities::get_item_data() for more information
	 * @param int|object $item(optional) Content item to retrieve field from (Default: null - global $post object will be used)
	 * @param string $field ID of field to retrieve
	 * @param string $layout(optional) Layout to use when returning field data (Default: display)
	 * @param mixed $default (optional) Default value to return in case of errors, etc.
	 * @param array $attr Additional attributes to pass to field
	 */
	function the_item_data($item = null, $field = null, $layout = null, $default = '', $attr = null) {
		echo apply_filters('cnr_the_item_data', $this->get_item_data($item, $field, $layout, $default, $attr), $item, $field, $layout, $default, $attr);
	}

	/**
	 * Build Admin URL for specified post type
	 * @param string|CNR_Content_Type $type Content type ID or object
	 * @param string $action Action to build URL for
	 * @param bool $sep_action Whether action should be a separate query variable (Default: false)
	 * @return string Admin page URL
	 */
	function get_admin_page_url($type, $action = '', $sep_action = false) {
		$url = admin_url('admin.php');
		$url .= '?page=' . $this->get_admin_page_file($type, $action, $sep_action);
		return $url; 
	}

	function get_edit_item_url($edit_url, $item_id, $context) {
		//Get post type
		$type = $this->get_type($item_id);
		if (  ! $this->is_default_post_type($type->id) && $this->type_exists($type) ) {
			$edit_url = $this->get_admin_page_url($type, 'edit-item', true) . '&post=' . $item_id;
		}

		return $edit_url;
	}
}