tinyMCEPopup.requireLangPack();

var CnrIntUrl = {
	init : function() {
		var f = document.forms[0];
		var ed = tinyMCEPopup.editor;
		var e = ed.dom.getParent(ed.selection.getNode(), 'A');
		
		//Set default input values
		if (e != null) {
			//Link Text
			f.link_text.value = e.innerHTML.toString();
			
			//Link Anchor
			f.link_anchor.value = this.getAnchor(e);
			
			//Link Title
			f.link_title.value = this.getTitle(e);
			
			//Post selection
			this.selectPost(e);
		}
		else
			f.link_text.value = ed.selection.getContent({format : 'text'});
		//f.somearg.value = tinyMCEPopup.getWindowArg('plugin_url');
	},

	insert : function() {
		// Insert the contents from the input into the document
		var f = document.forms[0];
		var ed = tinyMCEPopup.editor;
		var e = ed.dom.getParent(ed.selection.getNode(), 'A');
		
		//Setup internal URL properties
		var setupInturl = function(el) {
			var post = document.getElementById('link_post');
			var postId = post.options[post.selectedIndex].value;
			ed.dom.setAttribs(el, {
				href:	f.link_anchor.value,
				id:	 	'inturl_' + postId,
				title:	f.link_title.value,
				'class':	'inturl link'
			});
			if (f.link_anchor.value.toString().length > 0)
		
			el.innerHTML = f.link_text.value;
		}
		
		if (e == null) {
			var tempHref = '#inturl_temp#';
			//New Link
			tinyMCEPopup.execCommand("CreateLink", false, tempHref, {skip_undo : 1});
			tinymce.each(ed.dom.select('a'), function(n) {
				e = n;
				if (ed.dom.getAttrib(e, 'href') == tempHref)
					setupInturl(e);
			});
		} else {
			//Update previously created link
			setupInturl(e);
		}
		
		tinyMCEPopup.close();
	},
	
	getPostId : function(e) {
		var pId = tinyMCEPopup.editor.dom.getAttrib(e, 'id').toString().replace('inturl_', '');
		if (isNaN(pId))
			pId = 0;
		return pId;
	},
	
	getTitle : function(e) {
		return tinyMCEPopup.editor.dom.getAttrib(e, 'title').toString();
	},
	
	getAnchor : function(e) {
		return tinyMCEPopup.editor.dom.getAttrib(e, 'href').toString();
	},
	
	selectPost : function(pId) {
		if (isNaN(pId))
			pId = this.getPostId(pId);
		var post = document.getElementById('link_post');
		//Find option with matching post ID
		if (pId > 0) {
			for (var i = 0; i < post.options.length; i++) {
				if (post.options[i].value == pId) {
					post.selectedIndex = i;
					break;
				}
			}
		}
	}
};

tinyMCEPopup.onInit.add(CnrIntUrl.init, CnrIntUrl);
