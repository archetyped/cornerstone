<?php
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
	 * Class constructor
	 * @param string $id Content type ID
	 * @param string|bool $parent (optional) Parent to inherit properties from (Default: none)
	 * @param array $properties (optional) Properties to set for content type (Default: none)
	 */
	function __construct($id = '', $parent = null, $properties = null) {
		parent::__construct($id, $parent);
		
		//Set properties
		//TODO Iterate through additional arguments and set instance properties
	}
	
	/* Registration */
	
	/**
	 * Registers current content type w/CNR
	 */
	function register() {
		global $cnr_content_utilities;
		$cnr_content_utilities->register_content_type($this);
	}

	/* Getters/Setters */

	/**
	 * Adds group to content type
	 * Groups are used to display related fields in the UI 
	 * @param string $id Unique name for group
	 * @param string $title Group title
	 * @param string $description Short description of group's purpose
	 * @param string $location Where group will be displayed on post edit form (Default: main)
	 * @param array $fields (optional) ID's of existing fields to add to group
	 * @return object Group object
	 */
	function &add_group($id, $title = '', $description = '', $location = 'normal', $fields = array()) {
		//Create new group and set properties
		$id = trim($id);
		$this->groups[$id] =& $this->create_group($title, $description, $location);
		//Add fields to group (if supplied)
		if ( !empty($fields) && is_array($fields) )
			$this->add_to_group($id, $fields);
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
		return ( !is_null($this->get_member_value('groups', $id, null)) );
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
		$this->add_to_group($group, $field->id);
		return $field;
	}

	/**
	 * Removes field from content type
	 * @param string|CNR_Field $field Object or Field ID to remove 
	 */
	function remove_field($field) {
		if ( $field instanceof CNR_Field_Type ) {
			$field = $field->get_id();
		}
		if ( !is_string($field) || empty($field) )
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
			$field = $this->get_member_value('fields', $field);
		} else {
			//Return empty field if no field exists
			$field = new CNR_Field('');
		}
		return $field;
	}

	/**
	 * Checks if field exists in the content type
	 * @param string $field Field ID
	 * @return bool TRUE if field exists, FALSE otherwise
	 */
	function has_field($field) {
		return ( !is_string($field) || empty($field) || is_null($this->get_member_value('fields', $field, null)) ) ? false : true;
	}

	/**
	 * Adds field to a group in the content type
	 * Group is created if it does not already exist
	 * @param string|array $group ID of group (or group parameters if new group) to add field to
	 * @param string|array $fields Name or array of field(s) to add to group
	 */
	function add_to_group($group, $fields) {
		//Validate parameters
		$group_id = '';
		if ( !empty($group) ) {
			if ( !is_array($group) ) {
				$group = array($group, $group);
			}
			
			$group[0] = $group_id = trim(sanitize_title_with_dashes($group[0]));
		}
		if ( empty($group_id) || empty($fields) )
			return false;
		//Create group if it doesn't exist
		if ( !$this->group_exists($group_id) ) {
			call_user_func_array($this->m('add_group'), $group);
		}
		if ( ! is_array($fields) )
			$fields = array($fields);
		foreach ( $fields as $field ) {
			unset($fref);
			if ( ! $this->has_field($field) )
				continue;
			$fref =& $this->get_field($field);
			//Remove field from any other group it's in (fields can only be in one group)
			foreach ( array_keys($this->groups) as $group_name ) {
				if ( isset($this->groups[$group_name]->fields[$fref->id]) )
					unset($this->groups[$group_name]->fields[$fref->id]);
			}
			//Add reference to field in group
			$this->groups[$group_id]->fields[$fref->id] =& $fref;
		}
	}

	/**
	 * Remove field from a group
	 * If no group is specified, then field is removed from all groups
	 * @param string|CNR_Field $field Field object or ID of field to remove from group
	 * @param string $group (optional) Group ID to remove field from
	 */
	function remove_from_group($field, $group = '') {
		//Get ID of field to remove or stop execution if field invalid
		if ( $field instanceof CNR_Field_Type ) {
			$field = $field->get_id();
		}
		if ( !is_string($field) || empty($field) )
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
		if ( ! $this->group_exists($group) )
			$this->add_group($group);
		$group = $this->get_member_value('groups', $group);
		return $group;
	}

	/**
	 * Retrieve all groups in content type
	 * @return array Reference to group objects
	 */
	function &get_groups() {
		$groups = $this->get_member_value('groups');
		return $groups;
	}

	/**
	 * Output fields in a group
	 * @param string $group ID of Group to output
	 * @return string Group output
	 */
	function build_group($group) {
		$out = array();
		$classnames = (object) array(
			'multi'		=> 'multi_field',
			'single'	=> 'single_field',
			'elements'	=> 'has_elements'
		);

		//Stop execution if group does not exist
		if ( $this->group_exists($group) && $group =& $this->get_group($group) ) {
			$group_fields = ( count($group->fields) > 1 ) ? $classnames->multi : $classnames->single . ( ( ( $fs = array_keys($group->fields) ) && ( $f =& $group->fields[$fs[0]] ) && ( $els = $f->get_member_value('elements', '', null) ) && !empty($els) ) ? '_' . $classnames->elements : '' );
			$classname = array('cnr_attributes_wrap', $group_fields);
			$out[] = '<div class="' . implode(' ', $classname) . '">'; //Wrap all fields in group

			//Build layout for each field in group
			foreach ( array_keys($group->fields) as $field_id ) {
				/**
				 * CNR_Field_Type
				 */
				$field =& $group->fields[$field_id];
				$field->set_caller($this);
				//Start field output
				$id = 'cnr_field_' . $field->get_id();
				$class = array('cnr_attribute_wrap');
				//If single field in group, check if field title matches group
				if ( count($group->fields) == 1 && $group->title == $field->get_property('label') )
					$class[] = 'group_field_title';
				//Add flag to indicate that field was loaded on page
				$inc = 'cnr[fields_loaded][' . $field->get_id() . ']';
				$out[] = '<input type="hidden" id="' . $inc . '" name="' . $inc . '" value="1" />';
				$out[] = '<div id="' . $id . '_wrap" class="' . implode(' ', $class) . '">';
				//Build field layout
				$out[] = $field->build_layout();
				//end field output
				$out[] = '</div>';
				$field->clear_caller();
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
			if ( $field instanceof CNR_Field_Type ) {
				$field = $field->get_id();
			}
			if ( !is_string($field) || empty($field) )
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

	/**
	 * Retrieves type ID formatted as a meta value
	 * @return string
	 */
	function get_meta_value() {
		return serialize(array($this->id));
	}
}