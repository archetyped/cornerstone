<?php

require_once 'includes/class.base.php';
require_once 'includes/class.content-types.php';
require_once 'includes/class.posts.php';

/**
 * @package Cornerstone
 */
class Cornerstone extends CNR_Base {
	/* Variables */
	
	/**
	 * @var string Path to calling file
	 */
	var $_caller;
	
	/**
	 * @var string Prefix base for elements created by plugin
	 * @static
	 */
	var $_prefix = 'cnr_';
	
	/**
	 * @var string Prefix for elements created by plugin
	 */
	var $_prefix_db = '';
	
	/**
	 * @var string variable to add to queries to indicate that the plugin has modified the query
	 */
	var $_qry_var = 'var';
	
	/**
	 * @var string Name of property to store post parts under
	 */
	var $_post_parts_var = 'parts';
	
	/**
	 * @var string Base name for Content Type Admin Menus
	 */
	var $file_content_types = 'content-types';
	
	/**
	 * @var string Path to plugin
	 */
	var $path = __FILE__;
	
	
	/* State Variables */
	
	/**
	 * @var bool Whether the current request is occuring during WP initialization
	 */
	var $state_init = false;
	
	/**
	 * @var bool Whether the current request is occuring during section children request
	 */
	var $state_children = false;
	
	/**
	 * @var object|int Current section post object (or 0 if not in a section)
	 */
	var $section_current = null;
	
	/* Featured Content variables */
	
	/**
	 * @var string Category slug value that denotes a "featured" post
	 * @see posts_featured_cat()
	 */
	var $posts_featured_cat = "feature";
	
	/**
	 * @var array Stores featured posts
	 */
	//var $posts_featured = array();
	
	/**
	 * @var bool Whether or not there are any featured posts
	 */
	var $posts_featured_has = false;
	
	/**
	 * @var object Featured post object currently loaded in global $post variable
	 */
	var $posts_featured_current = -1;
	
	/**
	 * @var int Total number of featured posts
	 */
	var $posts_featured_count = 0;
	
	/**
	 * @var CNR_Posts Featured posts container
	 */
	var $posts_featured = null;
	
	/**
	 * @var string Key used to store post subtitle
	 */
	var $post_subtitle_field = "subtitle";
	
	/* Children Content Variables */
	
	/**
	 * @var array Children posts of current post
	 */
	var $post_children = null;
	
	/**
	 * @var int ID of Page that children were retrieved for
	 */
	var $post_children_parent = null;
	
	/**
	 * @var bool Whether or not post has children
	 */
	var $post_children_has = false;
	
	/**
	 * @var object Child post currently loaded in global $post variable
	 */
	var $post_children_current = -1;
	
	/**
	 * @var int Total number of children posts (in current request)
	 */
	var $post_children_count = 0;
	
	/**
	 * @var int Total number of children posts in database
	 */
	var $post_children_found = 0;
	
	/* Instance Variables */
	
	/**
	 * @var CNR_Content_Types Content Types instance
	 */
	var $ct;
	
	/* Content Types */
	
	var $post_images = array(
							'thumbnail',
							'header'
							);
	
	/* Constructor */
	
	function Cornerstone($caller)  {
		$this->__construct($caller);
	}
							
	function __construct($caller) {
		//Parent Constructor
		parent::__construct();
		
		//$GLOBALS['cnr'] =& $this;
		
		//Set Properties
		$this->_caller = $caller;
		$this->_prefix_db = $this->get_db_prefix();
		$this->_qry_var = $this->_prefix . $this->_qry_var;
		$this->_post_parts_var = $this->_prefix . $this->_post_parts_var;
		$this->path = str_replace('\\', '/', $this->path);
		$this->url_base = dirname(WP_PLUGIN_URL . str_replace(str_replace('\\', '/', WP_PLUGIN_DIR), '', $this->path));
		$this->posts_featured = new CNR_Posts( array( 'category' => $this->posts_featured_get_cat_id() ) );
		
		//Initialization
		
		//Initialize instance variables
		$this->ct = new CNR_Content_Types();

		register_activation_hook($this->_caller, $this->m('activate'));
		
		/* Register Hooks */
		
		//Admin
			//Initialization
		add_action('admin_init', $this->m('admin_init'));
			//Head
		//add_action('admin_head', $this->m('admin_add_styles'));
		add_action('admin_print_scripts', $this->m('admin_add_scripts'));
		add_action('admin_print_styles', $this->m('admin_add_styles'));
			//Menus
		add_action('admin_menu', $this->m('admin_menu'));
		add_action('admin_menu', $this->m('admin_post_sidebar'));
		add_action('admin_menu', $this->m('admin_post_edit'));
			//Dynamically built function call (media_upload_$type)
		add_action('media_upload_post_image', $this->m('admin_media_upload_post_image'));
			//Attachments
		add_filter('attachment_fields_to_edit', $this->m('attachment_fields_to_edit'), 11, 2);
		add_action('pre-html-upload-ui', $this->m('attachment_html_upload_ui'));
		add_filter('media_meta', $this->m('media_meta'), 10, 2);
		add_filter('admin_url', $this->m('media_upload_url'), 10, 2);
		add_action('save_post', $this->m('post_save'), 10, 2);
		
			//Management
		add_action('restrict_manage_posts', $this->m('admin_restrict_manage_posts'));
		add_action('request', $this->m('admin_manage_posts_filter_section'));
		add_filter('manage_posts_columns', $this->m('admin_manage_posts_columns'));
		add_action('manage_posts_custom_column', $this->m('admin_manage_posts_custom_column'), 10, 2);
		add_action('quick_edit_custom_box', $this->m('admin_quick_edit_custom_box'), 10, 2);

			//TinyMCE
		//add_action('init', $this->m('admin_mce_register'));
		add_filter('tiny_mce_before_init', $this->m('admin_mce_before_init'));
		add_filter('mce_buttons', $this->m('admin_mce_buttons'));
		add_filter('mce_external_plugins', $this->m('admin_mce_external_plugins'));
		add_action('admin_print_scripts', $this->m('admin_post_quicktags'));
		
		//Feeds
		add_action('template_redirect', $this->m('feed_redirect'));
		add_filter('get_wp_title_rss', $this->m('feed_title'));
		add_filter('get_bloginfo_rss', $this->m('feed_description'), 10, 2);
		
		//Rewrite Rules
		add_filter('query_vars', $this->m('query_vars'));
		add_filter('rewrite_rules_array', $this->m('rewrite_rules_array'));
		
		//Post Filtering
		
		//Initial request
		add_action('parse_request', $this->m('request_init_start'));
		//add_action('pre_get_posts', $this->m('pre_get_posts_excluded'));
		add_action('wp', $this->m('request_init_end'));
		
		//Posts
		add_filter('the_posts', $this->m('post_children_get'));
		add_filter('post_link', $this->m('post_link'), 10, 2);
		add_filter('redirect_canonical', $this->m('post_link'), 10, 2);
		add_filter('wp_list_pages', $this->m('post_section_highlight'));
		
		//Item retrieval
		add_action('pre_get_posts', $this->m('pre_get_posts'));
		//add_filter('posts_request', $this->m('posts_request'));

		//Activate Shortcodes
		$this->sc_activate();
		
		//Register Hooks for other classes
		//Page Group
		CNR_Page_Group::register_hooks();
	}
	
	/* Methods */
	
	/*-** Helpers **-*/
	
	/**
	 * Returns URL of file (assumes that it is in plugin directory)
	 * @return string File path
	 * @param string $file name of file get URL
	 */
	function get_file_url($file) {
		if (is_string($file) && '' != trim($file)) {
			$file = ltrim(trim($file), '/');
			$file = sprintf('%s/%s', $this->url_base, $file);
		}
		return $file;
	}
	
	/**
	 * Returns Database prefix for Cornerstone-related DB Tables
	 * @static
	 * @return string Database prefix
	 */
	function get_db_prefix() {
		global $wpdb;
		$c_vars = get_class_vars(__CLASS__);
		return $wpdb->prefix . $c_vars['_prefix'];
	}
	
	/**
	 * Returns Class prefix
	 * @static
	 * @return string Class prefix
	 */
	function get_prefix() {
		$c_vars = get_class_vars(__CLASS__);
		return $c_vars['_prefix'];
	}
	
	/**
	 * Get the IDs of a collection of posts
	 * @return array IDs of Posts passed to function
	 * @param array $posts Array of Post objects 
	 */
	function posts_get_ids($posts) {
		$callback = create_function('$post', 'return $post->ID;');
		$arr_ids = array_map($callback, $posts);
		return $arr_ids;
	}
	
	/*-** Activation **-*/
	
	function activate() {
		global $wpdb, $wp_rewrite;
		//Create DB Tables (if not yet created)
		//Setup table definitions
		$tables = array(
						$this->_prefix_db . 'page_groups' => 'CREATE TABLE ' . $this->_prefix_db . 'page_groups (
												group_id int(5) unsigned NOT NULL auto_increment,
												group_title varchar(90) NOT NULL,
												group_name varchar(90) NOT NULL,
												group_pages text,
												PRIMARY KEY (group_id) 
											)'
						);
		//Check tables and create/modify if necessary
		foreach ($tables as $key => $val)
		{
			if ($wpdb->get_var("SHOW TABLES LIKE '" . $key . "'") != $key) {
		        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		        dbDelta($val);
			}
		}
		
