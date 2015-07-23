<?php
/**
 * @package Cornerstone
 * @subpackage Posts
 * @author Archetyped
 *
 */
class CNR_Post extends CNR_Base {
	
	/*-** Properties **-*/
	
	/**
	 * Script files
	 * @see CNR_Base::client_files
	 * @var array
	 */
	var $scripts = array (
		'posts'		=> array (
			'file'		=> 'js/lib.posts.js',
			'deps'		=> '[core]',
			'context'	=> 'admin'
		),
	);
	
	/**
	 * Default title separator
	 * @var string
	 */
	var $title_sep = '&lsaquo;';
	
	/*-** Initialization **-*/
	
	function register_hooks() {
		parent::register_hooks();
		
		//Template
		// add_filter('wp_title', $this->m('page_title'), 11, 3);
		
		//Admin
		add_action('admin_head', $this->m('admin_set_title'), 11);
	}
	
	/**
	 * Gets entire parent tree of post as an array
	 * 
	 * Array order is from top level to immediate post parent
	 * @static
	 * @param object $post Post to get path for
	 * @param string $prop Property to retrieve from parents.  If specified, array will contain only this property from parents
	 * @param $depth Unused
	 * @return array of Post Objects/Properties
	 */
	static function get_parents($post, $prop = '', $depth = '') {
		$parents = get_post_ancestors($post = get_post($post, OBJECT, ''));
		if ( is_object($post) && !empty($parents) && ('id' != strtolower(trim($prop))) ) {
			//Retrieve post data for parents if full data or property other than post ID is required
			$args = array(
						'include'		=> $parents,
						'post_type'		=> 'any',
						);
			$ancestors = get_posts($args);
			
			//Sort array in ancestor order
			$temp_parents = array();
			foreach ($ancestors as $ancestor) {
				//Get index of ancestor
				$i = array_search($ancestor->ID, $parents);
				if ( false === $i )
					continue;
				//Insert post at index
				$temp_parents[$i] = $ancestor;
			}
			
			if ( !empty($temp_parents) )
				$parents = $temp_parents;
		}
		//Reverse Array (to put top level parent at beginning of array)
		$parents = array_reverse($parents);
		return $parents;
	}
	
	/**
	 * Get the IDs of a collection of posts
	 * @return array IDs of Posts passed to function
	 * @param array $posts Array of Post objects 
	 */
	function get_ids($posts) {
		$callback = create_function('$post', 'return $post->ID;');
		$arr_ids = array_map($callback, $posts);
		return $arr_ids;
	}
	
	/*-** Children **-*/
	
	/**
	 * Gets children posts of specified page and stores them for later use
	 * This method hooks into 'the_posts' filter to retrieve child posts for any single page retrieved by WP
	 * @param int|object $post ID or Object of Post to get children for
	 * @return CNR_Post_Query $posts Posts array (required by 'the_posts' filter)
	 * 
	 * @global WP_Query $wp_query
	 */
	function &get_children($post = null) {
		//Global variables
		global $wp_query;
		$children = new CNR_Post_Query();
		if ( empty($post) && !empty($wp_query->posts) )
			$post = $wp_query->posts[0];
		
		if ( is_object($post) )
			$post = $post->ID;
		if ( is_numeric($post) )
			$post = (int) $post;
		else
			return $children; 
				
		//Get children posts of page
		if ( $post ) {
			//Set arguments to retrieve children posts of current page
			$limit = ( is_feed() ) ? get_option('posts_per_rss') : get_option('posts_per_page');
			$offset = ( is_paged() ) ? ( (get_query_var('paged') - 1) * $limit ) : 0;
			$c_args = array(
							'post_parent'	=> $post,
							'numberposts'	=> $limit,
							'offset'		=> $offset
							);
			
			//Create post query object
			$children->set_arg($c_args);
			
			//Get children posts
			$children->get();
		}
		
		return $children;
	}
	
	/*-** Post Metadata **-*/
	
	/**
	 * Retrieves the post's section data
	 * @param string $data (optional) Section data to return (Default: full section object)
	 * Possible values:
	 *  NULL		Full section post object
	 *	Column name	Post column data (if exists)
	 *
	 * @return mixed post's section data (Default: ID value) 
	 */
	static function get_section($post = null, $data = null) {
		$p = get_post($post);
		$retval = 0;
		if ( is_object($p) && isset($p->post_parent) )
			$retval = intval($p->post_parent);
		
		//Get specified section data for posts with valid parents
		if ( $retval > 0 ) {
			if ( !empty($data) ) {
				$retval = get_post_field($data, $retval);
			} else {
				$retval = get_post($retval);
			}
		}
		
		return $retval;
	}
	
	/**
	 * Prints the post's section data
	 * @uses CNR_Post::get_section()
	 * @param string $type (optional) Type of data to return (Default: ID)
	 */
	static function the_section($post = null, $data = 'ID') {
		if ( empty($data) )
			$data = 'ID';
		echo CNR_Post::get_section($post, $data);
	}
	
	/*-** Admin **-*/
	
	function admin_set_title() {
		global $post;
		
		if ( !$post )
			return false;
		
		$obj = new stdClass();
		//Section title
		$sec = $this->get_section($post);
		if ( $sec )
			$obj->item_section = get_the_title($sec);
		//Separator
		$obj->title_sep = $this->page_title_get_sep();
		$this->util->extend_client_object('posts', $obj, true);
	}

	function page_title_get_sep($pad = true) {
		$sep = $this->title_sep;
		if ( $pad )
			$sep = ' ' . trim($sep) . ' ';
		return $sep;
	}
	
	/*-** Template **-*/
	
	/**
	 * Builds page title for current request
	 * Adds subtitle to title
	 * Filter called by `wp_title` hook
	 * @param $title
	 * @param $sep
	 * @param $seplocation
	 * @return string Title text
	 */
	function page_title_get($title, $sep = '', $seplocation = '') {
		global $post;
		
		$sep = $this->page_title_get_sep();
		
		if ( is_single() ) {
			//Append section name to post title
			$ptitle = get_the_title();
			$ptitle_pos = ( $ptitle ) ? strpos($title, $ptitle) : false;
			if ( $ptitle_pos !== false ) {
				//Get section
				if ( ( $sec = $this->get_section($post) ) ) {
					//Append section name to post title only once
					$title = substr_replace($ptitle, $ptitle . $sep . get_the_title($sec), $ptitle_pos, strlen($ptitle)) . substr($title, strlen($ptitle));
				}
			}
		}
		
		//Return new title
		return $title;
	}
	
	/**
	 * Builds page title for current request
	 * Filter called by `wp_title` hook
	 * @param $title
	 * @param $sep
	 * @param $seplocation
	 * @return string Title text
	 * @uses CNR::page_title_get()
	 */
	function page_title($title, $sep = '', $seplocation = '') {
		return $this->page_title_get($title, $sep, $seplocation);
	}
}