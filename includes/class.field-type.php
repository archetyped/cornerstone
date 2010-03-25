<?php

require_once 'class.base.php';

/**
 * Content Type - Field Types
 * Stores properties for a specific field
 * @package Cornerstone
 * @subpackage Content Types
 * @author SM
 *
 */
class CNR_Field_Type extends CNR_Base {
	/* Properties */
	
	/**
	 * Base class name
	 * @var string
	 */
	var $base_class = 'cnr_field_type';
	
	/**
	 * @var string Unique name for field type
	 */
	var $id = '';
	
	/**
	 * @var string Short description of field type
	 */
	var $description = '';
	
	/**
	 * @var array Array of Field types that make up current Field type
	 */
	var $elements = array();
	
	/**
	 * @var array Field type properties
	 */
	var $properties = array();
	
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
	 * Data for field
	 * May also contain data for nested fields
	 * @var mixed
	 */
	var $data = null;
	
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
		parent::__construct();
		
		$this->id = $id;
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
		
		//Process path to value
		$args = func_get_args();
		$testval = $this->get_path_value($path);
		//if ( is_object($testval) ) $this->debug->print_message('Path', $path, 'Value', $testval);
		
		/* Determine whether the value must be retrieved from a parent/container object
		 * Conditions:
		 * > Path does not exist in current field
		 * > Path exists and is not an object, but at least one of the following is true:
		 *   > Value at path is an array (e.g. properties, elements, etc. array)
		 *     > Parent/container values should be merged with retrieved array
		 *   > Value at path is a string that inherits from another field
		 *     > Value from other field will be retrieved and will replace inheritance placeholder in retrieved value
		 */ 
		if ( !$this->path_isset($path) /* Value does not exist in current object */
			|| ( ($val = $this->get_path_value($path)) /* Assigns variable */
				&& !is_object($val)
				&& ( is_array($val) /* Retrieved value is an array */
					|| ($inherit = strpos($val, $inherit_tag)) !== false /* Retrieved val inherits a value from another  */
					)
				)
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
	 * Search for specified member value in field type ancestors
	 * @param string $member Name of object member to search (e.g. properties, layout, etc.)
	 * @param string $name Value to retrieve from member
	 * @return mixed Member value if found (Default: empty string)
	 */
	function get_parent_value($member, $name = '', $default = '') {
		$ret = '';
		$parent =& $this->get_parent();
		if ( is_object($parent) )		
			$ret = $parent->get_member_value($member, $name, $default);
		return $ret;
	}
	
/**
	 * Search for specified member value in field's container object (if exists)
	 * @param string $member Name of object member to search (e.g. properties, layout, etc.)
	 * @param string $name Value to retrieve from member
	 * @return mixed Member value if found (Default: empty string)
	 */
	function get_container_value($member, $name = '', $default = '') {
		$ret = '';
		$container =& $this->get_container();
		if ( is_object($container) && method_exists($container, 'get_member_value') )
			$ret = $container->get_member_value($member, $name, $default, 'container');
		return $ret;
	}
	
	/**
	 * Retrieves field ID
	 * @param string|CNR_Field $field [optional] Field object or ID of field
	 * @return string|bool Field ID, FALSE if $field is invalid
	 */
	function get_id($field = null) {
		$ret = false;
		if ( !empty($field) ) {
			if ( is_a($field, 'cnr_field_type') )
				$field = $field->id;
		} elseif ( isset($this) ) {
			$field = $this->id;
		}

		if ( is_string($field) )
			$ret = trim($field);
		
		return $ret;
	}
	
	/**
	 * Retrieve value from data member
	 * @param string|array $name Name of value to retrieve (Can also be path to value)
	 * @return mixed Value at specified path
	 */
	function get_data($path = '') {
		return $this->get_member_value('data', $name, '');
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
	 * Sets reference to parent field type
	 * @param CNR_Field_Type $parent Parent field type
	 */
	function set_parent(&$parent) {
		//Validate parent object
		if ( is_array($parent) && !empty($parent) )
			$parent =& $parent[0];
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
	 * Set field type description
	 * @param string $description Description for field typ
	 */
	function set_description($description = '') {
		$this->description = attribute_escape( trim($description) );
	}
	
	/**
	 * Add/Set a property on the field definition
	 * @param string $name Name of property
	 * @param mixed $value Default value for property
	 * @param string|array $group Group(s) property belongs to
	 * @param boolean $uses_data Whether or not property uses data from the content item
	 * @return boolean TRUE if property is successfully added to field type, FALSE otherwise
	 * TODO: Implement $uses_data flag
	 */
	function set_property($name, $value = '', $group = null, $uses_data = false) {
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
	function set_element($name, &$type, $properties = array(), $id_prop = 'id') {
		$name = trim(strval($name));
		if ( empty($name) )
			return false;
		//Create new field for element
		$el = new CNR_Field($name, array(&$type));
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
	 * @return string Specified layout text
	 */
	function get_layout($name = 'form') {
		return $this->get_member_value('layout', $name);
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
					if (!isset($parse_result['values'][$instance]))
					continue;
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
	 * @param string|array $field_path Base ID for field
	 * @param array $attr_data Data for current field (Passed by reference)
	 * @param bool $parent_match TRUE if $attr_data contains a reference to the current field's parent FALSE if not (to avoid searching data for current item if parent is not in data)
	 */
	function build_layout($layout = 'form', $field_path = array()/*, &$attr_data, $parent_matched = true*/) {
		$args = func_get_args();
		$out = '';

		/* Layout */
		
		//Get base layout
		$out = $this->get_layout($layout);
		
		//Parse Layout
		$ph = $this->get_placeholder_defaults();
		
		//Find all nested layouts in current layout
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
		
		//Search layout for placeholders
		if ($ph->match = $this->parse_layout($out, $ph->pattern_general)) {
			//Iterate through placeholders (tag, id, etc.)
			foreach ($ph->match as $tag => $instances) {
				//Iterate through instances of current placeholder
				foreach ($instances as $instance) {
					//TODO Add hooks to allow plugins to process custom placeholders
					
					//Filter value based on placeholder name
					$target_property = apply_filters('cnr_process_placeholder_' . $tag, '', $this, $instance, $layout);
					
					//Filter value using general filters (if necessary)
					if ( empty($target_property) ) {
						$target_property = apply_filters('cnr_process_placeholder', $target_property, $this, $instance, $layout);
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
		
		//Remove any unprocessed placeholders from layout
		if ($ph->match = $this->parse_layout($out, $ph->pattern_general)) {
			$out = preg_replace($ph->pattern_general, '', $out);
		}
		
		/* Return generated value */
		
		return $out;
	}
	
	/*-** Static Methods **-*/
	
	/**
	 * Register a function to handle a placeholder
	 * Multiple handlers may be registered for a single placeholder
	 * Basically a wrapper function to facilitate adding hooks for placeholder processing
	 * @uses add_filter()
	 * @param string $placeholder Name of placeholder to add handler for (Using 'all' will set the function as a handler for all placeholders
	 * @param callback $handler Function to set as a handler
	 * @param int $priority [optional] Priority of handler
	 */
	function register_placeholder_handler($placeholder, $handler, $priority = 10) {
		global $cnr_debug;
		if ( 'all' == $placeholder )
			$placeholder = '';
		else
			$placeholder = '_' . $placeholder;
		
		add_filter('cnr_process_placeholder' . $placeholder, $handler, $priority, 4);
	}
	
	/**
	 * Default placeholder processing
	 * To be executed when current placeholder has not been handled by another handler
	 * @param string $target_property Value to be used in place of placeholder
	 * @param CNR_Field $field Field containing placeholder
	 * @param array $placeholder Current placeholder
	 * @see CNR_Field::parse_layout for structure of $placeholder array
	 * @param string $layout Layout to build
	 * @return string Value to use in place of current placeholder
	 */
	function process_placeholder_default($target_property, $field, $placeholder, $layout) {
		//Validate parameters before processing
		if ( empty($target_property) && is_a($field, 'CNR_Field_Type') && is_array($placeholder) ) {
			//Build path to replacement data
			$target_property = $field->get_member_value($placeholder);

			//Check if value is group (properties, etc.)
			//All groups must have additional attributes (beyond reserved attributes) that define how items in group are used
			if (is_array($target_property) 
				&& isset($target_property['value'])
				&& is_scalar($target_property['value'])
			) {
				/* Targeted property is an array but has a value key */
				/* We use the value of this key */
				$target_property = $target_property['value'];
				
			} elseif (is_array($target_property)
				&& !empty($placeholder['attributes'])
				&& is_array($placeholder['attributes'])
				&& ($ph = $field->get_placeholder_defaults())
				&& $attribs = array_diff(array_keys($placeholder['attributes']), array_values($ph->reserved))
			) {
				/* Targeted property is an array, but the placeholder contains additional options on how property is to be used */
				
				//Find items matching criteria in $target_property
				//Check for group criteria
				//TODO: Implement more robust/flexible criteria handling (2010-03-11: Currently only processes property groups)
				if ( 'properties' == $placeholder['tag'] && ($prop_group = $field->get_group($placeholder['attributes']['group'])) && !empty($prop_group) ) {
					/* Process group */
					$group_out = array();
					//Iterate through properties in group and build string
					foreach ( $prop_group as $prop_key => $prop_val ) {
						$group_out[] = $prop_key . '="' . $field->get_property($prop_key) . '"'; 
					}
					$target_property = implode(' ', $group_out);
				}
			} elseif ( is_object($target_property) && is_a($target_property, $field->base_class) ) {
				/* Targeted property is actually a nested field */
				//Set caller to current field
				$target_property->set_caller($field);
				//Build layout for nested element
				$target_property = $target_property->build_layout($layout);
			}
		}
		
		return $target_property;
	}
	
	/**
	 * Build Field ID attribute
	 * Uses containing objects in ID value
	 * @param string $target_property Value to replace placeholder with
	 * @param CNR_Field $field Field being processed
	 * @param array $placeholder Placeholder properties
	 * @param string $layout Current layout being processed
	 */
	function process_placeholder_id($target_property, $field, $placeholder, $layout) {
		$c = $field;
		$field_id = array();
		while ( !!$c ) {
			//Add ID of current field to array
			if ( isset($c->id) )
				$field_id[] = $c->id;
			$c = $c->get_caller();
		}
		//Convert array to string
		return implode('_', array_reverse($field_id));
	}
	
}

class CNR_Field extends CNR_Field_Type {

}

class CNR_Content_Type extends CNR_Base {
	
	/**
	 * Unique name for content type
	 * @var string
	 */
	var $id = '';
	
	/**
	 * Description
	 * @var string
	 */
	var $description = '';
	
	/**
	 * Indexed array of fields in content type
	 * @var array
	 */
	var $fields = array();
	
	/**
	 * Associative array of groups in conten type
	 * Key: Group name
	 * Value: Array of field references
	 * @var array
	 */
	var $groups = array();
	
	/**
	 * Data for content type
	 * @var array
	 */
	var $data = null;
	
	/* Constructors */
	
	/**
	 * Legacy constructor
	 * @param string $id Used for content type ID
	 */
	function CNR_Content_Type($id) {
		$this->__construct($id);
	}
	
	function __construct($id) {
		parent::__construct();
		$id = trim($id);
		$this->id = $id;
	}
	
	/* Getters/Setters */
	
	/**
	 * Sets description for content type
	 * @param string $description Description text
	 */
	function set_description($description = '') {
		if ( empty($description) )
			$description = '';
		$this->description = $description;
	}
	
	/**
	 * Adds group to content type
	 * Groups are used to display related fields in the UI 
	 * @param string $id Unique name for group
	 * @param string $description Short description of group's purpose
	 * @param string $location Where group will be displayed on post edit form (Default: main)
	 */
	function &add_group($id, $description = '', $location = 'main') {
		//Create new group and set properties
		$id = trim($id);
		$this->groups[$id] =& $this->create_group($description, $location);
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
	 * @param string $description Short description of group's purpose
	 * @param string $location Where group will be displayed on post edit form (Default: main)
	 * @return object Group object
	 */
	function &create_group($description = '', $location = 'main') {
		$group = new stdClass();
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
	 * @param CNR_Field_Type $parent Field type that this field is based on
	 * @param array $properties [optional] Field properties
	 * @param string $group [optional] Group ID to add field to
	 * @return CNR_Field Reference to new field
	 */
	function &add_field($id, &$parent, $properties = array(), $group = null) {
		//Create new field
		global $ct_debug;
		$ct_debug = true;
		$id = trim(strval($id));
		$field = new CNR_Field($id);
		$field->set_parent($parent);
		$field->set_container($this);
		$field->set_properties($properties);
	
		//Add field to content type
		$this->fields[$id] =& $field;
		//Add field to group
		$this->add_to_group($group, $field);
		$ct_debug = false;
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
	 * Adds field to a group in the content type
	 * Group is created if it does not already exist
	 * @param string $group ID of group to add field to
	 * @param CNR_Field $field Field to add to group
	 */
	function add_to_group($group, &$field) {
		//Validate parameters
		$group = trim(strval($group));
		if ( empty($group) || empty($field) || !is_a($field, 'CNR_Field_Type') )
			return false;
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
	 * @param string $group [optional] Group ID to remove field from
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
	
	function &get_group($group) {
		$group = trim($group);
		//Create group if it doesn't already exist
		if ( !$this->group_exists($group) )
			$this->add_group($group);
		
		return $this->groups[$group];
	}
	
	/**
	 * Output fields in a group
	 * @param string $group Group to output
	 * @return string Group output
	 */
	function build_group($group) {
		$out = '';
		//Stop execution if group does not exist
		if ( !$this->group_exists($group) )
			return $out;
		
		//Get specified group
		$group =& $this->get_group($group);
			
		//Build layout for each field in group
		foreach ( $group->fields as $field ) {
			$out .= $field->build_layout();
		}
		
		//Return group output
		return $out;
	}
	
	/**
	 * Set data for a field
	 * @param string|CNR_Field $field Reference or ID of Field to set data for
	 * @param mixed $value Data to set
	 */
	function set_data($field, $value = null) {
		//Prepare field
		
	}
}

function cnr_register_placeholder_handler($placeholder, $handler, $priority = 10) {
	CNR_Field_Type::register_placeholder_handler($placeholder, $handler, $priority);
}

/* Hooks */
cnr_register_placeholder_handler('all', array('CNR_Field_Type', 'process_placeholder_default'));
cnr_register_placeholder_handler('field_id', array('CNR_Field_Type', 'process_placeholder_id'));
$base = new CNR_Field_Type('base');
$base->set_description('Default Element');
$base->set_property('tag', 'span');
//$base->set_property('id', '', 'attr');
$base->set_property('class', '', 'attr');
$base->set_layout('form', '<{tag} name="{field_id}" id="{field_id}" {properties ref_base="root" group="attr"} />');

$base_closed = new CNR_Field_Type('base_closed');
$base_closed->set_parent($base);
$base_closed->set_description('Default Element (Closed Tag)');
$base_closed->set_property('value');
$base_closed->set_layout('form', '<{tag} {properties ref_base="root" group="attr"}>{value}</{tag}>');

$input = new CNR_Field_Type('input');
$input->set_parent($base);
$input->set_description('Default Input Element');
$input->set_property('tag', 'input');
$input->set_property('type', 'text', 'attr');
$input->set_property('value', '', 'attr', true);

$text = new CNR_Field_Type('text', array(&$input));
$text->set_description('Text Box');
$text->set_property('size', 15, 'attr');
$text->set_property('label');
$text->set_layout('form', '{label ref_base="layout"} {inherit}');
$text->set_layout('label', '<label for="{field_id}">{label}</label>');

$location = new CNR_Field_Type('location');
$location->set_description('Geographic Coordinates');
$location->set_element('latitude', $text, array( 'size' => 3, 'label' => 'Latitude' ));
$location->set_element('longitude', $text, array( 'size' => 3, 'label' => 'Longitude' ));
$location->set_layout('form', '<span>{latitude ref_base="elements"}</span>, <span>{longitude ref_base="elements"}</span>');

$phone = new CNR_Field_Type('phone');
$phone->set_description('Phone Number');
$phone->set_element('area', $text, array( 'size' => 3 ));
$phone->set_element('prefix', $text, array( 'size' => 3 ));
$phone->set_element('suffix', $text, array( 'size' => 4 ));
$phone->set_layout('form', '({area ref_base="elements"}) {prefix ref_base="elements"} - {suffix ref_base="elements"}');

$hidden = new CNR_Field_Type('hidden');
$hidden->set_parent($input);
$hidden->set_description('Hidden Field');
$hidden->set_property('type', 'hidden');

$image = new CNR_Field_Type('image');
$image->set_parent($base);
$image->set_description('Image');
$image->set_property('tag', 'img');
$image->set_property('src', '/wp-admin/images/wp-logo.gif', 'attr');

$span = new CNR_Field_Type('span');
$span->set_description('Inline wrapper');
$span->set_parent($base_closed);
$span->set_property('tag', 'span');
$span->set_property('value', 'Hello there!');

/* Sample Content Type creation */
$ct = new CNR_Content_Type('post');
$ct->set_description('Standard Post');
$ct->add_group('location', 'Geographic Details');
$field =& $ct->add_field('coordinates', $location, array('label' => 'Location'), 'location');
$field =& $ct->add_field('subtitle', $text, array('size' => '50', 'label' => 'Subtitle'));
$ct->add_to_group('location', $field);
/* END Sample Content Type creation */
?>