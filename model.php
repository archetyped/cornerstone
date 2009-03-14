<?php
/**
 * @package Cornerstone
 */
class Cornerstone {
	/* Variables */
	
	/**
	 * @var string Path to calling file
	 */
	var $_caller;
	
	/**
	 * @var string Prefix for elements created by plugin
	 */
	var $_prefix = 'cns_';
	
	/**
	 * @var string Base name for Content Type Admin Menus
	 */
	var $file_content_types = 'content-types';
	
	/**
	 * @var object Debug object
	 */
	var $debug;
	
	/* Constructor */
	
	function Cornerstone($caller) {
		$this->_caller = $caller;
		$this->debug = new S_DEBUG();
		//Initialization
		register_activation_hook($this->_caller, $this->m('activate'));
		//Add Actions
		//Menus
		add_action('admin_menu', $this->m('admin_menu'));
		add_action('admin_menu', $this->m('admin_post_sidebar'));
		//Add Filters
		//Rewrite Rules
		add_filter('query_vars', $this->m('query_vars'));
		add_filter('rewrite_rules_array', $this->m('rewrite_rules_array'));
		//Posts
		add_filter('the_posts', $this->m('get_children'));
		add_filter('post_link', $this->m('post_link'), 10, 2);
		//Item retrieval
		add_action('pre_get_posts', $this->m('pre_get_posts'));
		
		//Debug
		//add_filter('posts_request', $this->m('posts_request'));
		//add_filter('posts_where', $this->m('posts_where'));
	}
	
	/* Methods */
	
	/**
	 * Returns callback to instance method
	 * @return array
	 * @param string $method
	 */
	function m($method) {
		return array($this, $method);
	}

