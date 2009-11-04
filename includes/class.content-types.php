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
					'form'			=> '<{tag} {prop group="attr"} />'
				)
			),
			'base_closed'	=> array (
				'description'	=> 'Default Element (Closed Tag)',
				'properties'	=> array (
					'parent'		=> 'base',
					'value'			=> ''
				),
				'layout'		=> array (
					'form'			=> '<{tag} {prop group="attr"}>{value}</{tag}>'
				)
			),
			'input'			=> array (
				'description'	=> 'Default Input Element',
				'properties'	=> array (
						'parent'	=> 'base',
						'tag'		=> 'input',
						'type'		=> array (
							'group' 	=> 'attr',
							'value'		=> 'text'
						)
				)
			),
			'text'			=> array(
				'description'	=> 'Text Box',
				'properties'	=> array (
					'parent'		=> 'input',
					'size'			=> array (
						'group' 	=> 'attr',
						'value'		=> '15'
					)
				)
			),
			'location'		=> array(
				'description'	=> 'Geographic Coordinates',
				'properties'	=> array(),
				'elements'		=> array(
					array(
						'type'			=> 'text',
						'id'			=> 'latitude',
						'properties'	=> array (
							'size'			=> array (
								'value'			=> '3'
							)
						)
					),
					array(
						'type'			=> 'text',
						'id'			=> 'longitude',
						'properties'	=> array(
							'size'			=> array (
								'value'			=> '3'
							)
						)
					)
				),
				'layout'		=> array (
					'form'			=> '<span>{elements:latitude}</span>, <span>{elements:longitude}</span>'
				)
			),
			'phone'			=> array(
				'description'	=> 'Phone Number',
				'elements'		=> array(
					array(
						'type'		=> 'text',
						'id'		=> 'area',
						'properties'	=> array(
							'size'			=> '3'
						)
					),
					array(
						'type'		=> 'text',
						'id'		=> 'prefix',
						'properties'	=> array(
							'size'			=> '3'
						)
					),
					array(
						'type'		=> 'text',
						'id'		=> 'suffix',
						'properties'	=> array(
							'size'			=> '4'
						)
					)
				),
				'layout'		=> array (
					'form'			=> '({elements:area}) {elements:prefix} - {elements:suffix}'
				)
			),
			'hidden'		=> array(
				'description'	=> 'Hidden Field',
				'properties'	=> array (
					'parent'		=> 'input',
					'type'			=> 'hidden'
					)
			),
			'image'			=> array(
				'description'	=> 'Image',
				'properties'	=> array (
					'tag'			=> 'img',
					'src'			=> get_bloginfo('url') . '/wp-admin/images/wp-logo.gif',
					'closed'		=> false
					)
			),
			'span'			=> array(
				'description'	=> 'Inline wrapper',
				'properties'	=> array (
					'tag'			=> 'span',
					'text'			=> 'Hello there!',
					'closed'		=> true
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
						'fields'		=> array(
							array(
								'type'			=> 'phone',
								'id'			=> 'first'
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
		return (isset($this->field_types[$field]));
	}
	
	/**
	 * Retrieves specified field definition
	 * @param string $field Field name
	 * @return mixed Field definition or FALSE if field does not exist
	 */
	function get_field($field, $full = true) {
		$field = ($this->is_field($field)) ? $this->field_types[$field] : false;
		if (!$field)
			return $field;
		//Add Properties array (if it does not exist)
		if (!isset($field['properties']))
			$field['properties'] = array();
		//Parse properties for field
		if ($full)
			$field['properties'] = $this->get_field_properties($field);
		return $field;
	}
	
	/**
	 * Retrieves properties for a field
	 * 
	 * Returns array of properties defined with field as well as all properties from parent fields
	 * @param mixed $field Field definition array or field identifier
	 * @return array Field properties
	 */
	function get_field_properties($field) {
		if (!is_array($field)) {
			if ($this->is_field($field)) {
				$field = $this->get_field($field);
				return $field['properties'];
			} else {
				$field = false;
			}
		}
		
		if (empty($field))
			return array();
		//Build properties array
		
		//Merge parent properties (if field is a child of another field)
		$props = $field['properties'];
		$props_arr = $props;
		
		//Recurse through parents
		while (isset($props['parent'])) {
			//Save current properties
			$props_arr = $props;
			//Get parent properties
			if ($this->is_field($props['parent'])) {
				$props = $this->get_field($props['parent'], false);
				$props = $props['properties'];
				
				//Get inherited values from parent properties
				foreach ($props_arr as $key => $val) {
					if ('parent' == $key || strpos($val, 'inherit') === false)
						continue;
					//Replace "inherit" keyword with value from parent property
					if (array_key_exists($key, $props))
						$props_arr[$key] = str_replace('inherit', $props[$key], $props_arr[$key]); 
				}
				
				//Merge child properties with parent properties (child props overwrite parent props)
				$props_arr = array_merge($props, $props_arr);
			} else {
				//Remove parent from property array
				unset($props['parent']);
			}
		}
		
		//Remove parent property from properties array
		unset($props_arr['parent']);
		
		$prop_defaults = array (
			'closed'	=> false,
			'class'		=> ''
		);
		
		$props_arr = array_merge($prop_defaults, $props_arr);
		
		return $props_arr;
	}
	
	/**
	 * Builds HTML for a field based on its properties
	 * @param array $field Field properties (id, field, etc.)
	 * @param string $id Base ID for field
	 */
	function build_field($field, $id = '') {
		$out = '';
		$field_definition = $this->get_field($field['type']);
		
		//Stop processing & return empty string if field is not valid
		if (empty($field_definition)
			|| !isset($field_definition['properties'])
			|| !is_array($field_definition['properties'])
			|| (!isset($field_definition['properties']['tag']) && !isset($field_definition['properties']['parent']) && !isset($field_definition['elements']))
			)
			return $out;
		
		//Set ID for field
		$id_format = '%s[%s]';
		$field_id = '';
		
		if (empty($id)) {
			//Set default ID base
			$id = $this->prefix . '[attributes]';
		} elseif (isset($field['id'])) {
			//Set field-specific ID
			$field_id = $field['id'];
		}
		
		$id = sprintf($id_format, $id, $field_id);
		
		//Merge custom field properties with default properties
		$field['properties']['id'] = $id;
		$field_definition['properties'] = array_merge($field_definition['properties'], $field['properties']);
		
		//Build field elements (if exist)
		if (isset($field_definition['elements']) && is_array($field_definition['elements']) && !empty($field_definition['elements'])) {
			$elements = array();
			foreach ($field_definition['elements'] as $element) {
				$elements[$element['id']] = $this->build_field($element, $id);
			}
			//Check if a custom layout is defined
			if (isset($field_definition['layout']) && !empty($field_definition['layout'])) {
				//Set layout
				$out = $field_definition['layout'];
				//Replace placeholders in layout with actual field content
				foreach ($elements as $el_id => $el_val) {
					$out = str_replace('[[' . $el_id . ']]', $el_val, $out);
				}
				//Replace any unused placeholders
				$re_ph = "/\[\[.+\]\]/";
				if (preg_match($re_ph, $out) > 0) {
					$out = preg_replace($re_ph, '', $out);
				}
			} else {
				$out = implode('', $elements);
			}
		} else {
			//Build field
			$parts = array (
				'start'		=> '<',
				'end'		=>	'>',
				'close'		=>	'/'
			);
			
			//Determine if field tag is closed or not
			$closed = $field_definition['properties']['closed'];
			
			//Build Attributes
			$attr = '';
			foreach ($field_definition['properties'] as $prop => $val) {
				if ($prop == 'closed' || $prop == 'tag' || ($closed && $prop == 'text'))
					continue;
				$attr .= " $prop" . '="' . $val . '"';
			}
			
			//Build field
			$out = $parts['start'] . $field_definition['properties']['tag'] . $attr;
			if ($closed) {
				$text = (isset($field_definition['properties']['text'])) ? $field_definition['properties']['text'] : '';
				$out .= $parts['end'] . $text . $parts['start'] . $parts['close'] . $field_definition['properties']['tag'] . $parts['end'];
			}
			else
				$out .= ' ' . $parts['close'] . $parts['end'];
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
		foreach ($attribute['fields'] as $field) {
			$out[] = $this->build_field($field, $id);
		}
		array_push($out, $wrap['field_end'], $wrap['end']);
		
		//Return output as string
		return implode($out);
	}
	
}

?>