		//Rebuild URL Rewrite rules
		$wp_rewrite->flush_rules();
	}

	/*-** Admin **-*/
	
	/**
	 * Performs specified operations when admin is initialized
	 * @return void
	 */
	function admin_init() {
		$this->admin_settings_lightbox();
	}
	
	/**
	 * Adds settings section for Lightbox functionality
	 * Section is added to Settings > Media Admin menu
	 * @return void
	 */
	function admin_settings_lightbox() {
		$page = 'media';
		$section = 'cnr_lb';
		//Section
		add_settings_section($section, 'Lightbox Settings', $this->m('admin_lb_section'), $page);
		//Fields
		$fields = array(
						'enabled'			=>	'Enable Lightbox Functionality',
						'autostart'			=>	'Automatically Start Slideshow',
						'duration'			=>	'Slide Duration (Seconds)',
						'loop'				=>	'Loop through images',
						'overlay_opacity'	=>	'Overlay Opacity (0 - 1)'
						);
		foreach ($fields as $key => $title) {
			$id = 'cnr_lb_' . $key;
			$callback = $this->m('admin_lb_' . $key);
			add_settings_field($id, $title, $callback, $page, $section, array('label_for' => $id));
			register_setting('media', $id);
		}
	}
	
	/**
	 * Placeholder function for lightbox admin settings
	 * @return void
	 */
	function admin_lb_section() { }
	
	/**
	 * Lightbox setting - Enabled/Disabled
	 * @return void
	 */
	function admin_lb_enabled() {
		$checked = '';
		$id = 'cnr_lb_enabled';
		if (get_option($id))
			$checked = ' checked="checked" ';
		$format = '<input type="checkbox" %1$s id="%2$s" name="%2$s" class="code" /> (Default: Yes)';
		echo sprintf($format, $checked, $id);
	}
	
	/**
	 * Lightbox setting - Slideshow autostart
	 * @return void
	 */
	function admin_lb_autostart() {
		$checked = '';
		$id = 'cnr_lb_autostart';
		if (get_option($id))
			$checked = ' checked="checked" ';
		$format = '<input type="checkbox" %1$s id="%2$s" name="%2$s" class="code" /> (Default: Yes)';
		echo sprintf($format, $checked, $id);
	}
	
	/**
	 * Lightbox setting - Slide duration
	 * @return void
	 */
	function admin_lb_duration() {
		$val = 6;
		$id = 'cnr_lb_duration';
		$opt = get_option($id); 
		if ($opt) $val = $opt;
		$format = '<input type="text" size="3" maxlength="3" value="%1$s" id="%2$s" name="%2$s" class="code" /> (Default: 6)';
		echo sprintf($format, $val, $id);
	}
	
	/**
	 * Lightbox setting - Looping
	 * @return void
	 */
	function admin_lb_loop() {
		$checked = '';
		$id = 'cnr_lb_loop';
		if (get_option($id))
			$checked = ' checked="checked" ';
		$format = '<input type="checkbox" %1$s id="%2$s" name="%2$s" class="code" /> (Default: Yes)';
		echo sprintf($format, $checked, $id);
	}
	
	/**
	 * Lightbox setting - Overlay Opacity
	 * @return void
	 */
	function admin_lb_overlay_opacity() {
		$val = 0.8;
		$id = 'cnr_lb_overlay_opacity';
		$opt = get_option($id); 
		if ($opt) $val = $opt;
		$format = '<input type="text" size="3" maxlength="5" value="%1$s" id="%2$s" name="%2$s" class="code" /> (Default: 0.8)';
		echo sprintf($format, $val, $id);
	}
	
	/**
	 * Sets up Admin menus
	 * @return void
	 */
	function admin_menu() {
		global $menu, $submenu;
		//Content Types
		//add_menu_page('Content Types', 'Content Types', 8, $this->file_content_types, $this->m('menu_content_types'));
		//add_submenu_page($this->file_content_types, 'Manage Content Types', 'Manage', 8, $this->file_content_types . '_manage', $this->m('menu_content_types_manage'));
		
		//Page Groups
		add_pages_page('Page Groups', 'Groups', 8, 'page-groups', $this->m('menu_page_groups'));
		
		/**
		 * TODO Enable Site Pages replacement
		 */
		/*
		//Replace default Pages content
		$edit_pages = 'edit-pages';
		$edit_pages_file = $edit_pages . '.php';
		$edit_pages_level = 'edit_pages';
		$submenu[$edit_pages_file][5] = array( __('Edit'), $edit_pages_level, $edit_pages_file . '?page=' . $edit_pages);
		$hookname = get_plugin_page_hookname($edit_pages, $edit_pages_file);
		$function = $this->m('menu_pages_edit');
		if (!empty ( $function ) && !empty ( $hookname ))
			add_action( $hookname, $function );
		*/
	}

	/**
	 * Content to display for Content Types Admin Menu
	 * @return void
	 */
	function menu_content_types() {
		echo '<div class="wrap"><h2>Content Types</h2></div>';
	}
	
	/**
	 * Replacement content for default edit pages admin menu
	 * @return void
	 */
	function menu_pages_edit() {
		?>
		<div class="wrap edit-pages">
			<?php screen_icon(); ?>
			<h2>Edit Pages</h2>
			<ul class="site-pages">
				<?php wp_list_pages('title_li='); ?>
				<div class="actions actions-commit">
					<input type="button" value="Save" class="action save button-primary" />  <input type="button" value="Cancel" class="action reset button-secondary" />
				</div>
			</ul>
		</div>
		<?php
	}
	
	/**
	 * Content to display for Page Groups Admin Menu
	 * @return void
	 */
	function menu_page_groups() {
		?>
		<div class="wrap">
			<?php screen_icon() ?>
			<h2>Page Groups</h2>
			<table class="widefat page fixed" cellspacing="0">
				<thead>
					<tr>
					<!--?php print_column_headers('edit-pages'); ?-->
						<th>Name</th>
						<th class="column-rel">Code</th>
						<th class="column-rel">Pages</th>
					</tr>
				</thead>
				
				<tfoot>
					<tr>
					<!--?php print_column_headers('edit-pages', false); ?-->
						<th>Name</th>
						<th>Code</th>
						<th>Pages</th>
					</tr>
				</tfoot>
				
				<tbody class="page-groups_wrap">
					<!--?php page_rows($posts, $pagenum, $per_page); ?-->
					<?php CNR_Page_Groups::rows(); ?>
				</tbody>
			</table>
			<div class="tablenav">
				<div class="alignleft actions">
					<a href="#" class="page-group_new button-secondary">Add Page Group</a>
				</div>
			</div>
		</div>
		
		<?php
	}
	
	/**
	 * Content to display for Content Types Management Admin Submenu
	 * @return string
	 */
	function menu_content_types_manage() {
		echo '<div class="wrap"><h2>Manage Content Types</h2></div>';
	}
	
	function admin_add_styles() {
		//Define file properties
		$file_base = 'admin_styles';
		$handle = $this->_prefix . $file_base;
		$file_url = $this->get_file_url($file_base . '.css');
		
		//Add to page
		wp_register_style($handle, $file_url);
		wp_print_styles($handle);
	}
	
	/**
	 * Adds external javascript files to admin header
	 * @return void
	 */
	function admin_add_scripts() {
		//Page Groups
		if (strpos($_SERVER['QUERY_STRING'], 'page=page-groups') !== false) {
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('jquery-ui-effects', $this->get_file_url('effects.core.js'));
			wp_enqueue_script($this->_prefix . 'script-ns', $this->get_file_url('jtree.js'));
			wp_enqueue_script($this->_prefix . 'script', $this->get_file_url('cnr.js'));
		}
		//Edit Posts
		if ( 'edit.php' == basename($_SERVER['REQUEST_URI']) ) {
			wp_enqueue_script( $this->_prefix . 'inline-edit-post', $this->get_file_url('js/inline-edit-post.js'), array('jquery', 'inline-edit-post') );
			echo '<script type="text/javascript">postData = {};</script>';
		}
		wp_enqueue_script($this->_prefix . 'script_admin', $this->get_file_url('cnr_admin.js'));
	}
	
	/**
	 * Adds quicktags to post edit form
	 * @return void
	 */
	function admin_post_quicktags() {
		if (strpos($_SERVER['REQUEST_URI'], 'page-new.php') !== false || strpos($_SERVER['REQUEST_URI'], 'post-new.php') !== false) {
			wp_enqueue_script('cnr_quicktags', plugin_dir_url($this->path) . 'js/cnr_quicktags.js', array('quicktags'));
		}
	}
	
	/**
	 * Add content to post edit form
	 * @return void
	 */
	function admin_post_edit() {
		foreach ($this->post_images as $img) {
			add_meta_box($this->_prefix . 'image_' . $img, 'Post ' . ucwords(strtolower($img)), $this->m('admin_post_box_image_' . $img), 'post');
		}
	}
	
	function admin_post_box_image_thumbnail($post) {
		$this->admin_post_box_image($post, 'thumbnail');
	}
	
	function admin_post_box_image_header($post) {
		$this->admin_post_box_image($post, 'header');
	}
	
	/**
	 * Add Post Image meta box to Post edit form
	 * @param object $post Post Object
	 * @return void
	 */
	function admin_post_box_image($post, $img) {
		global $post_ID, $temp_ID;
		$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
		$media_upload_iframe_src = "media-upload.php?post_id=$uploading_iframe_ID";
		$image_upload_iframe_src = apply_filters('image_upload_iframe_src', "$media_upload_iframe_src&amp;type=post_image&amp;cnr_action=post_image&amp;cnr_image_type=$img");
		$image_name = "cnr-image-$img";
		$image_title = __('Set Post ' . ucwords(strtolower($img)));
		
		$post_image = get_post_meta($post_ID, $image_name, TRUE);
		//Get Attachment Image URL
		$post_image_src = ( ((int) $post_image) > 0 ) ? wp_get_attachment_image_src($post_image, '') : FALSE;
		if (!$post_image_src)
			$post_image = false;
		else
			$post_image_src = $post_image_src[0];
		
		//Start output
		ob_start();
?>
		<div id="<?php echo "$image_name-wrap" ?>" class="container">
	<?php
		if ($post_image) { 
	?>
			<img id="<?php echo "$image_name-frame"?>" src="<?php echo $post_image_src ?>" class="image_frame" />
			<input type="hidden" name="<?php echo "$image_name"?>" id="<?php echo "$image_name"?>" value="<?php echo $post_image ?>" />
	<?php
		}
	?>
			<div class="buttons">
				<a href="<?php echo "{$image_upload_iframe_src}&amp;TB_iframe=true" ?>" id="<?php echo "$image_name-lnk"?>" class="thickbox button" title="<?php echo $image_title ?>" onclick="return false;">Select Image</a>
				<span id="<?php echo "$image_name-options"?>" class="options <?php if (!$post_image) : ?> options-default <?php endif; ?>">
				or <a href="#" title="Remove Image" class="del-link" id="<?php echo "$image_name-option_remove"?>" onclick="postImageAction(this); return false;">Remove Image</a>
				 <span id="<?php echo "$image_name-remove_confirmation"?>" class="confirmation remove-confirmation confirmation-default">Are you sure? <a href="#" id="<?php echo "$image_name-remove"?>" class="delete" onclick="return postImageAction(this);">Remove</a> or <a href="#" id="<?php echo "$image_name-remove_cancel"?>" onclick="return postImageAction(this);">Cancel</a></span>
				</span>
			</div>
		</div>
	<?php
		//Output content
		ob_end_flush();
	}
	
	/**
	 * Executed whenever a post is saved
	 * @param int $post_id ID of post being saved
	 * @param object $post Post data
	 * @return void
	 */
	function post_save($post_id, $post) {
		$this->post_save_image($post_id, $post);
	}
	
	/**
	 * Saves image to meta data for post
	 * @param int $post_id ID of post being saved
	 * @param object $post Post data
	 * @return void
	 */
	function post_save_image($post_id, $post) {
		//Check for existence of post variable ('cnr_post_image_id')
		foreach ($this->post_images as $img) {
			$img_id = 'cnr-image-' . $img;
			if (!isset($_POST[$img_id]))
				return false;
			//Set Image ID as meta data for post
			$img_data = intval($_POST[$img_id]);
			//Remove meta data from post if no image is set
			if ($img_data <= 0) {
				delete_post_meta($post_id, $img_id);
			}
			else
				update_post_meta($post_id, $img_id, $img_data);
		}
	}
	
	/**
	 * Handles upload of Post image on post edit form
	 * @return 
	 */
	function admin_media_upload_post_image() {
		$errors = array();
		$id = 0;
		if (!isset($_POST['setimage'])) {
			wp_iframe( 'media_upload_type_form', 'post_image', $errors, $id );
		} else { /* Send image data to main post edit form and close popup */
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
			if (isset($_POST['attachments'][$attachment_id]['cnr_image_type']))
				$args .= ", '" . $_REQUEST['attachments'][$attachment_id]['cnr_image_type'] . "'";
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
	}
	
	function admin_post_sidebar() {
		add_meta_box($this->_prefix . 'section', 'Section', $this->m('admin_post_sidebar_section'), 'post', 'side', 'high');
	}
	
	/**
	 * Adds Section selection box to post sidebar
	 * @return void
	 * @param object $post Post Object
	 */
	function admin_post_sidebar_section($post) {
		wp_dropdown_pages(array('exclude_tree' => $post->ID,
								'selected' => $post->post_parent,
								'name' => 'parent_id',
								'show_option_none' => __('- No Section -'),
								'sort_column'=> 'menu_order, post_title'));
	}
	
	/**
	 * Adds additional options to filter posts
	 */
	function admin_restrict_manage_posts() {
		//Add to post edit only
		$section_param = 'cnr_section';
		if ( is_admin() && $this->util->is_file('edit.php') ) {
			$selected = ( isset($_GET[$section_param]) && is_numeric($_GET[$section_param]) ) ? $_GET[$section_param] : 0;
			//Add post statuses
			$options = array('exclude_tree'		=> $post->ID, 
							 'name'				=> $section_param,
							 'selected'			=> $selected,
							 'show_option_none'	=> __( 'View all sections' ),
							 'sort_column'		=> 'menu_order, post_title');
			wp_dropdown_pages($options);
		}
	}
	
	/**
	 * Filters posts by specified section on the Manage Posts admin page
	 * Hooks into 'request' filter
	 * @see WP::parse_request()
	 * @param array $query_vars Parsed query variables
	 * @return array Modified query variables
	 */
	function admin_manage_posts_filter_section($query_vars) {
		//Determine if request is coming from manage posts admin page
		if ( is_admin() 
			&& $this->util->is_file('edit.php')
			&& isset($_GET['cnr_section'])
			&& is_numeric($_GET['cnr_section']) 
			) {
				$query_vars['post_parent'] = intval($_GET['cnr_section']);
		}
	
		return $query_vars;
	}
	
	/**
	 * Modifies the columns that are displayed on the Post Management Admin Page
	 * @param array $columns Array of columns for displaying post data on each post's row
	 * @return array Modified columns array
	 */
	function admin_manage_posts_columns($columns) {
		$columns['section'] = __('Section');
		return $columns;
	}
	
	/**
	 * Adds section name that post belongs to in custom column on Post Management admin page
	 * @param string $column_name Name of current custom column
	 * @param int $post_id ID of current post
	 */
	function admin_manage_posts_custom_column($column_name, $post_id) {
		$section_id = $this->post_get_section();
		$section = null;
		if ($section_id > 0) 
			$section = get_post($section_id);
		if (!empty($section)) {
			echo $section->post_title;
			echo '<script type="text/javascript">postData["post_' . $post_id . '"] = {"post_parent" : ' . $section_id . '};</script>'; 
		} else
			echo 'None';
	}
	
	/**
	 * Adds field for Section selection on the Quick Edit form for posts
	 * @param string $column_name Name of custom column 
	 * @param string $type Type of current item (post, page, etc.)
	 */
	function admin_quick_edit_custom_box($column_name, $type) {
		global $post;
		if ($column_name == 'section' && $type == 'post') :
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<div class="inline-edit-group">
					<label><span class="title">Section</span></label>
					<?php
					$options = array('exclude_tree'				=> $post->ID, 
									 'name'						=> 'post_parent',
									 'show_option_none'			=> __('- No Section -'),
									 'sort_column'				=> 'menu_order, post_title');
					wp_dropdown_pages($options);
					?>
				</div>
			</div>
		</fieldset>
		<?php endif;
	}
	
	function admin_mce_before_init($initArray) {
		$initArray['content_css'] = $this->get_file_url('mce/mce_styles.css') . '?vt=' . time(); //Dev: vt param stops browser from caching css
		return $initArray;
	}
	
	function admin_mce_external_plugins($plugin_array) {
		$plugin_array['cnr_inturl'] = $this->url_base . "/mce/plugins/inturl/editor_plugin.js";
		return $plugin_array;
	}
	
	function admin_mce_buttons($buttons) {
		//Find Link button
		$pos = array_search('unlink', $buttons);
		if ($pos !== false)
			$pos += 1;
		else
			$pos = count($buttons);
		
		//Insert button into buttons array
		$start = array_slice($buttons, 0, $pos);
		$end = array_slice($buttons, $pos);
		$start[] = 'cnr_inturl';
		$buttons = array_merge($start, $end);
		return $buttons;
	}
	
	/*-** State Settings **-*/
	
	function toggle_state(&$state_var) {
		$state_var = !$state_var;
	}
	
	/*-** Content **-*/
	
	/*-** Request **-*/
	
	function request_init_start() {
		$this->state_init = true;
	}
	
	function request_init_end() {
		$this->state_init = false;
	}
	
	function request_children_start() {
		$this->state_children = true;
		$this->request_init_end();
		add_filter('found_posts', $this->m('post_children_set_found'));
	}
	
	function request_children_end() {
		$this->state_children = false;
		remove_filter('found_posts', $this->m('post_children_set_found'));
	}
	
	function post_section_highlight($output) {

		$class_current = 'current_page_item';
		$class_item_pre = 'page-item-';
		
		global $post;

		//Check if no pages are marked as 'current' yet
		//Also make sure current page is not home page (no links should be marked as current)
		if (is_singular() && $post && stripos($output, $class_current) === false) {
			//Get all parents of current post
			$parents = $this->post_get_parents($post);
			
			//Add current post to array
			$parents[] = $post;
			
			//Reverse array so we start with the current post first
			$parents = array_reverse($parents);
			
			//Iterate through current posts's parents to highlight all parents found
			foreach ($parents as $page) {
				$class_item = $class_item_pre . $page->ID;
				$class_replace = $class_item . ' ' . $class_current;
				$output = str_replace($class_item, $class_replace, $output);
			}
		}

		return $output;
	}
	
	/*-** Shortcodes **-*/
	
	/**
	 * Shortcode activation
	 * @return void
	 */
	function sc_activate() {
		add_shortcode("inturl", $this->m('sc_inturl'));
	}
	
	/**
	 * Internal URL Shortcode
	 * 
	 * Creates links to internal content based on the current permalink structure
	 * @return string Output for shortcode
	 */
	function sc_inturl($atts, $content = null) {
		$ret = '';
		$url_default = '#';
		$format = '<a href="%1$s" title="%2$s">%3$s</a>';
		$defaults = array(
						 'id'		=>	0,
						 'type'		=>	'post',
						 'title'	=>	'',
						 'anchor'	=>	''
						 );
		
		extract(shortcode_atts($defaults, $atts));
		
		$anchor = trim($anchor);
		if (!empty($anchor))
			$anchor = $url_default . $anchor;
		
		//Get URL/Permalink
		if ($type == 'post') {
			$id = (is_numeric($id)) ? (int) $id : 0;
			if ($id != 0) {
				$url = get_permalink($id);
				if (!$url)
					$url = $url_default;
			}
			else
				$url = $url_default;
			
			//Add anchor to URL
			if ($url != $url_default)
				$url .= $anchor;
			
			//Link Title
			$title = trim($title);
			if ($title == '' && $url != $url_default)
				$title = get_post_field('post_title', $id);
			$title = attribute_escape($title);
		
			if (empty($content) && $url != $url_default)
				$content = $url;
				
			$ret = sprintf($format, $url, $title, $content);
		}
		
		return $ret;
	}
	
	/*-** Child Content **-*/
	
	/**
	 * Resets object variables for child content
	 * @return void
	 */
	function post_children_init() {
		$this->post_children = null;
		$this->post_children_has = false;
		$this->post_children_current = -1;
		$this->post_children_count = 0;
		$this->post_children_total = 0;
	}
	
	/**
	 * Saves the retrieved children posts to object variables
	 * @return void
	 * @param array $children Children posts
	 */
	function post_children_save($children) {
		if ( !empty($children) ) {
			$this->post_children = $children;
			$this->post_children_has = true;
			$this->post_children_count = count($children);
		}
	}
	
	/**
	 * Sets total number of children
	 * Hooks into 'found_posts' filter
	 * @param int $children_total Total number of children
	 */
	function post_children_set_found($children_found) {
		$this->post_children_found = $children_found;
		return $children_found;
	}
	
	/**
	 * Gets children posts of specified page and stores them for later use
	 * This method hooks into 'the_posts' filter to retrieve child posts for any single page retrieved by WP
	 * @return array $posts Posts array (required by 'the_posts' filter) 
	 * @param array $posts Array of Posts (@see WP_QUERY)
	 */
	function post_children_get($posts = '') {
		//Global variables
		global $wp_query, $wpdb;
		if ( empty($posts) )
			$posts = $wp_query->posts;
		
		//Stop here if post is not a page
		if (!is_page() || $posts != $wp_query->posts) {
			return $posts;
		}
		//Reset children post variables
		$this->post_children_init();
		
		//Get children posts of page
		if ($wp_query->posts) {
			$page = $wp_query->posts[0];
			$limit = (is_feed()) ? get_option('posts_per_rss') : get_option('posts_per_page');
			$offset = (is_paged()) ? ( (get_query_var('paged') - 1) * $limit ) : 0;
			//Set arguments to retrieve children posts of current page
			$c_args = array(
							'post_parent'	=> $page->ID,
							'numberposts'	=> $limit,
							'offset'		=> $offset
							);
			//Set State
			$this->request_children_start();
			//Get children posts
			$children =& get_posts($c_args);
			//Save any children posts in new variables in global wp_query object
			$this->post_children_save($children);
			//Set State;
			$this->request_children_end();
		}
		
		//Return posts (required by filter)
		return $posts;
	}
	
	/**
	 * Checks whether post has children
	 * Children posts should have already been retrieved
	 * 
	 * If no accessible children are found, current post (section) is set as global post variable
	 * 
	 * @see 'the_posts' filter
	 * @see post_children_get()
	 * @return boolean TRUE if section contains children, FALSE otherwise
	 * Note: Will also return FALSE if section contains children, but all children have been previously accessed
	 */
	function post_children_has() {
		global $wp_query, $post;
		
		//Check if any children on current page were retrieved
		//If children are found, make sure there are more children
		 
		if ($this->post_children_count > 0 && $this->post_children_current < ($this->post_children_count - 1)) {
			return true;
		}
		
		//If no children were found (or the last child has been previously loaded),
		//load parent post back into global post variable
		if ( !empty($wp_query->post) ) {
			$post = $wp_query->post;
			setup_postdata($post);
		}
		return false;
	}
	
	/**
	 * Returns the total number of children posts in current request
	 * @return int Number of children posts
	 */
	function post_children_count() {
		return $this->post_children_count;
	}
	
	/**
	 * Returns total total number of children posts in DB
	 * @return int Number of children posts in DB
	 */
	function post_children_found() {
		return $this->post_children_found;
	}
	
	function post_children_max_num_pages() {
		return ceil( $this->post_children_found / get_option('posts_per_page') );
	}
	
	/**
	 * Loads next child post into global post variable for use in the loop
	 * @return void
	 */
	function post_children_get_next() {
		global $post;
		if ($this->post_children_has()) {
			$this->post_children_current++;
			$post = $this->post_children[$this->post_children_current];
			setup_postdata($post);
		}
	}
	
	function post_children_is_first() {
		if ($this->post_children_current == 0)
			return true;
		return false;
	}
	
	function post_children_is_last() {
		if ($this->post_children_current == ($this->post_children_count - 1))
			return true;
		return false;
	}
	
	/*-** Template **-*/
	
	/**
	 * Builds Page title for current Page/Content
	 * @return string Page title 
	 * @param array|string $args[optional] Parameters for customizing Page title
	 */
	function page_title_get($args = '') {
		$defaults = array(
							'sep'	=>	' &lsaquo; ',
							'base'	=>	get_bloginfo('title')
							);
		$args =  wp_parse_args($args, $defaults);
		$title_parts = array();
		$page_title = '';
		//Add Site Title
		$title_parts[] = $args['base'];
		
		$body_rule = '';
		$secondary = 'secondary';
		if (!is_home()) { //Evaluate all non-home page content
			if (is_page() || is_single()) { //Section page or Post
				global $post;
				if ($post->post_parent != 0 && ($parent = get_the_title($post->post_parent)) && !!$parent) {
					$title_parts[] = $parent;
				}
				$title_parts[] = get_the_title();
			} elseif (is_archive()) { //Archive Pages
				if (is_date()) {
					$format = 'F Y'; //Month Format (Default)
					$prep = 'in';
					if (is_day()) { //Day archive
						$format = 'F j, Y';
						$prep = 'on';
					} elseif (is_year()) { //Year archive
						$format = 'Y';
					}
					$title_parts[] = 'Content published ' . $prep . ' ' . get_the_time($format);
				} elseif (is_tag()) { //Tag Archive
					$title_parts[] = single_tag_title('', false);
				} elseif (is_category()) { //Category Archive
					$title_parts[] = single_cat_title('', false);
				}
			} elseif (is_search()) { //Search Results
				$title_parts[] = 'Search Results for: ' . esc_attr(get_search_query());
			} elseif (is_404()) { //404 Page
				$title_parts[] = 'Page Not Found';
			}
		} elseif (is_paged()) { //Default title for archive pages
			$title_parts[] = 'All Content';
		}
		
		//Build title based on parts
		$title_parts = array_reverse($title_parts);
		
		//Add Page Number to Title
		if (is_paged() && !empty($title_parts[0])) {
			$title_parts[0] .= ' (Page ' . get_query_var('paged') . ')';
		}
		
		for ($x = 0; $x < count($title_parts); $x++) {
			$page_title .= $title_parts[$x];
			if ($x < (count($title_parts) - 1))
				$page_title .= $args['sep'];
		}
		
		return $page_title;
	}
	
	/**
	 * Outputs formatted page title
	 * @return void
	 * @param array|string $args[optional] Parameters for customizing Page title
	 */
	function page_title($args = '') {
		echo $this->page_title_get($args);
	}
	
	/**
	 * Checks whether lightbox is currently enabled/disabled
	 * @return bool TRUE if lightbox is currently enabled, FALSE otherwise
	 */
	function lightbox_is_enabled() {
		if (get_option('cnr_lb_enabled'))
			return true;
		return false;
	}
	
	/**
	 * Sets options/settings to initialize lightbox functionality on page load
	 * @return void
	 */
	function lightbox_initialize() {
		$options = array();
		$out = array();
		$out['script_start'] = '<script type="text/javascript">Event.observe(window,"load",function(){ Lightbox.initialize(';
		$out['script_end'] = '); });</script>';
		//Get options
		$options['autoPlay'] = get_option('cnr_lb_autostart');
		$options['slideTime'] = get_option('cnr_lb_duration');
		$options['loop'] = get_option('cnr_lb_loop');
		$options['overlayOpacity'] = get_option('cnr_lb_overlay_opacity');
		$obj = '{';
		foreach ($options as $option => $val) {
			if ($val === TRUE || $val == 'on')
				$val = 'true';
			elseif ($val === FALSE || empty($val))
				$val = 'false';
			$obj .= "'{$option}': {$val},";
		}
		$obj = rtrim($obj, ',');
		$obj .= '}';
		echo $out['script_start'] . $obj . $out['script_end'];
	}
	
	/*-** Post Metadata **-*/
	
	/**
	 * Retrieves the post's section data 
	 * @return string post's section data 
	 * @param string $type[optional] Type of data to return (Default: ID)
	 * 	Possible values:
	 * 	ID		Returns the ID of the section
	 * 	name	Returns the name of the section
	 */
	function post_get_section($type = 'ID') {
		global $post;
		$retval = $post->post_parent;
		
		if ('title' == $type) {
			$retval = get_post_field('post_title', $post->post_parent);
		}
		return $retval;
	}
	
	/**
	 * Prints the post's section data
	 * @param string $type[optional] Type of data to return (Default: ID)
	 * @see cnr_get_the_section()
	 */
	function post_the_section($type = 'ID') {
		echo $this->post_get_section($type);
	}
	
		/*-** Post Attachments **-*/
	
	function is_custom_media() {
		$ret = false;
		$action = 'cnr_action';
		$upload = false;
		if (isset($_REQUEST[$action]))
			$ret = true;
		else {
			$qs = array();
			parse_str(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY), $qs);
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
		if (!isset($_REQUEST['cnr_action']))
			return false;
		echo '<input type="hidden" name="cnr_action" id="cnr_action" value="' . esc_attr($_REQUEST['cnr_action']) . '" />';
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
	
	function media_upload_url($url, $path) {
		if (strpos($path, 'media-upload.php') === 0) {
			//Get query vars
			$qs = parse_url($url, PHP_URL_QUERY);
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
			$post =& get_post($attachment);
			//Clear all form fields
			$form_fields = array();
			if ( substr($post->post_mime_type, 0, 5) == 'image' ) {
				//Add "Set as Image" button to form fields array
				$field = array(
								'input'		=> 'html',
								'html'		=> '<input type="submit" class="button" value="Set as thumbnail" name="setimage[' . $post->ID . ']" />'
								);
				$form_fields['buttons'] = $field;
				//Add image type property as hidden field (if set)
				if (isset($_REQUEST['cnr_image_type'])) {
					$field = array(
									'input'	=> 'hidden',
									'value'	=> $_REQUEST['cnr_image_type']
									);
					$form_fields['cnr_image_type'] = $field;
				}
			}
		}
		return $form_fields;
	}
	
	/**
	 * Retrieves matching attachments for post
	 * @param object|int $post Post object or Post ID (Default: current global post)
	 * @param array $args[optional] Associative array of query arguments
	 * @see get_posts() for query arguments
	 * @return array|bool Array of post attachments
	 */
	function post_get_attachments($post = null, $args = '') {
		if (!$this->util->check_post($post))
			return false;
		global $wpdb;
		
		$filter_special = true;
		
		//Default arguments
		$defaults = array(
						'post_type'			=>	'attachment',
						'post_parent'		=>	(int) $post->ID,
						'suppress_special'	=>	true
						);
		
		$args = wp_parse_args($args, $defaults);
		
		//Special items 
		if (isset($args['special_req']) && !empty($args['special_req'])) {
			$args['s'] =  "[" . $args['special_req'] . "]";
			$args['exact'] = true;
			$args['sentence'] = true;
			$filter_special = false;
		}
		
		//Get attachments
		$attachments = get_children($args);
		
		//Filter special items
		if (!empty($attachments) && $filter_special) {
			foreach($attachments as $key => $attachment) {
				if (strpos($attachment->post_title, '[') === 0 && strrpos($attachment->post_title, ']') === (strlen($attachment->post_title) - 1))
					unset($attachments[$key]);
			}
			$attachments = array_values($attachments);
		}
		
		//Return attachments
		return $attachments;
	}
	
	function post_get_attachment_path($post = null) {
		if (!$this->util->check_post($post))
			return '';
		//Get Attachment URL
		$url = wp_get_attachment_url($post->ID);
		//Replace with absolute path
		$path = str_ireplace(get_bloginfo('wpurl') . '/', ABSPATH, $url);
		return $path;
	}
	
	function post_get_attachment_filesize($post = null, $formatted = true) {
		$size = 0;
		if (!$this->util->check_post($post))
			return $size;
		//Get path to attachment
		$path = $this->post_get_attachment_path($post);
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
	
	function post_the_attachment_filesize($post = null, $formatted = true) {
		echo $this->post_get_attachment_filesize($post, $formatted);
	}
	
	/**
	 * Retrieve all images attached to post
	 * @return array Images attached to post. If there are no images attached to post, an empty array is returned
	 * @param object $post
	 * @param string $image_type [optional] Defines the type of image to retrieve.  (Default: null - All images)
	 * Image Types:
	 * 	- thumbnail:	Used as thumbnail for post
	 * 	- header:		Used as main header image for post (on post page, etc.)
	 */
	function post_get_images($post = null, $image_type = null) {
		//Determine/Normalize image type
		switch ($image_type) {
			case 'thumbnail':
			case 'header':
				$limit = 1;
				break;
			default:
				$image_type = null;
				$limit = 0;
		}
		
		//Get attachments
		$args = array('post_mime_type' => 'image/%', 'orderby' => 'menu_order', 'order' => 'ASC');
		//Set special item request
		if (!is_null($image_type))
			$args['special_req'] = $image_type;
		//Set Limit
		if (0 < $limit)
			$args['numberposts'] = (int) $limit;

		$post_images = $this->post_get_attachments($post, $args);

		if (!$post_images)
			$post_images = array();
		else
			$post_images = array_values($post_images);
		return $post_images;
	}
	
	/**
	 * Generates property name for storing specified image data in Post object 
	 * @param object $image_type [optional] Image type to generate property for
	 * @return string Property name
	 */
	function post_get_image_property($image_type = 'header') {
		return $this->prefix . '-image-' . $image_type;
	}
	
	/**
	 * Retrieves specified image attached to Post
	 * @param object $post [optional] Post Object (Default: current global post)
	 * @param string $image_type [optional] Type of image to retrieve for post (Default: header image)
	 * @return array Image metadata array (src, width, height)
	*/ 
	function post_get_image($post = null, $image_type = 'header') {
		
		//Default return value: Empty Array
		$ret = array();
		if (!$this->util->check_post($post))
			return $ret;
			
		//Get image name to retrieve
		$prop = $this->post_get_image_property($image_type);
		//Check for post meta info
		$val = get_post_meta($post->ID, $prop, true);
		//Get attachment with matching ID
		$img = wp_get_attachment_image_src($val, '');
		//Add properties to image data array
		$attr = array('src', 'width', 'height');
		$limit = count($attr);
		if (!empty($img)) {
			for ($x = 0; $x < $limit; $x++) {
				if (isset($img[$x]))
					$img[$attr[$x]] = $img[$x];
			}
			$ret = $img;
		}
		return $ret;
	}
	
	/**
	 * Determines whether or not specified image is attached to post
	 * @param object $post [optional] Post object (Default: current global post)
	 * @param string $image_type [optional] Image type to check for (Default: header image)
	 * @see post_get_images() for list of image types
	 * @param bool $object_only [optional] Check only post object for property existence/data if TRUE
	 * @return bool TRUE if post has specified image, FALSE otherwise
	 */
	function post_has_image($post = null, $image_type = 'header', $object_only = false) {
		if (!$this->util->check_post($post))
			return false;
			
		//Get image name to retrieve
		$prop = $this->post_get_image_property($image_type);
		//Check for post meta info
		$val = get_post_meta($post->ID, $prop, true);
		//Make sure attachment still exists
		$val = (!empty($val) && is_numeric($val)) ? get_post($val) : null;
		return (empty($val)) ? false : true;
	}
	
	/**
	 * Outputs img element of image retrieved for post
	 * @param object $post [optional]
	 * @return 
	 */
	function post_the_image($post = null, $image_type = 'header') {
		$img = $this->post_get_image($post, $image_type);
		$ret = '';
		if (!empty($img) && $this->util->check_post($post)) {
			$attributes = array(
								'alt'	=> $post->post_title,
								'class' => 'image_' . $image_type
								);
			$ret = $this->post_get_image_element($img, $attributes);
		}
		echo $ret;
	}
	
	/**
	 * Build HTML IMG element of an Image
	 * @param array $image Array of image properties
	 * 	0:	Source URI
	 * 	1:	Width
	 * 	2:	Height
	 * @return string HTML IMG element of specified image
	 */
	function post_get_image_element($image, $attributes = '') {
		$ret = '';
		if (is_array($image) && count($image) >= 3) {
			//Build attribute string
			if (is_array($attributes)) {
				$attribs = '';
				$attr_format = '%s = "%s" ';
				foreach ($attributes as $attr => $val) {
					$attribs .= sprintf($attr_format, $attr, attribute_escape($val));
				}
				$attributes = $attribs;
			}
			$format = '<img src="%1$s" width="%2$d" height="%3$d" ' . $attributes . ' />';
			$ret = sprintf($format, $image[0], $image[1], $image[2]);
		}
		return $ret;
	}
	
	/**
	 * Outputs HTML IMG element to browser for specified image
	 * @param array $image Array of image properties
	 * 	0:	Source URI
	 * 	1:	Width
	 * 	2:	Height
	 * @param string $alt [optional] Alt text for image
	 * @return void
	 */
	function post_the_image_element($image, $alt = '') {
		$ret = $this->post_get_image_element($image, $alt);
		echo $ret;
	}
	
	function post_the_image_data($data = 'src', $image_type = 'header') {
		$ret = '';
		//Get specified post image (array)
		$img_data = $this->post_get_image(null, $image_type);
		
		if (isset($img_data[$data]))
			$ret = $img_data[$data];
		echo $ret;
	}
	
	/**
	 * Gets image associated with post
	 * 
	 * @return array|bool Source image data (url, width, height), or false if no image is available
	 * @param int $post_id[optional] Post ID. Defaults to current post
	 */
	function post_get_image_src($post_id = 0) {
		$_post = null;
		if ($post_id == 0) {
			$_post = & $GLOBALS['post'];
			if ($_post)
				$post_id = $_post->ID;
		}
		if ($post_id == 0)
			return false;

		//Get attachments for post
		$attachments = &get_children(array( 'post_parent' => $post_id, 'post_type' => 'attachment'));
		$src = false;

		//Find image attachment
		if ($attachments) {
			//Stop at first image found
			foreach ($attachments as $attachment) {
				if (wp_attachment_is_image($attachment->ID)) {
					$src = wp_get_attachment_image_src($attachment->ID, '');
					break;
				}
			}
		}
		return $src;
	}
	
		/* Post Parts */
	
	/**
	 * Gets array of parts in post
	 * Parts are sorted by order in post
	 * @return array Parts in post
	 */
	function post_get_parts() {
		global $post;
		$parts = array();
		$matches = array();
		if ($post) {
			//Check if parts have already been fetched
			if ($this->post_check_parts()) {
				$parts = $post->{$this->_post_parts_var};
			}
			else {
				//Fetch the post's content
				$content = get_the_content();
				
				$part_code = 'part';
				$part_start = '[' . $part_code;
				$re_part = '/\[' . $part_code . '.*?\]/i';
				
				//Check if any parts exist in content
				$pos = stripos($content, $part_start);
				if ($pos !== false) {
					//Get all matches
					preg_match_all($re_part, $content, $matches);
					//Add part to array	
					if (is_array($matches))
						$parts = $matches;
				}
				//Add parts array to post variable for future reference
				$post->{$this->_post_parts_var} = $parts;
			}
		}
		
		//Return array of post parts
		return $parts;
	}
	
	function post_check_parts($post = null) {
		$ret = false;
		if (is_null($post) && isset($GLOBALS['post']))
			$post =& $GLOBALS['post'];
		else
			return false;
		
		//Check if post parts have already been fetched and added to post object
		$ret = ($this->util->property_exists($post, $this->_post_parts_var) && !is_array($post->{$this->_post_parts_var})) ? false : true;
		
		return $ret;
	}
	
	function post_the_parts() {
		//Get Post parts
		$parts = $this->post_get_parts();
		
		$output = '';
		
		//Build parts output
		foreach ($parts as $part) {
			
		}
		
		//Echo output
		echo $output;
	}
	
	function post_have_parts() {
		if ($this->post_get_parts())
			return true;
		return false;
	}
	
	/*-** Featured Content **-*/
	
	/**
	 * Gets featured posts matching parameters and stores them in global $wp_query variable 
	 * 
	 * @todo Fetch additional posts if total number of requested features are not available
	 * 	Example: 10 features requested, but only 6 available
	 * 		- Should perform additional query to get 4 more normal posts in order to return 10 posts total
	 * 		- Use parameter to enable/disable this functional
	 * 			- Ex: $fill_limit (bool) If TRUE, get additional posts to fill post limit
	 * 
	 * @return void
	 * @param int $limit[optional] Maximum number of featured posts to retrieve (Default: -1 = All Featured Posts)
	 * @param int|bool $parent[optional] Section to get featured posts of (Defaults to current section).
	 * 	FALSE if latest featured posts should be retrieved regardless of section
	 * @todo Integrate into CNR_Posts
	 */
	function posts_featured_get($limit = -1, $parent = null) {
		//Global variables
		global $wp_query, $wpdb;
		
		//Determine section
		if ($parent == null) {
			if (count($wp_query->posts) == 1) {
				$parent = $wp_query->posts[0]->ID;
			}
			elseif ($wp_query->current_post != -1 && isset($GLOBALS['post']) && is_object($GLOBALS['post']) && $this->util->property_exists($GLOBALS['post'], 'ID')) {
				$parent = $GLOBALS['post']->ID;
			}
		}
		//Check if parent is valid post ID
		if ((int)$parent < 1) {
			//Get featured posts from all sections if no valid parent is set
			$parent = null;
		}
		
		//Set post limit
		$limit = (int)$limit;
		
		//Build query for featured posts
		$args = array(
					'post_parent'	=>	$parent, //Section to get posts for
					'category'		=>	$this->posts_featured_get_cat_id(), //Restrict to posts matching "featured" category
					'numberposts'	=>	$limit //Limit the number of posts retrieved
					);
					
		//Retrieve featured posts
		$featured = get_posts($args);
		
		//Check to make sure the correct number of posts are returned
		if ($limit && (count($featured) < $limit)) {
			//Set arguments to fetch additional (non-feature) posts to meet limit
			
			//Remove category argument
			unset($args['category']);
			
			//Adjust Limit
			$args['numberposts'] = $limit - count($featured);
			
			//Exclude posts already fetched
			$args['post__not_in'] = $this->posts_get_ids($featured);
			
			//Get more posts
			$additional = get_posts($args);
			$featured = array_merge($featured, $additional);
		}

		//Load retrieved posts into wp_query variable
		//$this->posts_featured_load($featured);
		$this->posts_featured->load($featured);
		//Return retrieved posts so that array may be manipulated further if desired
		return $this->posts_featured->posts;
	}
	
	/**
	 * Retrieves featured post category object
	 * @return object Featured post category object
	 * @todo integrate into CNR_Posts
	 */
	function posts_featured_get_cat() {
		static $cat = null;
		
		//Only fetch category object if it hasn't already been retrieved
		if (is_null($cat) || !is_object($cat)) {
			//Retrieve category object
			if (is_int($this->posts_featured_cat)) {
				$cat = get_category((int)$this->posts_featured_cat);
			}
			elseif (is_string($this->posts_featured_cat) && strlen($this->posts_featured_cat) > 0) {
				$cat = get_category_by_slug($this->posts_featured_cat);
			}
		}
		
		return $cat;
	}
	
	/**
	 * @todo integrate into CNR_Posts
	 */
	function posts_featured_get_cat_id() {
		static $id = '';
		if ($id == '') {
			$cat = $this->posts_featured_get_cat();
			if (!is_null($cat) && is_object($cat) && $this->util->property_exists($cat, 'cat_ID'))
				$id = $cat->cat_ID;
		}
		return $id;
	}
	
	/**
	 * Populates global wp_query variable with additional properties for managing featured posts
	 * 
	 * Featured post data is stored in the following $wp_query variables
	 * --
	 * featured_posts	array	Stores featured posts [Default: empty array]
	 * has_featured		bool	Whether or not any featured posts were retrieved [Default: false]
	 * current_featured	int		Featured post currently loaded in global $post variable [Default: -1]
	 * featured_count	int		Number of featured posts [Default: 0]
	 * 
	 * @return void 
	 * @param array $posts Featured posts
	 * @deprecated
	 */
	function posts_featured_load($posts) {
		
		//Reset featured posts variables to default values
		$this->posts_featured_init();
		
		if (!empty($posts)) {
			//Save retrieved featured posts in newly created variables in global wp_query object
			$this->posts_featured = $posts;
			$this->posts_featured_has = true;
			$this->posts_featured_count = count($this->posts_featured);
		}
	}
	
	/**
	 * Resets featured post variables to default values
	 * @return void
	 * @deprecated
	 */
	function posts_featured_init() {
		$this->posts_featured = array();
		$this->posts_featured_has = false;
		$this->posts_featured_current = -1;
		$this->posts_featured_count = 0;
	}
	
	/**
	 * Checks whether featured posts are available in the current context
	 * Note: featured posts should have already been retrieved (see @CNR::posts_featured_get())
	 * 
	 * If no accessible featured posts are found, current post (section) is set as global post variable
	 * 
	 * @see 'the_posts' filter
	 * @see get_children()
	 * @return boolean TRUE if section contains children, FALSE otherwise
	 * Note: Will also return FALSE if section contains children, but all children have been previously accessed
	 * @deprecated
	 */
	function posts_featured_has() {
		global $wp_query, $post;
		
		//Check if any featured posts on current page were retrieved
		//If featured posts are found, make sure there are more featured posts 
		if ($this->posts_featured_count > 0 && $this->posts_featured_current < $this->posts_featured_count - 1) {
			return true;
		}
		
		//Reset current featured post position if all featured posts have been processed
		$this->posts_featured_rewind(); 
		
		//If no featured posts were found (or the last featured post has been previously loaded),
		//load previous post back into global post variable
		if ($wp_query->current_post >= 0) {
			$post = $wp_query->posts[$wp_query->current_post];
			setup_postdata($post);
		}
		return false;
	}
	
	/**
	 * Loads next featured post into global post variable for use in the loop
	 * @return void
	 * @deprecated
	 */
	function posts_featured_next() {
		global $post;
		
		if ($this->posts_featured_has()) {
			//Increment featured post position
			$this->posts_featured_current++;
			//Load featured post into global post variable
			$post = $this->posts_featured[$this->posts_featured_current];
			
			setup_postdata($post);
		}
	}
	
	/**
	 * Resets position of current featured post
	 * Allows for multiple loops over featured posts
	 * @return void
	 * @deprecated
	 */
	function posts_featured_rewind() {
		$this->posts_featured_current = -1;
	}
	
	/**
	 * Gets index of current featured post
	 * @return int Index position of current featured post
	 * @deprecated
	 */
	function posts_featured_current() {
		return $this->posts_featured_current;
	}
	
	/**
	 * Checks if current featured post is the first featured post
	 * @return bool TRUE if current post is the first featured post, FALSE otherwise
	 * @deprecated
	 */
	function posts_featured_is_first() {
		return ($this->posts_featured_current() == 0) ? true : false;
	}
	
	/**
	 * Determines whether a post is classified as a "feature" or not
	 * 
	 * @return bool TRUE if post is classified as a "feature", FALSE otherwise 
	 * @param int $post_id[optional] ID of the post.  Defaults to current post
	 * @todo Fix this
	 */
	function post_is_featured($_post = null) {
		$ret = false;
		
		//Set post ID
		if ($_post) {
			$_post = get_post($_post);
		}
		else {
			$_post = & $GLOBALS['post'];
		}
		
		if (!$_post)
			return false;
		
		//Check if post is in the featured category
		if (in_category($this->post_featured, $_post)) {
			$ret = true;
		}
		return $ret;
	}
	
	function post_has_content($post = null) {
		if (!$this->util->check_post($post))
			return false;
		if (isset($post->post_content) && trim($post->post_content) != '')
			return true;
		return false;
	}
	
	function post_get_subtitle($post = null) {
		$p_id = null;
		if (is_int($post))
			$p_id = $post;
		else {
			if (!is_object($post) || !$this->util->property_exists($post, 'ID'))
				$post =& $GLOBALS['post'];
			$p_id = $post->ID;
		}
		$subtitle = '';
		if (!((int)$p_id))
			return $subtitle;
		
		//Get post subtitle data
		$subtitle = get_post_meta($post->ID, $this->post_subtitle_field, true);
		return $subtitle;
	}
	
	function post_has_subtitle($post = null) {
		if (trim($this->post_get_subtitle($post)) != '')
			return true;
		return false;
	}
	
	function post_the_subtitle($post = null) {
		echo $this->post_get_subtitle($post);
	}
	
	/**
	 * Modifies post permalink to reflect position of post in site structure
	 * Example: baseurl/section-name/post-name/
	 * 
	 * @return string
	 * @param string $permalink Current permalink url for post
	 * @param object $post Post object
	 */
	function post_link($permalink, $post = '') {
		global $wp_rewrite, $wp_query;

		if ($wp_rewrite->using_permalinks()) {
            
			//Get base URL
			$base = get_bloginfo('url');
			
			//Canonical redirection usage
			if (is_string($post)) {
				//Only process single posts
				if (is_single()) {
					$post = $wp_query->get_queried_object();
				} else {
					//Stop processing for all other content (return control to redirect_canonical
					return false;
				}
			}
			
			//Get post path
			$path = $this->post_get_path($post);
			
			//Set permalink (Add trailing slash)
			$permalink = $base . $path . $post->post_name . '/';
		}
		return $permalink;
	}
	
	/**
	 * Gets entire parent tree of post as an array
	 * 
	 * Array order is from top level to immediate post parent
	 * @return array of Post Objects
	 * @param object $post Post to get path for
	 */
	function post_get_parents($post, $depth = '') {
		$parents = array();
		if ( is_object($post) ) {
			$post_parent = $post;
			//Iterate through post parents until top level parent is reached
			while ($post_parent->post_parent > 0)
			{
				$post_parent = get_post($post_parent->post_parent);
				$parents[] = $post_parent;
			}
			//Reverse Array (to put top level parent at beginning of array)
			$parents = array_reverse($parents);
		}
		return $parents;
	}
	
	/**
	 * Returns path to post based on site structure
	 * @return string Path to post enclosed in '/' (forward slashes)
	 * Example: /path/to/post/
	 * @param object $post Post object
	 */
	function post_get_path($post) {
		//Get post parents
		$parents = $this->post_get_parents($post);
		$sep = '/';
		$path = $sep;
		foreach ($parents as $post_parent) {
			$path .= $post_parent->post_name . $sep;
		}
		return $path;
	}
	
	/**
	 * Generates feed links based on current page
	 * @return string Feed links
	 */
	function feed_get_links() {
		$text = array();
		$links = array();
		$link_template = '<a class="link_feed" rel="alternate" href="%1$s" title="%3$s">%2$s</a>';
		//Page specific feeds
		if ( is_page() || is_single() ) {
			global $post;
			$href = get_post_comments_feed_link($post->ID);
			if ( is_page() )
				$title = 'Subscribe to this Section';
			else
				$title = 'Subscribe to Comments';
			$links[$href] = $title;
		} elseif ( is_search() ) {
			$links[get_search_feed_link()] = 'Subscribe to this Search';
		} elseif ( is_tag() ) {
			$links[get_tag_feed_link( get_query_var('tag_id') )] = 'Subscribe to this Tag';	
		} elseif ( is_category() ) {
			$links[get_category_feed_link( get_query_var('cat') )] = 'Subscribe to this Topic';
		}
		
		//Sitewide feed
		$title = ( !empty($links) ) ? 'Subscribe to All updates' : 'Subscribe to Updates';
		$links[get_feed_link()] = $title;
		foreach ($links as $href => $title) {
			$text[] = sprintf($link_template, $href, $title, esc_attr($title));
		}
		$text = implode(' or ', $text);
		return $text; //'<a class="link_feed" rel="alternate" href="' . get_feed_link() . '">Subscribe to All Updates</a>';
	}
	
	/**
	 * Outputs feed links based on current page
	 * @see feed_get_links()
	 * @return void
	 */
	function feed_the_links() {
		echo $this->feed_get_links();
	}
	
	/*-** Query **-*/
	
	/**
	 * Adds custom query variables
	 * @return array Array of query variables
	 * @param array $query_vars WP-built list of query variables
	 */
	function query_vars($query_vars) {
		$query_vars[] = $this->_qry_var;
		return $query_vars;
	}
	
	/**
	 * Resets certain query properties before post retrieval
	 * @return void
	 * @param object $query_obj WP_Query object reference to <tt>$wp_query</tt> variable
	 */
	function pre_get_posts($query_obj) {
		if (!isset($query_obj->query_vars[$this->_qry_var])) {
			return;
		}
		
		//$query_obj = new WP_Query;
		if (!isset($query_obj->queried_object_id) || !$query_obj->queried_object_id) {
			$query_obj->query_vars['name'] = sanitize_title(basename($query_obj->query_vars['pagename']));
			//Remove pagename variable from query
			unset($query_obj->query_vars['pagename']);
			//Reparse query variables
			$query_obj->parse_query($query_obj->query_vars);
		}
	}
	
	/**
	 * Fetches posts before actual post query
	 * Excludes retrieved posts from actual post query
	 * @return void
	 * @param object $query_obj WP_Query object reference to <tt>$wp_query</tt> variable
	 * @deprecated
	 */
	function pre_get_posts_excluded(&$query_obj) {
		//Featured posts
		//Only get featured posts during initial (on home page) or child requests
		if (!is_feed() && !is_paged() 
			&& (((is_home()) && $this->state_init)		/* Initial Query on Home Page */
				|| (is_page() && $this->state_children)				/* Children Query on Section Page */
			)
		) {
			//Toggle states (to avoid issues with queries)
			$this->toggle_state($this->state_init);
			$this->toggle_state($this->state_children);
			$this->posts_featured_get(4);
			
			//Toggle states back to original value (so that state can be used by other code)
			$this->toggle_state($this->state_init);
			$this->toggle_state($this->state_children);
			
			//Add retrieved posts to excluded variables
			$this->posts_add_excluded($this->posts_featured, $query_obj);
		}
	}
	
	/**
	 * Adds IDs of posts to excluded posts array for current query
	 * Posts will not be retrieved in current array as a result
	 * @return void
	 * @param array $posts Array of Post objects
	 * @param object $query_obj WP_Query object
	 */
	function posts_add_excluded($posts, &$query_obj) {
		//Validate posts array
		if (!is_array($posts) || count($posts) < 1)
			return false;
			
		//Validate query object
		if (!isset($query_obj)) {
			if (!isset($GLOBALS['wp_query']))
				return false;
			$query_obj =& $GLOBALS['wp_query'];
		}

		if (!$this->util->property_exists($query_obj, 'query_vars'))
			return false;
		
		//Query vars shorthand
		$q =& $query_obj->query_vars;
		
		//Get array of Post IDs
		$excluded = $this->posts_get_ids($posts);
		
		//Add to query variable in query object
		if (!isset($q['post__not_in']) || !is_array($q['post__not_in'])) {
			$q['post__not_in'] = array();
		}
		
		$q['post__not_in'] = array_merge($q['post__not_in'], $excluded);
	}
	
	/**
	 * Filter posts request prior to querying DB for posts
	 * 
	 * Operations:
	 * If query is for the home page
	 * - Clear request so no posts are retrieved from DB
	 *  
	 * @return string Updated posts request
	 * @param string $request Posts request
	 */
	function posts_request($request) {
		if (is_home())
			$request = '';
		
		return $request;
	}
	
	/**
	 * Adds custom URL rewrite rules
	 * @return array Rewrite Rules
	 * @param array $rewrite_rules_array Original rewrite rules array generated by WP
	 */
	function rewrite_rules_array($rewrite_rules_array) {
		global $wp_rewrite;
		//$this->debug->print_message('Rewrite Rules', $rewrite_rules_array);
		//Posts/Pages
		//$rules_extra['([\/\w-]+)/([A-Za-z0-9-]+)$'] = $wp_rewrite->index . "?name=\$matches[2]";
		//$rewrite_rules_array['(.+?)(/[0-9]+)?/?$'] = $wp_rewrite->index . "?pagename=\$matches[1]&" . $this->_qry_var . "=\$matches[1]";
		
		 //RSS FEEDS FOR TAGS IN SECTIONS
		//$cms_rules[$section_prefix.'([\w-]+)/'.$utw_prefix.'/([\w-\/\s]+)/feed/?$'] = "$wp_rewrite->index?pagename=\$matches[1]&feed=feed&tags=\$matches[2]";
		 //TAGS IN SECTIONS
		//$cms_rules['([\w-]+)/'.$utw_prefix.'/([\w-\/\s]+)/?$'] = $wp_rewrite->index."?pagename=\$matches[1]&tags=\$matches[2]";
		 //RSS FEEDS FOR CATEGORIES IN SECTIONS
		//$cms_rules['([\w-]+)'.$category_prefix.'/([\w-\/\s]+)/feed/?$'] = "$wp_rewrite->index?pagename=\$matches[1]&feed=feed&cat=\$matches[2]";
		//REWRITE RULES FOR CATEGORIES (IN SECTIONS)
		//$cms_rules['([\w-]+)'.$category_prefix.'/([\w-\/\s]+)/?$']= $wp_rewrite->index."?pagename=\$matches[1]&cat=\$matches[2]";
		//REWRITE RULES FOR MULTIPAGE POSTS
		//$cms_rules_2['([\w-]+/?)([\w-]+)'.$post_extension.'/([0-9]+)/?$'] = $wp_rewrite->index."?name=\$matches[2]&page=\$matches[3]";

		//Return rules with new rules added
		return $rewrite_rules_array;
	}
	
	/*-** Feeds **-*/
	
	/**
	 * Customizes feed to use for certain requests
	 * Example: Pages (Sections) should load the normal feed template (instead of the comments feed template)
	 */
	function feed_redirect() {
		if (is_page() && is_feed()) {
			//Turn off comments feed for sections
			global $wp_query;
			$wp_query->is_comment_feed = false;
			//Enable child content retrieval for section feeds
			$m = $this->m('feed_section');
			$feed = get_query_var('feed');
			if ('feed' == $feed)
				$feed = get_default_feed();
			$hook = ('rdf' == $feed) ? 'rdf_header' : $feed . "_head"; 
			add_action($hook, $m);
		}
	}
	
	/**
	 * Retrieves a section's child content for output in a feed 
	 */
	function feed_section() {
		if (is_page() && is_feed()) {
			global $wp_query;
			$this->post_children_get();
			
			//Set retrieved posts to global $wp_query object
			$wp_query->posts = $this->post_children;
			$wp_query->current_post = $this->post_children_current;
			$wp_query->post_count = $this->post_children_count;
		}
	}
	
	function feed_title($title) {
		$sep = '&#8250;';
		remove_filter('get_wp_title_rss', $this->m('feed_title'));
		$title = get_wp_title_rss($sep);
		add_filter('get_wp_title_rss', $this->m('feed_title'));
		return $title;
	}
	
	function feed_description($description, $show) {
		global $post;
		if (is_page() && 'description' == $show && '' != $post->post_content) {
			//Get section's own description (if exists)
			$description = convert_chars($post->post_content);
		}
		return $description;
	}
}

class CNR_Page_Groups {
	
	/**
	 * @var array Stores page groups
	 */
	var $_groups = array();
	
	var $_fetched = false;
	
	/**
	 * @var string Page Group DB table base name
	 * @static
	 */
	var $_db_table = 'page_groups';
	
	/*-** Constructor **-*/
	
	function CNR_Page_Groups() {
		
	}
	
	/**
	 * Populates $groups variable with all Page groups in DB
	 * @return mixed If a single group is specified, return the object, otherwise, return nothing
	 * @param object $group_id[optional] Retrieve a specific group by ID
	 */
	function get($group_id = 0) {
		global $wpdb;
		
		if (0 == $group_id || !is_int($group_id)) {
			//Get all Groups
			$qry = 'SELECT * FROM %s ORDER BY group_title';
		} elseif (is_int($group_id)) {
			//Get specific Group
			$qry = 'SELECT * FROM %s WHERE ID = %d LIMIT 0, 1';
		}
		//Get
		$db_table = call_user_func(array(__CLASS__, 'get_db_table'));
		$qry = sprintf($qry, $db_table, $group_id);
		$res_groups = $wpdb->get_results($qry);
		
		//Build Page_Group objects for each result
		$groups = array();
		foreach ($res_groups as $group) {
				$groups[] = new CNR_Page_Group($group);
		}
		return $groups;
	}
	
	/**
	 * @return string Page groups as Table rows 
	 * @static
	 */
	function get_rows() {
		//Get Groups
		$groups = call_user_func(array(__CLASS__, 'get'));
		$rows = '';
		
		//Iterate through groups and build table rows
		if (is_array($groups)) {
			$row;
			//All pages variable
			$pages = get_pages();
			//HTML element templates
			$ul_temp = '<ul>%s</ul>';
			$li_temp = '<li class="item-page pid_%s">%s</li>';
			//Group list
			$list = '';
			foreach ($pages as $page) {
				$list .= sprintf($li_temp, $page->ID, $page->post_title);
			}
			$list = sprintf($ul_temp, $list);
			foreach ($groups as $group) {
				//Title
				$row = sprintf($group->get_template('col_title'), $group->name, $group->build_pages_list(true), '');
				//Code
				$row .= sprintf($group->get_template('col_default'), 'group-code', $group->code);
				//Page Count
				$row .= sprintf($group->get_template('col_default'), 'group-count', $group->count_pages());
				$rows .= sprintf($group->get_template('row'), $group->get_node_id(), $row);
			}
			$rows .= call_user_func(array(__CLASS__, 'get_groups_js'), $groups);
		}
		
		return $rows;
	}
	
	/**
	 * Prints Page Groups as Table Rows
	 * @return void
	 */
	function rows() {
		echo call_user_func(array(__CLASS__, 'get_rows'));
	}
	
	/**
	 * Generates JS code to build page groups on client-side
	 * @param array $groups Page Groups array
	 * @return string JS code for building groups
	 * @static
	 */
	function get_groups_js($groups) {
		//var pageGroups = [new PageGroup({'id': 1, 'pages': [7,5,4], 'node': 'pg_1'})];
		$pg_var = '<script type="text/javascript">PageGroup.setTemplate(\'%s\'); var pageGroups = [%s]</script>';
		$pg_items = array();
		$count = 0;
		$li_temp = '';
		foreach ($groups as $group) {
			$pg_items[] = $group->build_js();
			if ($count == 0) {
				$li_temp = $group->get_template('page_item');
				$count++;
			}
		}
		return $pg_var = sprintf($pg_var, $li_temp, implode(', ', $pg_items));
	}
	
	/**
	 * Returns DB table that stores page groups
	 * @return string DB Table name
	 * @static
	 */
	function get_db_table() {
		$c_vars = get_class_vars(__CLASS__);
		return Cornerstone::get_db_prefix() . $c_vars['_db_table'];
	}
}

/**
 * Class for managing a Page Group
 * @package Cornerstone
 */
class CNR_Page_Group {
	
	/*-** Properties **-*/
	
	/**
	 * @var int Group ID
	 */
	var $id = 0;
	
	/**
	 * @var string Group name
	 */
	var $name = '';
	
	/**
	 * @var string Unique code for group
	 */
	var $code = '';
	
	/**
	 * @var string Page separator
	 */
	var $sep = '|';
	
	/**
	 * @var array Holds pages in group
	 */
	var $pages = array();
	
	/**
	 * @var object Cached group object from DB
	 */
	var $group_cache;
	
	var $db_table_groups = 'page_groups';
	
	/**
	 * @var string Prefix to use for Node ID
	 */
	var $node_prefix = "pg_";
	
	var $actions = array(
						'save' => 'save',
						'remove' => 'remove'
						);
	
	/**
	 * @var array Associative array of output templates
	 */
	var $_templates = array(
							'row'			=> '<tr id="%s" class="page-group">%s</tr>',
							'col_title' 	=> '<td class="post-title page-title column-title">
													<strong><a class="row-title group-title" href="#">%s</a></strong>
													<div class="row-actions actions">
														<span class="inline"><a href="#" class="action edit">Edit</a></span> | 
														<span class="delete"><a href="#" class="action remove">Delete</a></span>
													</div>
													<div class="group-pages_wrap">%s</div>
													<div class="site-pages list-pages">%s</div>
													<div class="actions actions-commit">
														<input type="button" value="Save" class="action save button-primary" />  <input type="button" value="Cancel" class="action reset button-secondary" />
													</div>
												</td>',
							'col_default'	=> '<td class="%s">%s</td>',
							'pages_wrap'	=> '<ul class="%s">%s</ul>',
							'page_item'		=> '<li class="item-page pid_%s">%s</li>',
							);
	
	/*-** Constructor **-*/
	
	/**
	 * Class Constructor
	 * @return void
	 * @param mixed $id[optional] May be ID of existing Group OR name of new group OR a Page Group row returned from DB
	 * @param string $name[optional] Name of Group
	 * @param string $pages[optional] Pages in Group (CSV)
	 */
	function CNR_Page_Group($id = 0, $name = '', $pages = '') {
		//Get DB Values
		$this->db_table_groups = CNR_Page_Groups::get_db_table();
		//Check if ID of existing group is being set
		if (is_numeric($id))
			$id = (int)$id;
		$this->load($id);
		/*
		if (is_int($id) || is_object($id)) {
			$this->load($id);
		}
		
		//Check if name of a new group is being set
		elseif (is_string($id)) {
			$this->set_name($id);
		}
		*/
	}
	
	function register_hooks() {
		$cls = __CLASS__;
		add_action('wp_ajax_pg_save', array($cls, 'save_ajax'));
		add_action('wp_ajax_pg_check_code', array($cls, 'check_code_ajax'));
		add_action('wp_ajax_pg_remove', array($cls, 'remove_ajax'));
		add_action('wp_ajax_pg_get_template', array($cls, 'build_template_ajax'));
	}
	
	/*-** Operations **-*/
	
	/**
	 * Loads Group from DB into object
	 * @return void
	 * @param mixed $group_id ID of group to retrieve from DB OR group object from DB
	 */
	function load($group_id) {
		global $wpdb;
		$group = null;
		$col = 'group_name';
		$col_format = '%s';
		if (is_int($group_id) && $group_id > 0) {
			$col = 'group_id';
			$col_format = '%d';
		}
		if (!is_object($group_id)) {
		//Get group data from DB
		$qry = $wpdb->prepare("SELECT * FROM $this->db_table_groups WHERE $col = $col_format", $group_id);
		$group = $wpdb->get_row($qry);
		} else {
			$group = $group_id;
		}
		if (!$group)
			return;
		if (is_object($group)) {
			//Evaluate group data and set object properties
			$this->group_cache = $group;
			$this->id = $group->group_id;
			$this->name = trim($group->group_title);
			$this->code = trim($group->group_name);
			$this->load_pages($group->group_pages);
		}
	}
	
	/**
	 * Parses pages from DB data
	 * @return void
	 * @param mixed $pages list of pages, may also be DB object containing group pages property
	 */
	function load_pages($pages) {
		global $wpdb;
		$this->pages = null;
		//Check if $pages is DB resultset
		if (is_object($pages) && $this->util->property_exists($pages, 'group_pages'))
			$pages = $pages->group_pages;
			
		//If $pages is an array, set it to $arr_pages
		if (is_array($pages))
			$arr_pages = $pages;
			
		//convert $pages string to array of pages
		elseif (is_string($pages) && '' != $pages)
			$arr_pages = explode($this->sep.$this->sep, trim($pages, $this->sep));
		
		//If $arr_pages in not a valid array, exit function
		if (!isset($arr_pages) || !is_array($arr_pages) || count($arr_pages) < 1) {
			return;
		}
		
		$qry = sprintf('SELECT * FROM %s WHERE ID in (%s)', $wpdb->posts, implode(',', array_unique($arr_pages)));
		$obj_pages = $wpdb->get_results($qry);
		
		//Build associative array of pages based on page ID
		$arr_db_pages = array();
		foreach ($obj_pages as $page) {
			$arr_db_pages[$page->ID] = $page;
		}
		
		//Add pages to object in set order
		$c_id = '';
		for ($x = 0; $x < count($arr_pages); $x++) {
			$c_id = $arr_pages[$x];
			if (isset($arr_db_pages[$c_id]))
				$this->pages[$c_id] = $arr_db_pages[$c_id];
		}
	}
	
	/**
	 * Add page to Group
	 * @return void
	 * @param mixed $page Page object or int ID of page to add to group
	 * @param int $position[optional] Position in group to add the page (Default = -1 (End of list))
	 */
	function add_page($page, $position = -1) {
		global $wpdb;

		//Get page if only ID is supplied
		if (is_int($page))
			$page = get_post($page);
		if (!is_object($page) || null == $page)
			return false;
		
		//Add page to list
		$this->pages[$page->ID] = $page;
	}
	
	/**
	 * Deletes page from group
	 * @return void
	 * @param mixed $page Page object or ID of page to remove from group
	 */
	function delete_page($page) {
		if (is_object($page) && isset($page->ID))
			$page = $page->ID;
		if (array_key_exists($page, $this->pages))
			unset($this->pages[$page]);
	}
	
	/**
	 * Saves group data to DB
	 * @return void
	 */
	function save() {
		global $wpdb;
		
		//Build Pages List
		$do_save = (0 == $this->id) ? true : false; //Will be cleared later if no changes to group have been made
		$pages_list = $this->serialize_pages();
		//Compare with cached group data (if available)
		if (isset($this->group_cache)) {
			if (0 == $this->id
				|| $this->group_cache->group_pages != $pages_list
				|| $this->name != $this->group_cache->group_title
				|| $this->code != $this->group_cache->group_name)
				$do_save = true;
		}

		if ($do_save) {
			//Determine whether group needs to be added to or updated in DB
			if (0 == $this->id) {
				$qry = $wpdb->prepare('INSERT INTO ' . $this->db_table_groups . ' (group_title, group_name, group_pages) VALUES (%s, %s, %s)', $this->name, $this->code, $pages_list);
			}
			else
				$qry = $wpdb->prepare('UPDATE ' . $this->db_table_groups . ' SET group_title = %s, group_name = %s, group_pages = %s WHERE group_id = %d', $this->name, $this->code, $pages_list, $this->id);
			
			//Execute query
			$res = $wpdb->query($qry);
			if ($res) {
				if (0 == $this->id)
					$this->id = $wpdb->insert_id;
			}
			$this->cache();
		}
	}
	
	function save_ajax() {
		//Create new page group using AJAX data
		$ret = '';
		if (isset($_POST['id'])) {
			$g_id = (int)$_POST['id'];
			$group = new CNR_Page_Group($g_id);
			$nonce_name = $group->get_nonce_name($group->parse_action($_POST['action']));
			//Validate admin referer AND ajax_referer
			if ($g_id == 0 || check_ajax_referer($nonce_name, '_ajax_nonce')) {
				//If nonce is valid, save page group
				if (isset($_POST['pages']) && $_POST['pages'] != 'undefined' && is_array($_POST['pages']))
					$group->load_pages($_POST['pages']);
				if (array_key_exists('title', $_POST) && is_string($_POST['title']) && $_POST['title'] != 'undefined')
					$group->name = $_POST['title'];
				if (array_key_exists('code', $_POST) && is_string($_POST['code']) && $_POST['code'] != 'undefined')
					$group->set_code($_POST['code']);
				$group->save();
				$ret = array(
							'success'	=> true,
							'id'		=> $group->id,
							'title'		=> $group->name,
							'code'		=> $group->code,
							'nonces'	=> $group->get_nonces_js()
							);	
			} else {
				$ret = array(
							'success'	=> false
							);
			}
			//Return success message + new nonces for actions
			$ret_temp = "{%s}";
			$ret_item = "'%s':%s";
			$ret_props = array();
			foreach ($ret as $key => $var) {
				$add_prop = true;
				if (is_string($var) && !is_numeric($var)) {
					$var = trim($var);
					//Check if value is a JS object (e.g. '{key:val}')
					if (strpos($var, '{') !== 0)
						$var = "'" . $var . "'";
				}
				elseif (is_bool($var))
					$var = ($var) ? 'true' : 'false';
				elseif (!is_numeric($var))
					$add_prop = false;
				if ($add_prop)
					$ret_props[] = sprintf($ret_item, $key, $var);
			}
			$ret = sprintf($ret_temp, implode(',', $ret_props));
		}
		echo $ret;
		exit;
	}
	
	/**
	 * Deletes Page Group from DB
	 * @return bool TRUE if group was successfully removed, FALSE otherwise 
	 */
	function remove() {
		//$wpdb = new wpdb();
		global $wpdb;
		$res = 0;
		//Confirm user has permission for action
		$nonce_name = $this->get_nonce_name($this->actions['remove']);
		$nonce_valid = false;
		$nonce_ajax = '_ajax_nonce';
		$nonce_valid = (isset($_POST[$nonce_ajax])) ? check_ajax_referer($nonce_name, $nonce_ajax) : check_adin_referer($nonce_name);
		if ($nonce_valid) {
			//Delete from DB
			$qry = $wpdb->prepare("DELETE FROM " . $this->db_table_groups . " WHERE group_id = %d", $this->id);
			$res = $wpdb->query($qry);
		}
		return ($res > 0) ? true : false; 
	}
	
	/**
	 * Deletes Page Group from DB via AJAX request
	 * echos string(JSON) AJAX response to client
	 */
	function remove_ajax() {
		$p = $_POST;
		$res = false;
		if (isset($p['id'], $p['_ajax_nonce']) && is_numeric($p['id'])) {
			$group = new CNR_Page_Group($p['id']);
			$res = $group->remove();
			unset($group);
		}
		$ret = sprintf("{'success': %s}", ($res) ? 'true' : 'false');
		echo $ret;
		exit;
	}
	
	/**
	 * Sets current group data in group cache
	 * @return void
	 */
	function cache() {
		if (!isset($this->group_cache)) {
			$this->group_cache = new stdClass;
		}
		
		$this->group_cache->ID = $this->id;
		$this->group_cache->group_title = $this->name;
		$this->group_cache->group_pages = $this->serialize_pages();
	}
	
	/*-** Property Methods (Getters/Setters) **-*/
	
	function set_name($name) {
		if (is_string($name))
			$this->name = trim($name);
	}
	
	/**
	 * Checks whether code is unique
	 * @return bool TRUE if code is unique (FALSE otherwise) 
	 * @param object $code
	 */
	function check_code($code) {
		global $wpdb;
		$group_id = $wpdb->get_var($wpdb->prepare("SELECT group_id FROM $this->db_table_groups WHERE group_name = %s AND group_id != %d", $code, $this->id));
		if (is_null($group_id))
			return true;
		return false;
	}
	
	function check_code_ajax() {
		$ret = false;
		if (isset($_POST['id']) && isset($_POST['code'])) {
			$group = new CNR_Page_Group($_POST['id']);
			$ret = $group->check_code($_POST['code']);
			printf("{val: %s, id: %s}", ($ret) ? 'true' : 'false', $group->id);
		} else {
			printf("{val: false}");
		}
		exit;
	}
	
	/**
	 * Sets code for Group
	 * @param string $code Code to set for Group
	 */
	function set_code($code) {
		$format = '%s-%d';
		$count = 1;
		$code_temp = $code;
		while (!$this->check_code($code_temp)) {
			$code_temp = sprintf($format, $code, $count);
			$count++;
		}
		$this->code = $code_temp;
	}
	
	/**
	 * Returns string of group pages for insertion into DB
	 * @return string 
	 */
	function serialize_pages() {
		return (0 < count($this->pages)) ? $this->sep . implode($this->sep.$this->sep, array_keys($this->pages)) . $this->sep : '';
	}
	
	/**
	 * Counts pages in Group
	 * @return int Number of pages in group
	 */
	function count_pages() {
		return count($this->pages);
	}
	
	/*-** Display **-*/
	
	/**
	 * Generates HTML list of group pages
	 * @return string Page list HTML
	 * @param bool $wrap_list[optional] Whether or not to wrap list items in a UL element (Default = true)
	 */
	function build_pages_list($wrap_list = true) {
		$list = ($wrap_list) ? '<ul class="group-pages">%s</ul>' : '%s';
		$item_temp = '<li class="item-page %s">%s</li>';
		$list_items = '<li class="empty">&nbsp;</li>';
		if (0 < count($this->pages)) {
			$list_items = '';
			$item_temp = '<li class="item-page %s"><a href="%s" title="%s">%s</a></li>';
			foreach ($this->pages as $page) {
				$list_items .= sprintf($item_temp, 'pid_' . $page->ID, get_page_link($page->ID), attribute_escape($page->post_title), $page->post_title);
			}
		}
		return sprintf($list, $list_items);
	}
	
	/**
	 * Print HTML list of group pages
	 * @return void
	 * @param bool $wrap_list[optional] Whether or not to wrap list items in a UL element (Default = true)
	 */
	function list_pages($wrap_list = true) {
		echo $this->build_pages_list($wrap_list);
	}
	
	/**
	 * Retrieves specified template from instance object
	 * @param string $temp[optional] Template to retrieve
	 * @return string Specified template
	 */
	function get_template($temp = '') {
		$temp = trim($temp);
		$c_vars = get_class_vars(__CLASS__);
		$templates = $c_vars['_templates'];
		$temp = ('' != $temp && array_key_exists($temp, $templates)) ? $templates[$temp] : '';
		return $temp;
	}
	
	/**
	 * Returns ID of DOM node containing Group data
	 * @return string ID of DOM node
	 */
	function get_node_id() {
		$ret = '';
		if ($this->id != 0) {
			$ret = $this->node_prefix . $this->id;
		}
		return $ret;
	}
	
	
	function build_js() {
		//var pageGroups = [new PageGroup({'id': 1, 'pages': [7,5,4], 'node': 'pg_1'})];
		$pages = (is_array($this->pages)) ? implode(',', array_keys($this->pages)) : '';
		$params = sprintf("'id': %s, 'pages': [%s], 'node': '%s', 'nonces': %s", $this->id, $pages, $this->get_node_id(), $this->get_nonces_js());
		$obj = sprintf('new PageGroup({%s})', $params);
		return $obj;
	}
	
	/**
	 * Gets nonces for the different admin actions
	 * @return array Associative array of actions => nonce_values
	 */
	function get_nonces() {
		$nonces = array();
		foreach ($this->actions as $action) {
			$nonces[$action] = wp_create_nonce($this->get_nonce_name($action)); 
		}
		return $nonces;
	}
	
	/**
	 * Gets Page Group nonces as JS object
	 * @return string Nonces as JS object
	 */
	function get_nonces_js() {
		$nonces = $this->get_nonces();
		//Build JS object from associative array
		$props = array();
		$obj = "{%s}";
		$prop_temp = "'%s': '%s'";
		foreach ($nonces as $action => $nonce) {
			$props[] = sprintf($prop_temp, $action, $nonce);
		}
		$obj = sprintf($obj, implode(', ', $props));
		return $obj;
	}
	
	/**
	 * Determines nonce name for specified action
	 * Nonce name is generated in the format: Plugin_Type_ID_Action
	 * Example: cnr_pg_1_save
	 * @return string Nonce name for specified action
	 * @param string $action Action to get nonce name for
	 */
	function get_nonce_name($action) {
		$name_temp = "%s%s%s_%s";
		$name = sprintf($name_temp, Cornerstone::get_prefix(), $this->node_prefix, $this->id, $action);
		return $name;
	}
	
	function parse_action($action) {
		$action = str_replace($this->node_prefix, '', $action);
		return $action;
	}
	
	/**
	 * Generates and returns template HTML for a Page Group based on AJAX request 
	 * @return string Template HTML
	 */
	function build_template_ajax() {
		$template = (isset($_REQUEST['template']) && !is_null($_REQUEST['template'])) ? $_REQUEST['template'] : '';
		$tmp = '';
		if ($template != '') {
			switch ($template) {
				case 'group':
					$title = sprintf(CNR_Page_Group::get_template('col_title'), '', sprintf(CNR_Page_Group::get_template('pages_wrap'), 'group-pages', ''), '');
					$code = sprintf(CNR_Page_Group::get_template('col_default'), 'group-code', '');
					$count = sprintf(CNR_Page_Group::get_template('col_default'), 'group-count', '');
					$tmp = sprintf(CNR_Page_Group::get_template('row'), '', $title . $code . $count);
					$tmp = str_replace('%s', '', $tmp);
					break;
				case 'sitePages':
					//$tmp = '<ul><li class="item-page pid_2">About</li><li class="item-page pid_4"><span class="item-page">Articles</span><ul><li class="item-page pid_8">Tutorials</li></ul></li><li class="item-page pid_7">People</li><li class="item-page pid_5">Software</li></ul>';
					$tmp = '<ul>' . wp_list_pages('echo=0&title_li=') . '</ul>';
					break;
			}
		}
		echo $tmp;
		exit;
	}
}

/**
 * Class for creating Admin Menus
 * @package Cornerstone
 */
class CNR_Admin_Menu {
	
}
?>
