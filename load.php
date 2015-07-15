<?php

/* Constants */

if ( !defined('CNR_DEV') ) {
	define('CNR_DEV', ( isset( $_REQUEST['cnr_dev'] ) && !!$_REQUEST['cnr_dev'] ) );
}

/* Class Management */

/**
 * Class loading handler
 * @param string $classname Class to load
 */
function cnr_autoload($classname) {
	$prefix = 'cnr_';
	$cls = strtolower($classname);
	//Remove prefix
	if ( 0 !== strpos($cls, $prefix) ) {
		return false;
	}
	//Format class for filename
	$fn = 'class.' . substr($cls, strlen($prefix)) . '.php';
	//Build path
	$path = dirname(__FILE__) . '/' . "includes/" . $fn;
	//Load file
	if ( is_readable($path) ) {
		require $path;
	}
}

// Register autoloader
//spl_autoload_register('cnr_autoload');

/* Load Assets */
$path = dirname(__FILE__) . '/';
require_once $path . 'controller.php';
$GLOBALS['cnr'] = new Cornerstone();
require_once $path . 'functions.php';