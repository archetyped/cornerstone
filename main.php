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

function cnr_has_children() {
	global $cnr;
	return $cnr->has_children();
}

function cnr_get_child() {
	global $cnr;
	$cnr->get_child();
}

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
