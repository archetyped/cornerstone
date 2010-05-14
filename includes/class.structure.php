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
	 * Custom post permalink structure 
	 * @var string
	 */
	var $permalink_structure = '/%postpath%/%postname%/';
	
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
	}
	
	/* Methods */
	
	function init() {
		$this->register_hooks();
	}
	
	function register_hooks() {
		//Request
		add_filter('post_rewrite_rules', $this->m('post_rewrite_rules'));
		
		//Query
		add_action('pre_get_posts', $this->m('pre_get_posts'));
		
		//Permalink
		add_filter('post_link', $this->m('post_link'), 10, 2);
		add_filter('redirect_canonical', $this->m('post_link'), 10, 2);
		
		//Admin
		add_filter('admin_enqueue_scripts', $this->m('admin_enqueue_scripts'));
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
	 * @param object $post Post object
	 * @return string
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 * @global WP_Query $wp_query
	 */
	function post_link($permalink, $post = '') {
		global $wp_query;
		//Do not process further if $post has no name (e.g. drafts)
		if ( is_object($post) && ( !$this->util->property_exists($post, 'post_name') || empty($post->post_name) ) )
			return $permalink;
		
		if ( $this->using_post_permastruct() ) {
            
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
			$path = $this->get_path($post);
			
			//Set permalink (Add trailing slash)
			$permalink = $base . $path . $post->post_name . '/';
		}
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
}
?>