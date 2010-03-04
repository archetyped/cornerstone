<?php

require_once 'class.base.php';

/**
 * Content Types Class
 *
 * @package Cornerstone
 * @author SM
 */
class CNR_Content_Types extends CNR_Base {

	/* Variables */
	
	/**
	 * @var string Base name for Content Type Admin Menus
	 */
	var $file_content_types = 'content-types';

	/**
	 * @var array Content Types
	 */
	var $types = array();

	/**
	 * @var array Field Type Definitions
	 */
	var $field_types = array();

	/**
	 * @var string Field reference delimeter
	 */
	var $field_path_delim = '.';

	/**
	 * @var string Property that is set when field is populated
	 */
	var $field_populated = 'populated';

	/**
	 * @var array Reserved attribute names for Field placeholders (Layouts)
	 */
	var $field_ph_attr_reserved = array();

	/**
	 * @var string Base path for references in placeholders
	 */
	var $field_ph_base_default = "properties";

	/**
	 * @var array Content Type Definitions
	 */
	var $content_types = array();

	/**
	 * @var string base name for all content type metadata
	 */
	var $attribute_basename = array('attributes');

	/**
	 * Legacy contructor
	 */
	function CNR_Content_Types() {
		$this->__construct();
	}

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		
		//Add prefix to attribute base array
		array_unshift($this->attribute_basename, $this->prefix);

		$this->field_ph_attr_reserved['ref_base'] = "ref_base";

