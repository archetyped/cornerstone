<?php

require_once 'class.base.php';

/* Variables */

if ( !isset($cnr_content_types) )
	$cnr_content_types = array();
if ( !isset($cnr_field_types) )
	$cnr_field_types = array();

/* Init */
$cnr_content_utilities = new CNR_Content_Utilities();
$cnr_content_utilities->init();
	
/* Functions */

function cnr_register_placeholder_handler($placeholder, $handler, $priority = 10) {
	CNR_Field_Type::register_placeholder_handler($placeholder, $handler, $priority);
}

function cnr_has_data($field_id = null, $item = null) {
	global $cnr_content_utilities;
	return $cnr_content_utilities->has_item_data($item, $field_id);
}

function cnr_get_data($field_id = null, $layout = 'display', $attr = null, $item = null, $default = '') {
	global $cnr_content_utilities;
	return $cnr_content_utilities->get_item_data($item, $field_id, $layout, $default, $attr);
}

function cnr_the_data($field_id = null, $layout = 'display', $attr = null, $item = null, $default = '') {
	global $cnr_content_utilities;
	$cnr_content_utilities->the_item_data($item, $field_id, $layout, $default, $attr);
}

/* Hooks */
cnr_register_placeholder_handler('all', array('CNR_Field_Type', 'process_placeholder_default'), 11);
cnr_register_placeholder_handler('field_id', array('CNR_Field_Type', 'process_placeholder_id'));
cnr_register_placeholder_handler('data', array('CNR_Field_Type', 'process_placeholder_data'));
cnr_register_placeholder_handler('loop', array('CNR_Field_Type', 'process_placeholder_loop'));
cnr_register_placeholder_handler('data_ext', array('CNR_Field_Type', 'process_placeholder_data_ext'));
	
