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
	 * @var array Field type layouts
	 */
	var $layout = array();
	
	/**
	 * @var CNR_Field_Type Parent field type (reference)
	 */
	var $parent = null;
	
	/**
	 * Legacy Constructor
	 */
	function CNR_Field_Type($id = '', $parent = null) {
		$this->__construct();
	}
	
	/**
	 * Constructor
	 */
	function __construct($id = '', $parent = null) {
		parent::__construct();
		
		$this->id = $id;
		$this->parent = $parent;
	}
	
	/* Setters/Getters */
	
	/**
	 * Sets reference to parent field type
	 * @param CNR_Field_Type $parent Parent field type
	 */
	function set_parent(&$parent) {
		//Validate parent object
		if ( is_array($parent) && !empty($parent) )
			$parent =& $parent[0];
		//Set reference to parent field type
		if ( get_class($parent) == get_class($this)  )
			$this->parent =& $parent;
	}
	
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
	 * TODO: Manage extending/overwriting already existing properties
	 * TODO: Allow adding multiple properties at once
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
			//TODO: Add Group functionality
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
		//Create new field type for element
		$el = new CNR_Field_Type($name, array(&$type));
		//Add properties to element
		$el->set_properties($properties);
		//Set name as internal property
		if ( !empty($id_prop) )
			$el->set_property($id_prop, $name);
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
	
}

$base = new CNR_Field_Type('base');
$base->set_description('Default Element');
$base->set_property('tag', 'span');
$base->set_property('class', '', 'attr');
$base->set_property('id', '', 'attr');
$base->set_layout('form', '<{tag} name="{id}" {properties ref_base="root" group="attr"} />');

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

$text = new CNR_Field_Type('text');
$text->set_parent($input);
$text->set_description('Text Box');
$text->set_property('size', 15, 'attr');
$text->set_property('label');
$text->set_layout('form', '{label ref_base="layout"} {inherit}');
$text->set_layout('label', '<label for="{id}">{label}</label>');

$location = new CNR_Field_Type('location');
$location->set_description('Geographic Coordinates');
$location->set_element('latitude', $text, array( 'size' => 3, 'label' => 'Longitude' ));
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
?>