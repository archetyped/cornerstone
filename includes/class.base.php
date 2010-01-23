<?php

require_once 'class.utilities.php';

/**
 * @package Cornerstone
 * @author SM
 *
 */
class CNR_Base {
	
	/**
	 * @var string Prefix for Cornerstone-related data (attributes, DB tables, etc.)
	 */
	var $prefix = 'cnr';
	
	/**
	 * @var CNR_Utilities Utilities instance
	 */
	var $util;
	
	/**
	 * @var CNR_Debug Debug instance
	 */
	var $debug;
	
	function CNR_Base() {
		$this->__construct();
	}
	
	function __construct() {
		$this->util = new CNR_Utilities();
		$this->debug = &$GLOBALS['cnr_debug'];
	}
	
	/**
	 * Returns callback to instance method
	 * @param string $method Method name
	 * @return array Callback array
	 */
	function &m($method) {
		return $this->util->m($this, $method);
	}
}

?>