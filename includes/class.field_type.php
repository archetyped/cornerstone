<?php
/**
 * Content Type - Field Types
 * Stores properties for a specific field
 * @package Cornerstone
 * @subpackage Content Types
 * @author Archetyped
 */
class CNR_Field_Type extends CNR_Content_Base {
	/* Properties */
	
	const USES_DATA = '{data}';
	
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
	 * Constructor
	 */
	function __construct($id = '', $parent = null) {
		parent::__construct($id);

		$this->id = $id;
		$this->set_parent($parent);
	}

	/* Getters/Setters */

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
		unset($this->caller);
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
	 * Checks if layout content is valid
	 * Layouts need to have placeholders to be valid
	 * @param string $layout_content Layout content (markup)
	 * @return bool TRUE if layout is valid, FALSE otherwise
	 */
	function is_valid_layout($layout_content) {
		$ph = $this->get_placeholder_defaults();
		return preg_match($ph->pattern_general, $layout_content);
	}

	/**
	 * Parse field layout with a regular expression
	 * @param string $layout Layout data
	 * @param string $search Regular expression pattern to search layout for
	 * @return array Associative array containing all of the regular expression matches in the layout data
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

	/**
	 * Retrieves default properties to use when evaluating layout placeholders
	 * @return object Object with properties for evaluating layout placeholders
	 */
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
		$out_default = '';

		/* Layout */

		//Get base layout
		$out = $this->get_layout($layout);

		//Only parse valid layouts
		if ( $this->is_valid_layout($out) ) {
			//Parse Layout
			$ph = $this->get_placeholder_defaults();

			//Search layout for placeholders
			while ( $ph->match = $this->parse_layout($out, $ph->pattern_general) ) {
				//Iterate through placeholders (tag, id, etc.)
				foreach ( $ph->match as $tag => $instances ) {
					//Iterate through instances of current placeholder
					foreach ( $instances as $instance ) {
						//Process value based on placeholder name
						$target_property = apply_filters('cnr_process_placeholder_' . $tag, '', $this, $instance, $layout, $data);

						//Process value using default processors (if necessary)
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
		} else {
			$out = $out_default;
		}

		/* Return generated value */

		return $out;
	}

	/*-** Static Methods **-*/

	/**
	 * Returns indacator to use field data (in layouts, property values, etc.)
	 */
	function uses_data() {
		return self::USES_DATA;
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
	static function register_placeholder_handler($placeholder, $handler, $priority = 10) {
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
	 * @param array $data Extended data for field
	 * @return string Value to use in place of current placeholder
	 */
	static function process_placeholder_default($ph_output, $field, $placeholder, $layout, $data) {
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
	static function process_placeholder_id($ph_output, $field, $placeholder, $layout, $data) {
		//Get attributes
		$args = wp_parse_args($placeholder['attributes'], array('format' => 'attr_id')); 
		return $field->get_id($args);
	}
	
	/**
	 * Build Field name attribute
	 * Name is formatted as an associative array for processing by PHP after submission
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	static function process_placeholder_name($ph_output, $field, $placeholder, $layout, $data) {
		//Get attributes
		$args = wp_parse_args($placeholder['attributes'], array('format' => 'default')); 
		return $field->get_id($args);
	}

	/**
	 * Retrieve data for field
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	static function process_placeholder_data($ph_output, $field, $placeholder, $layout) {
		$val = $field->get_data();
		if ( !is_null($val) ) {
			$ph_output = $val;
			$attr =& $placeholder['attributes'];
			//Get specific member in value (e.g. value from a specific field element)
			if ( isset($attr['element']) && is_array($ph_output) && ( $el = $attr['element'] ) && isset($ph_output[$el]) )
				$ph_output = $ph_output[$el];
			if ( isset($attr['format']) && 'display' == $attr['format'] )
				$ph_output = nl2br($ph_output);
		}

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
	static function process_placeholder_loop($ph_output, $field, $placeholder, $layout, $data) {
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
	static function process_placeholder_data_ext($ph_output, $field, $placeholder, $layout, $data) {
		if ( isset($placeholder['attributes']['id']) && ($key = $placeholder['attributes']['id']) && isset($data[$key]) ) {
			$ph_output = strval($data[$key]);
		}

		return $ph_output;
	}
	
	/**
	 * WP Editor
	 * @see CNR_Field_Type::process_placeholder_default for parameter descriptions
	 * @return string Placeholder output
	 */
	static function process_placeholder_rich_editor($ph_output, $field, $placeholder, $layout, $data) {
		$id = $field->get_id( array (
			'format' => 'attr_id'
		));
		$settings = array (
			'textarea_name' => $field->get_id( array (
				'format' => 'default'
			))
		);
		ob_start();
		wp_editor($field->get_data(), $id, $settings);
		$out = ob_get_clean();
		return $out;
	}
}