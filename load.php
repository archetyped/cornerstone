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
spl_autoload_register('cnr_autoload');

/* Load Assets */
$path = dirname(__FILE__) . '/';
require_once $path . 'controller.php';
require_once $path . 'functions.php';

/* Variables */

//Global content type variables
if ( !isset($GLOBALS['cnr_content_types']) )
	$GLOBALS['cnr_content_types'] = array();
if ( !isset($GLOBALS['cnr_field_types']) )
	$GLOBALS['cnr_field_types'] = array();

/* Init */
$GLOBALS['cnr_content_utilities'] = new CNR_Content_Utilities();
$GLOBALS['cnr_content_utilities']->init();

/* Hooks */

// Register Default placeholder handlers
cnr_register_placeholder_handler('all', array('CNR_Field_Type', 'process_placeholder_default'), 11);
cnr_register_placeholder_handler('field_id', array('CNR_Field_Type', 'process_placeholder_id'));
cnr_register_placeholder_handler('field_name', array('CNR_Field_Type', 'process_placeholder_name'));
cnr_register_placeholder_handler('data', array('CNR_Field_Type', 'process_placeholder_data'));
cnr_register_placeholder_handler('loop', array('CNR_Field_Type', 'process_placeholder_loop'));
cnr_register_placeholder_handler('data_ext', array('CNR_Field_Type', 'process_placeholder_data_ext'));
cnr_register_placeholder_handler('rich_editor', array('CNR_Field_Type', 'process_placeholder_rich_editor'));

/* Start */

$GLOBALS['cnr'] = new Cornerstone();