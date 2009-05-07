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
	 * @var string Base name for Content Type Admin Menus
	 */
	var $file_content_types = 'content-types';
	
	/**
	 * @var object Debug object
	 */
	var $debug;
	
	var $path = __FILE__;
	
	var $url_base = '';
	
	/* Constructor */
	
	function Cornerstone($caller) {
		//Set Properties
		$this->_caller = $caller;
		$this->_prefix_db = $this->get_db_prefix();
		$this->_qry_var = $this->_prefix . $this->_qry_var;
		$this->debug = new S_DEBUG();
		$this->path = str_replace('\\', '/', $this->path);
		$this->url_base = dirname(WP_PLUGIN_URL . str_replace(str_replace('\\', '/', WP_PLUGIN_DIR), '', $this->path));
		//Initialization
		register_activation_hook($this->_caller, $this->m('activate'));
		
		//Add Actions
		//Admin
			//Head
		add_action('admin_head', $this->m('admin_add_styles'));
		add_action('admin_print_scripts', $this->m('admin_add_scripts'));
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
		//printf('Plugin Path (Full): %s<br />Plugins Directory: %s<br />Plugins URL: %s<br />CNR URL: %s<br />', $this->path, WP_PLUGIN_DIR, WP_PLUGIN_URL, $this->url_base);
		
		//Register Hooks for other classes
		//Page Group
		CNR_Page_Group::register_hooks();
	}
	
	/* Methods */
	
	/*-** Helpers **-*/
	
	/**
	 * Returns callback to instance method
	 * @return array
	 * @param string $method
	 */
	function m($method) {
		return array($this, $method);
	}
	
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
	 * Sets up Admin menus
	 * @return void
	 */
	function admin_menu() {
		global $menu, $submenu;
		//Content Types
		add_menu_page('Content Types', 'Content Types', 8, $this->file_content_types, $this->m('menu_content_types'));
		add_submenu_page($this->file_content_types, 'Manage Content Types', 'Manage', 8, $this->file_content_types . '_manage', $this->m('menu_content_types_manage'));
		
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
	
	function admin_add_styles() {
		//Define file properties
		$file_base = 'admin_styles';
		$handle = $this->_prefix . $file_base;
		$file_url = $this->get_file_url($file_base . '.css');
		
		//Add to page
		wp_register_style($handle, $file_url);
		wp_print_styles($handle);
	}
	
	function admin_add_scripts() {
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_script('jquery-ui-draggable');
		wp_enqueue_script('jquery-ui-effects', $this->get_file_url('effects.core.js'));
		wp_enqueue_script($this->_prefix . 'script-ns', $this->get_file_url('jtree.js'));
		wp_enqueue_script($this->_prefix . 'script', $this->get_file_url('cnr.js'));
		wp_enqueue_script($this->_prefix . 'script_admin', $this->get_file_url('cnr_admin.js'));
	}
	
	/*-** Content **-*/
	
	/**
	 * Gets children posts of specified page and stores them in global $wp_query variable
	 * @return array $posts Posts array
	 * @param array $posts Array of Posts (@see WP_QUERY)
	 */
	function get_children($posts) {
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
	 * Example: baseurl/section-name/post-name/
	 * @return string
	 * @param string $permalink Current permalink url for post
	 * @param object $post Post object
	 */
	function post_link($permalink, $post) {
		global $wp_rewrite;

		if ($wp_rewrite->using_permalinks()) {
            
			//Get base URL
			$base = get_bloginfo('url');
			
			//Get post path
			$path = $this->get_post_path($post);
			
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
	 * Adds custom URL rewrite rules
	 * @return array Rewrite Rules
	 * @param array $rewrite_rules_array Original rewrite rules array generated by WP
	 */
	function rewrite_rules_array($rewrite_rules_array) {
		global $wp_rewrite;
		$rules_extra = array();
		//Posts/Pages
		//$rules_extra['([\/\w-]+)/([A-Za-z0-9-]+)$'] = $wp_rewrite->index . "?name=\$matches[2]";
		$rewrite_rules_array['(.+?)(/[0-9]+)?/?$'] = $wp_rewrite->index . "?pagename=\$matches[1]&" . $this->_qry_var . "=\$matches[1]";
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
		//$rewrite_rules_array = $rewrite_rules_array + $rules_extra;
		//Return rules with new rules added
		return $rewrite_rules_array;
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
		if (is_object($pages) && property_exists($pages, 'group_pages'))
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
