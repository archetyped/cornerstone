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

function cnr_get_post_data($field, $post = null) {
	$c = new CNR_Content_Utilities();
	return $c->get_item_data($post, $field);
}

function cnr_the_post_data($field, $post = null) {
	echo cnr_get_post_data($field, $post);
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
		if ( ( !$this->path_isset($path) /* Value does not exist in current object */
				|| ( ($val = $this->get_path_value($path)) /* Assigns variable */
					&& !is_object($val)
					&& ( is_array($val) /* Retrieved value is an array */
						|| ($inherit = strpos($val, $inherit_tag)) !== false /* Retrieved val inherits a value from another  */
						)
					)
				)
				&& 'current' != $dir
			) {
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
	 * Set field type description
	 * @param string $description Description for field typ
	 */
	function set_description($description = '') {
		$this->description = attribute_escape( trim($description) );
	}
	
	function get_description() {
		return $this->get_member_value('description', '','','current');
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
		$this->properties[$name] = $prop_arr;
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
		if ( isset($val['value']) )
			$val = $val['value'];
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
		
			while ($ph->match = $this->parse_layout($out, $ph->pattern_layout)) {
				//Iterate through the different types of layout placeholders
				foreach ($ph->match as $tag => $instances) {
					//Iterate through instances of a specific type of layout placeholder
					foreach ($instances as $instance) {
						//Get nested layout
						$nested_layout = $this->get_member_value($instance);
	
						//Replace layout placeholder with retrieved item data
						if ( !empty($nested_layout) )
							$out = str_replace($ph->start . $instance['match'] . $ph->end, $nested_layout, $out);
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
				&& isset($ph_output['value'])
				&& is_scalar($ph_output['value'])
			) {
				/* Targeted property is an array but has a value key */
				/* We use the value of this key */
				$ph_output = $ph_output['value'];
				
			} elseif (is_array($ph_output)
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
		if ( isset($loop_data['value']) )
			$loop_data = $loop_data['value'];
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
	function CNR_Content_Type($id = '') {
		$this->__construct($id);
	}
	
	/**
	 * Class constructor
	 * @param string $id Conten type ID
	 */
	function __construct($id = '') {
		parent::__construct($id);
	}
	
	/* Getters/Setters */
	
	/**
	 * Adds group to content type
	 * Groups are used to display related fields in the UI 
	 * @param string $id Unique name for group
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
		$group->title = trim($title);
		$group->description = trim($description);
		$group->location = trim($location);
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
		
		$image = new CNR_Field_Type('image');
		$image->set_parent('base');
		$image->set_description('Image');
		$image->set_property('tag', 'img');
		$image->set_property('src', '/wp-admin/images/wp-logo.gif', 'attr');
		$cnr_field_types[$image->id] =& $image;
		
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
		
		$attachment = new CNR_Field_Type('media');
		$attachment->set_description('Media Item');
		$attachment->set_parent('base_closed');
		$attachment->set_property('title', 'Select Media');
		$attachment->set_layout('form', '{media}');
		$cnr_field_types[$attachment->id] =& $attachment;
		
		//Enable plugins to modify (add, remove, etc.) field types
		do_action('cnr_register_field_types');
		
		//Content Types
		
		$ct = new CNR_Content_Type('post');
		$ct->set_description('Standard Post');
		$ct->add_group('subtitle', 'Subtitle');
		$ct->add_field('subtitle', 'text', array('size' => '50', 'label' => 'Subtitle'));
		$ct->add_to_group('subtitle', 'subtitle');
		$ct->add_group('image_thumbnail', 'Post Thumbnail (F)');
		$ct->add_field('image_thumbnail', 'media');
		$ct->add_to_group('image_thumbnail', 'image_thumbnail');
		$ct->add_group('image_header', 'Post Header (F)');
		$ct->add_field('image_header', 'media');
		$ct->add_to_group('image_header', 'image_header');
		$cnr_content_types[$ct->id] =& $ct;
		
		//Enable plugins to modify (add, remove, etc.) content types
		do_action('cnr_register_content_types');
	}
	
	/**
	 * Registers hooks for content types
	 */
	function register_hooks() {
		//Register types
		add_action('init', $this->m('register_types'));
		
		//Build UI on post edit form
		add_action('do_meta_boxes', $this->m('admin_do_meta_boxes'), 10, 3);
		
		//Save Field data
		add_action('save_post', $this->m('save_item_data'), 10, 2);
	}
	
	/**
	 * Retrieves content type definition for specified content item (post, page, etc.)
	 * @param string|object $item Post object, or item type (string)
	 * @return CNR_Content_Type Matching content type, empty content type if no matching type exists
	 */
	function &get_type($item) {
		$type = null;
		//Get item type from Post object
		if ( is_object($item) ) {
			//TODO retrieve content type for content item
		}
		global $cnr_content_types;
		if ( isset($cnr_content_types[$item]) ) {
			$type =& $cnr_content_types[$item];
		} else {
			$type =& new CNR_Content_Type($item);
		}
		
		return $type;
	}
	
	function get_meta_key() {
		return '_cnr_fields';
	}
	
	/**
	 * Checks if post contains specified field data
	 * @param Object $post (optional) Post to check data for
	 * @param string $field (optional) Field ID to check for
	 * @return bool TRUE if data exists, FALSE otherwise
	 */
	function has_item_data($post = null, $field = null) {
		$ret = $this->get_item_data($post, $field, null);
		return ( empty($ret) ) ? false : true;
	}
	
	/**
	 * Retrieve field data for item
	 * @param object|int $post Post object or Post ID
	 * @param string $field ID of field to retrieve data for
	 * @return mixed Field data array for post, or data for single field if specified
	 */
	function get_item_data($post = null, $field = null, $default = array()) {
		$ret = $default;
		if ( $this->util->check_post($post) ) {
			$ret = get_post_meta($post->ID, $this->get_meta_key(), true);
			if ( is_string($field) && ($field = trim($field)) && !empty($field) ) {
				$ret = ( isset($ret[$field]) ) ? $ret[$field] : '';
			}
		}
		return $ret; 
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
		update_post_meta($post_id, $this->get_meta_key(), $curr_data);
	}
}
?>