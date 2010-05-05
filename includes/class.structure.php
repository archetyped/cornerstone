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
	 * Variable to use in custom queries to redirect requests
	 * Value will be prefixed with class prefix (e.g. 'cnr_postname')
	 * @var string
	 */
	var $query_var = 'postname';
	
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
		add_filter('rewrite_rules_array', $this->m('rewrite_rules_array'));
		add_filter('query_vars', $this->m('query_vars'));
		
		//Query
		add_action('pre_get_posts', $this->m('pre_get_posts'));
		
		//Permalink
		add_filter('post_link', $this->m('post_link'), 10, 2);
		add_filter('redirect_canonical', $this->m('post_link'), 10, 2);
		
		//Admin
		add_filter('admin_enqueue_scripts', $this->m('admin_enqueue_scripts'));
	}
	
	function get_query_var() {
		static $qvar = '';
		if ( empty($qvar) )
			$qvar = $this->add_prefix($this->query_var);
		return $qvar;
	}
	
	/**
	 * Returns path to post based on site structure
	 * @return string Path to post enclosed in '/' (forward slashes)
	 * Example: /path/to/post/
	 * @param object $post Post object
	 */
	function get_path($post) {
		//Get post parents
		$parents = CNR_Posts::get_parents($post);
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
		global $wp_rewrite, $wp_query;
		//Do not process further if $post has no name (e.g. drafts)
		if ( is_object($post) && ( !$this->util->property_exists($post, 'post_name') || empty($post->post_name) ) )
			return $permalink;
		
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
			$path = $this->get_path($post);
			
			//Set permalink (Add trailing slash)
			$permalink = $base . $path . $post->post_name . '/';
		}
		return $permalink;
	}
	
	/**
	 * Resets certain query properties before post retrieval
	 * @return void
	 * @param WP_Query $q Reference to global <tt>$wp_query</tt> variable
	 */
	function pre_get_posts($q) {
		$qvar = $this->get_query_var();
		$qv =& $q->query_vars;
		//Stop processing if custom query variable is not present in current query
		if ( !isset($qv[$qvar]) ) {
			return;
		}
		
		//$this->debug->print_message('Custom Query variable', $qvar, 'Query Vars', $qv);
		
		global $wpdb;
		
		$qval = $qv[$qvar];
		//Get last segment
		$slug = array_reverse(explode('/', $qval));
		if ( is_array($slug) && !empty($slug) )
			$slug = $slug[0];
		else
			return;
		
		//Determine if query is for page or post
		$type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM $wpdb->posts WHERE post_name = %s", $slug));
		if ( empty($type) )
			return;
			
		if ( 'page' == $type ) {
			$new_var = 'pagename';
		} else {
			$new_var = 'name';
			$qval = $slug;
		}
		
		//Set new query var
		$qv[$new_var] = $qval;
		unset($qv[$qvar]);
		
		//Reparse query variables
		$q->parse_query($qv);
	}
	
	/**
	 * Adds custom query variables so that it will be passed to query
	 * @param array $qv WP-built list of query variables
	 * @return array Array of query variables
	 */
	function query_vars($qv) {
		//Add custom query var to query vars array
		$qv[] = $this->get_query_var();
		return $qv;
	}
	
	/**
	 * Modifies post rewrite rules when using custom permalink structure
	 * Removes all post rewrite rules since we are modifying page rewrite rules to process the request
	 * @param array $r Post rewrite rules from WP_Rewrite::rewrite_rules
	 * @return array Modified post rewrite rules
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 */
	function post_rewrite_rules($r) {
		global $wp_rewrite;
		if ( $wp_rewrite->permalink_structure == $this->permalink_structure )
			$r = array();
		return $r;
	}
	
	/**
	 * Adds custom URL rewrite rules
	 * @param array $rewrite_rules_array Original rewrite rules array generated by WP
	 * @return array Modified Rewrite Rules array
	 */
	function rewrite_rules_array($rewrite_rules_array) {
		$r =& $rewrite_rules_array;
		
		$wildcard = '(.+?)';
		$var_page = 'pagename';
		$var_new = $this->get_query_var();
		$qv_page = "$var_page=$matches[1]";
		$qv_new = str_replace($var_page, $var_new, $qv_page);
		
		//Replace all page rules with custom redirect so that we can process request before WP
		foreach ( $r as $regex => $redirect ) {
			if ( strpos($regex, $wildcard) === 0 && strpos($redirect, $qv_page) !== false ) {
				$r[$regex] = str_replace($qv_page, $qv_new, $r[$regex]);
			}
		}

		//Return rules array
		return $r;
	}
	
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