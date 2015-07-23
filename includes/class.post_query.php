<?php
/**
 * @package Cornerstone
 * @subpackage Posts
 * @author Archetyped
 * 
 * Represents a collection of posts in a query
 * Handles navigating through post collection, reporting status, etc.
 *
 */
class CNR_Post_Query extends CNR_Base {
	
	/*-** Variables **-*/
	
	var $scripts = array (
		'posts'		=> array (
			'file'		=> 'js/lib.posts.js',
			'deps'		=> '[core]',
			'context'	=> 'admin'
		),
	);
	
	/**
	 * Holds posts
	 * @var array
	 */
	var $posts;
	
	/**
	 * IDs of posts in $posts
	 * @var array
	 */
	var $post_ids;
	
	/**
	 * whether or not object contains posts
	 * @var bool
	 */
	var $has;
	
	/**
	 * Index of post in current iteration
	 * @var int
	 */
	var $current;
	
	/**
	 * Total number of posts in object
	 * @var int
	 */
	var $count;
	
	/**
	 * Total number of matching posts found in DB
	 * All found posts may not have been returned in current query though (paging, etc.) 
	 * @var int
	 */
	var $found = 0;
	
	/**
	 * Query arguments
	 * @var array
	 */
	var $args;
	
	/**
	 * Argument to be used during query to identify request
	 * Prefix added during init
	 * @see init()
	 * @var string
	 */
	var $arg_fetch = 'fetch';
	
	/**
	 * TRUE if posts have been fetched, FALSE otherwise
	 * @var bool
	 */
	var $fetched;
	
	function __construct( $args = null ) {
		parent::__construct();
		
		parent::init();
		//Init properties
		$this->init();
		
		//Set arguments
		if ( !empty($args) && is_array($args) ) {
			$this->args = wp_parse_args($args, $this->args);
		}
	}
	
	/**
	 * Initializes object properties with default values
	 * @return void
	 */
	function init() {
		$this->posts = array();
		$this->post_ids = array();
		$this->has = false;
		$this->current = -1;
		$this->count = 0;
		$this->found = 0;
		$this->arg_fetch = $this->add_prefix($this->arg_fetch);
		$this->args = array($this->arg_fetch => true, $this->get_prefix() => true);
		$this->fetched = false;
	}
	
	/**
	 * Set argument value
	 * @param string $arg Argument name
	 * @param mixed $value Argument value
	 */
	function set_arg($arg, $value = null) {
		if ( is_scalar($arg) ) { //Single argument (key/value) pair
			$this->args[$arg] = $value;
		} elseif ( is_array($arg) ) { //Multiple arguments
			$this->args = wp_parse_args($arg, $this->args);
		}
	}
	
	/**
	 * Retrieve argument value
	 * @param string $arg Argument name
	 * @return mixed Argument value
	 */
	function get_arg($arg) {
		return ( $this->arg_isset($arg) ) ? $this->args[$arg] : null;
	}
	
	/**
	 * Checks if an argument is set in the object
	 * @param string $arg Argument name
	 * @return bool TRUE if argument is set, FALSE otherwise
	 */
	function arg_isset($arg) {
		return ( isset($this->args[$arg]) );
	}
	
	/**
	 * Gets posts matching parameters and stores them in object
	 * 
	 * @param int $limit (optional) Maximum number of posts to retrieve (Default: -1 = All matching posts)
	 * @param array $args (optional) Additional arguments to use in post query
	 * @return array Retrieved posts
	 */
	function get( $limit = null, $args = null ) {
		//Global variables
		global $wp_query;
		
		//Clear previously retrieved post data
		$this->unload();
		
		//Determine section
		$p_arg = 'post_parent';
		if ( ! $this->arg_isset($p_arg) ) {
			$parent = null;
			if ( is_page() ) {
				$parent = $wp_query->get_queried_object_id();
			}
			if ( !! $parent )
				$this->set_arg($p_arg, $parent);
		}
		
		//Check if parent is valid post ID
		if ((int)$parent < 1) {
			//Get featured posts from all sections if no valid parent is set
			$parent = null;
		}
		
		//Set query args
		if ( !empty($args) )
			$this->args = wp_parse_args($args, $this->args);
		//Set post limit
		if ( is_numeric($limit) )
			$limit = intval($limit);
		if ( ! $limit && !$this->arg_isset('numberposts') )
			$limit = ( is_feed() ) ? get_option('posts_per_rss') : get_option('posts_per_page');
		if ( $limit > 0 )
			$this->set_arg('numberposts', $limit);
		
		//Set offset (pagination)
		if ( !is_feed() ) {
			$c_page = $wp_query->get('paged');
			$offset = ( $c_page > 0 ) ? $c_page - 1 : 0;
			$offset = $limit * $offset;
			$this->set_arg('offset', $offset);
		}
		
		//Retrieve posts
		$filter = 'found_posts';
		$f_callback = $this->m('set_found');
		$action = 'parse_query';
		$a_callback = $this->m('set_found_flag');
		
		//Add filter to populate found property during query
		add_filter($filter, $f_callback);
		add_action($action, $a_callback);
		//Get posts
		$posts = get_posts($this->args);
		//Remove filter after query has completed
		remove_action($action, $a_callback);
		remove_filter($filter, $f_callback);
		//Save retrieved posts to array
		$this->load($posts);
		//Return retrieved posts so that array may be manipulated further if desired
		return $this->posts;
	}
	
	/**
	 * Sets object properties with post data
	 * 
	 * @param array $posts Array of post objects
	 * @return void
	 */
	function load( $posts = null ) {
		$this->fetched = true;
		if ( !empty($posts) ) {
			$this->posts = $posts;
			$this->has = true;
			$this->count = count($this->posts);
		}
	}
	
