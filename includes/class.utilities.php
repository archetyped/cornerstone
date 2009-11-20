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
	
	/* Helper Functions */
	
	/**
	 * Merges 1 or more arrays together
	 * 
	 * @todo Append numerical elements (as opposed to overwriting element at same index in base array)
	 * @param array Variable number of arrays
	 * @return array Merged array
	 */
	function array_merge_recursive_distinct() {
		//Get all arrays passed to function
		$args = & func_get_args();
		if (empty($args))
			return false;
		//Set first array as base array
		$merged = $args[0];
		//Iterate through arrays to merge
		for ($x = 1; $x < count($args); $x++) {
			//Skip if argument is not an array
			if (!is_array($args[$x]))
				continue;
			foreach ($args[$x] as $key => $val) {
				if (is_numeric($key))
					$merged[] = $val;
				else
					$merged[$key] = (is_array($val)) ? $this->array_merge_recursive_distinct($merged[$key], $val) : $val;
			}
		}
		
		return $merged;
	}
	
	/**
	 * Replaces string value in one array with the value of the matching element in a another array
	 * 
	 * @param string $search Text to search for in array
	 * @param array $arr_replace Array to use for replacing values
	 * @param array $arr_subject Array to search for specified value
	 * @return array Searched array with replacements made
	 */
	function array_replace_recursive($search, $arr_replace, $arr_subject) {
		foreach ($arr_subject as $key => $val) {
			//Skip element if key does not exist in the replacement array
			if (!isset($arr_replace[$key]))
				continue;
			//If element values for both arrays are strings, replace text
			if (is_string($val) && strpos($val, $search) !== false && is_string($arr_replace[$key]))
				$arr_subject[$key] = str_replace($search, $arr_replace[$key], $val);
			//If value in both arrays are arrays, recursively replace text
			if (is_array($val) && is_array($arr_replace[$key]))
				$arr_subject[$key] = $this->array_replace_recursive($search, $arr_replace[$key], $val);
		}
		
		return $arr_subject;
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
		foreach (func_get_args() as $msg) {
			echo '<pre>';
			if (is_scalar($msg))
				echo "$msg<br />";
			else {
				var_dump($msg);
			}
			echo '</pre>';
		}
	}
}

$cnr_debug = new CNR_Debug();