		$this->register_hooks();
		$this->set_fields();
		$this->set_types();
	}

	/* Methods */

	/* Initialization */

	/**
	 * Sets up hooks for class
	 */
	function register_hooks() {
		//Create admin menus for Content Types
		add_action('admin_menu', $this->m('admin_menu'));

		//Add attributes to post edit form
		add_action('do_meta_boxes', $this->m('item_edit_form'), 10, 3);

		//Save item attributes when item is saved
		add_action('save_post', $this->m('save_item_attributes'), 10, 2);
	}

	/**
	 * Initialize fields
	 */
	function set_fields() {
		$this->field_types = array (
			'base'			=> array (
				'description'	=> 'Default Element',
				'properties'	=> array (
					'tag'			=> 'span',
					'class'			=> array (
						'group'			=> 'attr',
						'value'			=> ''
					),
					'id'			=> array (
						'group'			=> 'attr',
						'value'			=> ''
					)
				),
				'layout'		=> array (
					'form'			=> '<{tag} name="{id}" {properties ref_base="root" group="attr"} />'
				)
			),
			'base_closed'	=> array (
				'description'	=> 'Default Element (Closed Tag)',
				'parent'		=> 'base',
				'properties'	=> array (
					'value'			=> ''
				),
				'layout'		=> array (
					'form'			=> '<{tag} {properties ref_base="root" group="attr"}>{value}</{tag}>'
					)
			),
			'input'			=> array (
				'description'	=> 'Default Input Element',
				'parent'		=> 'base',
				'properties'	=> array (
						'tag'		=> 'input',
						'type'		=> array (
							'group' 	=> 'attr',
							'value'		=> 'text'
						),
						'value'		=> array(
							'group'		=> 'attr',
							'value'		=> '',
							'uses_data'	=> true	
						)
				)
			),
			'text'			=> array (
				'description'	=> 'Text Box',
				'parent'		=> 'input',
				'properties'	=> array (
					'size'			=> array (
						'group' 	=> 'attr',
						'value'		=> '15'
					),
					'label'			=> array (
						'value'			=> 'Text Input'
					)
				),
				'layout'			=> array (
					'form'				=> '{label ref_base="layout"} {inherit}',
					'label'				=> '<label for="{id}">{label}</label>'
				)
			),
			'location'		=> array(
				'description'	=> 'Geographic Coordinates',
				'properties'	=> array(),
				'elements'		=> array(
					'latitude'		=> array(
						'field'			=> 'text',
						'properties'	=> array (
							'id'			=> array (
								'value'			=> 'latitude'
							),
							'size'			=> array (
								'value'			=> '3'
							),
							'label'			=> array (
								'value'			=> 'Latitude'
							)
						)
					),
					'longitude'		=> array(
						'field'			=> 'text',
						'properties'	=> array(
							'id'			=> array (
								'value'			=> 'longitude'
							),
							'size'			=> array (
								'value'			=> '3'
							),
							'label'			=> array (
								'value'			=> 'Longitude'
							)
						)
					)
				),
				'layout'		=> array (
					'form'		=> '<span>{latitude ref_base="elements"}</span>, <span>{longitude ref_base="elements"}</span>'
				)
			),
			'phone'			=> array(
				'description'	=> 'Phone Number',
				'elements'		=> array(
					'area'			=> array(
						'field'			=> 'text',
						'properties'	=> array(
							'id'			=> array (
								'value'			=> 'area'
							),
							'size'			=> array (
								'value'			=> '3'
							)
						)
					),
					'prefix'		=> array(
						'field'			=> 'text',
						'properties'	=> array(
							'id'			=> array (
								'value'			=> 'prefix'
							),
							'size'			=> array (
								'value'			=> '3'
							)
						)
					),
					'suffix'		=> array(
						'field'			=> 'text',
						'properties'	=> array(
							'id'			=> array (
								'value'			=> 'suffix'
							),
							'size'			=> array (
								'value'			=> '4'
							)
						)
					)
				),
				'layout'		=> array (
					'form'			=> '({area ref_base="elements"}) {prefix ref_base="elements"} - {suffix ref_base="elements"}'
				)
			),
			'hidden'		=> array(
				'description'	=> 'Hidden Field',
				'parent'		=> 'input',
				'properties'	=> array (
					'type'			=> array (
						'value'			=> 'hidden'
					)
				)
			),
			'image'			=> array(
				'description'	=> 'Image',
				'parent'		=> 'base',
				'properties'	=> array (
					'tag'			=> 'img',
					'src'			=> array (
						'group'			=> 'attr',
						'value'			=> '/wp-admin/images/wp-logo.gif'
					),
				)
			),
			'span'			=> array(
				'description'	=> 'Inline wrapper',
				'parent'		=> 'base_closed',
				'properties'	=> array (
					'tag'			=> 'span',
					'value'			=> 'Hello there!'
				)
			)
		);
	}

	/**
	 * Initialize content types
	 */
	function set_types() {
		$this->content_types = array(
			'post'		=> array(
				'attributes'	=> array(
					'coordinates'	=> array(
						'id'			=> 'coordinates',
						'label'			=> 'Location',
						'elements'		=> array(
							'home'			=> array(
								'field'			=> 'location',
								'properties'	=> array (
									'id'			=> array (
										'value'			=> 'home'
									)
								)
							)
						)
					)
				),
				'groups'	=> array(
					'main'	=> array(
						'label'			=> 'Geographic Details',
						'attributes'	=>	array(
							'coordinates'
						)
					)
				)
			)
		);
	}

	/* Admin */

	/**
	 * Sets up Admin menus
	 * @return void
	 */
	function admin_menu() {
		global $menu, $submenu;
		//Content Types
		add_menu_page('Content Types', 'Content Types', 8, $this->file_content_types, $this->m('menu_main'));
	}

	/**
	 * Displays Content Types Admin Menu
	 * @return void
	 */
	function menu_main() {
		echo '<div class="wrap"><h2>Content Types</h2></div>';
		$this->the_field('text');
	}

	/**
	 * Adds fields to edit form based on content type of current item
	 * @param string $base_type Type of content being loaded (post or page)
	 * @param string $form_type Part of the form to do meta boxes for (normal, advanced, side)
	 * @param object $post Post object
	 * @return void
	 */
	function item_edit_form($base_type, $form_type, $post) {
		if ($form_type != 'normal' || empty($post->post_type) || !$this->is_type($post->post_type))
		return false;
			
		//Setup meta boxes for content type
		foreach ($this->content_types[$post->post_type]['groups'] as $group => $props) {
			add_meta_box("{$this->prefix}_meta_group_{$group}", $props['label'], $this->m('item_edit_form_set_box'), 'post', 'normal', 'high', array('group' => $group));
		}
	}

	/**
	 * Callback function to populate meta box on edit form with custom attributes based on content type
	 * @param object $object Post object
	 * @param array $box Meta box properties
	 */
	function item_edit_form_set_box($object, $box) {
		//Get attributes for group
		$args = &$box['args'];
		$group = $args['group'];

		//Build UI for attributes
		$type = $this->get_type();

		$output = '';

		if (!$type || !isset($type['groups'][$group]['attributes']))
		return false;

		//Iterate through attributes in group
		$attr;
		foreach ($type['groups'][$group]['attributes'] as $attribute) {
			//Get attribute definition
			$attr = $this->get_attribute($type, $attribute);
			if (!$attr)
			continue;
			//Build attribute UI
				
			$output .= $this->build_attribute_form($attr);
		}

		//Display UI
		echo $output;
	}

	/* Fields */

	/**
	 * Checks if field is valid
	 * @param string $field Field identifier
	 * @return boolean TRUE if field is valid
	 */
	function is_field($field) {
		return (isset($this->field_types[$field]) && is_array($this->field_types[$field]));
	}

	function is_field_populated($field = '') {
		return (is_array($field) && !empty($field[$this->field_populated])) ? true : false;
	}

	/**
	 * Retrieves specified field definition
	 * @param string|array $field Field name or Field definition array
	 * @param array $field_path Field ancestors
	 * @param array $field_data Data for field
	 * @param bool $full Fully retrieve field properties (for child fields) (Default: TRUE)
	 * @return mixed Field definition or FALSE if field does not exist
	 */
	function get_field($field, $field_path = array(), $field_data = array(), $full = true) {
		/* Get Valid Field */
		$args = func_get_args();
		if ($this->is_field_populated($field)) {
			return $field;
		}
		$props = array();
		//Separate field specifier and field properties for later use
		if (is_array($field) && isset($field['field'])) {
			if (isset($field['properties']))
				$props = $field['properties'];
			$field = $field['field'];
		}
		//Get base definition for specified field (if exists)
		$field = ($this->is_field($field)) ? $this->field_types[$field] : false;
		if (!$field)
			return false;
		
		/* Setup field properties */

		$props_default = array (
								'properties'	=> array(),
								'path'			=> $field_path,
								'data'			=> null
								);
		$field = array_merge($props_default, $field);
		
		//Field path processing
		if (!is_array($field['path'])) {
			if (!empty($field['path']))
				$field['path'] = array(strval($field['path']));
			else
				$field['path'] = array();
		}
		
		//Parse properties for field
		if ($full) {
			$field = $this->get_field_properties($field, $props);
			$field[$this->field_populated] = true;
		}
		
		/* Data Processing */
		
		//Build path to current field
		if (!empty($field_data) && count($field['path']) > 1) {
			//Get path (all path elements except first element)
			$attr_path = array_slice($field['path'], -1, 1);
			//Check if data for field exists
			if (!empty($attr_path) && $this->util->array_item_isset($field_data, $attr_path)) {
				//Get data for field
				$field['data'] = $this->util->get_array_item($field_data, $attr_path);
			}
		} else {
			//Add full field data to master field
			$field['data'] = $field_data;
		}
		
		return $field;
	}

	/**
	 * Retrieves properties for a field
	 *
	 * Returns array of properties defined with field as well as all properties from parent fields
	 * @param array|string $field Field definition (array) or field identifier (string)
	 * @param array $props Additional properties to add to field
	 * @return array Updated field definition
	 */
	function get_field_properties($field, $props = null) {
		$args = func_get_args();
		/* Get valid field */
		if (is_string($field)) {
			if ($this->is_field($field)) {
				$field = $this->get_field($field);
				return $field;
			} else {
				$field = false;
			}
		}

		if (empty($field) || !is_array($field))
			return false;
		
		/* Setup basic properties */
			
		//Merge additional properties with field properties
		if (is_array($props) && !empty($props)) {
			$field['properties'] = $this->util->array_merge_recursive_distinct($field['properties'], $props);
		}
		//Merge parent properties (if field is a child of another field)
		$curr_field = $field;

		//Recurse through parents field definitions and get inherited properties
		while (isset($curr_field['parent'])) {
			//Get Parent field definition (basic)
			$curr_field = $this->get_field($curr_field['parent'], false, null, false);

			//Check Child for inhertied values (deep)
			$field = $this->util->array_replace_recursive('{inherit}', $curr_field, $field);
				
			//Merge child with parent (child overwrites)
			$field = $this->util->array_merge_recursive_distinct($curr_field, $field);
		}
		
		/* Setup field properties specific to current field usage */
		
		//Process properties dependent on field path
		//ID
		if (isset($field['properties']['id'])) {
			if (!is_array($field['properties']['id']))
				$field['properties']['id'] = array('value' => $field['properties']['id']);
			//Add current field to field path
			$field['path'][] = $field['properties']['id']['value'];
			//Get formatted value for field ID
			$field['properties']['id']['value'] = $this->get_attribute_name($field['path']); 
		}
		return $field;
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
	function parse_field_layout($layout, $search) {
		$ph_xml = '';
		$parse_match;
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

	/**
	 * Retrieves a property from a field definition
	 *
	 * 	$properties Elements
	 * 	--------
	 * 	tag (string) Base name of targeted property
	 * 		- May contain multiple path segments to property using dot notation (e.g. path.segments.to.property)
	 * 	attributes (array) Key/Value array of attributes to customize the returned property
	 * 		- ref_base (string) [Optional] Sets base path of property relative to the field's root\
	 * 			> Uses dot notation to specify multiple path segments (e.g. root.layouts)
	 * 			> Note: If not set, base path defaults to "properties"
	 * 		- Any other attributes set in placeholder will be included as well
	 *  match (string) The layout placeholder to be replaced by the retrieved property
	 *
	 * @param array $field Field definition to get property from
	 * @param array $properties Data used to retrieve targeted property in a field definition
	 * @return mixed Field property targeted by $properties
	 */
	function &get_field_data_from_path(&$field, &$properties) {
		$field_data = $field['data'];
		/* Setup path to property */
		
		if (!isset($properties['path'])) {
			//Build path
			$path = $properties['tag'];
			
			//Get base path
			$ref_base = (isset($properties['attributes'][$this->field_ph_attr_reserved['ref_base']])) ? $properties['attributes'][$this->field_ph_attr_reserved['ref_base']] : '';
			$path_base = (!empty($ref_base)) ? (('root' != $ref_base) ? $ref_base : '') : $this->field_ph_base_default;
	
			//Append item path to base path
			if (!empty($path_base))
				$path = $path_base . $this->field_path_delim . $path;
			//Check if reference exists in field definition
			$properties['path'] = explode($this->field_path_delim, $path);
		}
		
		//Check if additional path segments have been included
		$args = func_get_args();
		$path_segments = $properties['path'];
		if (2 < ($args_count = count($args))) {
			for ($s = 2; $s < $args_count; $s++) {
				if (is_array($args[$s])) {
					$path_segments = array_merge($path_segments, $args[$s]);
				} else {
					$path_segments[] = $args[$s];
				}
			}
		}
		$el = ('properties' == $path_segments[count($path_segments) - 1] && 'longitude' == $path_segments[count($path_segments) - 2]) ? true : false;
		
		/* Get requested data */
		
		//Check if path points to a nested field or a property of a nested field property
		if (1 < count($path_segments) 													//Path is made up of more than 1 segment
			&& is_array($nested_field =& $this->util->get_array_item($field, array_slice($path_segments, 0, 2)))	//Field property located at first 2 segments is an array
			&& isset($nested_field['field'])			 								//Field property is a nested field
		) {
			if (!$this->is_field_populated($nested_field)) {							//Nested field has not already been populated (e.g. by a previous request for the same field)
				//Retrieve element
				$element = $this->get_field($nested_field, $field['path'], $field['data']);
				//Replace element in field definition with retrieved element
				if (!empty($element)) {
					$element['field'] = $nested_field['field'];
					$nested_field = $element;
				}
			}
			$field_data = $nested_field['data'];
		}
		$target_property =& $this->util->get_array_item($field, $path_segments);
		//Set property data
		if ($this->uses_field_data($target_property) && !empty($field_data)) {
			//Wrap property in array
			$target_property['value'] = $field_data;
			$target_property['data_retrieved'] = true;
		}
		
		return $target_property;
	}
	
	/**
	 * Checks if specified property uses field data
	 * @param array $property Property to check data usage for
	 * @return boolean TRUE if $property uses field data, FALSE otherwise
	 */
	function uses_field_data($property) {
		if (is_array($property)
			&& isset($property['uses_data']) 		//Contains 'Uses Data' property
			&& $property['uses_data']				//Confirmed to use field data
			&& !isset($property['data_retrieved'])	//Make sure data was not already retrieved for property
		) {
			return true;
		}
		return false;
	}
	
	/**
	 * Builds HTML for a field based on its properties
	 * @param array $field Field properties (id, field, etc.)
	 * @param string|array $field_path Base ID for field
	 * @param array $attr_data Data for current field (Passed by reference)
	 * @param bool $parent_match TRUE if $attr_data contains a reference to the current field's parent FALSE if not (to avoid searching data for current item if parent is not in data)
	 */
	function build_field($field, $field_path = array(), &$attr_data, $parent_matched = true) {
		$args = func_get_args();
		$out = '';
		$field_definition = $this->get_field($field, $field_path, $attr_data);
		//Stop processing & return empty string if field is not valid
		if (empty($field_definition)
		|| !isset($field_definition['properties'])
		|| (empty($field_definition['properties']) && !isset($field_definition['elements']))
		|| !isset($field_definition['layout']['form'])
		)
		return $out;
		
		/* Layout */
		
		//Parse Layout
		$out = $field_definition['layout']['form'];
		$ph_start = '{';
		$ph_end = '}';
		$re_ph = '/' . $ph_start . '([a-zA-Z0-9_].*?)' . $ph_end . '/i';
		$re_ph_layout = '/' . $ph_start . '([a-zA-Z0-9].*?\s+' . $this->field_ph_attr_reserved['ref_base'] . '="layout.*?".*?)' . $ph_end . '/i';

		//Find all nested layouts in layout
		while ($ph_match = $this->parse_field_layout($out, $re_ph_layout)) {
			//Iterate through the different types of layout placeholders
			foreach ($ph_match as $tag => $instances) {
				//Iterate through instances of a specific type of layout placeholder
				foreach ($instances as $instance) {
					//Build path to item
					$nested_layout = $this->get_field_data_from_path($field_definition, $instance);
						
					//Replace layout placeholder with retrieved item data
					$out = str_replace($ph_start . $instance['match'] . $ph_end, $nested_layout, $out);
				}
			}
		}

		//Search layout for placeholders
		if ($ph_match = $this->parse_field_layout($out, $re_ph)) {
			//Iterate through placeholders (tag, id, etc.)
			foreach ($ph_match as $tag => $instances) {
				//Iterate through instances of current placeholder
				foreach ($instances as $instance) {
					//Build path to replacement data
					$target_property = $this->get_field_data_from_path($field_definition, $instance);
					//Check if value is group (properties, etc.)
					//All groups must have additional attributes (beyond reserved attributes) that define how items in group are used
				
					if (is_array($target_property) && isset($target_property['value']) && is_scalar($target_property['value'])) { /* Check if item has a value property */
						$target_property = $target_property['value'];
					} elseif (is_array($target_property)
						&& !empty($instance['attributes'])
						&& is_array($instance['attributes'])
						&& $attribs = array_diff(array_keys($instance['attributes']), array_keys($this->field_ph_attr_reserved))
					) {
						//Find items matching criteria in $target_property
						$group_val = array();
						//Iterate through items in group
						while (list($item_key,) = each($target_property)) {
							//Set value as reference so that changes will be reflected in field definition
							$item_val =& $this->get_field_data_from_path($field_definition, $instance, $item_key);
							if (!is_array($item_val)) {
								unset($item_val);
								continue;
							}
								
							$valid_item = false;
							//Check item for each attribute
							foreach ($attribs as $attrib) {
								if (isset($item_val[$attrib]) && $item_val[$attrib] == $instance['attributes'][$attrib])
									$valid_item = true;
								else {
									$valid_item = false;
									break;
								}
							}
							//Add item to group
							if ($valid_item) {
								$group_val[] = $item_key . '="' . ((isset($item_val['value'])) ? $item_val['value'] : '') . '"';
							}
							//Unset group item to avoid reference issues
							unset($item_val);
						}
						//Build string from matching items
						if (!empty($group_val)) {
							$target_property = implode(' ', $group_val);
						}
					}
					elseif (is_array($target_property) && isset($target_property['field'])) { /* Check if item is a nested field */
						$target_property = $this->build_field($target_property, $field_definition['path'], $field_definition['data'], $parent_matched);
					}
					elseif (!is_scalar($target_property)) { /* Set default value for item */
						$target_property = '';
					}
					//Replace layout placeholder with retrieved item data
					$out = str_replace($ph_start . $instance['match'] . $ph_end, $target_property, $out);
				}
			}
		}

		//Remove any unprocessed placeholders from layout
		if ($ph_match = $this->parse_field_layout($out, $re_ph)) {
			$out = preg_replace($re_ph, '', $out);
		}
		
		/* Return generated value */
		
		return $out;
	}

	/**
	 * Outputs HTML for specified field
	 * @param string $field Name of field to output
	 */
	function the_field($field, $attribute = null) {
		echo $this->build_field($field);
	}

	/* Types */

	/**
	 * Retrieves content type definition
	 * @param string $type Content type to retrieve
	 * @return mixed Content type definition (array), or FALSE if type does not exist
	 */
	function get_type($type = null) {
		if (empty($type) || !is_string($type)) {
			$type = $this->get_item_type($type);
		}
		if ($this->is_type($type))
		return $this->content_types[$type];
		return false;
	}

	/**
	 * Get content type of item
	 * @param object $item Post object
	 * @return string Item content type
	 */
	function get_item_type($item) {
		$type = '';
		if (empty($item) && isset($GLOBALS['post'])) {
			$item = $GLOBALS['post'];
		}

		//Get item content type
		//TODO: Update to get CNR content type
		$type = $item->post_type;
		return $type;
	}

	/**
	 * Checks if specified content type is defined
	 * @param string $type Content type identifier
	 * @return boolean TRUE if content type is defined
	 */
	function is_type($type) {
		if (isset($this->content_types[$type]))
		return true;
		return false;
	}

	/* Type Attributes */

	/**
	 * Checks if type has specified attribute
	 * @param array $type Content type definition
	 * @param string $attribute Attribute identifier
	 * @return boolean TRUE if content type has attribute
	 */
	function has_attribute($type, $attribute) {
		return (isset($type['attributes'][$attribute]));
	}

	/**
	 * Retrieves attribute from content type definition
	 * @param array $type Content type definition
	 * @param string $attribute Attribute identifier
	 * @return mixed Attribute definition (array), FALSE if does not exist
	 */
	function get_attribute($type, $attribute) {
		if (!$this->has_attribute($type, $attribute))
		return false;
		return $type['attributes'][$attribute];
	}

	/**
	 * Builds HTML for attribute on edit form
	 * @param array $attribute Attribute definition
	 * @return string HTML for attribute form
	 */
	function build_attribute_form($attribute) {
		$id = 'attr_' . $attribute['id'];
		$out = '';

		//Wrappers
		$wrap = array(
			'start'			=> '<div id="' . $id . '_wrap" class="attribute_wrap">',
			'end'			=> '</div>',
			'label_start'	=> '<label for="' . $id . '">',
			'label_end'		=> '</label>',
			'field_start'	=> '<div id="' . $id . '_fields" class="attribute_fields">',
			'field_end'		=> '</div>'
			);
			//Define ID
			//$id = $this->get_attribute_name($attribute['id']);
			//Get previously saved attribute data
			$attr_data = $this->post_meta_get($GLOBALS['post']->ID, $this->get_attribute_name($attribute['id'], 'metadata'), true);

			$out = array($wrap['start'], $wrap['label_start'], $attribute['label'], $wrap['label_end'], $wrap['field_start']);
			foreach ($attribute['elements'] as $field) {
				$out[] = $this->build_field($field, $attribute['id'], $attr_data);
			}
			array_push($out, $wrap['field_end'], $wrap['end']);

			//Return output as string
			return implode($out);
	}

	/**
	 * Builds formatted base name for attributes
	 * @param string $format Defines how base name is formatted
	 * @see get_attribute_name for formatting options
	 */
	function get_attribute_basename($format = 'id') {
		return $this->get_attribute_name('', $format);
	}

	/**
	 * Builds formatted name for specified attribute
	 * @param string $attribute Attribute name
	 * @param string $format Defines how name is formatted
	 * 	Options:
	 * 		post		- Item in $_POST array
	 * 		attribute	- Sanitized name (spaces replaced with underscore)
	 * 		[Default]	- First segment is the prefix while the rest of the segments are formatted as a multi-dimensional array item
	 */
	function get_attribute_name($attribute = '', $format = 'id') {
		$arr_bs = $this->attribute_basename;
		//Add attribute to name segments array
		if (!empty($attribute)) {
			if (is_array($attribute))
				$arr_bs = array_merge($arr_bs, $attribute);
			else
				$arr_bs[] = $attribute;
		}
		
		//Format attribute name
		$bs = $this->util->get_array_path($arr_bs, $format);
		return $bs;
	}

	/**
	 * Saves content type meta data for post
	 * @param int $post_id ID of saved post
	 * @param object $post Saved post object
	 */
	function save_item_attributes($post_id, $post) {
		$bs = $this->get_attribute_basename('post');
		$post_vars = '$_POST' . $bs;
		if (eval("return isset($post_vars);")) {
			$attributes = eval("return $post_vars;") ;
			//Save serialized data for each attribute as item meta data
			foreach ($attributes as $attr => $data) {
				$meta_key = $this->get_attribute_name($attr, 'metadata');
				$ret = $this->post_meta_update($post_id, $meta_key, $data);
			}
		}
	}

}

?>