	/**
	 * Resets object properties to allow for new data to be saved
	 * @return void
	 */
	function unload() {
		//Temporarily save properties that should persist
		$_args = $this->args;
		
		//Initialize object properties
		$this->init();
		
		//Restore persistent properties
		$this->args = $_args;
	}
	
	/**
	 * Sets number of found posts in object's query
	 * @link `found_posts` hook
	 * @see WP_Query::get_posts()
	 * @param int $num_found 
	 */
	function set_found($num_found) {
		$this->found = $num_found;
	}
	
	/**
	 * Modifies query parameters to allow `found_posts` hook to be called
	 * Unsets `no_found_rows` query parameter set in WP 3.1
	 * @see WP_Query::parse_query()
	 * @link `parse_query` action hook
	 * @param WP_Query $q Query instance object
	 */
	function set_found_flag(&$q) {
		if ( isset($q->query_vars[$this->arg_fetch]) ) {
			$q->query_vars['no_found_rows'] = false;
		}
	}
	
	/**
	 * Makes sure query was run prior
	 * @return void
	 */
	function confirm_fetched() {
		if ( !$this->fetched )
			$this->get();
	}

	/**
	 * Returns number of matching posts found in DB
	 * May not necessarily match number of posts contained in object (due to post limits, pagination, etc.)
	 * @return int Number of posts found
	 */
	function found() {
		$this->confirm_fetched();
		return $this->found;
	}
	
	/**
	 * Checks whether posts related to this object are available in the current context
	 * 
	 * If no accessible posts are found, current post (section) is set as global post variable
	 * 
	 * @see 'the_posts' filter
	 * @see get_children()
	 * 
	 * @param bool $fetch Whether posts should be fetched if they have not yet been retrieved
	 * @return boolean TRUE if section contains children, FALSE otherwise
	 * Note: Will also return FALSE if section contains children, but all children have been previously accessed
	 * 
	 * @global WP_Query $wp_query
	 * @global obj $post
	 */
	function has( $fetch = true ) {
		global $wp_query, $post, $more;
		
		$this->confirm_fetched();
		
		//Check if any posts on current page were retrieved
		//If posts are found, make sure there are more posts
		if ( $this->count > 0 && ( $this->current < $this->count - 1 ) ) {
			return true;
		}
		
		//Reset current post position if all posts have been processed
		$this->rewind();
		$wp_query->in_the_loop = false;
		//If no posts were found (or the last post has been previously loaded),
		//load previous post back into global post variable
		$i = ( $wp_query->current_post >= 0 ) ? $wp_query->current_post : 0;
		if ( count($wp_query->posts) ) {
			$post = $wp_query->posts[ $i ];	
			setup_postdata($post);
		}
		
		if ( is_single() || is_page() )
			$more = 1;
		return false;
	}
	
	/**
	 * Loads next post into global $post variable for use in the loop
	 * Allows use of WP template tags
	 * @return void
	 * 
	 * @global obj $post Post object
	 */
	function next() {
		global $post, $more, $wp_query;
		
		if ( $this->has() ) {
			$wp_query->in_the_loop = true;
			//Increment post position
			$this->current++;
			
			//Load post into global post variable
			$post = $this->posts[ $this->current ];
			
			setup_postdata($post);
			$more = 0;
		}
	}
	
	/**
	 * Resets position of current post
	 * Allows for multiple loops over $posts array
	 * @return void
	 */
	function rewind() {
		$this->current = -1;
	}
	
	/**
	 * Gets index of current post
	 * @return int Index position of current post
	 */
	function current() {
		return $this->current;
	}
	
	/**
	 * Returns number of posts in object
	 * @return int number of posts
	 */
	function count() {
		$this->confirm_fetched();
		return $this->count;
	}
	
	/**
	 * Gets the number of pages needed to list all found posts
	 * @return int Total number of pages
	 */
	function max_num_pages() {
		$this->confirm_fetched();
		$posts_per_page = $this->get_arg('numberposts');
		if ( ! $posts_per_page )
			$posts_per_page = get_option('posts_per_page');
		return ceil( $this->found / $posts_per_page );
	}
	
	/**
	 * Checks if current post is the first post
	 * @return bool TRUE if current post is the first post in array, FALSE otherwise
	 */
	function is_first() {
		$this->confirm_fetched();
		return ( 0 == $this->current() );
	}
	
	/**
	 * Checks if current featured post is the last item in the post array
	 * @return bool TRUE if item is the last featured item, FALSE otherwise
	 */
	function is_last() {
		$this->confirm_fetched();
		return ($this->current == $this->count - 1) ? true : false;
	}
	
	/**
	 * @param int $post (optional) ID of post to check for existence in the object's posts array (uses global $post object if no value passed)
	 * @return bool TRUE if post is in posts array
	 */
	function contains( $post = null ) {
		$this->confirm_fetched();
		//Use argument value if it is an integer
		if ( is_numeric($post) && intval($post) > 0 ) {
			//Cast to object and set ID property (for later use)
			$post = (object) $post;
			$post->ID = $post->scalar;
		}
		//Otherwise check if argument is valid post
		elseif ( !$this->util->check_post($post) ) {
			return false;
		}
		
		//Check for existence of post ID in posts array
		return in_array($post->ID, $this->get_ids());
	}
	
	/**
	 * Retrieve IDs of all retrieved posts
	 */
	function get_ids() {
		$this->confirm_fetched();
		
		if ( $this->has && empty($this->post_ids) ) {
			//Build array of post ids in array
			foreach ($this->posts as $post) {
				$this->post_ids[] = $post->ID;
			}
		}
		
		return $this->post_ids;
	}
}