<?php

/**
 * Core properties/methods for feed management
 * @package Cornerstone
 * @subpackage Feeds
 * @author Archetyped
 * @uses CNR_Post
 */
class CNR_Feeds extends CNR_Base {
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}
	
	/* Methods */
	
	/**
	 * Registers plugin hooks
	 */
	function register_hooks() {
		// add_action('template_redirect', $this->m('feed_redirect'));
		// add_filter('get_wp_title_rss', $this->m('get_title'));
		// add_filter('get_bloginfo_rss', $this->m('get_description'), 10, 2);
		// add_filter('the_title_rss', $this->m('get_item_title'), 9);
		add_filter('the_content_feed', $this->m('get_item_content'));
		add_filter('the_excerpt_rss', $this->m('get_item_content'));

		//Feed links (header)
		/*
		if ( $p = has_action('wp_head', 'feed_links_extra') )
			remove_action('wp_head', 'feed_links_extra', $p);
		add_action('wp_head', $this->m('feed_links_extra'));
		*/
	}
	
	/**
	 * Customize feed links (header) for sections
	 * @uses feed_links_extra() To generate additional feed links
	 */
	function feed_links_extra() {
		$args = array();
		if ( is_page() ) {
			//Add custom feed title for section
			$args['singletitle'] = __('%1$s %2$s %3$s Feed');
			//Make sure feed is processed
			$cb = create_function('', 'return true;');
			$tag = 'pings_open';
			$priority = 99;
			add_filter($tag, $cb, $priority);
		}
		feed_links_extra($args);

		//Remove filter after section feed link has been generated
		if ( is_page() ) {
			remove_filter($tag, $cb, $priority);
		}
	}
	
	/**
	 * Customizes feed to use for certain requests
	 * Example: Pages (Sections) should load the normal feed template (instead of the comments feed template)
	 */
	function feed_redirect() {
		if ( is_page() && is_feed() ) {
			//Turn off comments feed for sections
			global $wp_query;
			$wp_query->is_comment_feed = false;
			//Enable child content retrieval for section feeds
			$m = $this->m('get_children');
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
	static function get_children() {
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
	function get_title($title) {
		$sep = '&#8250;';
		remove_filter('get_wp_title_rss', $this->m('get_title'));
		$title = get_wp_title_rss($sep);
		add_filter('get_wp_title_rss', $this->m('get_title'));
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
	function get_description($description, $show = '') {
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
	function get_item_title($title) {
		if ( is_feed() && !is_page() ) {
			//Get item's section
			$section = CNR_Post::get_section(null, 'post_title');
			$title = "$section &#8250; $title"; //Section precedes post title
		}
		return $title;
	}
	
	/**
	 * Modifies post content/excerpt for feed items
	 * @param string $content Post content
	 * @return string Updated post content
	 */
	function get_item_content($content = '') {
		global $post;
		
		//Skip processing in the following scenarios
		// > Request is not feed
		// > Current post requires a password
		if ( !is_feed() || post_password_required() ) {
			return $content;
		}

		//Add post thumbnail
		if ( has_post_thumbnail() ) {
			$content = get_the_post_thumbnail(null, 'large') . $content;
		}
		
		return $content;	
	}
	
	/**
	 * Generates feed links based on current request
	 * @return string Feed links (HTML)
	 */
	function get_links() {
		$text = array();
		$links = array();
		$link_template = '<a class="link_feed" rel="alternate" href="%1$s" title="%3$s">%2$s</a>';
		//Page specific feeds
		if ( is_page() || is_single() ) {
			global $post, $wpdb;
			$is_p = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE ID = $post->ID");
			if ( $is_p ) {
				$href = get_post_comments_feed_link($post->ID);
				if ( is_page() )
					$title = __('Subscribe to this Section');
				else
					$title = __('Subscribe to Comments');
				$links[$href] = $title;
			}
		} elseif ( is_search() ) {
			$links[get_search_feed_link()] = __('Subscribe to this Search');
		} elseif ( is_tag() ) {
			$links[get_tag_feed_link( get_query_var('tag_id') )] = __('Subscribe to this Tag');	
		} elseif ( is_category() ) {
			$links[get_category_feed_link( get_query_var('cat') )] = __('Subscribe to this Topic');
		}
		
		//Sitewide feed
		$title = ( !empty($links) ) ? __('Subscribe to All updates') : __('Subscribe to Updates');
		$links[get_feed_link()] = $title;
		foreach ($links as $href => $title) {
			$text[] = sprintf( $link_template, esc_attr( $href ), $title, esc_attr( $title ) );
		}
		$text = implode(' or ', $text);
		return $text;
	}
	
	/**
	 * Outputs feed links based on current page
	 * @see get_links()
	 * @return void
	 */
	function the_links() {
		echo $this->get_links();
	}
}