/**
 * Structure Administration
 * @package Cornerstone
 * @subpackage Structure
 */

(function($) {

if ( CNR && CNR.structure && CNR.structure.extend ) CNR.structure.extend('admin', {
	parent: CNR.structure,
	options: {},
	
	manage_option: function() {
		//Continue processing if permalink option has been defined
		if ( this.options && this.options.label && this.options.example && this.parent.options.permalink_structure ) {
			var o = this.options;
			var po = this.parent.options;
			//Get options table
			var opts_list = $('.wrap .form-table tbody:first');
			var rows = $(opts_list).find('tr');
			//Insert custom option
			if (rows.length) {
				var last = $(rows).get(rows.length - 1);
				var option = $(opts_list).find('tr:first').clone();
				var input = $(option).find('input');
				//Input
				$(input).val(po.permalink_structure);
				if (po.permalink_structure == $('#permalink_structure').val()) {
					$(input).attr('checked', 'checked');
				} else {
					$(input).removeAttr('checked');
				}
				//Label
				var label = $(option).find('label');
				//Move input element out of label element
				$(label).text(o.label).prepend(input);
				//Example text
				$(option).find('td code').text(o.example);
				//Insert element before last option in table
				$(last).before($(option));
			}
		}
	}	
});

if ( CNR.structure.admin.manage_option )
	$(document).ready(function() {CNR.structure.admin.manage_option();});

}) (jQuery);
