/**
 * Core
 * @package Cornerstone
 * @author Archetyped
 */

var CNR = {};

(function($){
/* Quick Hide */

$('html').addClass('js');

/* Classes */

/* CNR */
CNR = {
	prefix: 'cnr',
	context: 	[],	//Context
	options:	{	//Options
	},
	
	extend: function(member, data) {
		if ( $.type(member) == 'string' && $.isPlainObject(data) ) {
			//Add initial member
			var obj = {};
			obj[member] = $.extend({}, data);
			$.extend(this, obj);
			
			if ( member in this ) {
				//Add additional objects
				var args = ( arguments.length > 2 ) ? [].slice.apply(arguments).slice(2) : [];
				args.unshift(this[member]);
				//Add base properties
				args.push({
					base: CNR,
					parent: this,
					extend: this.extend
				});
				$.extend.apply($, args);
			}
		}
	},
	
	/* Prefix */
	
	/**
	 * Retrieve valid separator
	 * If supplied argument is not a valid separator, use default separator
	 * @param string (optional) sep Separator text
	 * @return string Separator text
	 */
	get_sep: function(sep) {
		if ( typeof sep == 'undefined' || sep == null )
			sep = '';
		
		return ( $.type(sep) == 'string' ) ? sep : '_';
	},
	
	/**
	 * Retrieve prefix
	 * @param string (optional) sep Separator text
	 * @return string Prefix (with separator if specified)
	 */
	get_prefix: function(sep) {
		return ( this.prefix && this.prefix.length ) ? this.prefix + this.get_sep(sep) : '';
	},
	
	/**
	 * Check if string is prefixed
	 */
	has_prefix: function(val, sep) {
		return ( $.type(val) == 'string' && val.length && val.indexOf(this.get_prefix(sep)) === 0 );
	},
	
	/**
	 * Add Prefix to value
	 * @param string val Value to add prefix to
	 * @param string sep (optional) Separator (Default: `_`)
	 * @param bool (optional) once If text should only be prefixed once (Default: true)
	 */
	add_prefix: function(val, sep, once) {
		if ( typeof sep == 'undefined' )
			sep = '_';
		once = ( typeof once == 'undefined' ) ? true : !!once;
		if ( once && this.has_prefix(val, sep) )
			return val;	
		return this.get_prefix(sep) + val;
	}
}

/* Utilities */
CNR.extend('util', {
	/**
	 * Return formatted string
	 */
	sprintf: function() {
		var format = '',
			params = [];
		if (arguments.length < 1)
			return format;
		if (arguments.length == 1) {
			format = arguments[0];
			return format;
		}
		params = arguments.slice(1);
		return format;
	},
	
	/* Request */
	
	/**
	 * Retrieve valid context
	 * @return array Context
	 */
	get_context: function() {
		//Valid context
		if ( !$.isArray(this.base.context) )
			this.base.context = [];
		//Return context
		return this.base.context;
	},
			
	/**
	 * Check if a context exists in current request
	 * If multiple contexts are supplied, result will be TRUE if at least ONE context exists
	 * 
	 * @param string|array ctx Context to check for
	 * @return bool TRUE if context exists, FALSE otherwise
	 */
	is_context: function(ctx) {
		var ret = false;
		//Validate context
		if ( typeof ctx == 'string' )
			ctx = [ctx];
		if ( $.isArray(ctx) && this.arr_intersect(this.get_context(), ctx).length ) {
			ret = true;
		}
		return ret;
	},
	
	/* DOM */
	
	/**
	 * Strip selector (ID, class, etc.)
	 * @param string id_value Original element
	 */
	esc_selector: function(id_value) {
		return id_value.toString().replace(/(\[|\])/gi, '\\$1');
	},
	
	/**
	 * Find common elements of 2 arrays
	 * @param array arr1 First array
	 * @param array arr2 Second array
	 * @return array Elements common to both arrays
	 */
	arr_intersect: function(arr1, arr2) {
		var ret = [];
		if ( arr1 == arr2 ) {
			return arr2;
		}
		if ( !$.isArray(arr2) || !arr2.length || !arr1.length ) {
			return ret;
		}
		//Compare elements in arrays
		var a1;
		var a2;
		var val;
		if ( arr1.length < arr2.length ) {
			a1 = arr1;
			a2 = arr2;
		} else {
			a1 = arr2;
			a2 = arr1;
		}

		for ( var x = 0; x < a1.length; x++ ) {
			//Add mutual elements into intersection array
			val = a1[x];
			if ( a2.indexOf(val) != -1 && ret.indexOf(val) == -1 )
				ret.push(val);
		}
		
		//Return intersection results
		return ret;
	}
});

})(jQuery); //END CNR init