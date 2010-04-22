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
	function &m(&$obj, $method = '') {
		if ( $obj == null && isset($this) )
			$obj =& $this;
		$arr = array(&$obj, $method);
		return $arr;
	}
	
	/* Helper Functions */
	
	/*-** WP **-*/
	
	/**
	 * Checks if $post is a valid Post object
	 * If $post is not valid, assigns global post object to $post (if available)
	 * @return bool TRUE if $post is valid object by end of function processing
	 * @param object $post Post object to evaluate
	 */
	function check_post(&$post) {
		if (empty($post)) {
			if (isset($GLOBALS['post'])) {
				$post = $GLOBALS['post'];
				$GLOBALS['post'] =& $post;
			}
			else
				return false;
		}
		if (is_array($post))
			$post = (object) $post;
		elseif (is_numeric($post))
			$post = get_post($post);
		if (!is_object($post))
			return false;
		return true;
	}
	
	/*-** Request **-*/
	
	/**
	 * Checks $_SERVER['SCRIPT_NAME'] to see if file base name matches specified file name
	 * @param string $filename Filename to check for
	 */
	function is_file( $filename ) {
		return ( $filename == basename( $_SERVER['SCRIPT_NAME'] ) );
	}
	
	/**
	 * Returns URL of file (assumes that it is in plugin directory)
	 * @param string $file name of file get URL
	 * @return string File path
	 */
	function get_file_url($file) {
		if (is_string($file) && '' != trim($file)) {
			$file = ltrim(trim($file), '/');
			$file = sprintf('%s/%s', $this->get_url_base(), $file);
		}
		return $file;
	}
	
	/**
	 * Retrieve base URL for plugin-specific files
	 * @return string Base URL
	 */
	function get_url_base() {
		static $url_base = '';
		if ( '' == $url_base ) {
			$sl_f = '/';
			$sl_b = '\\';
			$plugin_dir = str_replace(str_replace($sl_f, $sl_b, WP_PLUGIN_DIR), '', dirname(dirname(__FILE__)));
			$url_base = str_replace($sl_b, $sl_f, WP_PLUGIN_URL . $plugin_dir);
		}
		return $url_base;
	}
	
	/**
	 * Retrieve current action based on URL query variables
	 * @return string Current action
	 */
	function get_action($default = null) {
		$action = '';
		if ( isset($_GET['action']) )
			$action = $_GET['action'];
		elseif ( isset($_GET['page']) && ($pos = strrpos($_GET['page'], '-')) && $pos !== false && ( $pos != count($_GET['page']) - 1 ) )
			$action = trim(substr($_GET['page'], $pos + 1), '-_');	
		if ( empty($action) )
			$action = $default;
		return $action;
	}
	
	/*-** General **-*/
	
	/**
	 * Checks if a property exists in a class or object
	 * (Compatibility method for PHP 4
	 * @param mixed $class Class or object to check 
	 * @param string $property Name of property to look for in $class
	 */
	function property_exists($class, $property) {
		if ( !is_object($class) && !is_array($class) )
			return false;
		if ( function_exists('property_exists') && is_object($class) ) {
			return property_exists($class, $property);
		} else {
			return array_key_exists($property, $class);
		}
	}
	
	/**
	 * Retrieve specified property from object or array
	 * @param object|array $obj Object or array to get property from
	 * @param string $property Property name to retrieve
	 * @return mixed Property value
	 */
	function &get_property(&$obj, $property) {
		$property = trim($property);
		if ( is_object($obj) )
			return $obj->{$property};
		if ( is_array($obj) )
			return $obj[$property];
	}
	
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
	
	/**
	 * Checks if item at specified path in array is set
	 * @param array $arr Array to check for item
	 * @param array $path Array of segments that form path to array (each array item is a deeper dimension in the array)
	 * @return boolean TRUE if item is set in array, FALSE otherwise
	 */
	function array_item_isset(&$arr, &$path) {
		$f_path = $this->get_array_path($path);
		return eval('return isset($arr' . $f_path . ');');
	}
	
	/**
	 * Returns value of item at specified path in array
	 * @param array $arr Array to get item from
	 * @param array $path Array of segments that form path to array (each array item is a deeper dimension in the array)
	 * @return mixed Value of item in array (Default: empty string)
	 */
	function &get_array_item(&$arr, &$path) {
		$item = '';
		if ($this->array_item_isset($arr, $path)) {
			eval('$item =& $arr' . $this->get_array_path($path) . ';');
		}
		return $item;
	}
	
	function get_array_path($attribute = '', $format = null) {
		//Formatted value
		$fmtd = '';
		if (!empty($attribute)) {
			//Make sure attribute is array
			if (!is_array($attribute)) {
				$attribute = array($attribute);
			}
			//Format attribute
			$format = strtolower($format);
			switch ($format) {
				case 'id':
					$fmtd = array_shift($attribute) . '[' . implode('][', $attribute) . ']';
					break;
				case 'metadata':
				case 'attribute':
					//Join segments
					$delim = '_';
					$fmtd = implode($delim, $attribute);
					//Replace white space and repeating delimiters
					$fmtd = str_replace(' ', $delim, $fmtd);
					while (strpos($fmtd, $delim.$delim) !== false)
						$fmtd = str_replace($delim.$delim, $delim, $fmtd);
					//Prefix formatted value with delimeter for metadata keys
					if ('metadata' == $format)
						$fmtd = $delim . $fmtd;
					break;
				case 'path':
				case 'post':
				default:
					$fmtd = '["' . implode('"]["', $attribute) . '"]';
			}
		}
		return $fmtd;
	}
	
	/**
	 * Builds array of path elements based on arguments
	 * Each item in path array represents a deeper level in structure path is for (object, array, filesystem, etc.)
	 * @param array|string Value to add to the path
	 * @return array 1-dimensional array of path elements
	 */
	function build_path() {
		$path = array();
		$args = func_get_args();
		
		//Iterate through parameters and build path
		foreach ( $args as $arg ) {
			if ( empty($arg) )
				continue;
				
			if (is_array($arg)) {
				//Recurse through array items to pull out any more arrays
				foreach ($arg as $key => $val) {
					$path = array_merge($path, $this->build_path($val));
				}
				//$path = array_merge($path, array_values($arg));
			} elseif ( is_scalar($arg) ) {
				$path[] = $arg;
			}
		}
		
		return $path;
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
	
	/**
	 * @var array Holds various timer objects
	 */
	var $timers = array();
	
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
			if (is_scalar($msg) && !is_bool($msg)) {
				echo htmlspecialchars($msg) . "<br />";
			} else {
				var_dump($msg);
			}
			echo '</pre>';
		}
	}
	
	function microtime_float() {
		list($usec, $sec) = explode(' ', microtime());
		return (float)$usec + (float)$sec;
	}
	
	function timer_start($name = 'default', $time = null) {
		if (empty($time)) $time = $this->microtime_float();
		$this->timers[$name] = new stdClass();
		$this->timers[$name]->start = $time;
		$this->timers[$name]->end = $time;
	}
	
	function timer_stop($name = 'default') {
		$time = $this->microtime_float();
		if (!isset($this->timers[$name])
			|| !is_object($this->timers[$name])
		) {
			$this->timer_start($name, $time);
		} else {
			$this->timers[$name]->end = $time;
		}
	}
	
	function timer_show($name = 'default', $format = 'Elapsed time: %s') {
		if (!isset($this->timers[$name]) 
			|| !is_object($this->timers[$name])
			|| $this->timers[$name]->end < $this->timers[$name]->start
		) {
			$this->timer_start($name);
		}
		//Get difference in times
		$res = (float)$this->timers[$name]->end - (float)$this->timers[$name]->start;
		$this->print_message(sprintf($format, $res));
	}
}

$cnr_debug = new CNR_Debug();