<?php

require_once 'class.base.php';
require_once 'class.content-types.php';
require_once 'class.posts.php';

/**
 * Core properties/methods for feed management
 * @package Cornerstone
 * @subpackage Feeds
 * @author SM
 */
class CNR_Feeds extends CNR_Base {
	
	/**
	 * Legacy Constructor
	 */
	function CNR_Feeds() {
		$this->__construct();
	}
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}
	
	function init() {
		$this->register_hooks();
	}
	
	/* Methods */
	
	/**
	 * Registers plugin hooks
	 */
	function register_hooks() {
		add_action('template_redirect', $this->m('feed_redirect'));
		add_filter('get_wp_title_rss', $this->m('feed_title'));
		add_filter('get_bloginfo_rss', $this->m('feed_description'), 10, 2);
		add_filter('the_title_rss', $this->m('feed_item_title'), 9);
		add_filter('the_content', $this->m('feed_item_description'));
		add_filter('the_excerpt_rss', $this->m('feed_item_description'));
	}

	/**
	 * Customizes feed to use for certain requests
	 * Example: Pages (Sections) should load the normal feed template (instead of the comments feed template)
	 */
	function feed_redirect() {
		if (is_page() && is_feed()) {
			//Turn off comments feed for sections
			global $wp_query;
			$wp_query->is_comment_feed = false;
			//Enable child content retrieval for section feeds
			$m = $this->m('feed_section');
			$feed = get_query_var('feed');
			if ('feed' == $feed)
				$feed = get_default_feed();
			$hook = ('rdf' == $feed) ? 'rdf_header' : $feed . "_head"; 
			add_action($hook, $m);
		}
	}
	
	/**
	 * Retrieves a section's child content for output in a feed 
	 */
	function feed_section() {
		if ( is_page() && is_feed() ) {
			global $wp_query;
			//Get children of current page
			$children =& CNR_Post::get_children();
			
			//Set retrieved posts to global $wp_query object
			$wp_query->posts = $children->posts;
			$wp_query->current_post = $children->current;
			$wp_query->post_count = $children->count;
		}
	}
	
	/**
	 * Sets feed title for sections
	 * @param string $title Title passed from 'get_wp_title_rss' hook
	 * @return string Updated title
	 */
	function feed_title($title) {
		$sep = '&#8250;';
		remove_filter('get_wp_title_rss', $this->m('feed_title'));
		$title = get_wp_title_rss($sep);
		add_filter('get_wp_title_rss', $this->m('feed_title'));
		return $title;
	}
	
	/**
	 * Adds description to feed
	 * Specifically, adds feed descriptions for sections
	 * 
	 * @param string $description Description passed from 'get_bloginfo_rss' hook
	 * @param string $show Specifies data to retrieve
	 * @see get_bloginfo() For the list of possible values to display.
	 * @return string Modified feed description
	 */
	function feed_description($description, $show = '') {
		global $post;
		if ( is_feed() && is_page() && 'description' == $show && strlen($post->post_content) > 0 ) {
			//Get section's own description (if exists)
			$description = convert_chars($post->post_content);
		}
		return $description;
	}
	
	/**
	 * Sets title for feed items
	 * Specifies what section an item is in if the feed is not specifically for that section
	 * 
	 * @param string $title Post title passed from 'the_title_rss' hook
	 * @return string Updated post title
	 */
	function feed_item_title($title) {
		if ( is_feed() && !is_page() ) {
			//Get item's section
			$section = CNR_Post::get_section('title');
			$title = "$section &#8250; $title"; //Section precedes post title
			//$title .= " [$section]"; //Section follows post title
		}
		return $title;
	}
	
	/**
	 * Inserts post subtitle into description field for feed items
	 * @param string $content Post content
	 * @return string Updated post content
	 */
	function feed_item_subtitle($content = '') {
		$subtitle_format = '<p><em>%s</em></p>';
		if ( is_feed() && in_the_loop() && cnr_has_data('subtitle') && ( $subtitle = cnr_get_data('subtitle') ) && strlen($subtitle) > 0 ) {
			$subtitle = sprintf($subtitle_format, $subtitle);
			$content = $subtitle . $content;
		}
		
		return $content;
	}
	
	/**
	 * Inserts post image into description field for feed items
	 * @param string $content Post content
	 * @return string Updated post content
	 */
	function feed_item_image($content = '') {
		$img_format = '<p>%s</p>';
		
		$field = 'image_header';
		if ( is_feed() && in_the_loop() && cnr_has_data($field) && ( $image = cnr_get_data($field) ) ) {
			$image = sprintf($img_format, $image);
			$content = $image . $content;
		}
		
		return $content;
	}
	
	/**
	 * Adds site source text for feed items
	 * Helpful in directing readers to original content source when feeds are scraped
	 * @param string $content Post content
	 * @return string Updated post content
	 */
	function feed_item_source($content) {
		/* Conditions
		 * > Request for feed
		 * > Looping through posts
		 * > Retrieving content (not excerpt)
		 */
		if ( is_feed() && in_the_loop() && ( 'get_the_excerpt' != current_filter() ) ) {
			$source = '<p><a href="' . get_permalink() . '"> ' . get_the_title() . '</a> was originally published on <a href="' . get_bloginfo('url') . '">' . get_bloginfo() . '</a> on ' . get_the_time('F j, Y h:ia') . '</p>';
			$content .= $source;
		}
		return $content;
	}
	
	/**
	 * Modifies post content/excerpt for feed items
	 * @param string $content Post content
	 * @return string Updated post content
	 */
	function feed_item_description($content = '') {
		global $post, $wp_current_filter;
		
		//Skip processing in the following conditions
		// > Request is not feed
		// > Current post requires a password
		// > Current filter is retrieving data for the excerpt and post has no actual excerpt (i.e. generating excerpt from post content)
		if ( !is_feed() || post_password_required() || ( isset($wp_current_filter['get_the_excerpt']) && strlen($post->excerpt) == '' ) )
			return $content;

		//Process post content
		//TODO Add option and admin menu to allow user to configure what fields to include in field content
		//TODO Add enclosures for fields with media content
		$content = $this->feed_item_image($content);
		$content = $this->feed_item_subtitle($content);
		if ( 'the_content' == current_filter() )
			$content = $this->feed_item_source($content);
		
		return $content;	
	}
}
?>