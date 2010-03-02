(function($) {
	inlineEditPost.addEvents = function(r) {
		r.each(function() {
			var row = $(this);
			$('a.editinline', row).click(function() { cnrInlineEditPost.preEdit(this); inlineEditPost.edit(this); cnrInlineEditPost.postEdit(this); return false; });
		});
	};
	
	//Extend inlineEditPost object
	cnrInlineEditPost = jQuery.extend({}, inlineEditPost);
	
	cnrInlineEditPost.init = function() {
		var qeRow = $('#inline-edit')
		$('a.save', qeRow).click(function() { return cnrInlineEditPost.save(this); });
		$('td', qeRow).keydown(function(e) { if (e.which == 13) { return cnrInlineEditPost.save(this); } });
		
	};
	
	cnrInlineEditPost.save = function(id) {
		var t = this, post_id, post_parent;
		//Update post data
		if (typeof(id) == 'object')
			id = t.getId(id);
		if (id) {
			//Setup postData object for post
			post_id = 'post_' + id;
			if (typeof(postData) == 'undefined')
				postData = {};
			if (!(post_id in postData))
				postData[post_id] = {};
				
			//Get post parent
			post_parent = $('#edit-' + id + ' #post_parent option:selected');
			if (post_parent.length) {
				//Set post parent in postData
				postData[post_id]['post_parent'] = post_parent.val();
			}
		}
		return true;
	};
	
	cnrInlineEditPost.preEdit = function(id) {
		var t = this, post_id, section_select, parent_id;
		if (typeof(id) == 'object')
			id = t.getId(id);
		
		//Get master section selection
		section_select = $('#inline-edit #post_parent');
		//Get Parent ID
		if (section_select.length && typeof(postData) != 'undefined' && (post_id = 'post_' + id) && ((post_id) in postData) && ('post_parent' in postData[post_id])) {
			parent_id = postData[post_id].post_parent;
			//Set selected
			$('option[value=' + parent_id + ']', section_select).get(0).defaultSelected = true;
		}
	};
	
	cnrInlineEditPost.postEdit = function(id) {
		$('#inline-edit #post_parent option').removeAttr('selected');
	};
	
	$(document).ready(function() {cnrInlineEditPost.init();});
})(jQuery);
