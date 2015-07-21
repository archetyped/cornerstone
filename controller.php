<?php

/**
 * @package Cornerstone
 */
class Cornerstone extends CNR_Base {
	/* Variables */
	
	/**
	 * Script files
	 * @var array
	 * @see CNR_Base::files
	 */
	var $scripts = array (
		'core'			=> array (
			'file'		=> 'js/lib.core.js',
			'deps'		=> 'jquery'
		),
		'admin'			=> array (
			'file'		=> 'js/lib.admin.js',
			'deps'		=> array('jquery', '[core]'),
			'context'	=> 'admin'
		),
		'inline_edit'	=> array (
			'file'		=> 'js/lib.posts.inline_edit.js',
			'deps'		=> array('inline-edit-post','jquery', '[posts]'),
			'context'	=> 'admin_page_edit'
		)
	);
	
	/**
	 * Style files
	 * @var array
	 * @see CNR_Base::files
	 */
	var $styles = array (
		'admin'			=> array (
			'file'		=> 'css/admin.css',
			'context'	=> 'admin'
		)
	);
	
	/* Featured Content variables */
	
	/**
	 * Category slug value that denotes a "featured" post
	 * @var string
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
	 * Media instance
	 * @var CNR_Media
	 */
	var $media = null;
	
	/**
	 * Post class instance
	 * @var CNR_Post
	 */
	var $post = null;
	
	/**
	 * Feeds instance
	 * @var CNR_Feeds
	 */
	var $feeds = null;
	
	/* Constructor */
							
	function __construct() {
		//Parent Constructor
		parent::__construct();
		
		//Init
		$this->init();
		
		//Special Queries
		$this->posts_featured = new CNR_Post_Query( array( 'category' => $this->posts_featured_get_cat_id(), 'numberposts' => 4 ) );
		$this->post_children_collection = new CNR_Post_Query();

		$this->post = new CNR_Post();
		$this->post->init();

		
		//Init class instances
		$this->structure = new CNR_Structure();
		$this->structure->init();
		
		$this->media = new CNR_Media();
		$this->media->init();
		
		$this->feeds = new CNR_Feeds();
		$this->feeds->init();
	}
	
	/* Init */
	
	/**
	 * Initialize environment
	 * Overrides parent method
	 * @see parent::init_env
	 * @return void
	 */
	function init_env() {
		//Localization
		$ldir = 'l10n';
		$lpath = $this->util->get_plugin_file_path($ldir, array(false, false));
		$lpath_abs = $this->util->get_file_path($ldir);
		if ( is_dir($lpath_abs) ) {
			load_plugin_textdomain($this->util->get_plugin_textdomain(), false,	$lpath);
		}
		
		//Context
		add_action(( is_admin() ) ? 'admin_head' : 'wp_head', $this->m('set_client_context'));
	}
	
	/* Methods */
	
	/*-** Request **-*/

	/**
	 * Output current context to client-side
	 * @uses `wp_head` action hook
	 * @uses `admin_head` action hook
	 * @return void
	 */
	function set_client_context() {
		$ctx = new stdClass();
		$ctx->context = $this->util->get_context();
		$this->util->extend_client_object($ctx, true);
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
	
	/*-** Featured Content **-*/
	
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
	 * Checks if post has content to display
	 * @param object $post (optional) Post object
	 * @return bool TRUE if post has content, FALSE otherwise
	 * @todo Review for deletion/relocation
	 */
	function post_has_content($post = null) {
		if ( !$this->util->check_post($post) )
			return false;
		if ( isset($post->post_content) && trim($post->post_content) != '' )
			return true;
		return false;
	}
}
