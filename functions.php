<?php
/**
 * Functions
 * Provides global access to specific functionality
 * @package Cornerstone
 * @author Archetyped
 */

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

/* Content Types */

/**
 * Register handler for a placeholder in a content type template
 * Placeholders allow templates to be populated with dynamic content at runtime
 * Multiple handlers can be registered for a placeholder,
 * thus allowing custom handlers to override default processing, etc.
 * @uses CNR_Field_Type::register_placeholder_handler() to register placeholder
 * @param string $placeholder Placeholder identifier
 * @param callback $handler Callback function to use as handler for placeholder
 * @param int $priority (optional) Priority of registered handler (Default: 10)
 */
function cnr_register_placeholder_handler($placeholder, $handler, $priority = 10) {
	CNR_Field_Type::register_placeholder_handler($placeholder, $handler, $priority);
}

/**
 * Checks if data exists for specified field
 * @global $cnr_content_utilities
 * @param string $field_id ID of field to check for data
 * @param int|obj $item (optional) Post ID or object to check for field data (Default: global post)
 * @return bool TRUE if field data exists
 */
function cnr_has_data($field_id = null, $item = null) {
	global $cnr_content_utilities;
	return $cnr_content_utilities->has_item_data($item, $field_id);
}

/**
 * Retrieve data from a field
 * @global $cnr_content_utilities
 * @see CNR_Content_Utilities::get_item_data() for more information
 * @param string $field_id ID of field to retrieve
 * @param string $layout (optional) Name of layout to use when returning data
 * @param array $attr (optional) Additional attributes to pass to field
 * @param int|object $item (optional) Post object to retrieve data from (Default: global post object)
 * @param mixed $default Default value to return in case of errors (invalid field, no data, etc.)
 * @return mixed Specified field data
 */
function cnr_get_data($field_id = null, $layout = 'display', $attr = null, $item = null, $default = '') {
	global $cnr_content_utilities;
	return $cnr_content_utilities->get_item_data($item, $field_id, $layout, $default, $attr);
}

/**
 * Prints an item's field data
 * @see CNR_Content_Utilities::the_item_data() for more information
 * @param string $field_id Name of field to retrieve
 * @param string $layout(optional) Layout to use when returning field data (Default: display)
 * @param array $attr Additional items to pass to field
 * @param int|object $item(optional) Content item to retrieve field from (Default: null - global $post object will be used)
 * @param mixed $default Default value to return in case of errors, etc.
 */
function cnr_the_data($field_id = null, $layout = 'display', $attr = null, $item = null, $default = '') {
	global $cnr_content_utilities;
	$cnr_content_utilities->the_item_data($item, $field_id, $layout, $default, $attr);
}