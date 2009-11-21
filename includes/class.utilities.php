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
	 * Methodology
	 * - Set first parameter as base array
	 *   - All other parameters will be merged into base array
	 * - Iterate through other parameters (arrays)
	 *   - Skip all non-array parameters
	 *   - Iterate though key/value pairs of current array
	 *     - Merge item in base array with current item based on key name
	 *     - If the current item's value AND the corresponding item in the base array are BOTH arrays, recursively merge the the arrays
	 *     - If the current item's value OR the corresponding item in the base array is NOT an array, current item overwrites base item
	 * @todo Append numerical elements (as opposed to overwriting element at same index in base array)
	 * @param array Variable number of arrays
	 * @return array Merged array
	 */
	function array_merge_recursive_distinct() {
		//Get all arrays passed to function
		$args = func_get_args();
		if (empty($args))
			return false;
		$this->debug = new CNR_Debug();
		//$this->debug->print_message('Arguments', $args);
		//Set first array as base array
		$merged = $args[0];
		//Iterate through arrays to merge
		$arg_length = count($args);
		for ($x = 1; $x < $arg_length; $x++) {
			//Skip if argument is not an array (only merge arrays)
			if (!is_array($args[$x]))
				continue;
			//Iterate through argument items
			foreach ($args[$x] as $key => $val) {
					if (!isset($merged[$key]) || !is_array($merged[$key]) || !is_array($val)) {
						$merged[$key] = $val;
					} elseif (is_array($merged[$key]) && is_array($val)) {
						$merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $val);
					}
					//$merged[$key] = (is_array($val) && isset($merged[$key])) ? $this->array_merge_recursive_distinct($merged[$key], $val) : $val;
			}
		}
		//$this->debug->print_message('Merged Array', $merged);
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