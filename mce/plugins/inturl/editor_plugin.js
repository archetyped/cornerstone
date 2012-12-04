/**
 * Internal URL MCE Plugin
 *
 * @author Archetyped
 * @package Cornerstone
 */

(function() {
	// Load plugin specific language pack
	var pluginId = 'cnr_inturl';
	
	tinymce.PluginManager.requireLangPack(pluginId);
	
	tinymce.create('tinymce.plugins.CNRIntUrl', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 *
		*/
		
		//TODO Create icon
		init : function(ed, url) {
			
			var props = {
						'id':			pluginId,
						'className':	'inturl',
						'shortcode':	'inturl',
						'dialogPath':	'/dialog.php',
						'iconPath':		'/img/example.gif',
						};
			var util = {
						'isInturl':		function(e) {
								return (ed.dom.hasClass(e, props.className)) ? true : false;
							}
					};
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('cnr_inturl');
			ed.addCommand(props.id, function() {
				ed.windowManager.open({
					file : url + props.dialogPath,
					width : 360 + parseInt(ed.getLang(props.id + '.delta_width', 0)),
					height : 200 + parseInt(ed.getLang(props.id + '.delta_height', 0)),
					inline : 1
				}, {
					plugin_url : url, // Plugin absolute URL
					//some_custom_arg : 'custom arg' // Custom argument
				});
			});

			// Register button
			ed.addButton(props.id, {
				title : props.id + '.desc',
				cmd : props.id,
				image : url + props.iconPath
			});
			
			//Indent/Outdent shortcuts
			ed.addShortcut('alt+shift+0', 'Indent', 'Indent');
			ed.addShortcut('alt+shift+9', 'Outdent', 'Outdent');

			//Format quicktag in editor (HTML > Visual)
			ed.onBeforeSetContent.add(function(ed, o) {
				var reAnchor = /(\[inturl\s.*?)\banchor=("|'){0,1}(.*?\[\/inturl\])/g;
				o.content = o.content.replace(reAnchor, '$1href=$2$3');
				var re = /\[inturl\s(.*?)\bid=(?:"|'){0,1}(\d+?)(?:"|'){0,1}\b(.*?)\](.*?)\[\/inturl\]/g;
				o.content = o.content.replace(re, '<a id="' + props.className + '_$2" class="' + props.className + ' link"$1$3>$4</a>'); 
			});

			//Revert content to quicktag (Visual > HTML)
			ed.onPostProcess.add(function(ed, o) {
				if (o.get) {
					
					//Convert <a> elements to shortcode
					var reInit = /<a\s([^>]*?)id="inturl_(\d+)"([^>]*)>(.*?)<\/a>/g;
					o.content = o.content.replace(reInit, '[' + props.shortcode + ' id=$2$1$3]$4[/' + props.shortcode + ']');
					//Cleanup: Remove `class` attribute from shortcode
					var reClass = /\[inturl([^\]]*?)\sclass=(?:'|"){1}([^\]]*?)(?:'|"){1}([^\]]*?)\](.*?)\[\/inturl\]/g
					o.content = o.content.replace(reClass, '[' + props.shortcode + '$1$3]$4[/' + props.shortcode + ']');
					//Cleanup: Remove `href` attribute from shortcode
					var reAnchor = /\[inturl([^\]]*?)\shref=(?:'|"){1}([^\]]*?)(?:'|"){1}([^\]]*?)\](.*?)\[\/inturl\]/g
					o.content = o.content.replace(reAnchor, '[' + props.shortcode + '$1 anchor="$2"$3]$4[/' + props.shortcode + ']');
					/*
					*/
				}
				
				
			});
			
			//Add status bar text for internal urls
			ed.onPostRender.add(function() {
				if (ed.theme.onResolveName) {
					ed.theme.onResolveName.add(function(th, o) {
						if (o.node.nodeName == 'A') {
							if (ed.dom.hasClass(o.node, props.className))
								o.name = props.className;
						}
					});
				}
			});
			
			//Enable/Disable button based on text selection
			ed.onNodeChange.add(function(ed, cm, e) {
				var bId = props.id;
				
				var isIntUrl = function() {
					return (ed.dom.hasClass(e, props.className)) ? true : false;
				}
				
				if (util.isInturl(e)) {
					cm.setDisabled(bId, false);
					cm.setActive(bId, true);
				} else {
					cm.setActive(bId, false);
					if (ed.selection.getContent().length > 0)
						cm.setDisabled(bId, false);
					else
						cm.setDisabled(bId, true);
				}
			});
		},

		/**
		 * Creates control instances based in the incomming name. This method is normally not
		 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
		 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control was created.
		 */
		createControl : function(n, cm) {
			return null;
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : 'CNR Internal URL',
				author : 'Archetyped',
				authorurl : 'http://archetyped.com',
				infourl : '',
				version : "0.1"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add(pluginId, tinymce.plugins.CNRIntUrl);
})();