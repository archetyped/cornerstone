/* Quick Hide */

jQuery('html').addClass('js');

/* Prototypes */

/**
 * Binds a method to an object so that 'this' refers to the object instance within the method
 * Useful for setting an object's method as a callback in another object
 * Any arguments can also be passed to the method when it is called
 * @param object obj Object instance to bind the method to
 * @param function method Method of object to bind to object instance (obj)
 * @return bound object method wrapped in an anonymous function
 */
bindFunc = function(obj, method) {
	return function() {
		if (method in obj)
			obj[method].apply(obj, arguments);
	}
}

/**
 * Compares another array with this array
 * @param array arr Array to compare this array with
 * @return bool Whether arrays are equal or not
 */
Array.prototype.compare = function(arr) {
	if (typeof arr == 'object' && this.length == arr.length) {
		for (var x = 0; x < this.length; x++) {
			//Nested array check
			if (this[x].compare && !this.compare(arr[x])) {
				return false;
			}
			if (this[x] !== arr[x])
				return false;
		}
		return true;
	}
	return false;
}

String.prototype.trim = function() {
	return this.replace(/^\s+|\s+$/g,"");
}
String.prototype.ltrim = function() {
	return this.replace(/^\s+/,"");
}
String.prototype.rtrim = function() {
	return this.replace(/\s+$/,"");
}

/* Classes */

/* CNR */

function Cnr() {}
Cnr.urlAdmin = 'http://cns.wp/wp-admin/admin-ajax.php';

/* Helper Functions */

function sprintf() {
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
}

jQuery('document').ready(function() {
	//Sortable list (Site Pages)
	/*
	jQuery('ul').sortable({
		opacity: 0.6,
		placeholder: 'ph',
		items: 'li',
		connectWith: 'ul',
		start: function(event, ui) {
			ui.item.addClass('sorting');
		},
		stop: function(event, ui) {
			
		}
	});
	*/
	
	if (jQuery.jTree) {
		jQuery('.site-pages').jTree({
			showHelper: true,
			hOpacity: 0.5,
			hBg: "#FCC",
			hColor: "#222",
			pBorder: "1px dashed #CCC",
			pBg: "#EEE",
			pColor: "#222",
			pHeight: "20px",
			snapBack: 1200,
			childOff: 20
		});
	}
	jQuery('.site-pages a').click(function() { return false; });
	//Initialize Page Groups
	PageGroup.initGroups();
	var sp = new SitePages(SitePages.Selectors.wrap.get());
	sp.serialize();
	jQuery('h2').click(function() {
		sp.serialize();
	})
});