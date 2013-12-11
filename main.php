<?php
/* 
Plugin Name: Cornerstone
Plugin URI: http://archetyped.com/tools/cornerstone/
Description: Enhanced content management for Wordpress
Version: 0.7.1
Author: Archetyped
Author URI: http://archetyped.com
*/

/**
 * @package Cornerstone 
 */
require_once('model.php');
$cnr = new Cornerstone();

/* Template tags */

/**
 * Outputs feed links based on current page
 * @return void
 */
function cnr_the_feed_links() {
	global $cnr;
	$cnr->feeds->the_links();
}

/*-** Child Content **-*/

function cnr_is_section() {
	return ( is_page() && cnr_have_children() ) ? true : false;
}

/**
 * Checks if current post/page has children elements
 * @return bool TRUE if post/page has children, FALSE otherwise
 */
function cnr_have_children() {
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

/**
 * Returns number of children in current request
 * May not return total number of existing children (e.g. if output is paged, etc.)
 * @return int Number of children returned in current request 
 */
function cnr_children_count() {
	global $cnr;
	return $cnr->post_children_collection->count();	
}

/**
 * Returns total number of existing children
 * @return int Total number of children
 */
function cnr_children_found() {
	global $cnr;
	return $cnr->post_children_collection->found();
}

/**
 * Returns total number of pages of children
 * Based on 'posts_per_page' option
 * @return int Maximum number of pages
 */
function cnr_children_max_num_pages() {
	global $cnr;
	return $cnr->post_children_collection->max_num_pages();
}

/**
 * Checks if current child item is the first child item
 * @return bool TRUE if current item is first, FALSE otherwise
 */
function cnr_is_first_child() {
	global $cnr;
	return $cnr->post_children_collection->is_first();
}

/**
 * Checks if current child item is the last child item
 * @return bool TRUE if current item is last, FALSE otherwise
 */
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

function cnr_have_featured() {
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
 * Returns total number of found posts
 * @return int Total number of posts
 */
function cnr_featured_found() {
	global $cnr;
	return $cnr->posts_featured->found();
}

/*-** Post-Specific **-*/

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
 * @uses CNR_Post::get_section() 
 * @param string $data (optional) Type of data to return (Default: ID)
 * Possible values:
 *  NULL		Full section post object
 *	Column name	Post column data (if exists)
 *
 * @param int $id (optional) Post ID (Default: current post)
 * @return mixed post's section (or column data if specified via $data parameter) 
 */
function cnr_get_the_section($data = 'ID', $id = null) {
	return CNR_Post::get_section($id, $data);
}

/**
 * Prints the post's section data
 * @uses CNR_Post::the_section()
 * @param string $data (optional) Type of data to return (Default: ID)
 */
function cnr_the_section($data = 'ID') {
	CNR_Post::the_section(null, $data);
}
