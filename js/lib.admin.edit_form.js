var page = ( adminpage ) ? adminpage : 'post';
jQuery(document).ready( function($) {
	if ( postboxes && postboxes.add_postbox_toggles )
		postboxes.add_postbox_toggles(page);
});