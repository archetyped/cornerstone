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
	 * @var array Holds posts
	 */
	var $posts;
	
	/**
	 * @var array IDs of posts in $posts
	 */
	var $post_ids;
	
	/**
	 * @var bool whether or not object contains posts
	 */
	var $has;
	
	/**
	 * @var int Index of post in current iteration
	 */
	var $current;
	
	/**
	 * @var int Total number of posts in object
	 */
	var $count;
	
	/**
	 * @var array Query arguments
	 */
	var $args;
	
	/**
	 * @var bool TRUE if posts have been fetched, FALSE otherwise
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
		$this->args = array();
		$this->fetched = false;
	}
	
	function set_arg( $prop, $value = null ) {
		if ( is_scalar($prop) ) { //Single argument (key/value) pair
			$this->args[$prop] = $value;
		} elseif ( is_array($prop) ) { //Multiple arguments
			$this->args = wp_parse_args($prop, $this->args);
		}
	}
	
	/**
	 * Gets posts matching parameters and stores them in global $wp_query variable
	 * 
	 * @param int $limit (optional) Maximum number of posts to retrieve (Default: -1 = All matching posts)
	 * @param array $args (optional) Additional arguments to use in post query
	 * @return array Retrieved posts
	 */
	function get( $limit = -1, $args = null ) {
		//Global variables
		global $wp_query;
		
		//Clear previously retrieved post data
		$this->unload();
		
		//Determine section
		//TODO abstract parent selection code
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
		
		//Set query args
		if ( !empty($args) )
			$this->args = wp_parse_args($args, $this->args);
		
		//Set post limit
		$limit = intval($limit);
		
		$this->set_arg('post_parent', $parent);
		$this->set_arg('numberposts', $limit);
		
		//Retrieve featured posts
		$_posts =& get_posts($this->args);
		
		//Save retrieved posts to array
		$this->load($_posts);
		$this->fetched = true;
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
		if ( !empty($posts) ) {
			$this->posts = $posts;
			$this->has = true;
			$this->count = count($this->posts);
			$this->fetched = true;
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
	 * Checks whether posts related to this object are available in the current context
	 * 
	 * If no accessible posts are found, current post (section) is set as global post variable
	 * 
	 * @param bool $fetch Whether posts should be fetched if they have not yet been retrieved
	 * @see 'the_posts' filter
	 * @see get_children()
	 * @return boolean TRUE if section contains children, FALSE otherwise
	 * Note: Will also return FALSE if section contains children, but all children have been previously accessed
	 */
	function has( $fetch = true ) {
		global $wp_query, $post;
		
		//Get posts if not yet fetched
		if ($fetch && !$this->fetched)
			$this->get(4);
		
		//Check if any posts on current page were retrieved
		//If posts are found, make sure there are more posts
		if ( $this->count > 0 && ( $this->current < $this->count - 1 ) ) {
			return true;
		}
		
		//Reset current post position if all posts have been processed
		$this->rewind();
		
		//If no posts were found (or the last post has been previously loaded),
		//load previous post back into global post variable
		if ( $wp_query->current_post >= 0 ) {
			$post = $wp_query->posts[$wp_query->current_post];
			setup_postdata($post);
		}
		return false;
	}
	
/**
	 * Loads next post into global $post variable for use in the loop
	 * Allows use of WP template tags
	 * @return void
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
	 * @return array $posts Posts array (required by 'the_posts' filter) 
	 * @param array $posts Array of Posts (@see WP_QUERY)
	 */
	function get_children($posts = '') {
		//Global variables
		global $wp_query, $wpdb;
		if ( empty($posts) )
			$posts = $wp_query->posts;
		
		//Stop here if post is not a page
		if (!is_page() || $posts != $wp_query->posts) {
			return $posts;
		}
		//Reset children post variables
		//TODO Implement in CNR_Post
		$this->post_children_init();
		
		//Get children posts of page
		if ($wp_query->posts) {
			$page = $wp_query->posts[0];
			$limit = (is_feed()) ? get_option('posts_per_rss') : get_option('posts_per_page');
			$offset = (is_paged()) ? ( (get_query_var('paged') - 1) * $limit ) : 0;
			//Set arguments to retrieve children posts of current page
			$c_args = array(
							'post_parent'	=> $page->ID,
							'numberposts'	=> $limit,
							'offset'		=> $offset
							);
			//Set State
			//TODO Implement in CNR_Post
			$this->request_children_start();
			//Get children posts
			$children =& get_posts($c_args);
			//Save any children posts in new variables in global wp_query object
			//TODO Implement in CNR_Post
			$this->post_children_save($children);
			//Set State;
			//TODO Implement in CNR_Post
			$this->request_children_end();
		}
		
		//Return posts (required by filter)
		return $posts;
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