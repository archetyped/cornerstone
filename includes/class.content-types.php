<?php

/* Variables */

//Global content type variables
if ( !isset($cnr_content_types) )
	$cnr_content_types = array();
if ( !isset($cnr_field_types) )
	$cnr_field_types = array();

/* Init */
$cnr_content_utilities = new CNR_Content_Utilities();
$cnr_content_utilities->init();

/* Hooks */

//Default placeholder handlers
cnr_register_placeholder_handler('all', array('CNR_Field_Type', 'process_placeholder_default'), 11);
cnr_register_placeholder_handler('field_id', array('CNR_Field_Type', 'process_placeholder_id'));
cnr_register_placeholder_handler('field_name', array('CNR_Field_Type', 'process_placeholder_name'));
cnr_register_placeholder_handler('data', array('CNR_Field_Type', 'process_placeholder_data'));
cnr_register_placeholder_handler('loop', array('CNR_Field_Type', 'process_placeholder_loop'));
cnr_register_placeholder_handler('data_ext', array('CNR_Field_Type', 'process_placeholder_data_ext'));
cnr_register_placeholder_handler('rich_editor', array('CNR_Field_Type', 'process_placeholder_rich_editor'));


?>
