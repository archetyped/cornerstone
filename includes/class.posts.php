<?php

include_once 'class.base.php';

/**
 * @package Cornerstone
 * @author SM
 *
 */
class CNR_Posts extends CNR_Base {
	
	/*-** Variables **-*/
	
	/**
	 * @var array Holds posts
	 */
	var $posts;
	
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
	
	function CNR_Posts( $args = null ) {
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
	 * @param int $limit[optional] Maximum number of posts to retrieve (Default: -1 = All matching posts)
	 * @param array $args[optional] Additional arguments to use in post query
	 * @return array Retrieved posts
	 */
	function get( $limit = -1, $args = null ) {
		//Global variables
		global $wp_query;
		
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
		
		//Check to make sure the correct number of posts are returned
		/*
		if ( $limit && ( count($_posts) < $limit ) ) {
			//Set arguments to fetch additional (non-feature) posts to meet limit
			
			//Remove category argument
			unset($args['category']);
			
			//Adjust Limit
			$args['numberposts'] = $limit - count($featured);
			
			//Exclude posts already fetched
			$args['post__not_in'] = $this->posts_get_ids($featured);
			
			//Get more posts
			$_additional =& get_posts($args);
			$_posts = array_merge($_posts, $_additional);
		}
		*/
		
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
	function load($posts = null) {
		if ( !empty($posts) ) {
			$this->posts = $posts;
			$this->has = true;
			$this->count = count($this->posts);
			$this->fetched = true;
		} else {
			$_args = $this->args;
			$this->init();
			$this->args = $_args;
		}
	}
	
	/**
	 * Checks whether posts related to this object are available in the current context
	 * 
	 * If no accessible featured posts are found, current post (section) is set as global post variable
	 * 
	 * @see 'the_posts' filter
	 * @see get_children()
	 * @return boolean TRUE if section contains children, FALSE otherwise
	 * Note: Will also return FALSE if section contains children, but all children have been previously accessed
	 */
	function has( $fetch = true ) {
		global $wp_query, $post;
		
		//Get posts if not yet fetched
		if (!$this->fetched)
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
}

?>