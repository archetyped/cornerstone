<?php

require_once 'includes/class.base.php';
require_once 'includes/class.content-types.php';
require_once 'includes/class.media.php';
require_once 'includes/class.posts.php';
require_once 'includes/class.structure.php';
require_once 'includes/class.feeds.php';

/**
 * @package Cornerstone
 */
class Cornerstone extends CNR_Base {
	/* Variables */
	
	/**
	 * Name of property to store post parts under
	 * @var string
	 * @todo move to separate class/plugin
	 */
	var $_post_parts_var = 'parts';
	
	/* Featured Content variables */
	
	/**
	 * @var string Category slug value that denotes a "featured" post
	 * @see posts_featured_cat()
	 * @todo Remove need for this property
	 */
	var $posts_featured_cat = "feature";
	
	/**
	 * Featured posts container
	 * @var CNR_Post_Query
	 */
	var $posts_featured = null;
	
	/* Children Content Variables */
	
	/**
	 * Children posts
	 * @var CNR_Post_Query
	 */
	var $post_children_collection = null;
	
	/* Instance Variables */
	
	/**
	 * Structure instance
	 * @var CNR_Structure
	 */
	var $structure = null;
	
	/**
	 * Feeds instance
	 * @var CNR_Feeds
	 */
	var $feeds = null;
	
	/* Constructor */
	
	function Cornerstone()  {
		$this->__construct();
	}
							
	function __construct() {
		//Parent Constructor
		parent::__construct();
		
		//Set Properties
		$this->_post_parts_var = $this->add_prefix($this->_post_parts_var);
		
		//Special Queries
		$this->posts_featured =& new CNR_Post_Query( array( 'category' => $this->posts_featured_get_cat_id(), 'numberposts' => 4 ) );
		$this->post_children_collection =& new CNR_Post_Query();
		
		//Register hooks
		$this->register_hooks();
		
		//Init class instances
		$this->structure = new CNR_Structure();
		$this->structure->init();
		
		$this->feeds = new CNR_Feeds();
		$this->feeds->init();
	}
	
	function register_hooks() {
		/* Register Hooks */
		parent::register_hooks();
		
		//Admin
			//Initialization
		add_action('admin_init', $this->m('admin_init'));
			//Head
		add_action('admin_enqueue_scripts', $this->m('admin_add_styles'));
		add_action('admin_enqueue_scripts', $this->m('admin_add_scripts'));
			//TinyMCE
		//add_action('init', $this->m('admin_mce_register'));
		add_filter('tiny_mce_before_init', $this->m('admin_mce_before_init'));
		add_filter('mce_buttons', $this->m('admin_mce_buttons'));
		add_filter('mce_external_plugins', $this->m('admin_mce_external_plugins'));
		add_action('admin_enqueue_scripts', $this->m('admin_post_quicktags'));
		
		//Posts
		add_filter('the_posts', $this->m('post_children_get'));
		add_filter('wp_list_pages', $this->m('post_section_highlight'));
		
		//Activate Shortcodes
		$this->sc_activate();
	}
	
	/* Methods */

	/*-** Admin **-*/
	
	/**
	 * Performs specified operations when admin is initialized
	 * @return void
	 */
	function admin_init() {
		$this->admin_register_scripts();
	}
	
	function admin_register_scripts() {
		wp_register_script( $this->add_prefix('script_admin'), $this->util->get_file_url('js/cnr_admin.js'), array('jquery') );
		wp_register_script( $this->add_prefix('inline-edit-post'), $this->util->get_file_url('js/inline-edit-post.js'), array('jquery', 'inline-edit-post') );
	}
	
	function admin_add_styles() {
		//Define file properties
		$file_base = 'admin_styles';
		$handle = $this->add_prefix($file_base);
		$file_url = $this->util->get_file_url('css/' . $file_base . '.css');
		
		//Enqueue style
		wp_enqueue_style($handle,$file_url);
	}
	
	/**
	 * Adds external javascript files to admin header
	 * @return void
	 */
	function admin_add_scripts() {
		//Edit Posts
		if ( 'edit.php' == basename($_SERVER['SCRIPT_NAME']) ) {
			wp_enqueue_script( $this->add_prefix('inline-edit-post') );
		}
		//Default admin scripts
		wp_enqueue_script($this->add_prefix('script_admin'));
	}
	
	/**
	 * Adds quicktags to post edit form
	 * @return void
	 */
	function admin_post_quicktags() {
		$actions = array('edit-item', 'add');
		if ( in_array($this->util->get_action(), $actions) ) {
			wp_enqueue_script('cnr_quicktags', $this->util->get_file_url('js/cnr_quicktags.js'), array('quicktags'));
		}
	}
	
	function admin_mce_before_init($initArray) {
		$initArray['content_css'] = $this->util->get_file_url('mce/mce_styles.css') . '?vt=' . time(); //Dev: vt param stops browser from caching css
		return $initArray;
	}
	
