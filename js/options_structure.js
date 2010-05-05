(function($) {
	var addPermalinkOption = function() {
		//Continue processing if permalink option has been defined
		if (typeof(cnr_permalink_option) == 'object') {
			o = cnr_permalink_option;
			//Get options table
			var options = $('.wrap .form-table tbody:first');
			var rows = $(options).find('tr');
			//Insert custom option
			if (rows.length) {
				var last = $(rows).get(rows.length - 1);
				var option = $(options).find('tr:first').clone();
				var input = $(option).find('input');
				//Input
				$(input).val(o.structure);
				if (o.structure == $('#permalink_structure').val()) {
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
	};
	
	$(document).ready(function() {addPermalinkOption();});
}) (jQuery);
