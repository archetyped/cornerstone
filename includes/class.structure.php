<?php

require_once 'class.base.php';
require_once 'class.posts.php';

/**
 * Core properties/methods for Media management
 * @package Cornerstone
 * @subpackage Media
 * @author SM
 */
class CNR_Structure extends CNR_Base {
	
	/* Properties */
	
	/**
	 * Postname token
	 * @var string
	 */
	var $tok_post = '%postname%';
	
	/**
	 * Post Path token
	 * @var string
	 */
	var $tok_path = '%postpath%';
	
	/**
	 * Custom post permalink structure 
	 * @var string
	 */
	var $permalink_structure = null;
	
	/* Constructor */
	
	/**
	 * Legacy Constructor
	 */
	function CNR_Structure() {
		$this->__construct();
	}
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->permalink_structure = "/$this->tok_path/$this->tok_post/";
	}
	
	/* Methods */
	
	function init() {
		$this->register_hooks();
	}
	
	function register_hooks() {
		parent::register_hooks();
		
		//Request
		add_filter('post_rewrite_rules', $this->m('post_rewrite_rules'));
		
		//Query
		add_action('pre_get_posts', $this->m('pre_get_posts'));
		
		//Permalink
		add_filter('post_link', $this->m('post_link'), 10, 3);
		add_filter('post_type_link', $this->m('post_link'), 10, 3);
		//TODO: Handle redirect_canonic (currently not evaluated)
//		add_filter('redirect_canonical', $this->m('post_link'), 10, 2);
		
		//Admin
		add_filter('admin_enqueue_scripts', $this->m('admin_enqueue_scripts'));
			//Edit
		add_action('do_meta_boxes', $this->m('admin_post_sidebar'), 1, 3);
			//Management
		add_action('restrict_manage_posts', $this->m('admin_restrict_manage_posts'));
		add_action('parse_query', $this->m('admin_manage_posts_filter_section'));
		add_filter('manage_posts_columns', $this->m('admin_manage_posts_columns'));
		add_action('manage_posts_custom_column', $this->m('admin_manage_posts_custom_column'), 10, 2);
		add_action('quick_edit_custom_box', $this->m('admin_quick_edit_custom_box'), 10, 2);
		add_action('bulk_edit_custom_box', $this->m('admin_bulk_edit_custom_box'), 10, 2);
	}
	
	/**
	 * Plugin activation routines
	 * @global WP_Rewrite $wp_rewrite
	 */
	function activate() {
		global $wp_rewrite;
		//Rebuild URL Rewrite rules
		$wp_rewrite->flush_rules();
	}
	
	/**
	 * Plugin deactivation routines
	 */
	function deactivate() {
		$this->activate();
	}
	
	/**
	 * Returns formatted query variable for use in post requests
	 * @return string Custom query variable
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 */
	function get_query_var() {
		static $qvar = '';
		
		//Retrieve query var used for page queries
		if ( empty($qvar) ) {
			global $wp_rewrite;
			//Get page permastruct
			$page_tag = $wp_rewrite->get_page_permastruct();
			
			//Extract tag for page
			$page_tag = str_replace($wp_rewrite->index, '', $page_tag);
			
			//Get query var for tag
			if (  ($idx = array_search($page_tag, $wp_rewrite->rewritecode)) !== false ) {
				$qvar = trim($wp_rewrite->queryreplace[$idx], '=');
			}
		}
		
		return $qvar;
	}
	
	/**
	 * Checks if custom permalink structure is currently in use
	 * @return bool TRUE if custom permalink structure is in use, FALSE otherwise
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 */
	function using_post_permastruct() {
		global $wp_rewrite;
		return ( $wp_rewrite->using_permalinks() && get_option('permalink_structure') == $this->permalink_structure );
	}
	
	/**
	 * Returns path to post based on site structure
	 * @return string Path to post enclosed in '/' (forward slashes)
	 * Example: /path/to/post/
	 * @param object $post Post object
	 */
	function get_path($post) {
		//Get post parents
		$parents = CNR_Post::get_parents($post);
		$sep = '/';
		$path = $sep;
		foreach ($parents as $post_parent) {
			$path .= $post_parent->post_name . $sep;
		}
		return $path;
	}
	
	/**
	 * Modifies post permalink to reflect position of post in site structure
	 * Example: baseurl/section-name/post-name/
	 * 
	 * @param string $permalink Current permalink url for post
	 * @param object|int $post Post object or Post ID
	 * @param bool $leavename Whether to leave post name 
	 * @return string
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 * @global WP_Query $wp_query
	 * 
	 * @see get_permalink
	 * @see get_post_permalink
	 * 
	 * @todo Enable redirect_canonical functionality
	 *
	 */
	function post_link($permalink, $post, $leavename = false) {
		global $wp_query, $cnr_content_utilities;
		
		/* Stop processing immediately if:
		 * Custom permalink structure is not activated by user
		 * Post data is not a valid post
		 * Post has no name (e.g. drafts)
		 * Custom post type NOT in a section
		 */
		if ( !$this->using_post_permastruct()
			|| ( !$this->util->check_post($post) )
			|| ( empty($post->post_name) && empty($post->post_title) )
			|| ( 'draft' == $post->post_status && empty($post->post_name) )
			|| ( !$cnr_content_utilities->is_default_post_type($post->post_type) && empty($post->post_parent) ) )
			return $permalink;	
		
		/*
		//Canonical redirection usage
		if ( is_string($post) ) {
			//Only process single posts
			if ( is_single() ) {
				$post = $wp_query->get_queried_object();
			} else {
				//Stop processing for all other content (return control to redirect_canonical)
				return false;
			}
		}
		*/
			
		//Get base URL
		$base = get_bloginfo('url');
		
		$name = '';
		//Use permalink placeholder if sample permalink is being generated (@see get_sample_permalink())
		if ( isset($post->filter) && 'sample' == $post->filter )
			$name = '%postname%';
		elseif ( !empty($post->post_name) )
			$name = $post->post_name;
		//Build name from title (if not yet set)
		if ( empty($name) ) {
			$post->post_status = 'publish';
			$name = sanitize_title($name ? $name : $post->post_title, $post->ID);
			$name = wp_unique_post_slug($name, $post->ID, $post->post_status, $post->post_type, $post->post_parent);
		}
		
		//Get post path
		$path = $this->get_path($post);
		
		//Set permalink (Add trailing slash)
		$permalink = trailingslashit($base . $path . $name);

		return $permalink;
	}
	
	/**
	 * Resets certain query properties before post retrieval
	 * Checks if request is for a post (using value from pagename query var) and adjusts query to retrieve the post instead of a page
	 * @return void
	 * @param WP_Query $q Reference to global <tt>$wp_query</tt> variable
	 * 
	 * @global wpdb $wpdb
	 */
	function pre_get_posts($q) {
		//Do not process query if custom post permastruct is not in use
		if ( !$this->using_post_permastruct() )
			return;
		$qvar = $this->get_query_var();
		$qv =& $q->query_vars;

		//Stop processing if custom query variable is not present in current query
		if ( empty($qvar) || !isset($qv[$qvar]) || empty($qv[$qvar]) ) {
			return;
		}
		global $wpdb;

		$qval = $qv[$qvar];

		//Get last segment
		$slug = array_reverse( explode('/', $qval) );
		if ( is_array($slug) && !empty($slug) )
			$slug = $slug[0];
		else
			return;
		
		//Determine if query is for page or post
		$type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM $wpdb->posts WHERE post_name = %s LIMIT 1", $slug));
		if ( empty($type) )
			return;
			
		//Adjust query if requested item is not a page 
		if ( 'page' != $type ) {
			$new_var = 'name';
			$qval = $slug;
			//Set new query var
			$qv[$new_var] = $qval;
			unset($qv[$qvar]);
			//Set post type
			$qv['post_type'] = $type;
			//Reparse query variables
			$q->parse_query($qv);
		}
	}
	
	/**
	 * Modifies post rewrite rules when using custom permalink structure
	 * Removes all post rewrite rules since we are modifying page rewrite rules to process the request
	 * @param array $r Post rewrite rules from WP_Rewrite::rewrite_rules
	 * @return array Modified post rewrite rules
	 */
	function post_rewrite_rules($r) {
		if ( $this->using_post_permastruct() )
			$r = array();
		return $r;
	}
	
	/**
	 * Enqueues script to manage post permastruct to permalink admin options page
	 * @param string $page Current page
	 */
	function admin_enqueue_scripts($page) {
		//Only continue processing on permalink options page
		if ( 'options-permalink.php' != $page )
			return;
		
		//Enqueue script to insert custom permalink option
		wp_enqueue_script($this->add_prefix('options-permalink'), $this->util->get_file_url('js/options_structure.js'), array('jquery'));
		
		//Insert permalink option data in javascript for use in enqueued script
		?>
		<script type="text/javascript">
		cnr_permalink_option = {
			'structure': '<?php echo $this->permalink_structure; ?>',
			'label': '<?php _e('Structured'); ?>',
			'example': '<?php echo get_option('home') . '/section-name/sample-post/'?>'
		};
		</script>
		<?php
	}
	
	/*-** Admin **-*/
	
	/**
	 * Adds meta box for section selection on post edit form
	 */
	function admin_post_sidebar($type, $context = null, $post = null) {
		$child_types = get_post_types(array('show_ui' => true, '_builtin' => false));
		$child_types[] = 'post';
		$side_context = 'side';
		$priority = 'high';
		if ( in_array($type, $child_types) && $side_context == $context )
			add_meta_box($this->add_prefix('section'), __('Section'), $this->m('admin_post_sidebar_section'), $type, $context, $priority);
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
		if ( $this->util->is_admin_management_page() ) {
			$selected = ( isset($_GET[$section_param]) && is_numeric($_GET[$section_param]) ) ? $_GET[$section_param] : 0;
			//Add post statuses
			$options = array('name'				=> $section_param,
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
	function admin_manage_posts_filter_section($q) {
		//Determine if request is coming from manage posts admin page
		//TODO Modify condition to work in this class
		if ( $this->util->is_admin_management_page()
			&& isset($_GET['cnr_section'])
			&& is_numeric($_GET['cnr_section']) 
			) {
				$q->query_vars['post_parent'] = intval($_GET['cnr_section']);
		}
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
		$section_id = CNR_Post::get_section();
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
	function admin_quick_edit_custom_box($column_name, $type, $bulk = false) {
		global $post;
		$child_types = get_post_types(array('show_ui' => true, '_builtin' => false));
		$child_types[] = 'post';
		if ( $column_name == 'section' && in_array($type, $child_types) ) :
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<div class="inline-edit-group">
					<label><span class="title">Section</span></label>
					<?php
					$options = array('exclude_tree'				=> $post->ID, 
									 'name'						=> 'post_parent',
									 'show_option_none'			=> __('- No Section -'),
									 'option_none_value'		=> 0,
									 'show_option_no_change'	=> ($bulk) ? __('- No Change -') : '',
									 'sort_column'				=> 'menu_order, post_title');
					wp_dropdown_pages($options);
					?>
				</div>
			</div>
		</fieldset>
		<?php endif;
	}
	
	function admin_bulk_edit_custom_box($column_name, $type) {
		$this->admin_quick_edit_custom_box($column_name, $type, true);
	}
}
?>