	function admin_mce_external_plugins($plugin_array) {
		$plugin_array['cnr_inturl'] = $this->util->get_file_url('mce/plugins/inturl/editor_plugin.js');
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
	
	/*-** Content **-*/
	
	/*-** Request **-*/
	
	function post_section_highlight($output) {

		$class_current = 'current_page_item';
		$class_item_pre = 'page-item-';
		
		global $post;

		//Check if no pages are marked as 'current' yet
		//Also make sure current page is not home page (no links should be marked as current)
		if (is_singular() && $post && stripos($output, $class_current) === false) {
			//Get all parents of current post
			$parents = CNR_Post::get_parents($post, 'id');
			
			//Add current post to array
			$parents[] = $post->ID;
			//Reverse array so we start with the current post first
			$parents = array_reverse($parents);
			
			//Iterate through current posts's parents to highlight all parents found
			foreach ($parents as $page) {
				$class_item = $class_item_pre . $page;
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
	 * Gets children posts of specified page and stores them for later use
	 * This method hooks into 'the_posts' filter to retrieve child posts for any single page retrieved by WP
	 * @return array $posts Posts array (required by 'the_posts' filter) 
	 * @param array $posts Array of Posts (@see WP_QUERY)
	 */
	function post_children_get($posts) {
		//Global variables
		global $wp_query;
		
		//Reset post children collection
		$this->post_children_collection->init();
		
		//Stop here if post is not a page
		if ( ! is_page() || empty($posts) )
			return $posts;

		//Get children posts
		$post =& $posts[0];
		$this->post_children_collection =& CNR_Post::get_children($post);
		
		//Return posts (required by filter)
		return $posts;
	}
	
	/*-** Template **-*/
	
	/**
	 * Builds Page title for current Page/Content
	 * @return string Page title 
	 * @param array|string $args (optional) Parameters for customizing Page title
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
	 * @param array|string $args (optional) Parameters for customizing Page title
	 */
	function page_title($args = '') {
		echo $this->page_title_get($args);
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
	 * @param int $limit (optional) Maximum number of featured posts to retrieve (Default: -1 = All Featured Posts)
	 * @param int|bool $parent (optional) Section to get featured posts of (Defaults to current section).
	 * 	FALSE if latest featured posts should be retrieved regardless of section
	 * @todo Integrate into CNR_Post
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
			$args['post__not_in'] = CNR_Post::get_ids($featured);
			
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
	 * @todo integrate into CNR_Post_Query
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
	 * @todo integrate into CNR_Post_Query
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
	 * Determines whether a post is classified as a "feature" or not
	 * 
	 * @return bool TRUE if post is classified as a "feature", FALSE otherwise 
	 * @param int $post_id (optional) ID of the post.  Defaults to current post
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
	
	/**
	 * Generates feed links based on current page
	 * @return string Feed links
	 * @todo Move to CNR_Feeds
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
	 * @todo Move to CNR_Feeds
	 */
	function feed_the_links() {
		echo $this->feed_get_links();
	}
}

class CNR_Page_Groups extends CNR_Base {
	
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
		$this->__construct();
	}
	
	function __construct() {
		parent::__construct();
	}
	
	/*-** Initialization **-*/
	
	function register_hooks() {
		//Menus
		add_action('admin_menu', $this->m('admin_menu'));
	}
	
	function activate() {
		global $wpdb;
		//Create DB Tables (if not yet created)
		//Setup table definitions
		$tables = array(
						$this->get_db_prefix() . 'page_groups' => 'CREATE TABLE ' . $this->get_db_prefix() . 'page_groups (
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
	}
	
	/**
	 * Adds external javascript files to admin header
	 * @return void
	 */
	function admin_add_scripts() {
		//Page Groups
		if ( isset($_GET['page']) && 'page-groups' == $_GET['page'] ) {
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('jquery-ui-effects', $this->util->get_file_url('effects.core.js'));
			wp_enqueue_script($this->add_prefix('script-ns'), $this->util->get_file_url('jtree.js'));
			wp_enqueue_script($this->add_prefix('script'), $this->util->get_file_url('js/cnr.js'));
		}
	}
	
	/**
	 * Sets up Admin menus
	 * @return void
	 */
	function admin_menu() {
		global $menu, $submenu;
		
		//Page Groups
		add_pages_page('Page Groups', 'Groups', 8, 'page-groups', $this->m('admin_menu_page_groups'));
	}
	
	/**
	 * Content to display for Page Groups Admin Menu
	 * @return void
	 */
	function admin_menu_page_groups() {
		?>-->
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
	 * Populates $groups variable with all Page groups in DB
	 * @return mixed If a single group is specified, return the object, otherwise, return nothing
	 * @param object $group_id (optional) Retrieve a specific group by ID
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
		$db_table = $this->get_db_table();
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
	 */
	function get_db_table() {
		return $this->get_db_prefix() . $this->_db_table;
	}
}

/**
 * Class for managing a Page Group
 * @package Cornerstone
 */
class CNR_Page_Group extends CNR_Base {
	
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
	
	function CNR_Page_Group($id = 0, $name = '', $pages = '') {
		$this->__construct($id, $name, $pages);
	}
							
	/**
	 * Class Constructor
	 * @return void
	 * @param mixed $id (optional) May be ID of existing Group OR name of new group OR a Page Group row returned from DB
	 * @param string $name (optional) Name of Group
	 * @param string $pages (optional) Pages in Group (CSV)
	 */
	function __construct($id = 0, $name = '', $pages = '') {
		parent::__construct();
		//Get DB Values
		$grps = new CNR_Page_Groups();
		$this->db_table_groups = $grps->get_db_table();
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
		add_action('wp_ajax_pg_save', $this->m('save_ajax'));
		add_action('wp_ajax_pg_check_code', $this->m('check_code_ajax'));
		add_action('wp_ajax_pg_remove', $this->m('remove_ajax'));
		add_action('wp_ajax_pg_get_template', $this->m('build_template_ajax'));
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
	 * @param int $position (optional) Position in group to add the page (Default = -1 (End of list))
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
	 * @param bool $wrap_list (optional) Whether or not to wrap list items in a UL element (Default = true)
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
	 * @param bool $wrap_list (optional) Whether or not to wrap list items in a UL element (Default = true)
	 */
	function list_pages($wrap_list = true) {
		echo $this->build_pages_list($wrap_list);
	}
	
	/**
	 * Retrieves specified template from instance object
	 * @param string $temp (optional) Template to retrieve
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
		$name = sprintf($name_temp, $this->get_prefix('_'), $this->node_prefix, $this->id, $action);
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

?>