/**
 * Content Types - Base Class
 * Core properties/methods for Content Type derivative classes
 * @package Cornerstone
 * @subpackage Content Types
 * @author SM
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
	 * Legacy Constructor
	 */
	function CNR_Content_Base($id = '') {
		$this->__construct($id);
	}
	
	/**
	 * Constructor
	 */
	function __construct($id = '') {
		parent::__construct();
		$id = trim($id);
		$this->id = $id;
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
	 * Retrieves field ID
	 * @param string|CNR_Field $field (optional) Field object or ID of field
	 * @return string|bool Field ID, FALSE if $field is invalid
	 */
	function get_id($field = null) {
		$ret = false;
		if ( ( !is_object($field) || !is_a($field, 'cnr_field_type') ) && isset($this) ) {
			$field =& $this;
		}
		
		if ( is_a($field, 'cnr_field_type') )
			$id = $field->id;
		
		if ( is_string($id) )
			$ret = trim($id);
		
		//Check if field should be formatted
		if ( is_string($ret) && ($num_args = func_num_args()) > 0 && ($format = func_get_arg($num_args - 1)) && true === $format ) {
			$c = $field->get_caller();
			$field_id = array($ret);
			$wrap = array(
				'open'	=> '[',
				'close'	=> ']'	
			);
			while ( !!$c ) {
				//Add ID of current field to array
				if ( isset($c->id) )
					$field_id[] = $c->id;
				$c = $c->get_caller();
			}
			
			//Add prefix to ID value
			$field_id[] = 'attributes';
			
			//Convert array to string
			return $field->prefix . $wrap['open'] . implode($wrap['close'] . $wrap['open'], array_reverse($field_id)) . $wrap['close'];
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
			//Use singular description for plural base
			$title = $this->get_member_value('title', '', '', $dir);
			//Determine technique for making description plural
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
	 * @param bool $plural TRUE if plural description should be retrieved, FALSE otherwise (Default: FALSE)
	 */
	function get_description($plural = false) {
		$dir = 'current';
		return $this->get_member_value('description', '','', $dir);
		return $desc;
	}
	
	function add_dependency($type, $context, $handle, $src = false, $deps = array(), $ver = false, $ex = false) {
		$args = func_get_args();
		//Remove type/context from arguments
		$args = array_slice($args, 2);
		
		//Set context
		if ( !is_array($context) ) {
			if ( is_string($context) )
				$context = array($context);
			else 
				$context = array();
		}
		$this->{$type}[$handle] = array('context' => $context, 'params' => $args);
	}
	
	function add_script( $context, $handle, $src = false, $deps = array(), $ver = false, $in_footer = false ) {
		$args = func_get_args();
		array_unshift($args, 'scripts');
		call_user_func_array(array(&$this, 'add_dependency'), $args);
	}
	
	function get_scripts() {
		return $this->get_member_value('scripts', '', array());
	}
	
	function add_style( $handle, $src = false, $deps = array(), $ver = false, $media = false ) {
		$args = func_get_args();
		array_unshift($args, 'styles');
		call_user_method_array('add_dependency', $this, $args);
	}
	
	function get_styles() {
		return $this->get_member_value('styles', '', array());
	}
}

/**
 * Content Type - Field Types
 * Stores properties for a specific field
 * @package Cornerstone
 * @subpackage Content Types
 * @author SM
 */
class CNR_Field_Type extends CNR_Content_Base {
	/* Properties */
	
	/**
	 * Base class name
	 * @var string
	 */
	var $base_class = 'cnr_field_type';
	
	/**
	 * @var array Array of Field types that make up current Field type
	 */
	var $elements = array();
	
	/**
	 * Structure: Property names stored as keys in group
	 * Root
	 *  -> Group Name
	 *    -> Property Name => Null
	 * Reason: Faster searching over large arrays
	 * @var array Groupings of Properties
	 */
	var $property_groups = array();
	
	/**
	 * @var array Field type layouts
	 */
	var $layout = array();
	
	/**
	 * @var CNR_Field_Type Parent field type (reference)
	 */
	var $parent = null;
	
	/**
	 * Object that field is in
	 * @var CNR_Field|CNR_Field_Type|CNR_Content_Type
	 */
	var $container = null;
	
	/**
	 * Object that called field
	 * Used to determine field hierarchy/nesting
	 * @var CNR_Field|CNR_Field_Type|CNR_Content_Type
	 */
	var $caller = null;
	
	/**
	 * Legacy Constructor
	 */
	function CNR_Field_Type($id = '', $parent = null) {
		$this->__construct($id, $parent);
	}
	
	/**
	 * Constructor
	 */
	function __construct($id = '', $parent = null) {
		parent::__construct($id);
		
		$this->id = $id;
		$this->set_parent($parent);
	}
	
	/* Getters/Setters */
	
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
	 * Search for specified member value in field's container object (if exists)
	 * @param string $member Name of object member to search (e.g. properties, layout, etc.)
	 * @param string $name Value to retrieve from member
	 * @return mixed Member value if found (Default: empty string)
	 */
	function get_container_value($member, $name = '', $default = '') {
		$container =& $this->get_container();
		return $this->get_object_value($container, $member, $name, $default, 'container');
	}
	
	/**
	 * Search for specified member value in field's container object (if exists)
	 * @param string $member Name of object member to search (e.g. properties, layout, etc.)
	 * @param string $name Value to retrieve from member
	 * @return mixed Member value if found (Default: empty string)
	 */
	function get_caller_value($member, $name = '', $default = '') {
		$caller =& $this->get_caller();
		return $this->get_object_value($caller, $member, $name, $default, 'caller');
	}
	
	/**
	 * Sets reference to parent field type
	 * @param CNR_Field_Type $parent Parent field type
	 */
	function set_parent($parent) {
		//Validate parent object
		if ( is_array($parent) && !empty($parent) )
			$parent =& $parent[0];
			
		//Check if only ID of parent field was supplied
		if ( is_string($parent) ) {
			global $cnr_field_types;
			if ( isset($cnr_field_types[$parent]) )
				$parent =& $cnr_field_types[$parent];
			else
				$parent =& new CNR_Field_Type($parent);
		}
		//Set reference to parent field type
		if ( !empty($parent) && is_a($parent, $this->base_class) ) {
			$this->parent =& $parent;
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
	 * Sets reference to container object of current field
	 * Reference is cleared if no valid object is passed to method
	 * @param object $container
	 */
	function set_container(&$container) {
		if ( !empty($container) && is_object($container) ) {
			//Set as param as container for current field
			$this->container =& $container;
		} else {
			//Clear container member if argument is invalid
			$this->clear_container();
		}
	}
	
	/**
	 * Clears reference to container object of current field
	 */
	function clear_container() {
		$this->container = null;
	}
	
	/**
	 * Retrieves reference to container object of current field
	 * @return object Reference to container object
	 */
	function &get_container() {
		$ret = null;
		if ( $this->has_container() )
			$ret =& $this->container;
		return $ret;
	}
	
	/**
	 * Checks if field has a container reference
	 * @return bool TRUE if field is contained, FALSE otherwise
	 */
	function has_container() {
		return !empty($this->container);
	}
	
	/**
	 * Sets reference to calling object of current field
	 * Any existing reference is cleared if no valid object is passed to method
	 * @param object $caller Calling object
	 */
	function set_caller(&$caller) {
		if ( !empty($caller) && is_object($caller) )
			$this->caller =& $caller;
		else
			$this->clear_caller();
	}
	
	/**
	 * Clears reference to calling object of current field
	 */
	function clear_caller() {
		$this->caller = null;
	}
	
	/**
	 * Retrieves reference to caller object of current field
	 * @return object Reference to caller object
	 */
	function &get_caller() {
		$ret = null;
		if ( $this->has_caller() )
			$ret =& $this->caller;
		return $ret;
	}
	
	
	
	/**
	 * Checks if field has a caller reference
	 * @return bool TRUE if field is called by another field, FALSE otherwise
	 */
	function has_caller() {
		return !empty($this->caller);
	}
	
	/**
	 * Add/Set a property on the field definition
	 * @param string $name Name of property
	 * @param mixed $value Default value for property
	 * @param string|array $group Group(s) property belongs to
	 * @param boolean $uses_data Whether or not property uses data from the content item
	 * @return boolean TRUE if property is successfully added to field type, FALSE otherwise
	 */
	function set_property($name, $value = '', $group = null) {
		//Do not add if property name is not a string
		if ( !is_string($name) )
			return false;
		//Create property array
		$prop_arr = array();
		$prop_arr['value'] = $value;
		//Add to properties array
		$this->properties[$name] = $value;
		//Add property to specified groups
		if ( !empty($group) ) {
			$this->set_group_property($group, $name);
		}
		return true;
	}
	
	/**
	 * Sets multiple properties on field type at once
	 * @param array $properties Properties. Each element is an array containing the arguments to set a new property
	 * @return boolean TRUE if successful, FALSE otherwise 
	 */
	function set_properties($properties) {
		if ( !is_array($properties) )
			return false;
		foreach ( $properties as $name => $val) {
			$this->set_property($name, $val);
		}
	}
	
	/**
	 * Retreives property from field type
	 * @param string $name Name of property to retrieve
	 * @return mixed Specified Property if exists (Default: Empty string)
	 */
	function get_property($name) {
		$val = $this->get_member_value('properties', $name);
		/*if ( isset($val['value']) )
			$val = $val['value'];
		*/
		return $val;
	}
	
	/**
	 * Adds Specified Property to a Group
	 * @param string|array $group Group(s) to add property to
	 * @param string $property Property to add to group
	 */
	function set_group_property($group, $property) {
		if ( is_string($group) && isset($this->property_groups[$group][$property]) )
			return;
		if ( !is_array($group) ) {
			$group = array($group);
		}
		
		foreach ($group as $g) {
			$g = trim($g);
			//Initialize group if it doesn't already exist
			if ( !isset($this->property_groups[$g]) )
				$this->property_groups[$g] = array();
			
			//Add property to group
			$this->property_groups[$g][$property] = null;
		}
	}
	
	/**
	 * Retrieve property group
	 * @param string $group Group to retrieve
	 * @return array Array of properties in specified group
	 */
	function get_group($group) {
		return $this->get_member_value('property_groups', $group, array());
	}
	
	/**
	 * Sets an element for the field type
	 * @param string $name Name of element
	 * @param CNR_Field_Type $type Reference of field type to use for element
	 * @param array $properties Properties for element (passed as keyed associative array)
	 * @param string $id_prop Name of property to set $name to (e.g. ID, etc.)
	 */
	function set_element($name, $type, $properties = array(), $id_prop = 'id') {
		$name = trim(strval($name));
		if ( empty($name) )
			return false;
		//Create new field for element
		$el = new CNR_Field($name, $type);
		//Set container to current field instance
		$el->set_container($this);
		//Add properties to element
		$el->set_properties($properties);
		//Save element to current instance
		$this->elements[$name] =& $el;
	}
	
	/**
	 * Add a layout to the field
	 * @param string $name Name of layout
	 * @param string $value Layout text
	 */
	function set_layout($name, $value = '') {
		if ( !is_string($name) )
			return false;
		$name = trim($name);
		$this->layout[$name] = $value;
		return true;
	}
	
	/**
	 * Retrieve specified layout
	 * @param string $name Layout name
	 * @param bool $parse_nested (optional) Whether nested layouts should be expanded in retreived layout or not (Default: TRUE)
	 * @return string Specified layout text
	 */
	function get_layout($name = 'form', $parse_nested = true) {
		//Retrieve specified layout (use $name value if no layout by that name exists)
		$layout = $this->get_member_value('layout', $name, $name);
		
		//Find all nested layouts in current layout
		if ( !empty($layout) && !!$parse_nested ) {
			$ph = $this->get_placeholder_defaults();

			while ($ph->match = $this->parse_layout($layout, $ph->pattern_layout)) {
				//Iterate through the different types of layout placeholders
				foreach ($ph->match as $tag => $instances) {
					//Iterate through instances of a specific type of layout placeholder
					foreach ($instances as $instance) {
						//Get nested layout
						$nested_layout = $this->get_member_value($instance);
	
						//Replace layout placeholder with retrieved item data
						if ( !empty($nested_layout) )
							$layout = str_replace($ph->start . $instance['match'] . $ph->end, $nested_layout, $layout);
					}
				}
			}
		}
		
		return $layout;
	}
	
	/**
	 * Checks if specified layout exists
	 * Finds layout if it exists in current object or any of its parents
	 * @param string $layout Name of layout to check for
	 * @return bool TRUE if layout exists, FALSE otherwise
	 */
	function has_layout($layout) {
		$ret = false;
		if ( is_string($layout) && ($layout = trim($layout)) && !empty($layout) ) {
			$layout = $this->get_member_value('layout', $layout, false);
			if ( $layout !== false )
				$ret = true;
		}
		
		return $ret;
	}
	
	/**
	 * Parse field layout with a regular expression
	 * @param string $layout Layout data
	 * @param string $search Regular expression pattern to search layout for
	 * @return array Associative array with containing all of the regular expression matches in the layout data
	 * 	Array Structure:
	 *		root => placeholder tags
	 *				=> Tag instances (array)
	 *					'tag'			=> (string) tag name
	 *					'match' 		=> (string) placeholder match
	 *					'attributes' 	=> (array) attributes
	 */
	function parse_layout($layout, $search) {
		$ph_xml = '';
		$parse_match = '';
		$ph_root_tag = 'ph_root_element';
		$ph_start_xml = '<';
		$ph_end_xml = ' />';
		$ph_wrap_start = '<' . $ph_root_tag . '>';
		$ph_wrap_end = '</' . $ph_root_tag . '>';
		$parse_result = false;
		
		//Find all nested layouts in layout
		$match_value = preg_match_all($search, $layout, $parse_match, PREG_PATTERN_ORDER);

		if ($match_value !== false && $match_value > 0) {
			$parse_result = array();
			//Get all matched elements
			$parse_match = $parse_match[1];

			//Build XML string from placeholders
			foreach ($parse_match as $ph) {
				$ph_xml .= $ph_start_xml . $ph . $ph_end_xml . ' ';
			}
			$ph_xml = $ph_wrap_start . $ph_xml . $ph_wrap_end;
			//Parse XML data
			$ph_prs = xml_parser_create();
			xml_parser_set_option($ph_prs, XML_OPTION_SKIP_WHITE, 1);
			xml_parser_set_option($ph_prs, XML_OPTION_CASE_FOLDING, 0);
			$ret = xml_parse_into_struct($ph_prs, $ph_xml, $parse_result['values'], $parse_result['index']);
			xml_parser_free($ph_prs);
				
			//Build structured array with all parsed data
				
			unset($parse_result['index'][$ph_root_tag]);
				
			//Build structured array
			$result = array();
			foreach ($parse_result['index'] as $tag => $instances) {
				$result[$tag] = array();
				//Instances
				foreach ($instances as $instance) {
					//Skip instance if it doesn't exist in parse results
					if (!isset($parse_result['values'][$instance]))
						continue;
						
					//Stop processing instance if a previously-saved instance with the same options already exists
					foreach ($result[$tag] as $tag_match) {
						if ($tag_match['match'] == $parse_match[$instance - 1])
							continue 2;
					}
					
					//Init instance data array
					$inst_data = array();
						
					//Add Tag to array
					$inst_data['tag'] = $parse_result['values'][$instance]['tag'];
						
					//Add instance data to array
					$inst_data['attributes'] = (isset($parse_result['values'][$instance]['attributes'])) ? $inst_data['attributes'] = $parse_result['values'][$instance]['attributes'] : '';
						
					//Add match to array
					$inst_data['match'] = $parse_match[$instance - 1];
						
					//Add to result array
					$result[$tag][] = $inst_data;
				}
			}
			$parse_result = $result;
		}

		return $parse_result;
	}
	
	function get_placeholder_defaults() {
		$ph = new stdClass();
		$ph->start = '{';
		$ph->end = '}';
		$ph->reserved = array('ref' => 'ref_base');
		$ph->pattern_general = '/' . $ph->start . '([a-zA-Z0-9_].*?)' . $ph->end . '/i';
		$ph->pattern_layout = '/' . $ph->start . '([a-zA-Z0-9].*?\s+' . $ph->reserved['ref'] . '="layout.*?".*?)' . $ph->end . '/i';
		return $ph;
	}
	
	/**
	 * Builds HTML for a field based on its properties
	 * @param array $field Field properties (id, field, etc.)
	 * @param array data Additional data for current field
	 */
	function build_layout($layout = 'form', $data = null) {
		$out = '';

		/* Layout */
		
		//Get base layout
		$out = $this->get_layout($layout);
		
		//Parse Layout
		$ph = $this->get_placeholder_defaults();
		
		//Search layout for placeholders
		while ( $ph->match = $this->parse_layout($out, $ph->pattern_general) ) {
			//Iterate through placeholders (tag, id, etc.)
			foreach ($ph->match as $tag => $instances) {
				//Iterate through instances of current placeholder
				foreach ($instances as $instance) {
					//Filter value based on placeholder name
					$target_property = apply_filters('cnr_process_placeholder_' . $tag, '', $this, $instance, $layout, $data);
					
					//Filter value using general filters (if necessary)
					if ( '' == $target_property ) {
						$target_property = apply_filters('cnr_process_placeholder', $target_property, $this, $instance, $layout, $data);
					}
					
					//Clear value if value not a string
					if ( !is_scalar($target_property) ) {
						$target_property = '';
					}
					//Replace layout placeholder with retrieved item data
					$out = str_replace($ph->start . $instance['match'] . $ph->end, $target_property, $out);
				}
			}
		}
		
		/* Return generated value */
		
		return $out;
	}
	
	/*-** Static Methods **-*/
	
	/**
	 * Returns indacator to use field data (in layouts, property values, etc.)
	 */
	function uses_data() {
		return '{data}';
	}
	
	/**
	 * Register a function to handle a placeholder
	 * Multiple handlers may be registered for a single placeholder
	 * Basically a wrapper function to facilitate adding hooks for placeholder processing
	 * @uses add_filter()
	 * @param string $placeholder Name of placeholder to add handler for (Using 'all' will set the function as a handler for all placeholders
	 * @param callback $handler Function to set as a handler
	 * @param int $priority (optional) Priority of handler
	 */
	function register_placeholder_handler($placeholder, $handler, $priority = 10) {
		global $cnr_debug;
		if ( 'all' == $placeholder )
			$placeholder = '';
		else
			$placeholder = '_' . $placeholder;
		
		add_filter('cnr_process_placeholder' . $placeholder, $handler, $priority, 5);
	}
	
	/**
	 * Default placeholder processing
	 * To be executed when current placeholder has not been handled by another handler
	 * @param string $ph_output Value to be used in place of placeholder
	 * @param CNR_Field $field Field containing placeholder
	 * @param array $placeholder Current placeholder
	 * @see CNR_Field::parse_layout for structure of $placeholder array
	 * @param string $layout Layout to build
	 * @param array $data Extended data for field (Default: null)
	 * @return string Value to use in place of current placeholder
	 */
	function process_placeholder_default($ph_output, $field, $placeholder, $layout, $data) {
		//Validate parameters before processing
		if ( empty($ph_output) && is_a($field, 'CNR_Field_Type') && is_array($placeholder) ) {
			//Build path to replacement data
			$ph_output = $field->get_member_value($placeholder);

			//Check if value is group (properties, etc.)
			//All groups must have additional attributes (beyond reserved attributes) that define how items in group are used
			if (is_array($ph_output)
				&& !empty($placeholder['attributes'])
				&& is_array($placeholder['attributes'])
				&& ($ph = $field->get_placeholder_defaults())
				&& $attribs = array_diff(array_keys($placeholder['attributes']), array_values($ph->reserved))
			) {
				/* Targeted property is an array, but the placeholder contains additional options on how property is to be used */
				
				//Find items matching criteria in $ph_output
				//Check for group criteria
				//TODO: Implement more robust/flexible criteria handling (2010-03-11: Currently only processes property groups)
				if ( 'properties' == $placeholder['tag'] && ($prop_group = $field->get_group($placeholder['attributes']['group'])) && !empty($prop_group) ) {
					/* Process group */
					$group_out = array();
					//Iterate through properties in group and build string
					foreach ( $prop_group as $prop_key => $prop_val ) {
						$group_out[] = $prop_key . '="' . $field->get_property($prop_key) . '"'; 
					}
					$ph_output = implode(' ', $group_out);
				}
			} elseif ( is_object($ph_output) && is_a($ph_output, $field->base_class) ) {
				/* Targeted property is actually a nested field */
				//Set caller to current field
				$ph_output->set_caller($field);
				//Build layout for nested element
				$ph_output = $ph_output->build_layout($layout);
			}
		}
		
		return $ph_output;
	}
	
	/**
	 * Build Field ID attribute
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	function process_placeholder_id($ph_output, $field, $placeholder, $layout, $data) {
		/*
		$c = $field;
		$field_id = array();
		$wrap = array(
			'open'	=> '[',
			'close'	=> ']'	
		);
		while ( !!$c ) {
			//Add ID of current field to array
			if ( isset($c->id) )
				$field_id[] = $c->id;
			$c = $c->get_caller();
		}
		
		//Add prefix to ID value
		$field_id[] = 'attributes';
		
		//Convert array to string
		return $field->prefix . $wrap['open'] . implode($wrap['close'] . $wrap['open'], array_reverse($field_id)) . $wrap['close'];
		*/
		return $field->get_id(true);
	}
	
	/**
	 * Retrieve data for field
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	function process_placeholder_data($ph_output, $field, $placeholder, $layout) {
		$val = $field->get_data();
		if ( !is_null($val) )
			$ph_output = $val;
		
		//Return data
		return $ph_output;
	}
	
	/**
	 * Loops over data to build field output
	 * Options:
	 *  data		- Dot-delimited path in field that contains data to loop through
	 *  layout		- Name of layout to use for each data item in loop
	 *  layout_data	- Name of layout to use for data item that matches previously-saved field data
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	function process_placeholder_loop($ph_output, $field, $placeholder, $layout, $data) {
		//Setup loop options
		$attr_defaults = array (
								'layout'		=> '',
								'layout_data'	=> null,
								'data'			=> ''
								);
								
		$attr = wp_parse_args($placeholder['attributes'], $attr_defaults);
		
		if ( is_null($attr['layout_data']) ) {
			$attr['layout_data'] =& $attr['layout'];
		}
		
		//Get data for loop
		$path = explode('.', $attr['data']);
		$loop_data = $field->get_member_value($path);
		/*if ( isset($loop_data['value']) )
			$loop_data = $loop_data['value'];
		*/
		$out = array();
		
		//Get field data
		$data = $field->get_data();
		
		//Iterate over data and build output
		if ( is_array($loop_data) && !empty($loop_data) ) {
			foreach ( $loop_data as $value => $label ) {
				//Load appropriate layout based on field value
				$layout = ( ($data === 0 && $value === $data) xor $data == $value ) ? $attr['layout_data'] : $attr['layout'];
				//Stop processing if no valid layout is returned
				if ( empty($layout) )
					continue;
				//Prep extended field data
				$data_ext = array('option_value' => $value, 'option_text' => $label);
				$out[] = $field->build_layout($layout, $data_ext);
			}
		}
		
		//Return output
		return implode($out);
	}
	
	/**
	 * Returns specified value from extended data array for field
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	function process_placeholder_data_ext($ph_output, $field, $placeholder, $layout, $data) {
		if ( isset($placeholder['attributes']['id']) && ($key = $placeholder['attributes']['id']) && isset($data[$key]) ) {
			$ph_output = strval($data[$key]);
		}
		
		return $ph_output;
	}
	
}

class CNR_Field extends CNR_Field_Type {

}

class CNR_Content_Type extends CNR_Content_Base {
	
	/**
	 * Base class for instance objects
	 * @var string
	 */
	var $base_class = 'cnr_content_type';
	
	/**
	 * Indexed array of fields in content type
	 * @var array
	 */
	var $fields = array();
	
	/**
	 * Associative array of groups in conten type
	 * Key: Group name
	 * Value: object of group properties
	 *  > description string Group description
	 *  > location string Location of group on edit form
	 *  > fields array Fields in group
	 * @var array
	 */
	var $groups = array();
	
	/* Constructors */
	
	/**
	 * Legacy constructor
	 * @param string $id Content type ID
	 */
	function CNR_Content_Type($id) {
		$this->__construct($id);
	}
	
	/**
	 * Class constructor
	 * @param string $id Conten type ID
	 */
	function __construct($id = '') {
		parent::__construct($id);
		//Set title
		$this->set_title($id);
		//TODO Register custom post type
	}
	
	/* Getters/Setters */
	
	/**
	 * Adds group to content type
	 * Groups are used to display related fields in the UI 
	 * @param string $id Unique name for group
	 * @param string $title Group title
	 * @param string $description Short description of group's purpose
	 * @param string $location Where group will be displayed on post edit form (Default: main)
	 */
	function &add_group($id, $title = '', $description = '', $location = 'normal') {
		//Create new group and set properties
		$id = trim($id);
		$this->groups[$id] =& $this->create_group($title, $description, $location);
		return $this->groups[$id];
	}
	
	/**
	 * Remove specified group from content type
	 * @param string $id Group ID to remove
	 */
	function remove_group($id) {
		$id = trim($id);
		if ( $this->group_exists($id) ) {
			unset($this->groups[$id]);
		}
	}
	
	/**
	 * Standardized method to create a new field group
	 * @param string $title Group title (used in meta boxes, etc.)
	 * @param string $description Short description of group's purpose
	 * @param string $location Where group will be displayed on post edit form (Default: main)
	 * @return object Group object
	 */
	function &create_group($title = '', $description = '', $location = 'normal') {
		$group = new stdClass();
		$title = ( is_scalar($title) ) ? trim($title) : '';
		$group->title = $title;
		$description = ( is_scalar($description) ) ? trim($description) : '';
		$group->description = $description;
		$location = ( is_scalar($location) ) ? trim($location) : 'normal';
		$group->location = $location;
		$group->fields = array();
		return $group;
	}
	
	/**
	 * Checks if group exists
	 * @param string $id Group name
	 * @return bool TRUE if group exists, FALSE otherwise
	 */
	function group_exists($id) {
		$id = trim($id);
		//Check if group exists in content type
		return ( array_key_exists($id, $this->groups) );
	}
	
	/**
	 * Adds field to content type
	 * @param string $id Unique name for field
	 * @param CNR_Field_Type|string $parent Field type that this field is based on
	 * @param array $properties (optional) Field properties
	 * @param string $group (optional) Group ID to add field to
	 * @return CNR_Field Reference to new field
	 */
	function &add_field($id, $parent, $properties = array(), $group = null) {
		//Create new field
		$id = trim(strval($id));
		$field = new CNR_Field($id);
		$field->set_parent($parent);
		$field->set_container($this);
		$field->set_properties($properties);
	
		//Add field to content type
		$this->fields[$id] =& $field;
		//Add field to group
		$this->add_to_group($group, $field);
		return $field;
	}
	
	/**
	 * Removes field from content type
	 * @param string|CNR_Field $field Object or Field ID to remove 
	 */
	function remove_field($field) {
		$field = CNR_Field_Type::get_id($field);
		if ( !$field )
			return false;
		
		//Remove from fields array
		//$this->fields[$field] = null;
		unset($this->fields[$field]);
		
		//Remove field from groups
		$this->remove_from_group($field);
	}
	
	/**
	 * Retrieve specified field in Content Type
	 * @param string $field Field ID
	 * @return CNR_Field Specified field
	 */
	function &get_field($field) {
		if ( $this->has_field($field) ) {
			$field = trim($field);
			return $this->fields[$field];
		}
		//Return empty field if no field exists
		$field =& new CNR_Field('');
		return $field;
	}
	
	/**
	 * Checks if field exists in the content type
	 * @param string $field Field ID
	 * @return bool TRUE if field exists, FALSE otherwise
	 */
	function has_field($field) {
		return ( !is_string($field) || empty($field) || !isset($this->fields[$field]) ) ? false : true;
	}
	
	/**
	 * Adds field to a group in the content type
	 * Group is created if it does not already exist
	 * @param string $group ID of group to add field to
	 * @param string $field ID of field to add to group
	 */
	function add_to_group($group, $field) {
		//Validate parameters
		$group = trim(strval($group));
		if ( empty($group) || !$this->has_field($field) )
			return false;
		$field =& $this->get_field($field);
		//Create group if it doesn't exist
		if ( !$this->group_exists($group) )
			$this->add_group($group);
		//Remove field from any other group it's in (fields can only be in one group)
		foreach ( array_keys($this->groups) as $group_id ) {
			if ( isset($this->groups[$group_id]->fields[$field->id]) )
				unset($this->groups[$group_id]->fields[$field->id]);
		}
		//Add reference to field in group
		$this->groups[$group]->fields[$field->id] =& $field;
	}
	
	/**
	 * Remove field from a group
	 * If no group is specified, then field is removed from all groups
	 * @param string|CNR_Field $field Field object or ID of field to remove from group
	 * @param string $group (optional) Group ID to remove field from
	 */
	function remove_from_group($field, $group = '') {
		//Get ID of field to remove or stop execution if field invalid
		$field = CNR_Field_Type::get_id($field);
		if ( !$field )
			return false;
			
		//Remove field from group
		if ( !empty($group) ) {
			//Remove field from single group
			if ( ($group =& $this->get_group($group)) && isset($group->fields[$field]) ) {
				unset($group->fields[$field]);
			}
		} else {
			//Remove field from all groups
			foreach ( array_keys($this->groups) as $group ) {
				if ( ($group =& $this->get_group($group)) && isset($group->fields[$field]) ) {
					unset($group->fields[$field]);
				}
			}
		}
	}
	
	/**
	 * Retrieve specified group
	 * @param string $group ID of group to retrieve
	 * @return object Reference to specified group
	 */
	function &get_group($group) {
		$group = trim($group);
		//Create group if it doesn't already exist
		if ( !$this->group_exists($group) )
			$this->add_group($group);
		
		return $this->groups[$group];
	}
	
	/**
	 * Retrieve all groups in content type
	 * @return array Reference to group objects
	 */
	function &get_groups() {
		return $this->groups;
	}
	
	/**
	 * Output fields in a group
	 * @param string $group Group to output
	 * @return string Group output
	 */
	function build_group($group) {
		$out = array();
		//Stop execution if group does not exist
		if ( $this->group_exists($group) && $group =& $this->get_group($group) ) {
			$out[] = '<div class="cnr_attributes_wrap">'; //Wrap all fields in group

			//Build layout for each field in group
			foreach ( $group->fields as $field ) {
				//Start field output
				$id = 'cnr_field_' . $field->get_id();
				$out[] = '<div id="' . $id . '_wrap" class="cnr_attribute_wrap">';
				//Build field layout
				$out[] = $field->build_layout();
				//end field output
				$out[] = '</div>';
			}
			$out[] = '</div>'; //Close fields container
			//Add description if exists
			if ( !empty($group->description) )
				$out[] = '<p class="cnr_group_description">' . $group->description . '</p>';
		}
		
		//Return group output
		return implode($out);
	}
	
	/**
	 * Set data for a field
	 * @param string|CNR_Field $field Reference or ID of Field to set data for
	 * @param mixed $value Data to set
	 */
	function set_data($field, $value = '') {
		if ( 1 == func_num_args() && is_array($field) )
			$this->data = $field;
		else {
			$field = CNR_Field_Type::get_id($field);
			if ( empty($field) )
				return false;
			$this->data[$field] = $value;
		}
	}
	
	/*-** Admin **-*/
	
	/**
	 * Adds meta boxes for post's content type
	 * Each group in content type is a separate meta box
	 * @param string $type Type of item meta boxes are being build for (post, page, link)
	 * @param string $context Location of meta box (normal, advanced, side)
	 * @param object $post Post object
	 */
	function admin_do_meta_boxes($type, $context, $post) {
		//Add post data to content type
		global $cnr_content_utilities;
		$this->set_data($cnr_content_utilities->get_item_data($post));
		
		//Get Groups
		$groups = array_keys($this->get_groups());
		$priority = 'default';
		//Iterate through groups and add meta box if it fits the context (location)
		foreach ( $groups as $group_id ) {
			$group =& $this->get_group($group_id);
			if ( $context == $group->location && count($group->fields) ) {
				//Format ID for meta box
				$meta_box_id = $this->prefix . '_group_' . $group_id;
				$group_args = array( 'group' => $group_id );
				add_meta_box($meta_box_id, $group->title, $this->m('admin_build_meta_box'), $type, $context, $priority, $group_args);
			}
		}
	}
	
	/**
	 * Outputs group fields for a meta box 
	 * @param object $post Post object
	 * @param array $box Meta box properties
	 */
	function admin_build_meta_box($post, $box) {
		//Stop execution if group not specified
		if ( !isset($box['args']['group']) )
			return false;
			
		//Get ID of group to output
		$group_id =& $box['args']['group'];
		
		$output = array();
		$output[] = '<div class="cnr_group_wrap">';
		$output[] = $this->build_group($group_id);
		$output[] = '</div>';
		
		//Output group content to screen
		echo implode($output);
	}
	
}

/**
 * Utilities for Content Type functionality
 * @package Cornerstone
 * @subpackage Content Types
 * @author SM
 */
class CNR_Content_Utilities extends CNR_Base {

	/**
	 * Initialize content type functionality
	 */
	function init() {
		$this->register_hooks();
	}
	
	/**
	 * Initialize fields and content types
	 */
	function register_types() {
		//Global variables
		global $cnr_field_types, $cnr_content_types;
		
		/* Field Types */
		$base = new CNR_Field_Type('base');
		$base->set_description('Default Element');
		$base->set_property('tag', 'span');
		$base->set_property('class', '', 'attr');
		$base->set_layout('form', '<{tag} name="{field_id}" id="{field_id}" {properties ref_base="root" group="attr"} />');
		$base->set_layout('label', '<label for="{field_id}">{label}</label>');
		$base->set_layout('display', '{data}');
		$cnr_field_types[$base->id] =& $base;
		
		$base_closed = new CNR_Field_Type('base_closed');
		$base_closed->set_parent('base');
		$base_closed->set_description('Default Element (Closed Tag)');
		$base_closed->set_property('value');
		$base_closed->set_layout('form_start', '<{tag} id="{field_id}" name="{field_id}" {properties ref_base="root" group="attr"}>');
		$base_closed->set_layout('form_end', '</{tag}>');
		$base_closed->set_layout('form', '{form_start ref_base="layout"}{value}{form_end ref_base="layout"}');
		$cnr_field_types[$base_closed->id] =& $base_closed;
		
		$input = new CNR_Field_Type('input');
		$input->set_parent('base');
		$input->set_description('Default Input Element');
		$input->set_property('tag', 'input');
		$input->set_property('type', 'text', 'attr');
		$input->set_property('value', CNR_Field::uses_data(), 'attr');
		$cnr_field_types[$input->id] =& $input;
		
		$text = new CNR_Field_Type('text', 'input');
		$text->set_description('Text Box');
		$text->set_property('size', 15, 'attr');
		$text->set_property('label');
		$text->set_layout('form', '{label ref_base="layout"} {inherit}');
		$cnr_field_types[$text->id] =& $text;
		
		$location = new CNR_Field_Type('location');
		$location->set_description('Geographic Coordinates');
		$location->set_element('latitude', 'text', array( 'size' => 3, 'label' => 'Latitude' ));
		$location->set_element('longitude', 'text', array( 'size' => 3, 'label' => 'Longitude' ));
		$location->set_layout('form', '<span>{latitude ref_base="elements"}</span>, <span>{longitude ref_base="elements"}</span>');
		$cnr_field_types[$location->id] =& $location;
		
		$phone = new CNR_Field_Type('phone');
		$phone->set_description('Phone Number');
		$phone->set_element('area', 'text', array( 'size' => 3 ));
		$phone->set_element('prefix', 'text', array( 'size' => 3 ));
		$phone->set_element('suffix', 'text', array( 'size' => 4 ));
		$phone->set_layout('form', '({area ref_base="elements"}) {prefix ref_base="elements"} - {suffix ref_base="elements"}');
		$cnr_field_types[$phone->id] =& $phone;
		
		$hidden = new CNR_Field_Type('hidden');
		$hidden->set_parent('input');
		$hidden->set_description('Hidden Field');
		$hidden->set_property('type', 'hidden');
		$cnr_field_types[$hidden->id] =& $hidden;
		
		$span = new CNR_Field_Type('span');
		$span->set_description('Inline wrapper');
		$span->set_parent('base_closed');
		$span->set_property('tag', 'span');
		$span->set_property('value', 'Hello there!');
		$cnr_field_types[$span->id] =& $span;
		
		$select = new CNR_Field_Type('select');
		$select->set_description('Select tag');
		$select->set_parent('base_closed');
		$select->set_property('tag', 'select');
		$select->set_property('tag_option', 'option');
		$select->set_property('options', array());
		$select->set_layout('form', '{label ref_base="layout"} {form_start ref_base="layout"}{loop data="properties.options" layout="option" layout_data="option_data"}{form_end ref_base="layout"}');
		$select->set_layout('option', '<{tag_option} value="{data_ext id="option_value"}">{data_ext id="option_text"}</{tag_option}>');
		$select->set_layout('option_data', '<{tag_option} value="{data_ext id="option_value"}" selected="selected">{data_ext id="option_text"}</{tag_option}>');		
		$cnr_field_types[$select->id] =& $select;
		
		//Enable plugins to modify (add, remove, etc.) field types
		do_action_ref_array('cnr_register_field_types', array(&$cnr_field_types));
		
		//Content Types
		
		$ct = new CNR_Content_Type('post');
		$ct->set_title('Post');
		$ct->add_group('subtitle', 'Subtitle');
		$ct->add_field('subtitle', 'text', array('size' => '50', 'label' => 'Subtitle'));
		$ct->add_to_group('subtitle', 'subtitle');
		$cnr_content_types[$ct->id] =& $ct;
		
		$proj = new CNR_Content_Type('project');
		$proj->set_title('Project');
		$cnr_content_types[$proj->id] =& $proj;
		
		//Enable plugins to add/remove content types
		do_action_ref_array('cnr_register_content_types', array(&$cnr_content_types));
		//Enable plugins to modify content types after they have all been registered
		do_action_ref_array('cnr_post_register_content_types', array(&$cnr_content_types));
	}
	
	/**
	 * Registers hooks for content types
	 */
	function register_hooks() {
		//Register types
		add_action('init', $this->m('register_types'));
		
		//Add menus
		add_action('admin_menu', $this->m('admin_menu'));
		
		//Build UI on post edit form
		add_action('do_meta_boxes', $this->m('admin_do_meta_boxes'), 10, 3);
		
		//Get edit link for items
		add_filter('get_edit_post_link', $this->m('get_edit_item_url'), 10, 3);
		
		//Save Field data
		add_action('save_post', $this->m('save_item_data'), 10, 2);
		
		//Enqueue scripts for fields in current post type
		add_action('admin_enqueue_scripts', $this->m('enqueue_files'));
		
		//Modify post query for content type compatibility
		add_action('pre_get_posts', $this->m('pre_get_posts'), 20);
	}
	
	/*-** Handlers **-*/
	
	/**
	 * Modifies query parameters to be compatible with custom content types
	 * If a custom content type is specified in the 'post_type' query variable,
	 * query parameters will be modified to include posts of this type in the query
	 * @param obj $q Reference to WP_Query object being used to perform posts query
	 * @see WP_Query for reference
	 * @todo Make compabitible with WP 3.0 custom post types (stored in posts table instead of postmeta table)
	 */
	function pre_get_posts($q) {
		$qv =& $q->query_vars;
		$pt =& $qv['post_type'];
		
		$default_types = $this->get_default_post_types();
		
		//Unwrap array if only one post type is set within
		if ( is_array($pt) && count($pt) == 1 )
			$pt = implode($pt);
		//Use meta key/value for single custom post types
		if ( is_scalar($pt) && ! in_array($pt, $default_types) && $this->type_exists($pt) ) {
			$qv['meta_key'] = $this->get_type_meta_key();
			$qv['meta_value'] = serialize(array($pt));
			//Reset post type variable
			$pt = 'post';
		} elseif ( is_array($pt) && ( $custom_types = array_diff($pt, $default_types) ) && !empty($custom_types) ) {
			//Multiple post types specified
			global $wpdb;
			$compare = '{compare}';
			$compare_ids = '{ids}';
			$operator = 'IN';
			$id_query = "SELECT DISTINCT post_id from $wpdb->postmeta WHERE meta_key = %s AND meta_value $compare $compare_ids";
			//Check if only custom types are supplied
			if ( count($custom_types) == count($pt) ) {
				/* Query contains ONLY custom post types (use Inclusion - post__in) */
				$id_var = 'post__in';
			} else {
				/* Query contains default AND custom post types (use Exclusion - post__not_in) */
				$id_var = 'post__not_in';
				$operator = 'NOT IN';
			}
			
			$serialized = array();
			foreach ( $custom_types as $type ) {
				if ( $this->type_exists($type) ) {
					//Wrap type in array and serialize
					$serialized[] = serialize(array($type));
				}
			}
			$serialized = "('" . implode("','", $serialized) . "')";
			//Get matching post IDs
			$id_query = str_replace($compare_ids, $serialized, str_replace($compare, $operator, $id_query));
			$id_query = $wpdb->prepare($id_query, $this->get_type_meta_key());
			$ids = $wpdb->get_col($id_query);
			//Add IDs to appropriate query variable
			if ( is_array($qv[$id_var]) )
				$ids = array_unique( array_merge($ids, $qv[$id_var]) );
			$qv[$id_var] = $ids;
		}
	}
	
	/**
	 * Enqueues files for fields in current content type
	 * @param string $page Current context
	 */
	function enqueue_files($page = null) {
		$post = false;
		if ( isset($GLOBALS['post']) && !is_null($GLOBALS['post']) )
			$post = $GLOBALS['post'];
		elseif ( isset($_REQUEST['post_id']) )
			$post = $_REQUEST['post_id'];
		elseif ( isset($_REQUEST['post']) )
			$post = $_REQUEST['post'];
		
		//Get post's content type
		if ( !empty($post) ) {
			$ct =& $this->get_type($post);
			$file_types = array('scripts' => 'script', 'styles' => 'style');
			//Get content type fields
			foreach ( $ct->fields as $field ) {
				//Enqueue scripts/styles for each field
				foreach ( $file_types as $type => $func_base ) {
					$deps = $field->{"get_$type"}();
					foreach ( $deps as $handle => $args ) {
						//Confirm context
						if ( 'all' == $args['context'] || in_array($page, $args['context']) ) {
							$this->enqueue_file($func_base, $args['params']);
						}
					}
				}
			}
		}
	}
	
	/**
	 * Enqueues files
	 * @param string $type Type of file to enqueue (script or style)
	 * @param array $args (optional) Arguments to pass to enqueue function
	 */
	function enqueue_file($type = 'script', $args = array()) {
		$func = 'wp_enqueue_' . $type;
		if ( function_exists($func) ) {
			call_user_func_array($func, $args);
		}
	}
	
	/**
	 * Add admin menus for content types
	 */
	function admin_menu() {
		global $cnr_content_types;
		
		$pos = 21;
		foreach ( $cnr_content_types as $id => $type ) {
			$page = $this->get_admin_page_file($id);
			$callback = $this->m('admin_page');
			$access = 8;
			$pos += 1;
			$title = $type->get_title(true);
			if ( !empty($title) ) {
				//Main menu
				add_menu_page($type->get_title(true), $type->get_title(true), $access, $page, $callback, '', $pos);
				//Edit
				add_submenu_page($page, __('Edit'), __('Edit'), $access, $page, $callback);
				$hook = get_plugin_page_hookname($page, $page);
				add_action('load-' . $hook, $this->m('admin_menu_load_plugin'));
				//Add
				$page_add = $this->get_admin_page_file($id, 'add');
				add_submenu_page($page, __('Add New'), __('Add New'), $access, $page_add, $callback);
			}
		}
	}
	
	function admin_menu_load_plugin() {
		//Get Action
		$action = $this->util->get_action();
		switch ( $action ) {
			case 'edit' :
			case 'add'	:
				break;
			default		:
				wp_enqueue_script( $this->add_prefix('inline-edit-post') );
		}
	}
	
	/**
	 * Build admin page file name for the specified post type
	 * @param string|CNR_Content_Type $type Content type ID or object
	 * @param string $action Action to build file name for
	 * @param bool $sep_action Whether action should be a separate query variable (Default: false)
	 * @return string Admin page file name
	 */
	function get_admin_page_file($type, $action = '', $sep_action = false) {
		if ( isset($type->id) )
			$type = $type->id;
		$page = $this->add_prefix('post_type_' . $type);
		if ( !empty($action) ) {
			if ( $sep_action )
				$page .= '&amp;action=';
			else
				$page .= '-';
			
			$page .= $action;
		}
		return $page;
	}
	
	/**
	 * Populate administration page for content type
	 */
	function admin_page() {
		$prefix = $this->add_prefix('post_type_');
		if ( strpos($_GET['page'], $prefix) !== 0 )
			return false;
		
		//Get action
		$action = $this->util->get_action('manage');
		//Get content type
		$type = $_GET['page'];
		//Remove prefix
		if ( ($pos = strpos($type, $prefix)) === 0)
			$type = substr($type, strlen($prefix));
		//Remove action
		if ( ($pos = strrpos($type, '-')) && $pos !== false )
			$type = substr($type, 0, $pos);
		$type =& $this->get_type($type);
		global $title, $parent_file, $submenu_file;
		$title = $type->get_title(true);
		//$parent_file = $prefix . $type->id;
		//$submenu_file = $parent_file;
		
		switch ( $action ) {
			case 'edit' :
			case 'add' :
				$this->admin_page_edit($type, $action);
				break;
			default :
				$this->admin_page_manage($type, $action);
		}
	}
	
	/**
	 * Builds management page for items of a specific custom content type
	 * @param CNR_Content_Type $type Content Type to manage
	 * @param string $action Current action
	 */
	function admin_page_manage($type, $action) {
		if ( !current_user_can('edit_posts') )
			wp_die(__('You do not have sufficient permissions to access this page.'));
		global $title, $parent_file;
		$admin_path = ABSPATH . 'wp-admin/'; 
		
		global $plugin_page, $page_hook;
		//require_once(ABSPATH . 'wp-admin/admin.php');
		$add_url = $this->get_admin_page_url($type->id, 'add');
		
		//Get content items
		$query = array('post_type' => $type->id);
		wp($query);
		
		?>
		<div class="wrap">
		<?php screen_icon('edit'); ?>
		<h2><?php echo esc_html( $title ); ?> <a href="<?php echo $add_url; ?>" class="button add-new-h2"><?php echo esc_html_x('Add New', 'post'); ?></a> <?php
		if ( isset($_GET['s']) && $_GET['s'] )
			printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', esc_html( get_search_query() ) ); ?>
		</h2>
		<form id="posts-filter" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
		<?php 
		if ( have_posts() ) {
			include ($admin_path . 'edit-post-rows.php');
		}
		?>
		</form>
		<?php inline_edit_row('post'); ?>
		<div id="ajax-response"></div>
		<br class="clear" />
		</div>
		<?php
	}
	
	function admin_page_edit($type, $action) {
		$this->debug->print_message('Edit');
	}
	
	/**
	 * Adds meta boxes for post's content type
	 * Each group in content type is a separate meta box
	 * @param string $type Type of item meta boxes are being build for (post, page, link)
	 * @param string $context Location of meta box (normal, advanced, side)
	 * @param object $post Post object
	 */
	function admin_do_meta_boxes($type, $context, $post) {
		//Validate $type. Should be 'post' or 'page' for our purposes
		if ( 'post' != $type && 'page' != $type )
			return false;

		//TODO Determine actual content type of post
		$item_type = 'post';
		
		//Get content type definition
		$ct =& $this->get_type($item_type);

		//Pass processing to content type instance
		$ct->admin_do_meta_boxes($type, $context, $post);
	}
	
	
	/**
	 * Saves field data submitted for current post
	 * @param int $post_id ID of current post
	 * @param object $post Post object
	 */
	function save_item_data($post_id, $post) {
		if ( !isset($_POST['cnr']['attributes']) || empty($post_id) || empty($post) )
			return false;
		$prev_data = $this->get_item_data($post_id);
		
		//Get current field data
		$curr_data = $_POST['cnr']['attributes'];
		
		//Merge arrays together (new data overwrites old data)
		if ( is_array($prev_data) && is_array($curr_data) ) {
			$curr_data = array_merge($prev_data, $curr_data);
		}
		
		//Save to database
		update_post_meta($post_id, $this->get_fields_meta_key(), $curr_data);
	}
	
	
	/*-** Helpers **-*/
	
	/**
	 * Get array of default post types
	 * @return array Default post types
	 */
	function get_default_post_types() {
		return array('post', 'page', 'attachment', 'revision');
	}
	
	/**
	 * Checks if post's post type is a standard WP post type
	 * @param mixed $post_type Post type (default) or post ID/object to evaluate
	 * @see CNR_Content_Utilities::get_type() for possible parameter values
	 * @return bool TRUE if post is default type, FALSE if it is a custom type
	 */
	function is_default_post_type($post_type) {
		$type = $this->get_type($post_type);
		return in_array($type->id, $this->get_default_post_types());
	}
	
	/**
	 * Checks if specified content type has been defined
	 * @param string|CNR_Content_Type $type Content type ID or object
	 * @return bool TRUE if content type exists, FALSE otherwise
	 */
	function type_exists($type) {
		global $cnr_content_types;
		if ( ! is_scalar($type) ) {
			if ( is_a($type, 'CNR_Content_Type') )
				$type = $type->id;
			else
				$type = null;
		}
		return ( isset($cnr_content_types[$type]) );
	}
	
	/**
	 * Retrieves content type definition for specified content item (post, page, etc.)
	 * @param string|object $item Post object, or item type (string)
	 * @return CNR_Content_Type Reference to matching content type, empty content type if no matching type exists
	 */
	function &get_type($item) {
		$type = null;
		$post = $item;
		if ( ($post = get_post($post)) && isset($post->post_type) ) {
			//Get item type from Post object
			$type = $post->post_type;
			//Check for post_type in meta data if item type is standard type
			if ( $this->is_default_post_type($type) && ( $type_meta = get_post_meta($post->ID, $this->get_type_meta_key(), true) ) && !empty($type_meta) ) {
				$type = ( is_array($type_meta) ) ? implode($type_meta) : $type_meta;
			}
		} else {
			$type = $item;
		}
		
		global $cnr_content_types;
		if ( $this->type_exists($type) ) {
			//Retrieve content type from global array
			$type =& $cnr_content_types[$type];
		} else {
			//Create new empty content type if it does not already exist
			$type =& new CNR_Content_Type($type);
			//Add to global content types array (if valid ID provided)
			if ( !empty($type) )
				$cnr_content_types[$type->id] =& $type;
		}
		
		return $type;
	}
	
	/**
	 * Retrieve meta key for post fields
	 * @return string Fields meta key
	 */
	function get_fields_meta_key() {
		return $this->make_meta_key('fields');
	}
	
	/**
	 * Retrieve meta key for post type
	 * @return string Post type meta key
	 */
	function get_type_meta_key() {
		return $this->make_meta_key('post_type');
	}
	
	/**
	 * Checks if post contains specified field data
	 * @param Object $post (optional) Post to check data for
	 * @param string $field (optional) Field ID to check for
	 * @return bool TRUE if data exists, FALSE otherwise
	 */
	function has_item_data($item = null, $field = null) {
		$ret = $this->get_item_data($item, $field, 'raw', null);
		return ( !empty($ret) || $ret === 0 );
	}
	
	/**
	 * Retrieve specified field data from content item (e.g. post)
	 * Usage Examples:
	 * get_item_data($post_id, 'field_id')
	 *  - Retrieves field_id data from global $post object
	 *  - Field data is formatted using 'display' layout of field
	 *  
	 * get_item_data($post_id, 'field_id', 'raw')
	 *  - Retrieves field_id data from global $post object
	 *  - Raw field data is returned (no formatting)
	 *  
	 * get_item_data($post_id, 'field_id', 'display', $post_id)
	 *  - Retrieves field_id data from post matching $post_id
	 *  - Field data is formatted using 'display' layout of field
	 *  
	 * get_item_data($post_id, 'field_id', null)
	 *  - Retrieves field_id data from post matching $post_id
	 *  - Field data is formatted using 'display' layout of field
	 *    - The default layout is used when no valid layout is specified
	 *
	 * get_item_data($post_id)
	 *  - Retrieves full data array from post matching $post_id
	 *  
	 * @param int|object $item(optional) Content item to retrieve field from (Default: null - global $post object will be used)
	 * @param string $field ID of field to retrieve
	 * @param string $layout(optional) Layout to use when returning field data (Default: display)
	 * @return mixed Specified field data 
	 */
	function get_item_data($item = null, $field = null, $layout = null, $default = '', $attr = null) {
		$ret = $default;
		
		//Get item
		$item = get_post($item);
			
		if ( !isset($item->ID) )
			return $ret;
		
		//Get item data
		$data = get_post_meta($item->ID, $this->get_fields_meta_key(), true);
		
		//Get field data
		
		//Set return value to data if no field specified
		if ( empty($field) || !is_string($field) )
			$ret = $data;
		//Stop if no valid field specified
		if ( !isset($data[$field]) ) {
			//TODO Check $item object to see if specified field exists (e.g. title, post_status, etc.)
			return $ret;
		}

		$ret = $data[$field];
		
		//Initialize layout value
		$layout_def = 'display';
		
		if ( !is_scalar($layout) || empty($layout) )
			$layout = $layout_def;

		$layout = strtolower($layout);
					
		//Check if raw data requested
		if ( 'raw' == $layout )
			return $ret;
		
		/* Build specified layout */
		
		//Get item's content type
		$ct =& $this->get_type($item);
		$ct->set_data($data);
		
		//Get field definition
		$fdef =& $ct->get_field($field);
		
		//Validate layout
		if ( !$fdef->has_layout($layout) )
			$layout = $layout_def;
		
		//Build layout
		$ret = $fdef->build_layout($layout, $attr);
		
		//Return formatted value
		return $ret;
	}
	
	/**
	 * Prints an item's field data
	 * @see CNR_Content_Utilities::get_item_data() for more information
	 * @param int|object $item(optional) Content item to retrieve field from (Default: null - global $post object will be used)
	 * @param string $field ID of field to retrieve
	 * @param string $layout(optional) Layout to use when returning field data (Default: display)
	 */
	function the_item_data($item = null, $field = null, $layout = null, $default = '', $attr = null) {
		//echo apply_filters('cnr_the_item_data', $this->get_item_data($item, $field, $layout, $default), $item, $field, $layout, $default);
		echo $this->get_item_data($item, $field, $layout, $default, $attr);
	}
	
	/**
	 * Build Admin URL for specified post type
	 * @param string|CNR_Content_Type $type Content type ID or object
	 * @param string $action Action to build URL for
	 * @param bool $sep_action Whether action should be a separate query variable (Default: false)
	 * @return string Admin page URL
	 */
	function get_admin_page_url($type, $action = '', $sep_action = false) {
		$url = admin_url('admin.php');
		$url .= '?page=' . $this->get_admin_page_file($type, $action, $sep_action);
		return $url; 
	}
	
	function get_edit_item_url($edit_url, $item_id, $context) {
		//Get post type
		$type = $this->get_type($item_id);
		if (  ! $this->is_default_post_type($type->id) && $this->type_exists($type) ) {
			$edit_url = $this->get_admin_page_url($type, 'edit', true) . '&amp;post=' . $item_id;
		}
		
		return $edit_url;
	}
}
?>
