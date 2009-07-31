<?php
/* 
Plugin Name: Cornerstone
Plugin URI:
Description: CMS Plugin for WP
Version: 0
Author: SM
Author URI: http://archetyped.com
*/

/**
 * @package Cornerstone 
 */
require_once('model.php');
$cnr = new Cornerstone(__FILE__);

/* Page Level */

/**
 * Outputs formatted page title for current page
 * @return void
 * @param string|array Arguments for formatting page title
 * May be an associative array or querystring-style list of arguments
 */
function cnr_page_title($args = '') {
	global $cnr;
	$cnr->page_title($args);
}

/**
 * Checks if current post/page has children elements
 * 
 * @return bool TRUE if post/page has children, FALSE otherwise
 */
function cnr_has_children() {
	global $cnr;
	return $cnr->post_children_has();
}

/**
 * Prepares next child post for output to page
 * 
 * @return void 
 */
function cnr_next_child() {
	global $cnr;
	$cnr->post_children_get_next();
}

function cnr_children_count() {
	global $cnr;
	return $cnr->post_children_count();	
}

function cnr_is_first_child() {
	global $cnr;
	return $cnr->post_children_is_first();
}

function cnr_is_last_child() {
	global $cnr;
	return $cnr->post_children_is_last();
}

/*-** Featured Content **-*/

/**
 * Retrieves featured posts
 * @return array Featured posts matching criteria 
 * @param int $limit[optional] Maximum number of featured posts to retrieve
 * @param int|bool $parent[optional] Section to get featured posts of (Defaults to current section).  FALSE if latest featured posts should be retrieved regardless of section
 */
function cnr_get_featured($limit = 0, $parent = null) {
	global $cnr;
	return $cnr->posts_featured_get($limit, $parent);
}

function cnr_has_featured() {
	global $cnr;
	return $cnr->posts_featured_has();
}

function cnr_next_featured() {
	global $cnr;
	return $cnr->posts_featured_next();
	
}

function cnr_current_featured() {
	global $cnr;
	return $cnr->posts_featured_current();
}

function cnr_is_first_featured() {
	global $cnr;
	return $cnr->posts_featured_is_first();
}

function cnr_featured_count() {
	global $cnr;
	return $cnr->posts_featured_count;
}

/**
 * Checks if Post is "featured"
 * 
 * @return bool TRUE if post is featured, FALSE otherwise 
 * @param object $post[optional] Post to check.  Defaults to current post
 */
function cnr_is_featured($post = null) {
	global $cnr;
	return $cnr->post_is_featured($post);
}

/*-** Post Content **-*/

/**
 * Checks if post has content to display
 * @param object $post [optional] Post object
 * @return bool TRUE if post has content, FALSE otherwise
 */
function cnr_has_content($post = null) {
	global $cnr;
	return $cnr->post_has_content($post);
}

	/* Images */

function cnr_is_lightbox_enabled() {
	global $cnr;
	return $cnr->lightbox_is_enabled();
}

function cnr_lightbox_initialize() {
	global $cnr;
	$cnr->lightbox_initialize();
}

/**
 * Gets Image associated with post
 * 
 * @return array|bool Source image data (url, width, height), or false if no image is available
 * @param int $post_id[optional] Post ID. Defaults to current post
 */
function cnr_get_post_image_src($post_id = 0) {
	global $cnr;
	return $cnr->post_get_image_src($post_id);
}

/**
 * Prints the post's subtitle text
 */
function cnr_the_subtitle() {
	global $cnr;
	$cnr->post_the_subtitle();
}

function cnr_has_image($image_type = 'header') {
	global $cnr;
	return $cnr->post_has_image(null, $image_type);
}

function cnr_get_image($image_type = 'header') {
	global $cnr;
	return $cnr->post_get_image(null, $image_type);
}

function cnr_the_image($image_type = 'header') {
	global $cnr;
	$cnr->post_the_image(null, $image_type);
}

function cnr_get_attachments($post = null) {
	global $cnr;
	return $cnr->post_get_attachments($post);
}

function cnr_get_filesize($post = null, $formatted = true) {
	global $cnr;
	return $cnr->post_get_attachment_filesize($post, $formatted);
}

function cnr_the_filesize($post = null, $formatted = true) {
	global $cnr;
	$cnr->post_the_attachment_filesize($post, $formatted);
}

	/* Section */
	
/**
 * Retrieves the post's section data 
 * @return string post's section data 
 * @param string $type[optional] Type of data to return (Default: ID)
 * 	Possible values:
 * 	ID		Returns the ID of the section
 * 	name	Returns the name of the section
 */
function cnr_get_the_section($type = 'ID') {
	global $cnr;
	return $cnr->post_get_section($type);
}

/**
 * Prints the post's section data
 * @param string $type[optional] Type of data to return (Default: ID)
 * @see cnr_get_the_section()
 */
function cnr_the_section($type = 'ID') {
	global $cnr;
	$cnr->post_the_section($type);
}

	/* Parts (Sections within a Post) */

/**
 * Gets an array of parts within post
 * Parts are sorted by their order in the post
 * @return array Headings in post
 */
function cnr_get_the_parts() {
	global $cnr;
	return $cnr->post_get_parts();
}

/**
 * Outputs links to parts within post
 * @return string List of links to headings within post
 */
function cnr_the_parts() {
	global $cnr;
	$cnr->post_the_parts();
}

/**
 * Checks whether post contains any parts
 * @return bool TRUE if post contains 1 or more headings
 */
function cnr_have_parts() {
	global $cnr;
	return $cnr->post_have_parts();
}

/**
 * Outputs HTML list of pages in specified page group
 * 
 * @return void
 * @param string $group Code (unique name) of page group to display
 * @param bool $wrap_list[optional] whether or not to wrap list in <ul></ul> tags.
 * 	Useful if page group list items are meant to be part of a larger list
 */
function cnr_list_page_group($group, $wrap_list = true) {
	$group = new CNR_Page_Group($group);
	$group->list_pages($wrap_list);
}

/*-** Debug functions **-*/

function add_line($text = '') {
//	echo $text . "<br />";
}

function pre_dump($title = '', $obj = '') {
	if (func_num_args() == 1) {
		$obj = $title;
		$title = 'unknown variable';
	}
	echo '<pre class="debug">Dumping: ' . $title . '<br />';
	var_dump($obj);
	echo '</pre>';
}



?>
