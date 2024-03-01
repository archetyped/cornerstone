<?php
/**
 * Cornerstone
 *
 * @package Cornerstone
 * @author Archetyped <support@archetyped.com>
 * @copyright 2022 Archetyped
 *
 * Plugin Name: Cornerstone
 * Plugin URI: http://archetyped.com/tools/cornerstone/
 * Description: Enhanced content management for WordPress
 * Version: 0.8.1
 * Requires at least: 5.3
 * Text Domain: cornerstone
 * Author: Archetyped
 * Author URI: http://archetyped.com
 * Support URI: https://github.com/archetyped/cornerstone/wiki/Support-&-Feedback
*/

$cnr = null;
/**
 * Initialize CNR
 */
function cnr_init() {
	$path = dirname(__FILE__) . '/';
	require_once $path . 'load.php';
}

add_action('init', 'cnr_init', 1);