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
$cnr =& new Cornerstone();

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
	return $cnr->post_children_collection->has();
}

/**
 * Prepares next child post for output to page
 * 
 * @return void 
 */
function cnr_next_child() {
	global $cnr;
	$cnr->post_children_collection->next();
}

function cnr_children_count() {
	global $cnr;
	return $cnr->post_children_collection->count();	
}

function cnr_children_found() {
	global $cnr;
	return $cnr->post_children_collection->found();
}

function cnr_children_max_num_pages() {
	global $cnr;
	return $cnr->post_children_collection->max_num_pages();
}

function cnr_is_first_child() {
	global $cnr;
	return $cnr->post_children_collection->is_first();
}

function cnr_is_last_child() {
	global $cnr;
	return $cnr->post_children_collection->is_last();
}

/*-** Featured Content **-*/

/**
 * Retrieves featured posts
 * @return array Featured posts matching criteria 
 * @param int $limit (optional) Maximum number of featured posts to retrieve
 * @param int|bool $parent (optional) Section to get featured posts of (Defaults to current section).  FALSE if latest featured posts should be retrieved regardless of section
 */
function cnr_get_featured($limit = 0, $parent = null) {
	global $cnr;
	return $cnr->posts_featured->get($limit, $parent);
}

function cnr_in_featured($post_id = null) {
	global $cnr;
	return $cnr->posts_featured->contains($post_id);
}

function cnr_has_featured() {
	global $cnr;
	return $cnr->posts_featured->has();
}

function cnr_next_featured() {
	global $cnr;
	return $cnr->posts_featured->next();
	
}

function cnr_current_featured() {
	global $cnr;
	return $cnr->posts_featured->current();
}

function cnr_is_first_featured() {
	global $cnr;
	return $cnr->posts_featured->is_first();
}

function cnr_is_last_featured() {
	global $cnr;
	return $cnr->posts_featured->is_last();
}

function cnr_featured_count() {
	global $cnr;
	return $cnr->posts_featured->count();
}

/**
 * Checks if Post is "featured"
 * 
 * @return bool TRUE if post is featured, FALSE otherwise 
 * @param object $post (optional) Post to check.  Defaults to current post
 */
function cnr_is_featured($post = null) {
	global $cnr;
	return $cnr->post_is_featured($post);
}

/*-** Post Content **-*/

/**
 * Checks if post has content to display
 * @param object $post (optional) Post object
 * @return bool TRUE if post has content, FALSE otherwise
 */
function cnr_has_content($post = null) {
	global $cnr;
	return $cnr->post_has_content($post);
}

	/* Images */

function cnr_get_attachments($post = null) {
	$m = new CNR_Media();
	return $m->post_get_attachments($post);
}
 
function cnr_get_filesize($post = null, $formatted = true) {
	$m = new CNR_Media();
	return $m->get_attachment_filesize($post, $formatted);
}

	/* Section */
	
/**
 * Retrieves the post's section data 
 * @return string post's section data 
 * @param string $type (optional) Type of data to return (Default: ID)
 * 	Possible values:
 * 	ID		Returns the ID of the section
 * 	name	Returns the name of the section
 */
function cnr_get_the_section($type = 'ID') {
	return CNR_Post::get_section($type);
}

/**
 * Prints the post's section data
 * @param string $type (optional) Type of data to return (Default: ID)
 * @see cnr_get_the_section()
 */
function cnr_the_section($type = 'ID') {
	CNR_Post::the_section($type);
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
 * @param bool $wrap_list (optional) whether or not to wrap list in <ul></ul> tags.
 * 	Useful if page group list items are meant to be part of a larger list
 */
function cnr_list_page_group($group, $wrap_list = true) {
	$group = new CNR_Page_Group($group);
	$group->list_pages($wrap_list);
}

function cnr_the_feed_links() {
	global $cnr;
	$cnr->feed_the_links();
}

?>
