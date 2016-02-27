<?php
/* 
Plugin Name: Cornerstone
Plugin URI: http://archetyped.com/tools/cornerstone/
Description: Enhanced content management for Wordpress
Version: 0.7.5
Author: Archetyped
Author URI: http://archetyped.com
Text Domain: cornerstone
Support URI: https://github.com/archetyped/cornerstone/wiki/Reporting-Issues
*/
/*
Copyright 2015 Archetyped (support@archetyped.com)
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