	function activate() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cnr_';
		//Create DB Tables (if not yet created)
		//Setup table definitions
		$tables = array(
						$prefix . 'groups' => 'CREATE TABLE ' . $prefix . "groups (                                     
								                id smallint(5) unsigned NOT NULL auto_increment,              
								                group_title varchar(90) NOT NULL,   
								                group_name varchar(90) NOT NULL,    
								                PRIMARY KEY  (id)
								              )",
						$prefix . 'groups_pages' => 'CREATE TABLE ' . $prefix . "groups_pages (                               
								                      id smallint(5) NOT NULL default '0',                          
								                      group_id smallint(5) default NULL,                            
								                      page_id bigint(20) unsigned default NULL,                     
								                      page_order int(11) default '0',                               
								                      PRIMARY KEY  (id)
								                    )" 
						);
		//Check tables
		foreach ($tables as $key => $val)
		{
			if ($wpdb->get_var("SHOW TABLES LIKE '" . $key . "'") != $key) {
		        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		        dbDelta($val);
			}
		}
	}

	/**
	 * Sets up Admin menus
	 * @return void
	 */
	function admin_menu() {
		//Page Groups
		//add_submenu_page('edit-pages.php', 'Groups ', 'Groups', 8, __FILE__, 'cnr_menu_page_groups');
		//Content Types
		add_menu_page('Content Types', 'Content Types', 8, $this->file_content_types, $this->m('menu_content_types'));
		add_submenu_page($this->file_content_types, 'Manage Content Types', 'Manage', 8, $this->file_content_types . '_manage', $this->m('menu_content_types_manage'));
	}

	/**
	 * Content to display for Content Types Admin Menu
	 * @return 
	 */
	function menu_content_types() {
		echo '<div class="wrap"><h2>Content Types</h2></div>';
	}
	
	/**
	 * Content to display for Content Types Management Admin Submenu
	 * @return string
	 */
	function menu_content_types_manage() {
		echo '<div class="wrap"><h2>Manage Content Types</h2></div>';
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
		wp_dropdown_pages(array('exclude_tree' => $post->ID, 'selected' => $post->post_parent, 'name' => 'parent_id', 'show_option_none' => __('No Section'), 'sort_column'=> 'menu_order, post_title'));
	}
	
	/* Section */
	
	function get_children($posts)
	{
		//Global variables
		global $wp_query, $wpdb;
		//Additional wp_query variables
		$wp_query->post_children = 0;
		$wp_query->has_children = false;
		$wp_query->current_child = 0;
		$wp_query->children_count = 0;
		
		//Check if current item is an actual page
		if (!is_single() && !is_page()) {
			return $posts;
		}
		//Get children posts of page
		if ($wp_query->posts) {
			$page = $wp_query->posts[0];
			$qry = sprintf('SELECT * FROM %s WHERE post_parent = %s AND post_type = \'post\' AND post_status = \'publish\'', $wpdb->posts, $page->ID);
			$children = $wpdb->get_results($qry);
			if (!!$children) {
				$wp_query->post_children = $children;
				$wp_query->has_children = true;
				$wp_query->children_count = count($wp_query->post_children);
			}
		}
		
		//Return posts (required by filter)
		return $posts;
	}
	
	/**
	 * Checks whether post has children
	 * @return boolean
	 */
	function has_children()
	{
		global $wp_query, $post;
		if ($wp_query->children_count > 0 && $wp_query->current_child < $wp_query->children_count) {
			return true;
		}
		if ($wp_query->current_post >= 0) {
			$post = $wp_query->posts[$wp_query->current_post];
			setup_postdata($post);
		}
		return false;
	}
	
	/**
	 * Loads next child post into global post variable for use in the loop
	 * @return void
	 */
	function get_child() {
		global $wp_query, $post;
		
		if ($this->has_children()) {
			$post = $wp_query->post_children[$wp_query->current_child];
			setup_postdata($post);
			$wp_query->current_child++;
		}
	}
	
	/**
	 * Modifies post permalink to reflect position of post in site structure
	 * Example: baseurl/section-name/post-name
	 * @return string
	 * @param string $permalink Current permalink url for post
	 * @param object $post Post object
	 * @param bool $leavename[optional] Defaults to false. Whether to keep post name or page name.
	 */
	function post_link($permalink, $post) {
		global $wp_rewrite;

		if ($wp_rewrite->using_permalinks()) {
            
			//Get base URL
			$base = get_bloginfo('url');
			
			//Get post path
			$path = $this->get_post_path($post);
			
			//Set permalink
			$permalink = $base . $path . $post->post_name;
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
	function get_post_parents($post) {
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
	function get_post_path($post) {
		//Get post parents
		$parents = $this->get_post_parents($post);
		$sep = '/';
		$path = $sep;
		foreach ($parents as $post_parent) {
			$path .= $post_parent->post_name . $sep;
		}
		return $path;
	}
	
	/**
	 * Adds custom query variables
	 * @return array Array of query variables
	 * @param array $query_vars WP-built list of query variables
	 */
	function query_vars($query_vars) {
		$query_vars[] = 'tester';
		return $query_vars;
	}
	
	/**
	 * Resets certain query properties before post retrieval
	 * @return void
	 * @param object $query_obj WP_Query object reference to <tt>$wp_query</tt> variable
	 */
	function pre_get_posts($query_obj) {
		if (!isset($query_obj->query_vars['tester'])) {
			return;
		}

		if (!isset($query_obj->queried_object_id) || !$query_obj->queried_object_id) {
			$query_obj->query_vars['name'] = sanitize_title(basename($query_obj->query_vars['pagename']));
			unset($query_obj->query_vars['pagename']);
			$query_obj->is_page = false;
		}

	}
	
	/**
	 * Adds custom URL rewrite rules
	 * @return array Rewrite Rules
	 * @param array $rewrite_rules_array Original rewrite rules array generated by WP
	 */
	function rewrite_rules_array($rewrite_rules_array) {
		global $wp_rewrite;
		$rules_extra = array();
		
		//Posts
		//$rules_extra['([\/\w-]+)/([A-Za-z0-9-]+)$'] = $wp_rewrite->index . "?name=\$matches[2]";
		$rules_extra['(.+?)(/[0-9]+)?/?$'] = $wp_rewrite->index . "?pagename=\$matches[1]&tester=\$matches[1]";
		//Pages
		//$rules_extra['^([/\w-]+)$'] = $wp_rewrite->index . "?pagename=\$matches[1]";
		
		//$wp_rewrite = new WP_Rewrite();
		//global $utw_prefix; //DEBUG
		//$url=get_bloginfo('url');
		//$section_prefix = '';
		//$post_extension = '';
		//$option=cms_get_options();
		//DEBUG - NOT USED - $secton_prefix = (strlen($option['permastruct_section'])>0)?$option['permastruct_section']."\/":"";
		//DEBUG - NOT USED - $post_prefix = (strlen($option['permastruct_post'])>0)?$option['permastruct_post']."\/":"";
		//$post_extension = (strlen($option['permastruct_extension']) > 0)?'\.'.$option['permastruct_extension']:'';
		//$category_prefix = (strlen($wp_rewrite->category_base) > 0) ? $wp_rewrite->category_base : "/category";
		//ULTIMATE TAG WARRIOR PREFIX
		//$utw_prefix = str_replace("/", "", get_option("utw_base_url"));
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
		//RSS FEEDS FOR POSTS
		//$cms_rules_2['([\/\w-]+)/([A-Za-z0-9-]+)'.$post_extension.'/feed/?$'] = $wp_rewrite->index."?name=\$matches[2]&feed=feed";

		//add_rewrite_rule('([\/\w-]+)/([A-Za-z0-9-]+)$', $wp_rewrite->index . "?name=\$matches[2]");
		//RSS FEEDS FOR POSTS
		//$cms_rules_2['([\/\w-]+)/([A-Za-z0-9-]+)'.$post_extension.'/feed/?$'] = $wp_rewrite->index."?name=\$matches[2]&feed=feed";
		//$rules['feed/(feed|rdf|rss|rss2|atom)/?$'] = "http://feeds.feedburner.com/archetyped";
		//REWRITE RULES FOR SECTIONS (PAGES)
		//DEBUG - DO NOT USE $cms_rules[$section_prefix.'(.*)/?$']=$wp_rewrite->index."?pagename=\$matches[1]";
		//$cms_rules['(feed)/?$'] = "$wp_rewrite->index?feed=feed";
		//return ($cms_rules + $rewrite_rules_array + $cms_rules_2);
		$rewrite_rules_array = $rules_extra + $rewrite_rules_array;
		//Return rules with new rules prepended
		return $rewrite_rules_array;
	}
	
	/* Remove */
	
	function posts_request($request) {
		$this->debug->add_message('posts_request', $request);
		return $request;
	}
	
	function posts_where($where) {
		global $wp_query, $wpdb;
		$this->debug->add_message('posts_where', $where);
		//Check for post name request
		if (isset($wp_query->query_vars['tester'])) {
			//Remove post_type condition
			$type_pattern = "/(\sAND )($wpdb->posts.post_type)\s*=\s*'*\w+'*/i";
			$where = preg_replace($type_pattern, '$1 ($2 = \'post\' OR $2 = \'page\') ', $where);
			//Add post name to where condition
			$where .= sprintf(' AND %s.post_name = \'%s\'', $wpdb->posts, sanitize_title(basename($wp_query->query_vars['tester'])));
		}
		$this->debug->add_message('posts_where (modified)', $where);
		return $where;
	}
}

/**
 * Class for debugging
 */
class S_DEBUG {
	/**
	 * @var array Associative array of debug messages
	 */
	var $msgs = array();
	
	/**
	 * Adds debug data to object
	 * @return void
	 * @param String $title Title of debug message
	 * @param mixed $message value to store in message for debugging purposes
	 */
	function add_message($title, $message) {
		$this->msgs[$title] = $message;
	}
	
	/**
	 * Returns debug message array
	 * @return array Debug message array
	 */
	function get_messages() {
		return $this->msgs;
	}
	
	function show_messages() {
		echo '<pre>';
		var_dump($this->get_messages());
		echo '</pre>';
	}
}
?>
