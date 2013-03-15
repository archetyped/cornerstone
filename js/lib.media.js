/**
 * Media
 * @package Cornerstone
 * @author Archetyped
 */

(function($) {

if ( CNR && CNR.extend ) CNR.extend('media', {
		/**
		 * Convert array-based element IDs to standalone IDs
		 * @param string el_id Element ID
		 * @return string Converted ID
		 */
		convertId : function (el_id) {
			return 'cnr_field_' + el_id.replace('cnr[attributes]', '').replace('][', '_').replace('[', '').replace(']', '')
		},
		/**
		 * Set selected media as value of specified post field
		 * @param object a Media arguments
		 * Arguments
		 * 	id: Attachment ID
		 *  field: Field ID
		 *  url: Source URL
		 *  type: Mime type
		 *  preview: URL of preview image (file type icon, etc.)
		 */
		setPostMedia : function (a) {
			if ( typeof(a) != 'undefined' ) {
				var selContainer = '#' + CNR.util.esc_selector(a.field) + '-data';
				var container = $(selContainer);
				var mediaElName = CNR.util.esc_selector(a.field);
				//Set Media ID
				var mediaId = $(container).find("#" + mediaElName);
				if ( mediaId.length == 0 ) {
					mediaId = $('<input />').attr({
						type: 'hidden',
						id: a.field,
						name: a.field
					});
					$(container).prepend(mediaId);
				}
				$(mediaId).attr('value', a.id);
				
				//Add source file link
				media_el = $(container).find('#' + mediaElName + '-link');
				if ( media_el.length == 0 ) {
					//Build link
					media_el = $('<a>').attr({
						title: 'Media Link',
						'class': 'media_link',
						'target': '_blank',
						id: a.field + '-link'
					});
					$(container).prepend(media_el);
				}
				$(media_el).attr('href', a.url).text( a.url.substr(a.url.lastIndexOf('/') + 1) );
				
				//Build preview based on media type
				var media_el;
				if ( a.preview.length > 0 ) {
					media_el = $(container).find("#" + mediaElName + '-frame');
					if (media_el.length == 0) {
						media_el = $("<img />").attr({
							title: 'Media Preview',
							alt: 'Media Preview',
							'class': 'media_frame',
							id: a.field + '-frame'
						});
						$(container).prepend(media_el);
					}
					$(media_el).attr('src', a.preview);
				}
				
				//Show Media Options
				var opts = $('#' + mediaElName + '-options').removeClass('options-default');
				//Hide confirmation options
				opts.find('.confirmation').addClass('confirmation-default');
			}
			//Close popup
			tb_remove();
		},
		/**
		 * Execute an action
		 * Uses attributes (class, etc.) of triggering element to determine action to execute
		 * @param {Object} el Element that triggered the action
		 */
		doAction : function (el) {
			if (!el.id || el.id.length < 1)
				return false;
			var sep = '-';
			//Get Element Parts
			var parts = el.id.split(sep);
			//Base
			var base = (parts.length > 2) ? parts.slice(0, parts.length - 1).join(sep) : parts[0];
			//Action
			var action = parts[parts.length - 1];
			var actEl;
			var getEl = function (ident) {
				ident = (typeof(ident) != 'undefined' && ident.length > 0) ? sep + ident : '';
				return $('#' + CNR.util.esc_selector(base) + ident);
			};
			switch (action) {
				case 'option_remove':
					actEl = getEl('remove_confirmation').removeClass('confirmation-default');
					break;
				case 'remove_cancel':
					getEl('remove_confirmation').addClass('confirmation-default');
					break;
				case 'remove':
					//Remove Image Frame
					getEl('data').empty();
					//Reset Image ID
					getEl().attr('value', 0);
					//Hide remove options
					$($(el).parents('.options').get(0)).addClass('options-default');
					//Hide confirmation options
					$(el).parents('.confirmation').addClass('confirmation-default');
					break;
			}
			
			return false;
		}
});
})(jQuery);
