<?php
/**
 * Content Types - Base Class
 * Core properties/methods for Content Type derivative classes
 * @package Cornerstone
 * @subpackage Content Types
 * @author Archetyped
 */
class CNR_Content_Base extends CNR_Base {

	/**
	 * Base class name
	 * @var string
	 */
	var $base_class = 'cnr_content_base';

	/**
	 * @var string Unique name
	 */
	var $id = '';

	/**
	 * Reference to parent object that current instance inherits from
	 * @var object
	 */
	var $parent = null;

	/**
	 * Title
	 * @var string
	 */
	var $title = '';

	/**
	 * Plural Title
	 * @var string
	 */
	var $title_plural = '';

	/**
	 * @var string Short description
	 */
	var $description = '';

	/**
	 * @var array Object Properties
	 */
	var $properties = array();

	/**
	 * Data for object
	 * May also contain data for nested objects
	 * @var mixed
	 */
	var $data = null;

	/**
	 * @var array Script resources to include for object
	 */
	var $scripts = array();

	/**
	 * @var array CSS style resources to include for object
	 */
	var $styles = array();

	/**
	 * Hooks (Filters/Actions) for object
	 * @var array
	 */
	var $hooks = array();

	/**
	 * Constructor
	 */
	function __construct($id = '', $parent = null) {
		parent::__construct();
		$id = trim($id);
		$this->id = $id;
		if ( is_bool($parent) && $parent )
			$parent = $id;
		$this->set_parent($parent);
	}

	/* Getters/Setters */
	
	/**
	 * Checks if the specified path exists in the object
	 * @param array $path Path to check for
	 * @return bool TRUE if path exists in object, FALSE otherwise
	 */
	function path_isset($path = '') {
		//Stop execution if no path is supplied
		if ( empty($path) )
			return false;
		$args = func_get_args();
		$path = $this->util->build_path($args);
		$item =& $this;
		//Iterate over path and check if each level exists before moving on to the next
		for ($x = 0; $x < count($path); $x++) {
			if ( $this->util->property_exists($item, $path[$x]) ) {
				//Set $item as reference to next level in path for next iteration
				$item =& $this->util->get_property($item, $path[$x]);
				//$item =& $item[ $path[$x] ];
			} else {
				return false;
			}
		}
		return true; 
	}

	/**
	 * Retrieves a value from object using a specified path
	 * Checks to make sure path exists in object before retrieving value
	 * @param array $path Path to retrieve value from. Each item in array is a deeper dimension
	 * @return mixed Value at specified path
	 */
	function &get_path_value($path = '') {
		$ret = '';
		$path = $this->util->build_path(func_get_args());
		if ( $this->path_isset($path) ) {
			$ret =& $this;
			for ($x = 0; $x < count($path); $x++) {
				if ( 0 == $x )
					$ret =& $ret->{ $path[$x] };
				else
					$ret =& $ret[ $path[$x] ];
			}
		}
		return $ret;
	}

	/**
	 * Search for specified member value in field type ancestors
	 * @param string $member Name of object member to search (e.g. properties, layout, etc.)
	 * @param string $name Value to retrieve from member
	 * @return mixed Member value if found (Default: empty string)
	 */
	function get_parent_value($member, $name = '', $default = '') {
		$parent =& $this->get_parent();
		return $this->get_object_value($parent, $member, $name, $default, 'parent');
	}

