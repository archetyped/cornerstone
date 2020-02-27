(function($) {
if ( inlineEditPost && CNR && CNR.posts ) {
	//Move init method to guarantee execution order
	inlineEditPost.initSaved = inlineEditPost.init;
	inlineEditPost.init = function() {};
	
	//Extend inlineEditPost object
	CNR.posts.extend('inline_edit', inlineEditPost, {
		field_parent: '',
		
		init: function() {
			this.field_parent = this.base.add_prefix('post_parent');
			var t = this;
			//Execute default init method
			if ( inlineEditPost.initSaved ) {
				inlineEditPost.initSaved();
			}
			//Unbind quick edit click events
			$('.wp-list-table tbody').off( 'click',  '.editinline' );
			//Bind new quick edit click handler
			$('.wp-list-table tbody').on( 'click', '.editinline',
				function() {
					t.editHandler(this);
					return false;
				}
			);
			var qeRow = $('#inline-edit');
			$('a.save', qeRow).click(function() { return t.save(this); });
			$('td', qeRow).keydown(function(e) { if (e.which == 13) { return t.save(this); } });
			//Restore original init method for future use
			if ( inlineEditPost.initSaved ) {
				inlineEditPost.init = inlineEditPost.initSaved;
			}
		},
	
		save: function(id) {
			var t = this, post_parent;
			//Update post data
			if (typeof(id) == 'object') {
				id = t.getId(id);
			}
			if (id) {
				//Get post parent
				post_parent = $('#edit-' + id + ' #' + t.field_parent + ' option:selected');
				if (post_parent.length) {
					//Set post parent in postData
					this.parent.set_data(id, 'post_parent', post_parent.val());
				}
			}
			return true;
		},
		
		editHandler: function(id) {
			this.preEdit(id);
			inlineEditPost.edit(id);
			this.postEdit(id);
		},
		
		preEdit: function(id) {
			var t = this, post_id, section_select, parent_id;
			if (typeof(id) == 'object') {
				id = t.getId(id);
			}
			//Get master section selection
			section_select = $('#inline-edit #' + t.field_parent);
			//Get Parent ID
			if ( section_select.length && t.parent.has_data(id, 'post_parent') ) {
				parent_id = t.parent.get_data(id, 'post_parent');
				//Set selected
				$('option[value=' + parent_id + ']', section_select).get(0).defaultSelected = true;
			}
		},
		
		postEdit: function(id) {
			$('#inline-edit #' + this.field_parent + ' option').removeAttr('selected');
		}
	});
}

if ( CNR && CNR.admin && CNR.posts.inline_edit && CNR.posts.inline_edit.init ) {
	$(document).ready(function() { CNR.posts.inline_edit.init(); });
}
	
})(jQuery);