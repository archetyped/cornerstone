(function($){
	
if ( !CNR || !CNR.posts || !CNR.posts.extend )
	return false;

CNR.posts.extend('quicktags', {
	get_button_id: function(id) {
		return this.base.add_prefix(id);
	}
});
	

//Create Quicktag button
if ( QTags && QTags.addButton ) {
	QTags.addButton(CNR.posts.quicktags.get_button_id('inturl'), 'link (internal)', function(qtEl, qtContent, qtObj) {
		//Only process when text is selected
		if ( ( qtContent.selectionStart || qtContent.selectionStart == 0 ) && qtContent.selectionStart < qtContent.selectionEnd ) {
			//Get post ID from user
			var val = prompt('Enter Post/Page ID');
			//Wrap selected text in quicktag
			if (val && !isNaN(parseInt(val))) {
				var text = '';
				//Get selected text
				text = qtContent.value.substring(qtContent.selectionStart, qtContent.selectionEnd);
				//Wrap selection in quicktag
				val = '[inturl id=' + val + ']' + text + '[/inturl]';
				//Update selection in editor
				QTags.insertContent(val);
			}
		} else {
			alert('Text must be selected');
		}
	}, null, null, 'Insert internal link', 31);
}

})(jQuery);