	/**
	 * Retrieves specified member value
	 * Handles inherited values
	 * Merging corresponding parents if value is an array (e.g. for property groups)
	 * @param string|array $member Member to search.  May also contain a path to the desired member
	 * @param string $name Value to retrieve from member
	 * @param mixed $default Default value if no value found (Default: empty string)
	 * @param string $dir Direction to move through hierarchy to find value
	 * Possible Values:
	 *  parent (default) 	- Search through field parents
	 *  current				- Do not search through connected objects
	 *  container			- Search through field containers
	 *  caller				- Search through field callers
	 * @return mixed Specified member value
	 */
	function get_member_value($member, $name = '', $default = '', $dir = 'parent') {
		//Check if path to member is supplied
		$path = array();
		if ( is_array($member) && isset($member['tag']) ) {
			if ( isset($member['attributes']['ref_base']) ) {
				if ( 'root' != $member['attributes']['ref_base'] )
					$path[] = $member['attributes']['ref_base'];
			} else {
				$path[] = 'properties';
			}

			$path[] = $member['tag'];
		} else {
			$path = $member;
		}

		$path = $this->util->build_path($path, $name);
		//Set defaults and prepare data
		$val = $default;
		$inherit = false;
		$inherit_tag = '{inherit}';

		/* Determine whether the value must be retrieved from a parent/container object
		 * Conditions:
		 * > Path does not exist in current field
		 * > Path exists and is not an object, but at least one of the following is true:
		 *   > Value at path is an array (e.g. properties, elements, etc. array)
		 *     > Parent/container values should be merged with retrieved array
		 *   > Value at path is a string that inherits from another field
		 *     > Value from other field will be retrieved and will replace inheritance placeholder in retrieved value
		 */

		$deeper = false;

		if ( !$this->path_isset($path) )
			$deeper = true;
		else {
			$val = $this->get_path_value($path);
			if ( !is_object($val) && ( is_array($val) || ($inherit = strpos($val, $inherit_tag)) !== false ) )
				$deeper = true;
			else
				$deeper = false;
		}
		if ( $deeper && 'current' != $dir ) {
				//Get Parent value (recursive)
				$ex_val = ( 'parent' != $dir ) ? $this->get_container_value($member, $name, $default) : $this->get_parent_value($member, $name, $default);
				//Handle inheritance
				if ( is_array($val) ) {
					//Combine Arrays
					if ( is_array($ex_val) )
						$val = array_merge($ex_val, $val);
				} elseif ( $inherit !== false ) {
					//Replace placeholder with inherited string
					$val = str_replace($inherit_tag, $ex_val, $val);
				} else {
					//Default: Set parent value as value
					$val = $ex_val;
				}
		}

		return $val;
	}

	/**
	 * Search for specified member value in an object
	 * @param object $object Reference to object to retrieve value from
	 * @param string $member Name of object member to search (e.g. properties, layout, etc.)
	 * @param string $name (optional) Value to retrieve from member
	 * @param mixed $default (optional) Default value to use if no value found (Default: empty string)
	 * @param string $dir Direction to move through hierarchy to find value @see CNR_Field_Type::get_member_value() for possible values
	 * @return mixed Member value if found (Default: $default)
	 */
	function get_object_value(&$object, $member, $name = '', $default = '', $dir = 'parent') {
		$ret = $default;
		if ( is_object($object) && method_exists($object, 'get_member_value') )
			$ret = $object->get_member_value($member, $name, $default, $dir);
		return $ret;
	}

	/**
	 * Retrieve value from data member
	 * @param bool $top (optional) Whether to traverse through the field hierarchy to get data for field (Default: TRUE)
	 * @return mixed Value at specified path
	 */
	function get_data($top = true) {
		$top = !!$top;
		$obj = $this;
		$obj_path = array($this);
		$path = array();
		//Iterate through hiearchy to get top-most object
		while ( !empty($obj) ) {
			$new = null;
			//Try to get caller first
			if ( method_exists($obj, 'get_caller') ) {
				$checked = true;
				$new = $obj->get_caller();
			}
			//Try to get container if no caller found
			if ( empty($new) && method_exists($obj, 'get_container') ) {
				$checked = true;
				$new = $obj->get_container();
			}

			$obj = $new;

			//Stop iteration
			if ( !empty($obj) ) {
				//Add object to path if it is valid
				$obj_path[] = $obj;
			}
		}

		//Check each object (starting with top-most) for matching data for current field

		//Reverse array
		$obj_path = array_reverse($obj_path);
		//Build path for data location
		foreach ( $obj_path as $obj ) {
			if ( $this->util->property_exists($obj, 'id') )
				$path[] = $obj->id;
		}

		//Iterate through objects
		while ( !empty($obj_path) ) {
			//Get next object
			$obj = array_shift($obj_path);
			//Shorten path
			array_shift($path);
			//Check for value in object and stop iteration if matching data found
			if ( ($val = $this->get_object_value($obj, 'data', $path, null, 'current')) && !is_null($val) ) {
				break;
			}
		}

		return $val;
	}

	/**
	 * Sets value in data member
	 * Sets value to data member itself by default
	 * @param mixed $value Value to set
	 * @param string|array $name Name of value to set (Can also be path to value)
	 */
	function set_data($value, $name = '') {
		$ref =& $this->get_path_value('data', $name);
		$ref = $value;
	}
	
	/**
	 * Retrieve base_class property
	 * @return string base_class property of current class/instance object
	 */
	static function get_base_class() {
		$ret = '';
		if ( isset($this) )
			$ret = $this->base_class;
		else {
			$ret = CNR_Utilities::get_property(__CLASS__, 'base_class');
		}
		
		return $ret;
	}
	
