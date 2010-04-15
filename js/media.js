/**
 * Media
 * @package Cornerstone
 * @author SM
 */

/* Post */

function convertImageId(img_id) {
	return 'cnr_field_' + img_id.replace('cnr[attributes]', '').replace('][', '_').replace('[', '').replace(']', '')
}

function setPostImage(img_id, img_url, img_type) {
	//console.dir(arguments);
	if (typeof(img_type) != 'undefined' && img_type.length) {
		var selContainer = '#' + convertImageId(img_type) + '_wrap';
		//console.log('Selector: %o', selContainer);
		var container = jQuery(selContainer);
		//console.log('Selected Element: %o', container);
		var img;
		var imgElName = escSelector(img_type);
		//Set ID of image
		var imgId = jQuery(container).find("#" + imgElName);
		//console.log('Field with ID (%s): %o', imgElName, imgId);
		if (imgId.length == 0) {
			imgId = jQuery('<input />').attr({
				type: 'hidden',
				id: img_type,
				name: img_type
			});
			jQuery(container).prepend(imgId);
		}
		jQuery(imgId).attr('value', img_id);
		//Build Image Element with Image URL
		if (img_url.length > 0) {
			img = jQuery(container).find("#" + imgElName + '-frame');
			if (img.length == 0) {
				img = jQuery("<img />").attr({
					title: 'Post Image',
					alt: 'Post Image',
					'class': 'image_frame',
					id: img_type + '-frame'
				});
				jQuery(container).prepend(img);
			}
			jQuery(img).attr('src', img_url);
		}
		
		//Show Image Options
		var opts = jQuery('#' + imgElName + '-options').removeClass('options-default');
		//Hide confirmation options
		opts.find('.confirmation').addClass('confirmation-default');
	}
	//Close popup
	tb_remove();
}

function postImageAction(el) {
	if (!el.id || el.id.length < 1)
		return false;
	var sep = '-';
	//Get Element Parts
	var parts = el.id.split(sep);
	//Base
	var base = (parts.length > 2) ? parts.slice(0, parts.length - 1).join(sep) : parts[0];
	//Action
	var action = parts[parts.length - 1];
	//console.log('Parts: %o \nBase: %o \nAction: %o', parts, base, action);
	var actEl;
	var getEl = function (ident) {
		ident = (typeof(ident) != 'undefined' && ident.length > 0) ? sep + ident : '';
		return jQuery('#' + escSelector(base) + ident);
	}
	switch (action) {
		case 'option_remove':
			actEl = getEl('remove_confirmation').removeClass('confirmation-default');
			break;
		case 'remove_cancel':
			getEl('remove_confirmation').addClass('confirmation-default');
			break;
		case 'remove':
			//Remove Image Frame
			getEl('frame').remove();
			//Reset Image ID
			getEl().attr('value', 0);
			//Hide remove options
			jQuery(jQuery(el).parents('.options').get(0)).addClass('options-default');
			//Hide confirmation options
			jQuery(el).parents('.confirmation').addClass('confirmation-default');
			break;
	}
	
	return false;
}