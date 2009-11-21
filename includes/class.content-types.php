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
					'form'			=> '<{tag} {properties ref_base="root" group="attr"} />'
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
					'tagline'	=> array(
						'id'			=> 'tagline',
						'label'			=> 'Location',
						'elements'		=> array(
							'first'			=> array(
								'field'			=> 'phone',
								'properties'	=> array (
									'id'			=> array (
										'value'			=> 'first'
									)
								)
							)
						)
					)
				),
				'groups'	=> array(
					'main'	=> array(
						'label'			=> 'Main',
						'attributes'	=>	array(
							'tagline'
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
		return (is_array($field) && isset($field[$this->field_populated]) && $field[$this->field_populated]) ? true : false;
	}
	
	/**
	 * Retrieves specified field definition
	 * @param string $field Field name
	 * @param bool $full Fully retrieve field properties (for child fields) (Default: TRUE)
	 * @return mixed Field definition or FALSE if field does not exist
	 */
	function get_field($field, $full = true) {
		if ($this->is_field_populated($field)) {
			//$this->debug->print_message('Field has already been populated. Returning field');
			return $field;
		}
		$props = array();
		if (is_array($field) && isset($field['field'])) {
			if (isset($field['properties']))
				$props = $field['properties'];
			$field = $field['field'];
		}
		$field = ($this->is_field($field)) ? $this->field_types[$field] : false;
		if (!$field)
			return $field;
		//Add Properties array (if it does not exist)
		if (!isset($field['properties']))
			$field['properties'] = array();
		//Parse properties for field
		if ($full) {
			$field = $this->get_field_properties($field, $props);
			$field[$this->field_populated] = true;
		}
		return $field;
	}
	
	/**
	 * Retrieves properties for a field
	 * 
	 * Returns array of properties defined with field as well as all properties from parent fields
	 * @param array|string $field Field definition (array) or field identifier (string)
	 * @return array Updated field definition
	 */
	function get_field_properties($field, $props = null) {
		//$this->debug->print_message('Getting Field Properties');
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
		
		//Merge additional properties with field properties
		if (is_array($props) && !empty($props)) {
			$field['properties'] = $this->util->array_merge_recursive_distinct($field['properties'], $props);
		}
		//Merge parent properties (if field is a child of another field)
		$curr_field = $field;

		//Recurse through parents field definitions and get inherited properties
		while (isset($curr_field['parent'])) {
			//Get Parent field definition
			$curr_field = $this->get_field($curr_field['parent'], false);
			 
			//Check Child for inhertied values (deep)
			$field = $this->util->array_replace_recursive('{inherit}', $curr_field, $field);
			
			//Merge child with parent (child overwrites)
			$field = $this->util->array_merge_recursive_distinct($curr_field, $field);
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
		//$this->debug->print_message("Search", $search, 'Subject', $layout, 'Match Value', $match_value);
		
		if ($match_value !== false && $match_value > 0) {
			$parse_result = array();
			//Get all matched elements
			$parse_match = $parse_match[1];
			//$this->debug->print_message("Parse Match", $parse_match);

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
			
			//$this->debug->print_message("Parsed Result (Pre-processed)", $parse_result);
			
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
			//$this->debug->print_message("Combined Results", $result);
		}
		//$this->debug->print_message("Parse Result", $parse_result);
		return $parse_result;
	}
	
	function get_field_data_from_path($properties, &$field) {
		$var = '$field';
		//Build path
		$path = $properties['tag'];
		
		//Get base path
		$ref_base = (isset($properties['attributes'][$this->field_ph_attr_reserved['ref_base']])) ? $properties['attributes'][$this->field_ph_attr_reserved['ref_base']] : '';
		$path_base = (!empty($ref_base)) ? (('root' != $ref_base) ? $ref_base : '') : $this->field_ph_base_default;
		
		//Append item path to base path
		if (!empty($path_base))
			$path = $path_base . $this->field_path_delim . $path;
		//Check if reference exists in field definition
		$path_segments = explode($this->field_path_delim, $path);
		//Check if path points to a nested field or a property of a nested field property
		if (1 < count($path_segments) && is_array($nested_field = $field[$path_segments[0]][$path_segments[1]]) && array_key_exists('field', $nested_field) && !array_key_exists($this->field_populated, $nested_field)) {
			//Retrieve element
			$field_props = $field[$path_segments[0]][$path_segments[1]];
			$element = $this->get_field($field_props);
			//Replace element in field definition with retrieved element
			if (!!$element) {
				$element['field'] = $field_props['field'];
				$field[$path_segments[0]][$path_segments[1]] = $element;
			}
		}
		
		$path = $var . '["' . implode('"]["', $path_segments) . '"]';
		if (!eval('return isset(' . $path . ');')) {
			$path = '';
		} else {
			$path = eval('return ' . $path . ';');
		}
		return $path;
	}
	
	/**
	 * Builds HTML for a field based on its properties
	 * @param array $field Field properties (id, field, etc.)
	 * @param string $base_id Base ID for field
	 */
	function build_field($field, $base_id = '') {
		$out = '';
		$field_definition = $this->get_field($field);
		
		//Stop processing & return empty string if field is not valid
		if (empty($field_definition)
			|| !isset($field_definition['properties'])
			|| (empty($field_definition['properties']) && !isset($field_definition['elements']))
			|| !isset($field_definition['layout']['form'])
			)
			return $out;
		
		//Set ID for field
		$id_format = '%s[%s]';
		$field_id = '';
		if (empty($base_id)) {
			//Set default ID base
			$base_id = $this->prefix . '[attributes]';
		}
		
		if (isset($field['properties']['id'])) {
			//Set field-specific ID
			$field_id = $field['properties']['id']['value'];
		}
		
		if (!isset($field_definition['properties']['id']))
			$field_definition['properties']['id'] = array();
		$field_definition['properties']['id']['value'] = $base_id = sprintf($id_format, $base_id, $field_id);
		
		//Parse Layout
		$out = $field_definition['layout']['form'];
		$ph_start = '{';
		$ph_end = '}';
		$re_placeholder = '/' . $ph_start . '([a-zA-Z0-9_].*?)' . $ph_end . '/i';
		$re_ph_layout = '/' . $ph_start . '([a-zA-Z0-9].*?\s+' . $this->field_ph_attr_reserved['ref_base'] . '="layout.*?".*?)' . $ph_end . '/i';
		$subtype_delim = ':';
		$ph_layouts;
		$ph_match;
		$ph_root_tag = 'ph_root_element';
		$ph_start_xml = '<';
		$ph_end_xml = ' />';
		$ph_wrap_start = '<' . $ph_root_tag . '>';
		$ph_wrap_end = '</' . $ph_root_tag . '>'; 
		$ph_values = $ph_index = array();
		
		//Find all nested layouts in layout
		while ($ph_layouts = $this->parse_field_layout($out, $re_ph_layout)) {
			//Iterate through the different types of layout placeholders
			foreach ($ph_layouts as $tag => $instances) {
				//Iterate through instances of a specific type of layout placeholder
				foreach ($instances as $instance) {
					//Build path to item
					$path_data = $this->get_field_data_from_path($instance, $field_definition);
					
					//Replace layout placeholder with retrieved item data
					$out = str_replace($ph_start . $instance['match'] . $ph_end, $path_data, $out);
				}
			}
		}
		
		//TODO Populate dynamic data for elements before retrieving values from element properties
		//Example: elements.latitude.properties.id will need to be properly formatted (with base ID attached) prior to retrieving this value
		
		
		//Search layout for placeholders
		if ($ph_match = $this->parse_field_layout($out, $re_placeholder)) {
			//Iterate through placeholders (tag, id, etc.)
			foreach ($ph_match as $tag => $instances) {
				//Iterate through instances of current placeholder
				foreach ($instances as $instance) {
					//Build path to replacement data
					$path_data = $this->get_field_data_from_path($instance, $field_definition);
					
					/* Get actual value for item */
					
					//Check if value is group (properties, etc.)
					//All groups must have additional attributes (beyond reserved attributes) that define how items in group are used
					if (is_array($path_data) 
						&& !empty($instance['attributes'])
						&& is_array($instance['attributes'])
						&& $attribs = array_diff(array_keys($instance['attributes']), array_keys($this->field_ph_attr_reserved))
						) {
						//Find items matching criteria in $path_data
						$group_val = array();
						//Iterate through items in path data
						foreach ($path_data as $item_key => $item_val) {
							if (!is_array($item_val))
								continue;
					
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
						}
						//Build string from matching items
						if (!empty($group_val)) {
							$path_data = implode(' ', $group_val);
						}
					}
					elseif (is_array($path_data) && isset($path_data['field'])) { /* Check if item is a nested field */
						$path_data = $this->build_field($path_data, $base_id);
						//$this->debug->print_message("Build nested field", $path_data);
						//$path_data = 'nested field';
					}
					elseif (is_array($path_data) && isset($path_data['value'])) { /* Check if item has a value property */
						$path_data = $path_data['value'];
					}
					elseif (!is_scalar($path_data)) { /* Set default value for item */
						$path_data = '';
					}
					//Replace layout placeholder with retrieved item data
					$out = str_replace($ph_start . $instance['match'] . $ph_end, $path_data, $out);
				}
			}
		}
		
		//Remove any unprocessed placeholders from layout
		if ($ph_match = $this->parse_field_layout($out, $re_placeholder)) {
			$out = preg_replace($re_placeholder, '', $out);
		}
		
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
	 * @return string HTML for attribute
	 */
	function build_attribute_form($attribute) {
		$out = '';
		
		//Define ID
		$id = 'cnr[attributes][' . $attribute['id'] . ']';
		
		//Wrappers
		$wrap = array(
			'start'			=> '<div id="' . $id . '_wrap" class="attribute_wrap">',
			'end'			=> '</div>',
			'label_start'	=> '<label for="' . $id . '">',
			'label_end'		=> '</label>',
			'field_start'	=> '<div id="' . $id . '_fields" class="attribute_fields">',
			'field_end'		=> '</div>'
		);
		
		$out = array($wrap['start'], $wrap['label_start'], $attribute['label'], $wrap['label_end'], $wrap['field_start']);
		foreach ($attribute['elements'] as $field) {
			$out[] = $this->build_field($field, $id);
		}
		array_push($out, $wrap['field_end'], $wrap['end']);
		
		//Return output as string
		return implode($out);
	}
	
}

?>