	/**
	 * Sets parent object of current instance
	 * Parent objects must be the same object type as current instance
	 * @param string|object $parent Parent ID or reference
	 */
	function set_parent($parent) {
		if ( !empty($parent) ) {
			//Validate parent object
			if ( is_array($parent) )
				$parent =& $parent[0];
	
			//Retrieve reference object if ID was supplied
			if ( is_string($parent) ) {
				$parent = trim($parent);
				//Check for existence of parent
				$lookup = $this->base_class . 's';
				if ( isset($GLOBALS[$lookup][$parent]) ) {
					//Get reference to parent
					$parent =& $GLOBALS[$lookup][$parent];
				}
			}
			
			//Set reference to parent field type
			if ( is_a($parent, $this->base_class) ) {
				$this->parent =& $parent;
			}
		}
	}

	/**
	 * Retrieve field type parent
	 * @return CNR_Field_Type Reference to parent field
	 */
	function &get_parent() {
		return $this->parent;
	}

	/**
	 * Retrieves field ID
	 * @param string|CNR_Field|array $field (optional) Field object or ID of field or options array
	 * @return string|bool Field ID, FALSE if $field is invalid
	 */
	function get_id($field = null) {
		$ret = false;
		
		// Get options.
		$num_args = func_num_args();
		$options = ( $num_args > 0 && ( $last_arg = func_get_arg($num_args - 1) ) && is_array($last_arg) ) ? $last_arg : array();
		
		// Validate field parameter.
		if ( ( !is_object($field) || !is_a($field, 'cnr_field_type') ) && isset($this) ) {
			$field =& $this;
		}

		if ( is_a($field, CNR_Field_Type::get_base_class()) )
			$id = $field->id;

		if ( is_string($id) )
			$ret = trim($id);
		
		// Setup options.
		$options = wp_parse_args( $options, [ 'format' => null ] );
		//Check if field should be formatted
		if ( is_string($ret) && !empty($options['format']) ) {
			//Clear format option if it is an invalid value
			if ( is_bool($options['format']) || is_int($options['format']) )
				$options['format'] = null;
			//Setup values
			$wrap = array('open' => '[', 'close' => ']');
			if ( isset($options['wrap']) && is_array($options['wrap']) )
				$wrap = wp_parse_args($options['wrap'], $wrap);
			$wrap_trailing = ( isset($options['wrap_trailing']) ) ? !!$options['wrap_trailing'] : true;
			switch ( $options['format'] ) {
				case 'attr_id' :
					$wrap = (array('open' => '_', 'close' => '_'));
					$wrap_trailing = false;
					break;
			}
			$c = $field->get_caller();
			$field_id = array($ret);
			while ( !!$c ) {
				//Add ID of current field to array
				if ( isset($c->id) && is_a($c, $this->base_class) )
					$field_id[] = $c->id;
				$c = ( method_exists($c, 'get_caller') ) ? $c->get_caller() : null;
			}

			//Add prefix to ID value
			$field_id[] = 'attributes';

			//Convert array to string
			$ret = $field->prefix . $wrap['open'] . implode($wrap['close'] . $wrap['open'], array_reverse($field_id)) . ( $wrap_trailing ? $wrap['close'] : '');
		}
		return $ret;
	}

	/**
	 * Set object title
	 * @param string $title Title for object
	 * @param string $plural Plural form of title
	 */
	function set_title($title = '', $plural = '') {
		$this->title = strip_tags(trim($title));
		if ( isset($plural) )
			$this->title_plural = strip_tags(trim($plural));
	}

	/**
	 * Retrieve object title
	 * @param bool $plural TRUE if plural title should be retrieved, FALSE otherwise (Default: FALSE)
	 */
	function get_title($plural = false) {
		$dir = 'current';
		//Singular
		if ( !$plural )
			return $this->get_member_value('title', '','', $dir);
		//Plural
		$title = $this->get_member_value('title_plural', '', '', $dir);
		if ( empty($title) ) {
			//Use singular title for plural base
			$title = $this->get_member_value('title', '', '', $dir);
			//Determine technique for making title plural
			//Get last letter
			if ( !empty($title) ) {
				$tail = substr($title, -1);
				switch ( $tail ) {
					case 's' :
						$title .= 'es';
						break;
					case 'y' :
						$title = substr($title, 0, -1) . 'ies';
						break;
					default :
						$title .= 's';
				}
			}
		}
		return $title;
	}

