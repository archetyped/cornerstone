(function($) {

if ( !CNR || !CNR.extend )
	return false;

CNR.extend('posts', {
	data: {},
	
	item_section: false,
	title_sep: '',
	
	get_data_id: function(id) {
		var pre = 'post_';
		if ( id.indexOf(pre) !== 0 )
			id = pre + id;
		return id;
	},
	
	has_data: function(id, prop) {
		var ret = false;
		id = this.get_data_id(id);
		if ( id in this.data ) {
			ret = true;
			if ( $.type(prop) == 'string' )
				ret = ( prop in this.data[id] ) ? true : false;
		}
		return ret;
	},
	
	get_data: function(id, prop) {
		var ret = '';
		id = this.get_data_id(id);
		if ( this.has_data(id) && prop in this.data[id] ) {
			ret = this.data[id][prop];
		}
		return ret;
	},
	
	set_data: function(id, prop, val) {
		//Validate args
		if ( $.type(id) != 'string' || $.type(prop) != 'string' || typeof val == 'undefined' )
			return false;
		//Create data object (if necessary)
		id = this.get_data_id(id);
		if ( ! this.has_data(id) )
			this.data[id] = {};
		//Set data
		this.data[id][prop] = val;
	},
	
	set_wpseo_title: function() {
		if ( typeof wpseo_title_template == 'undefined' || !this.item_section )
			return false;
		var ph_title = '%%title%%';
		wpseo_title_template = wpseo_title_template.replace(ph_title, ph_title + this.title_sep + this.item_section);
	}
});

$(document).ready(function() {
	CNR.posts.set_wpseo_title();
})

})(jQuery);
