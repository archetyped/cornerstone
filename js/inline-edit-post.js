(function($) {
	inlineEditPost.addEvents = function(r) {
		r.each(function() {
			var row = $(this);
			$('a.editinline', row).click(function() { inlineEditPost.edit(this); cnrInlineEditPost.edit(this); return false; });
		});
	};
	
	//Extend inlineEditPost object
	cnrInlineEditPost = jQuery.extend({}, inlineEditPost);
	
	cnrInlineEditPost.edit = function(id) {
		var t = this, f_post_parent, parent_id;
		if (typeof(id) == 'object')
			id = t.getId(id);
		f_post_parent = $('#post_parent');
		if (f_post_parent.length && typeof(postData) != 'undefined' && (('post_' + id) in postData) && ('post_parent' in postData['post_' + id])) {
			//Get post's parent ID
			parent_id = postData['post_' + id].post_parent;
			//Select post's parent in dropdown
			$('option[value=' + parent_id + ']').attr('selected', 'true');
		}
	};
})(jQuery);