	/**
	 * Set object description
	 * @param string $description Description for object
	 */
	function set_description($description = '') {
		$this->description = strip_tags(trim($description));
	}

	/**
	 * Retrieve object description
	 * @return string Object description
	 */
	function get_description() {
		$dir = 'current';
		return $this->get_member_value('description', '','', $dir);
		return $desc;
	}
	
	/*-** Hooks **-*/
	
	/**
	 * Retrieve hooks added to object
	 * @return array Hooks
	 */
	function get_hooks() {
		return $this->get_member_value('hooks', '', array());
	}
	
	/**
	 * Add hook for object
	 * @see add_filter() for parameter defaults
	 * @param $tag
	 * @param $function_to_add
	 * @param $priority
	 * @param $accepted_args
	 */
	function add_hook($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
		//Create new array for tag (if not already set)
		if ( !isset($this->hooks[$tag]) )
			$this->hooks[$tag] = array();
		//Build Unique ID
		if ( is_string($function_to_add) )
			$id = $function_to_add;
		elseif ( is_array($function_to_add) && !empty($function_to_add) )
			$id = strval($function_to_add[count($function_to_add) - 1]);
		else
			$id = 'function_' . ( count($this->hooks[$tag]) + 1 ); 
		//Add hook
		$this->hooks[$tag][$id] = func_get_args();
	}
	
	/**
	 * Convenience method for adding an action for object
	 * @see add_filter() for parameter defaults
	 * @param $tag
	 * @param $function_to_add
	 * @param $priority
	 * @param $accepted_args
	 */
	function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
		$this->add_hook($tag, $function_to_add, $priority, $accepted_args);
	}
	
	/**
	 * Convenience method for adding a filter for object
	 * @see add_filter() for parameter defaults
	 * @param $tag
	 * @param $function_to_add
	 * @param $priority
	 * @param $accepted_args
	 */
	function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
		$this->add_hook($tag, $function_to_add, $priority, $accepted_args);
	}
	
	/*-** Dependencies **-*/
	
	/**
	 * Adds dependency to object
	 * @param string $type Type of dependency to add (script, style)
	 * @param array|string $context When dependency will be added (@see CNR_Utilities::get_action() for possible contexts)
	 * @see wp_enqueue_script for the following of the parameters
	 * @param $handle
	 * @param $src
	 * @param $deps
	 * @param $ver
	 * @param $ex
	 */
	function add_dependency($type, $context, $handle, $src = false, $deps = array(), $ver = false, $ex = false) {
		$args = func_get_args();
		//Remove type/context from arguments
		$args = array_slice($args, 2);

		//Set context
		if ( !is_array($context) ) {
			//Wrap single contexts in an array
			if ( is_string($context) )
				$context = array($context);
			else 
				$context = array();
		}
		//Add file to instance property
		$this->{$type}[$handle] = array('context' => $context, 'params' => $args);
	}
	
	/**
	 * Add script to object to be added in specified contexts
	 * @param array|string $context Array of contexts to add script to page
	 * @see wp_enqueue_script for the following of the parameters
	 * @param $handle
	 * @param $src
	 * @param $deps
	 * @param $ver
	 * @param $in_footer
	 */
	function add_script( $context, $handle, $src = false, $deps = array(), $ver = false, $in_footer = false ) {
		$args = func_get_args();
		//Add file type to front of arguments array
		array_unshift($args, 'scripts');
		call_user_func_array(array(&$this, 'add_dependency'), $args);
	}

	/**
	 * Retrieve script dependencies for object
	 * @return array Script dependencies
	 */
	function get_scripts() {
		return $this->get_member_value('scripts', '', array());
	}
	
	/**
	 * Add style to object to be added in specified contexts
	 * @param array|string $context Array of contexts to add style to page
	 * @see wp_enqueue_style for the following of the parameters
	 * @param $handle
	 * @param $src
	 * @param $deps
	 * @param $ver
	 * @param $in_footer
	 */
	function add_style( $handle, $src = false, $deps = array(), $ver = false, $media = false ) {
		$args = func_get_args();
		array_unshift($args, 'styles');
		call_user_method_array('add_dependency', $this, $args);
	}

	/**
	 * Retrieve Style dependencies for object
	 * @return array Style dependencies
	 */
	function get_styles() {
		return $this->get_member_value('styles', '', array());
	}
}