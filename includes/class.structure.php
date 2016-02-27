<?php

/**
 * Core properties/methods for Media management
 * @package Cornerstone
 * @subpackage Media
 * @author Archetyped
 * @uses CNR_Post
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
	
	/**
	 * ID of old permalink structure
	 * Used as base of meta key
	 * @see __construct() Value prefixed
	 * @var string
	 */
	var $permalink_structure_old = 'permastruct_old';
	
	/**
	 * Name of field used for setting post parent
	 * Prefix added in constructor
	 * @var string
	 */
	var $field_parent = 'post_parent';
	
	/**
	 * Name of post being created/edited
	 * @var string
	 */
	var $data_insert_post_name = null;
	
	/**
	 * Indicates whether process is currently occurring
	 * Used to determine whether to skip hook handlers, etc.
	 * @var bool
	 */
	var $status_processing = false;
	
	/**
	 * Column name on posts management page
	 * @var string
	 */
	var $management_column = array( 'name' => 'section', 'title' => 'Section' );
	
	/**
	 * Client scripts
	 * @var array
	 */
	var $scripts = array(
		'structure'				=> array (
			'file'		=> 'js/lib.structure.js',
			'deps'		=> array('jquery', '[core]'),
			'context'	=> 'admin'
		),
		'structure_permalink'	=> array (
			'file'		=> 'js/lib.structure.admin.js',
			'deps'		=> array('jquery', '[admin]','[structure]'),
			'context'	=> 'admin_page_options-permalink'
		)
	);
	
	/* Constructor */
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->add_prefix_ref($this->field_parent);
		$this->add_prefix_ref($this->permalink_structure_old);
		$this->permalink_structure = $this->util->normalize_path($this->tok_path, $this->tok_post, array(true, true));
		$this->add_prefix_ref($this->management_column['name']);
	}
	
	/* Methods */
	
	function register_hooks() {
		parent::register_hooks();

		//Rewrite Rules
		add_filter('rewrite_rules_array', $this->m('rewrite_rules_array'));
		
		//Request
		add_filter('post_rewrite_rules', $this->m('post_rewrite_rules'));
		
		//Query
		add_action('pre_get_posts', $this->m('pre_get_posts'));
		
		//Permalink
		add_filter('post_link', $this->m('post_link'), 10, 3);
		add_filter('post_type_link', $this->m('post_link'), 10, 4);

		//Navigation
		add_filter('wp_nav_menu_objects', $this->m('nav_menu_objects'), 10, 2);
		
		//Admin
		add_action('admin_print_scripts', $this->m('admin_print_scripts'), 30);
		add_action('update_option_permalink_structure', $this->m('update_permastruct'), 10, 3);
			//Edit
		//Add meta boxes
		add_action('do_meta_boxes', $this->m('admin_post_sidebar'), 1, 3);
		//Process post creation/updating
		add_filter('wp_insert_post_data', $this->m('admin_post_insert_data'), 10, 2);
		add_action('save_post', $this->m('admin_post_save'), 10, 2);
		add_action('delete_post', $this->m('admin_post_delete'), 10, 1);
			//Management
		add_action('restrict_manage_posts', $this->m('admin_restrict_manage_posts'));
		add_action('parse_request', $this->m('admin_manage_posts_filter_section'));
		add_filter('manage_posts_columns', $this->m('admin_manage_posts_columns'));
		add_action('manage_posts_custom_column', $this->m('admin_manage_posts_custom_column'), 10, 2);
		add_action('quick_edit_custom_box', $this->m('admin_quick_edit_custom_box'), 10, 2);
		add_action('bulk_edit_custom_box', $this->m('admin_bulk_edit_custom_box'), 10, 2);
	}
	
	/**
	 * Flushes rewrite rules
	 */
	function reset() {
		global $wp_rewrite;
		//Rebuild URL Rewrite rules
		$wp_rewrite->flush_rules();
	}
	
	/**
	 * Plugin activation routines
	 * @global WP_Rewrite $wp_rewrite
	 */
	function activate() {
		global $wp_rewrite;
		//Update permalink structure
		$struct = get_option($this->permalink_structure_old, null);
		if ( $struct )
			$wp_rewrite->set_permalink_structure($this->permalink_structure);
		else {
			delete_option($this->permalink_structure_old);
		}
		$this->reset();
	}
	
	/**
	 * Plugin deactivation routines
	 * @uses WP_Rewrite $wp_rewrite to Update permalink structure
	 */
	function deactivate() {
		//Reset permalink structure
		if ( $this->using_post_permastruct() ) {
			global $wp_rewrite;
			$permastruct = get_option($this->permalink_structure_old, '');
			$wp_rewrite->set_permalink_structure($permastruct);
			//Save permastruct setting
			update_option($this->permalink_structure_old, true);
		} else {
			delete_option($this->permalink_structure_old);
		}
		//Flush Rules
		$this->reset();
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
	 * Save old permastruct when custom one set
	 * @uses `update_option_permalink_structure` Action hook
	 * @see update_option()
	 * @see register_hooks() Initialized
	 * @param string $option Option name
	 * @param string $old Previous permalink structure value
	 * @param string $new New permalink structure value
	 * @return void
	 */
	function update_permastruct($old, $new) {
		//Validate request
		if ( $old == $new || ( $new != $this->permalink_structure && $old != $this->permalink_structure ) )
			return false;
		//Save old permastruct
		if ( $new == $this->permalink_structure)
			update_option($this->permalink_structure_old, $old);
		elseif ( $old == $this->permalink_structure )
			delete_option($this->permalink_structure_old);
	}
	
	/**
	 * Returns path to post based on site structure
	 * @return string Path to post enclosed in '/' (forward slashes)
	 * Example: /path/to/post/
	 * @param object $post Post object
	 */
	function get_path($post, $sample = false) {
		//Get post parents
		$parents = CNR_Post::get_parents($post);
		$sep = '/';
		$path = $sep;
		//Modify parent for sample permalink request
		if ( $sample ) {
			$this->util->check_post($post);
			$parent = get_post_meta($post->ID, $this->get_parent_meta_key(), true);
			
			if ( $parent && $parent = get_post($parent) ) {
				if ( !empty($parents) ) {
					//Remove last element
					array_pop($parents);
				}
				//Add new parent
				$parents[] = $parent;
			}
		}
		//Build path
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
	function post_link($permalink, $post, $leavename = false, $sample = false) {
		global $wp_query;
		
		/* Stop processing immediately if:
		 * Custom permalink structure is not activated by user
		 * Post data is not a valid post
		 * Post has no name (e.g. drafts)
		 * Custom post type NOT in a section
		 */
		if ( !$this->using_post_permastruct()
			|| ( !$this->util->check_post($post) )
			|| ( empty($post->post_name) && empty($post->post_title) )
			|| ( 'draft' == $post->post_status && empty($post->post_name) && !$sample )
			|| ( 'inherit' == $post->post_status )
			|| ( !in_array($post->post_type, array('post', 'page', 'attachment', 'revision', 'nav_menu')) && empty($post->post_parent) ) )
			return $permalink;
		
		//Get base data
		$base = get_bloginfo('url');
		$name = '';
		
		//Set mode
		$sample = ( isset($post->filter) && 'sample' == $post->filter ) ? true : $sample;
		
		//Get post slug
		
		//Sample
		if ( $sample ) {
			/**
			 * Sample permalink
			 * Use permalink placeholder if sample permalink is being generated 
			 * @see get_sample_permalink()
			 */
			$name = '%postname%';
			
			//Remove temporary parent if not valid for current request
			if ( !defined('DOING_AJAX') || !DOING_AJAX || !isset($_POST['action']) || 'sample-permalink' != $_POST['action'] ) {
				delete_post_meta($post->ID, $this->get_parent_meta_key());
			}
		}
		//Use existing name
		elseif ( !empty($post->post_name) ) {
			$name = $post->post_name;
		}
		//Build name from title (if not yet set)
		if ( empty($name) ) {
			$post->post_status = 'publish';
			$name = sanitize_title($name ? $name : $post->post_title, $post->ID);
			$name = wp_unique_post_slug($name, $post->ID, $post->post_status, $post->post_type, $post->post_parent);
		}
		
		//Get post path
		$path = $this->get_path($post, $sample);
		
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
	 * Modifies rewrite rules array
	 * Removes unnecessary paged permalink structure for Pages/Content Types (/pagename/[0-9]/)
	 * - Conflicts with /pagename/postname/ permalink structure
	 * @param array $rules Generated rewrite rules
	 * @return array Modified rewrite rules
	 */
	function rewrite_rules_array($rules) {
		$subpattern_old = '(/[0-9]+)?/?$';
		$subpattern_new = '(/)?$';
		$qvar = '&page=';
		$rules_temp = array();
		
		//Find rules containing subpattern
		$patterns = array_keys($rules);
		
		foreach ( $patterns as $idx => $patt ) {
			$rule = '';
			//Check if pattern contains subpattern
			if ( strpos($patt, $subpattern_old) !== false && strpos($rules[$patt], $qvar) !== false ) {
				//Generate new pattern
				$rule = str_replace($subpattern_old, $subpattern_new, $patt);
			} else {
				$rule = $patt;
			}
			//Add rule to temp array
			$rules_temp[$rule] = $rules[$patt];
		}
		
		//Return modified rewrite rules
		return $rules_temp;
	}
	
	/**
	 * Performs additional processing on nav menu objects
	 * > Adds additional classes to menu items based on current request
	 * @see wp_nav_menu()
	 * @uses `wp_nav_menu_objects` filter hook to modify items
	 * @param array $menu_items Sorted menu items
	 * @param object $args Arguments passed to function
	 * @return array Menu items array 
	 */
	function nav_menu_objects($menu_items, $args) {
		$class_base = 'current-page-';
		$class_ancestor = $class_base . 'ancestor';
		$class_parent = $class_base . 'parent';
		//Get current item
		if ( is_singular()
			&& ( $item = get_queried_object() )
			&& !empty($item->post_type)
			&& !is_post_type_hierarchical($item->post_type) //Only process non-hierarchical post types
		) {
			//Get ancestors of current item
			$ancestors = get_ancestors($item->ID, $item->post_type);
			
			//Loop through menu items and add classes to ancestors of current item
			foreach ( $menu_items as $key => $m_item ) {
				//Only process menu items representing posts/pages
				if ( isset($m_item->type) && 'post_type' == $m_item->type && in_array($m_item->object_id, $ancestors) ) {
					//Add ancestor class to item
					if ( !is_array($m_item->classes) )
						$m_item->classes = array();
					$m_item->classes[] = $class_ancestor;
					//Check if menu item is parent of current item
					if ( $item->post_parent == $m_item->object_id )
						$m_item->classes[] = $class_parent;
					//Filter duplicate classes
					$m_item->classes = array_unique($m_item->classes);
					//Update menu array
					$menu_items[$key] = $m_item;
				}
			}
		}
		
		return $menu_items;
	}
	
	/**
	 * Sets post parent
	 * @param obj|int $post Post object or ID
	 * @param obj|int $parent Parent post object or ID
	 * @param bool $temp (optional) Whether parent should be temporarily set (Default: NO)
	 */
	function set_parent($post, $parent = null, $temp = false) {
		/* Validation */
		//Post
		if ( !$this->util->check_post($post) || ( is_null($parent) && !isset($post->post_parent) ) )
			return false;
		//Default parent
		if ( is_null($parent) )
			$parent = $post->post_parent;
		//Request
		if ( 0 != $parent && ( !$this->util->check_post($parent) || !isset($parent->ID) || $post->ID == $parent->ID ) )
			return false;
		
		if ( is_object($parent) ) {
			$parent = $parent->ID;
		}
		
		/* Set Parent */
		
		//Temporary
		if ( $temp ) {
			update_post_meta($post->ID, $this->get_parent_meta_key(), $parent);
		} 
		//Standard
		else {
			//Remove temporary parent
			delete_post_meta($post->ID, $this->get_parent_meta_key());
		}
	}

	/*-** Admin **-*/
	
	/**
	 * Outputs structure data to client
	 * @param string $page Current page
	 */
	function admin_print_scripts() {
		$out = array();
		$opts = array (
			'using_permastruct'		=> $this->using_post_permastruct(),
			'permalink_structure'	=> $this->permalink_structure,
			'field_parent' 			=> $this->field_parent
		);
		
		$out[] = $this->util->extend_client_object('structure.options', $opts);
		
		if ( $this->util->is_context($this->scripts->structure_permalink->context) ) {
			$opts_admin = array (
				'label'		=> __('Structured', $this->get_prefix()),
				'example'	=> get_option('home') . '/section-name/sample-post/'
			);
			
			$out[] = $this->util->extend_client_object('structure.admin.options', $opts_admin);
		}

		//Output data
		echo $this->util->build_script_element(implode(PHP_EOL, $out), 'structure_options');
	}
	
	/**
	 * Set post parent for current post
	 * If custom field is present in $postarr, use value to set post's parent
	 * Post parent is set in WP posts table (as post_parent value) in calling function
	 * @see $this->admin_post_save() Saves parent as metadata for post
	 * @see wp_insert_post()
	 * @uses $this->field_parent as field name to check for
	 * @param array $data Post data (restricted default columns only) 
	 * @param array $postarr Post data passed to wp_insert_post() and parsed with defaults
	 * @return array Modified post data
	 */
	function admin_post_insert_data($data, $postarr) {
		if ( !in_array($data['post_type'], array('revision', 'page')) && !$this->is_processing() ) {
			//Check for custom field and validate value
			if ( isset($postarr[$this->field_parent]) ) {
				$parent_val = intval($postarr[$this->field_parent]);
				//If field is set, set as parent
				if ( $parent_val >= 0 )
					$data['post_parent'] = $parent_val;
				//Set post name
				// $this->data_insert_post_name = $data['post_name'];
			} else {
				//If field is not set, remove metadata (if previously set)
				// $this->data_insert_post_name = null;
			}
		}
		//Return updated post data
		return $data;
	}
	
	/**
	 * Save post parent as metadata
	 * Metadata used for backwards compatibility and future-proofing
	 * @param int $post_ID Saved post ID
	 * @param obj $post Saved post object
	 */
	function admin_post_save($post_ID, $post) {
		$parent = null;
		$temp = false;
			
		//Autosave
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && $pid =  wp_is_post_autosave($post) ) {
			//Get temporary parent ID
			$parent = ( isset($_POST['parent_id']) ) ? $_POST['parent_id'] : 0;
			//Save temporary parent as metadata
			$temp = true;
			$post_ID = $post->post_parent;
		}
		
		$this->set_parent($post_ID, $parent, $temp);
	}
	
	/**
	 * Move children of deleted section to parent
	 * Process only pages
	 * @uses $wpdb
	 * @param int $post_ID ID of deleted post
	 */
	function admin_post_delete($post_ID) {
		static $p = null;
		//Only set if post type is page
		$page = get_post($post_ID);
		if ( 'page' != $page->post_type )
			return false;
		if ( is_null($p) ) {
			//Set static var and stop processing
			$p = $post_ID;
		}
		elseif ( $p != $post_ID ) {
			//Clear static var
			$p = null;	
		}
		else {
			global $wpdb;
			//Move children
			$page = get_post($post_ID);
			$children_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $post_ID));
			$parent_data = array( 'post_parent' => $page->post_parent );
			$parent_where = array( 'post_parent' => $page->ID );
			$wpdb->update($wpdb->posts, $parent_data, $parent_where);
			
			//Clear child caches & update postmeta
			foreach ( $children_ids as $child ) {
				$this->set_parent($child, $page->post_parent);
				clean_post_cache($child);
			}
			
			//Clear static var
			$p = null;
		}
	}
	
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
		?>
		<div class="<?php echo $this->add_prefix('pages_dropdown'); ?>">
		<?php
		wp_dropdown_pages(array('exclude_tree' => $post->ID,
								'selected' => $post->post_parent,
								'name' => $this->field_parent,
								'show_option_none' => __('- No Section -'),
								'sort_column'=> 'menu_order, post_title'));
		?>
		</div>
		<?php
	}
	
	/**
	 * Adds additional options to filter posts
	 */
	function admin_restrict_manage_posts() {
		//Add to post edit only
		$section_param = $this->add_prefix('section');
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
	 * Hooks into 'parse_request' action
	 * @see WP::parse_request()
	 * @param array $query_vars Parsed query variables
	 * @return array Modified query variables
	 */
	function admin_manage_posts_filter_section($q) {
		//Determine if request is coming from manage posts admin page
		$var = $this->add_prefix('section');
		if ( $this->util->is_admin_management_page()
			&& isset($_GET[$var])
			&& is_numeric($_GET[$var]) 
			) {
				$q->query_vars['post_parent'] = intval($_GET[$var]);
		}
	}
	
	/**
	 * Modifies the columns that are displayed on the Post Management Admin Page
	 * @param array $columns Array of columns for displaying post data on each post's row
	 * @return array Modified columns array
	 */
	function admin_manage_posts_columns($columns) {
		$columns[$this->management_column['name']] = __($this->management_column['title']);
		return $columns;
	}
	
	/**
	 * Adds section name that post belongs to in custom column on Post Management admin page
	 * @param string $column_name Name of current custom column
	 * @param int $post_id ID of current post
	 */
	function admin_manage_posts_custom_column($column_name, $post_id) {
		//Only process for specific columns (stop processing otherwise)
		if ( $this->management_column['name'] != $column_name )
			return false;
		//Get section
		$section = CNR_Post::get_section($post_id);

		if ( !empty($section) ) {
			echo $section->post_title;
			$data = array("post_$post_id" => (object) array('post_parent' => $section->ID));
			echo $this->util->build_script_element($this->util->extend_client_object('posts.data', $data));
			//echo '<script type="text/javascript">postData["post_' . $post_id . '"] = {"post_parent" : ' . $section->ID . '};</script>'; 
		} else
			_e('None', 'cornerstone');
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
		if ( $column_name == $this->add_prefix('section') && in_array($type, $child_types) ) :
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<div class="inline-edit-group">
					<label><span class="title">Section</span></label>
					<?php
					$options = array('exclude_tree'				=> $post->ID, 
									 'name'						=> $this->field_parent,
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
	
	/**
	 * Adds field for Section selection on the bulk edit form for posts
	 * @see admin_quick_edit_custom_box()
	 * @param string $column_name Name of custom column 
	 * @param string $type Type of current item (post, page, etc.)
	 */
	function admin_bulk_edit_custom_box($column_name, $type) {
		$this->admin_quick_edit_custom_box($column_name, $type, true);
	}
	
	/* Helpers */
	
	/**
	 * Return key for storing post parent metadata
	 * @return string Meta key value
	 */
	function get_parent_meta_key() {
		return '_' . $this->field_parent;
	}
	
	/**
	 * Set processing flag
	 * @uses status_processing
	 * @param bool $status (optional) Set processing status
	 */
	function start_processing($status = true) {
		$this->status_processing = !!$status;
	}
	
	/**
	 * Unset processing flag
	 * @uses start_processing() to set processing flag
	 */
	function stop_processing() {
		$this->start_processing(false);
	}
	
	/**
	 * Check if request is currently being processed
	 * @uses status_processing
	 * return bool Processing status
	 */
	function is_processing() {
		return !!$this->status_processing;
	}
}