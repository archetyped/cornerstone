<?php

include_once 'class.base.php';

/**
 * @package Cornerstone
 * @subpackage Posts
 * @author SM
 * 
 * Represents a collection of posts in a query
 * Handles navigating through post collection, reporting status, etc.
 *
 */
class CNR_Post_Query extends CNR_Base {
	
	/*-** Variables **-*/
	
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
	 * TRUE if posts have been fetched, FALSE otherwise
	 * @var bool
	 */
	var $fetched;
	
	function CNR_Post_Query( $args = null ) {
		$this->__construct($args);
	}
	
	function __construct( $args = null ) {
		parent::__construct();

		//Init properties
		$this->init();
		
		//Set arguments
		if ( !empty($args) && is_array($args) ) {
			$this->args =& $args;
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
		$this->args = array();
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
			if ( count($wp_query->posts) == 1 ) {
				$parent = $wp_query->posts[0]->ID;
			}
			elseif ( $wp_query->current_post != -1 && isset($GLOBALS['post']) && is_object($GLOBALS['post']) && $this->util->property_exists($GLOBALS['post'], 'ID') ) {
				$parent = $GLOBALS['post']->ID;
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
		if ( is_numeric($limit) ) {
			$limit = intval($limit);
			if ( ! $limit ) {
				$limit = ( is_feed() ) ? get_option('posts_per_rss') : get_option('posts_per_page');
			}
			if ( $limit > 0 )
				$this->set_arg('numberposts', $limit);
		}
		
		//Retrieve featured posts
		$callback = $this->m('set_found');
		$filter = 'found_posts';
		//Add filter to populate found property during query
		add_filter($filter, $callback);
		//Remove filter after query has completed
		$posts =& get_posts($this->args);
		remove_filter($filter, $callback);
		
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
	 * @param int $num_found 
	 */
	function set_found($num_found) {
		$this->found = $num_found;
	}
	
	/**
	 * Returns number of matching posts found in DB
	 * May not necessarily match number of posts contained in object (due to post limits, pagination, etc.)
	 * @return int Number of posts found
	 */
	function found() {
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
		global $wp_query, $post;
		
		//Get posts if not yet fetched
		if ( $fetch && !$this->fetched ) {
			$this->get();
		}
		
		//Check if any posts on current page were retrieved
		//If posts are found, make sure there are more posts
		if ( $this->count > 0 && ( $this->current < $this->count - 1 ) ) {
			return true;
		}
		
		//Reset current post position if all posts have been processed
		$this->rewind();
		
		//If no posts were found (or the last post has been previously loaded),
		//load previous post back into global post variable
		$i = ( $wp_query->current_post >= 0 ) ? $wp_query->current_post : 0;
		if ( count($wp_query->posts) ) {
			$post = $wp_query->posts[ $i ];	
			setup_postdata($post);
		}
		
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
		global $post;
		
		if ( $this->has() ) {
			//Increment post position
			$this->current++;
			
			//Load post into global post variable
			$post = $this->posts[ $this->current ];
			
			setup_postdata($post);
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
		return $this->count;
	}
	
	/**
	 * Gets the number of pages needed to list all found posts
	 * @return int Total number of pages
	 */
	function max_num_pages() {
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
		return ( 0 == $this->current() );
	}
	
	/**
	 * Checks if current featured post is the last item in the post array
	 * @return bool TRUE if item is the last featured item, FALSE otherwise
	 */
	function is_last() {
		return ($this->current == $this->count - 1) ? true : false;
	}
	
	/**
	 * @param int $post (optional) ID of post to check for existence in the object's posts array (uses global $post object if no value passed)
	 * @return bool TRUE if post is in posts array
	 */
	function contains( $post = null ) {
		//TODO Validate $post_id
		if ( !$this->util->check_post($post) ) {
			return false;
		}
		
		return in_array($post->ID, $this->get_ids());
	}
	
	/**
	 * Retrieve IDs of all retrieved posts
	 */
	function get_ids() {
		if ( $this->has && empty($this->post_ids) ) {
			//Build array of post ids in array
			foreach ($this->posts as $post) {
				$this->post_ids[] = $post->ID;
			}
		}
		
		return $this->post_ids;
	}
	
}

/**
 * @package Cornerstone
 * @subpackage Posts
 * @author SM
 *
 */
class CNR_Post extends CNR_Base {
	
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
	function get_parents($post, $prop = '', $depth = '') {
		$parents = get_post_ancestors($post = get_post($post, OBJECT, ''));
		if ( is_object($post) && !empty($parents) && ('id' != strtolower(trim($prop))) ) {
			//Retrieve post data for parents if full data or property other than post ID is required
			$args = array(
						'include'		=> implode(',', $parents),
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
		$children =& new CNR_Post_Query();
		if ( empty($post) && count($wp_query->posts) )
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
	 * @return string post's section data 
	 * @param string $type (optional) Type of data to return (Default: ID)
	 * 	Possible values:
	 * 	ID		Returns the ID of the section
	 * 	name	Returns the name of the section
	 */
	function get_section($type = 'ID') {
		global $post;
		$retval = $post->post_parent;
		
		if ('title' == $type) {
			$retval = get_post_field('post_title', $post->post_parent);
		}
		return $retval;
	}
	
	/**
	 * Prints the post's section data
	 * @param string $type (optional) Type of data to return (Default: ID)
	 * @see cnr_get_the_section()
	 */
	function the_section($type = 'ID') {
		echo CNR_Post::get_section($type);
	}
}

?>