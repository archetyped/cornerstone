<?php

/**
 * Utility methods
 * 
 * @package Cornerstone
 * @author SM
 *
 */
class CNR_Utilities {
	
	function CNR_Utilities() {
		$this->__construct();
	}
	
	function __construct() {
		
	}
	
	/**
	 * Returns callback array to instance method
	 * @param object $obj Instance object
	 * @param string $method Name of method
	 * @return array Callback array
	 */
	function m($obj = null, $method = '') {
		if ($obj == null)
			$obj = $this;
		return array($obj, $method);
	}
}

/**
 * Class for debugging
 */
class CNR_Debug {
	/**
	 * @var array Associative array of debug messages
	 */
	var $msgs = array();
	
	/* Constructor */
	
	function CNR_Debug() {
		$this->__construct();
	}
	
	function __construct() {
		
	}
	
	/**
	 * Adds debug data to object
	 * @return void
	 * @param String $title Title of debug message
	 * @param mixed $message value to store in message for debugging purposes
	 */
	function add_message($title, $message) {
		$this->msgs[$title] = $message;
	}
	
	/**
	 * Returns debug message array
	 * @return array Debug message array
	 */
	function get_messages() {
		return $this->msgs;
	}
	
	function show_messages() {
		echo '<pre>';
		var_dump($this->get_messages());
		echo '</pre>';
	}
	
	function print_message($msg) {
		echo "<pre>$msg<br /></pre>";
	}
}

$cnr_debug = new CNR_Debug();