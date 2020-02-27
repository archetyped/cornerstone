/**
 * @package Cornerstone
 * @subpackage Structure
 * @author Archetyped
 */

(function ($) {
	
if ( CNR && CNR.extend ) CNR.extend('structure', {
	options: {	//Options
		using_permastruct: false
	},
	
	/**
	 * Init
	 */
	init: function() {
		if ( this.base.util.is_context(['admin_page_post-new', 'admin_page_post']) ) {
			this.setup_item_edit();
		}
	},
	
	/**
	 * Setup item edit form
	 */
	setup_item_edit: function() {
		var id = 'parent_id',
			sel = '#' + id;
		//Check for parent element
		if ( !$(sel).length ) {
			//Add parent element
			var el_par = $('<input type="hidden" />').attr({
				'id': id
			}).appendTo('body');
		}
		
		//Setup parent field
		if ( this.options.field_parent && $('#' + this.options.field_parent).length ) {
			var f_par = $('#' + this.options.field_parent);
			//Define event handler
			var setParent = function(p) {
				if ( typeof p == 'undefined' || p.toString().length == 0 ) {
					p = 0;
				}
				$(sel).val(p);
			};
			//Set initial value
			setParent();
			//Add event handler
			$(f_par).change(function(e) {
				//Set parent
				setParent($(this).val());
				//Autosave (if possible)
				if ( $('#title').val().length > 0 && window.delayed_autosave ) {
					$('#edit-slug-box').html('');
					autosaveLast = '';
					delayed_autosave();
				}
			});
		}
		
	}
});

if ( CNR.structure.init ) {
	$(document).ready(function() {
		CNR.structure.init();
	});
}

})(jQuery);