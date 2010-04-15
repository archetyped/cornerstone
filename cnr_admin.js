/* Selector */

function Selector(value, type) {
	this.val = value;
	this.type = type;
	if (this.type != "" && this.val.indexOf(this.type) == 0)
		this.val = this.val.substr(1);
}

Selector.Types = {
					'Class':	'.',
					'ID':		'#',
					'Tag':		''
					};

/**
 * Returns formatted value for use as jQuery selector
 * @return string formatted as a jQuery selector
 */
Selector.prototype.get = function() {
	return this.type + this.val;
}

function escSelector(id_value) {
	return id_value.toString().replace(/(\[|\])/gi, '\\$1');
}

/* Page Group */

/**
 * PageGroup
 */
function PageGroup() {
	
	this.defaults = {
					'fields': [],
					'values': {}
					};
	
	this.addDefault('title', 'code', 'pages');
	
	/**
	 * @var int Group ID
	 */
	this.id;
	/**
	 * @var string Group title
	 */
	this.title = '';
	/**
	 * @var string Group code (unique)
	 */
	this.code = '';
	
	/**
	 * @var object DOM Nodes used in Group (jQuery Object)
	 * group:		Entire group
	 * title:		Group Title
	 * code:		Group Code
	 * count:		Group Page count
	 * pages:		Pages list
	 * sitePages:	Site Pages List
	 * actions:		Group actions (save, reset, etc.)
	 */
	this.nodes = {
				'group':		'',
				'title':		'',
				'code':			'',
				'count':		'',
				'pages':		'',
				'sitePages':	'',
				'actions':		''
				}
	/**
	 * @var object Pages arrays (default/current)
	 */
	this.pages = {
				'def':		[],
				'current':	[]
				};
	
	/**
	 * @var object Values to use for connecting elements together
	 */
	this.connections = {
						'group':	'pageGroup',
						'page':		'page'
						}
	/**
	 * @var object class values used for different types of elements in DOM
	 */
	this.classes = {
					'group':		'page-group',
					'title':		'group-title',
					'code':			'group-code',
					'count':		'group-count',
					'pages':		'group-pages',
					'page':			'item-page',
					'sitePages':	'site-pages',
					'actions':		'actions',
					'actionsShy':	'actions-commit',
					'action':		'action',
					'pageDelete':	'page-delete',
					'propInput':	'prop-input',
					'empty':		'empty'
					};
	
	/**
	 * @var object actions Actions for different events
	 */
	this.actions = {
					'save':		new PageGroup.Action('save', '', 'clean'),
					'edit':		new PageGroup.Action('edit', '', 'modify'),
					'remove':	new PageGroup.Action('remove', '', 'clean'),
					'reset':	new PageGroup.Action('reset', '', 'clean'),
					'page':		{
								'delete':	new PageGroup.Action('delete',
											bindFunc(this, 'addPageDelete'),
											'modify')
								}
					};
					
	/**
	 * @var object events Custom Event handlers
	 */
	this.events = {
					'modify':			function(e) {
											console.warn('Modify event triggered: %o', e);
											var pg;
											if ('pg' in e)
												e = e.pg;
											if (e instanceof PageGroup)
												pg = e;
											if (pg)
												jQuery(pg.nodes.actions).filter(pg.getClass('actionsShy')).show(300);
										},
					'clean':			function(e) {
											console.warn('Clean event triggered: %o', e);
											console.log(e.pg);
											jQuery(e.pg.nodes.actions).filter(e.pg.getClass('actionsShy')).hide(300);
										},
					'pagesEmpty':		function(e) {
											console.warn('Pages empty');
											var pg = false;
											if ('pg' in e)
												e = e.pg;
											if (e instanceof PageGroup)
												pg = e;
											if (!pg)
												return false;
											pg.getNode('pages').addClass(pg.classes.empty);
										},
					'pagesNonEmpty':	function(e) {
											console.warn('Pages Not Empty');
											var pg = false;
											if ('pg' in e)
												e = e.pg;
											if (e instanceof PageGroup)
												pg = e;
											if (!pg)
												return false;
											pg.getNode('pages').removeClass(pg.classes.empty);
										}
					}
	
	/**
	 * @var object nonces Nonces from server for security validation
	 */
	this.nonces = {};
	
	/**
	 * @var object Variable to store various states of group (HTML nodes, etc.) for restoration
	 * States:
	 * default: Default state of group (usually populated on page load or upon group creation)
	 */
	this.states = {
					'def':	''
					};
	this.init(arguments);
}

PageGroup.prototype.init = function() {
	console.group('Page Group Initialization');
	//Set object properties based on arguments passed (if available)
	if (arguments.length > 0) {
		var args = arguments[0];
		if (args.length && args.length > 0)
			args = args[0];
		if ('node' in args)
			this.nodes.group = args['node'];
		if ('id' in args)
			this.setId(args['id']);
		if ('pages' in args)
			this.setPages(args['pages'], 'def');
		if ('actions' in args)
			this.nodes.actions = args['actions'];
		//Add the rest of the arguments to the object
		var excluded = ['node', 'id', 'pages', 'actions'];
		for (var arg in args) {
			if (excluded.indexOf(arg) != -1)
				continue;
			this[arg] = args[arg];
		}
	}
	this.setNode();
	this.setTitle();
	this.setCode();
	this.checkPages();
	this.setDefaults();
	this.saveState();
	this.makeSortable();
	this.setActions();
	console.groupEnd();
}

PageGroup.prototype.addDefault = function(propName) {
	if (typeof propName == 'undefined' || arguments.length < 1 || !propName)
		return false;
	for (var x = 0; x < arguments.length; x++) {
		propName = arguments[x];
		if (this.defaults.fields.indexOf(propName) == -1)
			this.defaults.fields.push(propName);
	}
}

PageGroup.prototype.getDefaults = function(propName) {
	var ret = '';
	if (arguments.length > 0) {
		if (propName in this.defaults.values)
			ret = this.defaults.values[propName];
	} else {
		ret = this.defaults.values;
	}
	return ret;
}

PageGroup.prototype.setDefaults = function() {
	var prop;
	this.clearDefaults();
	//Iterate through default properties
	for (var x = 0; x < this.defaults.fields.length; x++) {
		prop = this.defaults.fields[x];
		//Add property to instance if not yet declared
		if (!(prop in this))
			this[prop];
		//Set default values of properties
		this.defaults.values[prop] = this[prop];
	}
}

PageGroup.prototype.clearDefaults = function() {
	this.defaults.values = {};
}

/**
 * Sets ID of Group object
 * @param mixed val ID value (should be parseable as an integer)
 */
PageGroup.prototype.setId = function(val) {
	if (!isNaN(val.toString()))
		this.id = parseInt(val);
}

PageGroup.prototype.getNodeText = function(node) {
	var txt = '';
	if (node in this.nodes && this.nodes[node].length) {
		//Get DOM node
		txt = this.nodes[node].text();
	}
	return txt; 
}

/**
 * Sets pages property (Defaults to current pages)
 * @param array pagesArr Array of page ID's to set as pages property
 * @param string [pagesType] which pages property to set (def or current [default])
 */
PageGroup.prototype.setPages = function(pagesArr, pagesType) {
	console.group('Setting Pages')
	console.info('Type: %o \nData: %o', pagesType, pagesArr);
	if (pagesArr instanceof Array)
		this.pages = pagesArr;
	this.formatPages();
	console.dir(this.pages);
	console.groupEnd();
}

/**
 * Formats values in pages property to integers
 * @param string [pagesType] which pages to format (def or current [default])
 */
PageGroup.prototype.formatPages = function() {
	console.group('Formatting Pages Array');
	console.info('Data: %o', this.pages);
	var pVal,
		pagesTemp = [];
	for (x = 0; x < this.pages.length; x++) {
		pVal = parseInt(this.pages[x]);
		//Only add pages that have valid IDs (integers)
		if (pVal)
			pagesTemp.push(pVal); 
	}
	this.pages = pagesTemp;
	console.groupEnd();
}

/**
 * Gets DOM nodes of Pages in Group
 * @return object jQuery object of Pages in Group
 * 
 */
PageGroup.prototype.getPagesNodes = function() {
	return this.getNode('pages').find(this.getClass('page'));
}

/**
 * Returns array of pages in group
 * Pages are represented by their (int) page IDs
 */
PageGroup.prototype.getPages = function() {
	console.group('Get Pages from DOM');
	var pagesArr = [];
	//Regex to extract page id
	var rePid = /.*\bpid_(\d+?)\b.*/i;
	var match;
	//Iterate through group pages
	this.getPagesNodes().each(function(i) {
		//Check if Page ID is included in item's class
		match = rePid.exec(this.className);
		if (match && match.length >= 2 && match[1] > 0) 
			pagesArr.push(parseInt(match[1]));
	});
	console.dir(pagesArr);
	this.pages = pagesArr;
	console.groupEnd();
	return this.pages;
}

PageGroup.prototype.checkPages = function() {
	console.warn('Checking pages');
	console.log(this);
	var node = this.getNode('pages');
	var count = this.getPagesNodes().length;
	if (count < 1) {
		this.triggerEvent(this.events.pagesEmpty);
		return false;
	}
	else {
		this.triggerEvent(this.events.pagesNonEmpty);
		return true;
	}
}

/**
 * Determines whether a property has been changed from its default
 * @param string prop Name of property to check
 * @return bool TRUE if property is different from default (FALSE otherwise)
 */
PageGroup.prototype.propChanged = function(prop) {
	console.group('propChanged')
	console.log('Property: %s', prop);
	var ret = false;
	if (prop in this && prop in this.defaults.values) {
		console.log('Current: %o \nDefault: %o', this[prop], this.getDefaults(prop));
		if (typeof this[prop] == 'string' && this[prop] != this.getDefaults(prop))
			ret = true;
	}
	console.log('Property Changed: %o', ret);
	console.groupEnd();
	return ret;
}

/**
 * Compares current pages in group to default pages in group
 */
PageGroup.prototype.pagesChanged = function() {
	console.group('Comparing Pages array');
	this.getPages();
	console.dir(this.pages);
	console.dir(this.defaults.pages);
	var ret = !this.pages.compare(this.getDefaults('pages'));
	console.info('Pages have changed: %o', ret);
	console.groupEnd();
	return ret;
}

/**
 * Sets the DOM node that represents the object's page group
 * Also adds reference to the group object to the DOM node (for reciprocal references)
 * @param mixed groupNode DOM node of group (may also be string ID of node) 
 */
PageGroup.prototype.setNode = function(groupNode) {
	//Set Group Node (main)
	console.group('Setting Node');
	if (typeof this.nodes.group == 'string')
		this.nodes.group = jQuery('#' + this.nodes.group);
	switch (typeof groupNode) {
		case 'string':
			groupNode = jQuery('#' + groupNode);
			break;
		default:
			groupNode = this.nodes.group;
	}
	//Check if node has already been set
	if (this.nodes.group != groupNode) {
		this.nodes.group = jQuery(groupNode);
		var nodeOrig = groupNode;
		//Check if specified node is a valid page group node (based on class)
		if (!this.nodes.group.hasClass(this.classes.group)) {
			var gNodes = this.nodes.group.find(this.getClass('group'));
			if (gNodes.length && gNodes.length > 0)
				this.nodes.group = jQuery(gNodes[0]);
		}
		
		//Parse group node and set group ID
		var checkAttr = 'id',
			reGID = /.*\bpg_(\d+?)\b.*/i,
			gMatch = reGID.exec(this.nodes.group.attr(checkAttr));
			
		if (gMatch && gMatch.length >= 2 && gMatch[1] > 0) {
			this.setId(gMatch[1]);
		}
	}
	if (this.nodes.group) {
		//Get other nodes
		var subNode;
		console.group('Getting Sub Nodes')
		for (var node in this.nodes) {
			console.info('Node: %o', node);
			if (node != 'group' && node in this.classes) {
				subNode = jQuery(this.nodes.group).find(this.getClass(node));
				if (subNode.length) {
					this.nodes[node] = subNode;
				}
			}
			//Unset node variable for next iteration
			subNode = null;
		}
		console.groupEnd();
	}
	//Add group object to DOM node
	this.nodes.group.get(0)[this.connections.group] = this;
	console.groupEnd();
}

PageGroup.prototype.setTitle = function(title) {
	var tType = typeof title;
	//Check value of title parameter
	if (tType != 'string' || title.trim().length == 0) {
		//Get title value from node
		title = '';
		var tTemp = this.getNodeText('title');
		if (tTemp.trim().length > 0)
			title = tTemp;
	} else {
		//Set node value to title
		jQuery(this.nodes.title).text(title);
	}
	//Set title property
	this.title = title;
}

PageGroup.prototype.setCode = function(code) {
	var cType = typeof code;
	if (cType != 'string' || code.trim().length == 0) {
		code = '';
		var cTemp = this.getNodeText('code');
		if (cTemp.trim().length > 0)
			code = cTemp;
	} else {
		jQuery(this.nodes.code).text(code);
	}
	this.code = code;
}

/**
 * Saves current state of group (HTML nodes) to variable for later restoration
 */
PageGroup.prototype.saveState = function(state) {
	state = this.getStateType(state);
	if (this.nodes.group == '')
		return false;
	this.states[state] = jQuery(this.nodes.group).html();
}

/**
 * Restores specified saved state of group
 * @param string state[optional] Saved state to restore (default: 'def')
 */
PageGroup.prototype.restoreState = function(state) {
	state = this.getState(state);
	if (this.nodes.group == '')
		return false;
	//Replace node content with content from selected state
	jQuery(this.nodes.group).html(state);
	//Nodes (currently point to non-existent nodes)
	this.resetNodes();
	//Reinitialize object data
	this.init();
}

/**
 * Resets object nodes
 * @param bool resetGroup Resets 'group' node if set to true
 */
PageGroup.prototype.resetNodes = function(resetGroup) {
	resetGroup = !!resetGroup;
	var excluded = [];
	if (!resetGroup)
		excluded.push('group');
	for (var node in this.nodes) {
		if (excluded.indexOf(node) == -1)
			this.nodes[node] = null;
	}
}

/**
 * Retrieve saved state of group
 * @param string state Saved state to retrieve
 * @return string State HTML
 */
PageGroup.prototype.getState = function(state) {
	state = this.getStateType(state);
	return this.states[state];
}

/**
 * Validates specified state
 * Checks that state exists, and if not automatically selects the default state
 * @param string stateType State being requested
 * @return mixed State data (Should be DOM object or string representing HTML content)
 */
PageGroup.prototype.getStateType = function(stateType) {
	if (typeof stateType == 'undefined' || !(stateType in this.states))
		stateType = 'def';
	return stateType;
}

/**
 * Returns jQuery object containing node of group
 * @return object jQuery object of group node
 */
PageGroup.prototype.getNode = function(node) {
	if (!this.nodes.group)
		this.setNode();
	var ret = (typeof node == 'undefined') ? this.nodes.group : jQuery();
	if (this.hasNode(node))
		ret = this.nodes[node];
	return ret;
}

PageGroup.prototype.hasNode = function(node) {
	if (node in this.nodes && this.nodes[node].jquery && this.nodes[node].length)
		return true;
	return false;
}

/**
 * Sets up Actions/Events for Pages in a Group
 */
PageGroup.prototype.setPageActions = function() {
	console.group('Add Actions to Page Nodes in Group');
	var pg = this;
	var pages = this.getPagesNodes();
	var actObj;
	for (var action in this.actions.page) {
		actObj = this.actions.page[action];
		console.log('Action: %o', action);
		console.dir(actObj);
		console.dir(actObj.callback);
		if (!actObj.hasCallback()) {
			actObj.callback = (jQuery.isFunction(this[action])) ? this[action] : function() {};
		}
		console.log('Action Function: %o', actObj.callback.toSource());
		pages.each(function(i) {
			actObj.callback(this);
		});
	}
	console.groupEnd();
}

/**
 * Adds page delete button/element to each Page in Group
 * @param object DOM element representing a Page in a Group
 */
PageGroup.prototype.addPageDelete = function(page) {
	console.group('PageGroup.addPageDelete()');
	var delNode = document.createElement('span');
	var pg = this;
	delNode = jQuery(delNode)
		.text('Delete')
		.addClass(pg.classes.pageDelete)
		.click(function(e) {
			var msg = "Delete: ";
			console.warn('Click Event for: %o', this);
			if (pg.connections.page in this) {
				jQuery(this[pg.connections.page]).remove();
				pg.triggerEvent(pg.events.modify, e);
				pg.checkPages();
			}
			return false;
		})
		.hover(
			function() {
				jQuery(this).addClass('on');
			},
			function() {
				jQuery(this).removeClass('on');
			}
		)
	//Add reference to page node in page
	delNode.get(0)[this.connections.page] = page;
	if (jQuery(page).find(this.getClass('pageDelete')).length == 0)
		jQuery(page).append(delNode);
	console.groupEnd();
}

/**
 * Sets event handlers for actionable items connected to group (save/cancel buttons, etc.)
 * @param mixed actionNode DOM node containing action nodes (may also be string ID of node)
 */
PageGroup.prototype.setActions = function(actionNode) {
	console.group('Setting Event Handlers');
	var nType = typeof actionNode;
	if (nType != 'undefined') {
		if (nType == 'string' && actionNode.length > 0 && actionNode.charAt(0) != '#')
			actionNode = '#' + actionNode;
		this.nodes.actions = actionNode;
	}
	if (!this.nodes.actions) {
		console.warn('Getting actions container based on class: %s', this.getClass(this.classes.actions));
		this.nodes.actions = this.getNode().find(this.getClass(this.classes.actions));
	}
	if (this.nodes.actions) {
		this.nodes.actions = jQuery(this.nodes.actions);
		//Scan for different actions in wrapper and add event handlers
		var pg = this;
		//this = PageGroup instance
		console.group('Scanning container for action elements');
		this.nodes.actions.find(this.getClass(this.classes.action)).each(function() {
			//this = matching DOM element
			console.warn('Element Found: %o', this);
			var action = pg.getAction(this);
			
			//Set action handler
			console.log('Element: %o \nHandler: %o', this, action.toSource());
			if (action) {
				jQuery(this).click(function(e) {
					//this = jQuery object (element firing event)
					//e = jQuery Event object (contains DOM element that fires event)
					action.callback(pg);
					
					//Trigger event on object
					pg.triggerEvent(action, e);
					return false;
				});
			}
		});
		console.groupEnd();
	}
	//Add actions to page nodes
	this.setPageActions();
	console.groupEnd();
}

/**
 * 
 * @param object action PageGroup.Action object
 * @param object event jQuery.Event object
 */
PageGroup.prototype.triggerEvent = function(action, event) {
	console.group('Page Group Event Triggered');
	console.info('Action: %o \nEvent: %o', action, event);
	//Add Page Group instance to event
	if (typeof event == 'undefined')
		event = {};
	if (typeof event == 'object')
		event['pg'] = this;
	//Setup default event handler
	var fn = function(e) {
		console.warn('Default Event Handler Triggered: %o', e);
	};
	//Check if event is registered
	if (jQuery.isFunction(action)) 
		fn = action;
	else {
		var aType = (typeof action == 'object' && 'type' in action) ? action.type : action.toString();
		if (aType in this.events) {
			if (jQuery.isFunction(this.events[aType])) 
				fn = this.events[aType];
			else 
				if (jQuery.isFunction(this[aType])) 
					fn = this[aType];
		}
	}
	//Call event handler
	fn(event);
	console.groupEnd();
}

/**
 * Determine's which action specified object is supposed to trigger
 * @param {Object} el jQuery object of DOM element
 * @return object PageGroup.Action object (if exists), FALSE if no matching action exists
 */
PageGroup.prototype.getAction = function(el) {
	el = jQuery(el);
	for (var action in this.actions) {
		if (!(this.actions[action] instanceof PageGroup.Action))
			continue;
		//Check if element is assigned to action
		if (el.hasClass(action)) {
			var actObj = this.actions[action];
			if (!actObj.hasCallback()) {
				actObj.callback = (jQuery.isFunction(this[action])) ? this[action] : function() {};
			}
			return actObj;
		}
	}
	return false;
}

/**
 * Returns value of class from classes property
 * @param string cls Class to retrieve
 */
PageGroup.prototype.getClass = function(cls) {
	if (cls in this.classes) {
		cls = "." + this.classes[cls];
	}
	return cls;
}

/**
 * Makes the Pages in a Group sortable
 * Part of Group initialization process
 */
PageGroup.prototype.makeSortable = function() {
	console.group('Make Sortable');
	//Sortable List
	var pg = this;
	console.info('Node to make sortable: %o', this.getNode('pages'));
	this.getNode('pages').sortable({
		opacity: 0.6,
		placeholder: 'ph',
		start: function(event, ui) {
			ui.item.addClass('sorting');
		},
		stop: function(event, ui) {
			console.log('Stopped Sorting\nEvent: %o \nUI: %o', event, ui);
			pg.checkPages();
			ui.item.removeClass('sorting');
			var item = ui.item.get(0);
			if ('tagName' in item && item.tagName.toLowerCase() != "li") {
				//Wrap content in LI element
				var wrap = ui.item.wrapAll('<li></li>').parent();
				//Move class to LI
				wrap.get(0).className = ui.item.get(0).className;
				//Set as Page item
				wrap.addClass(pg.classes.page);
				//Transfer content from dropped element into LI
				wrap.html(ui.item.html());
				//Set LI as item
				ui.item = wrap;
			}
			pg.addPageDelete(ui.item);
			if (pg.pagesChanged())
				pg.triggerEvent(pg.events.modify, event);	
		}
	});
	//this.getNode('pages').sortable('refresh');
	//Click Events
	//this.nodes.group.find('a').click(function() {return false;});
	console.groupEnd();
}

/**
 * Saves Group properties (title, code, pages, etc.) to server
 * @param object pg Page Group instance object to save to server
 */
PageGroup.prototype.save = function(pg) {
	console.group('Save Group')
	console.log(pg);
	//Close edit mode
	pg.editFinalize();
	if (pg.isEmpty()) {
		pg.getNode().remove();
		return false;
	}
	if (pg.pagesChanged() || pg.propChanged('title') || pg.propChanged('code')) {
		//Pages have been changed, save changes
		console.warn('Pages have been changed, save changes');
		//Prepare data
		var data = {
					'action':		'pg_save',
					'id':			pg.id,
					'cookie':		encodeURIComponent(document.cookie),
					'title':		pg.title,
					'code':			pg.code,
					'pages[]':		pg.pages,
					'_ajax_nonce':	pg.nonces.save
					};
		//Send data
		jQuery.post(Cnr.urlAdmin, data, function(ret) {
			if ('success' in ret) {
				console.dir(ret);
				console.warn('Return Value: ' + ret.success);
				if (ret.success) {
					console.log('Saving Server Data to Page Group');
					//Save current state as default
					if ('id' in ret)
						pg.setId(ret.id);
					if ('title' in ret)
						pg.setTitle(ret.title);
					if ('code' in ret)
						pg.setCode(ret.code);
					if ('nonces' in ret) {
						pg.nonces = ret.nonces;
					}
					pg.setPages(pg.getPages(), 'def');
					pg.saveState();
				}
			}
		},
		'json');
	} else {
		console.warn('Pages have not been changed, do nothing');
	}
	console.groupEnd();
}

/**
 * Sets Page Group into Edit mode
 * @param object pg Page Group instance object to edit
 */
PageGroup.prototype.edit = function(pg) {
	console.info('Edit');
	if (this instanceof PageGroup)
		pg = this;
	pg.events.modify(pg);
	var setPropClass = function(prop) {
		return 'prop-' + prop;
	}
	//Replace property nodes content with input elements
	var inTemp = jQuery(document.createElement('input'))
				.attr('type', 'text')
				.attr('maxlength', 30)
				.addClass(pg.classes.propInput);
	//Title
	var inTitle = inTemp.clone()
		.addClass(setPropClass('title'))
		.val(pg.title);
	pg.nodes.title.html(inTitle);
	//Code
	var inCode = inTemp.clone()
		.addClass(setPropClass('code'))
		.val(pg.code)
		/*TODO: Check code via AJAX
		.keyup(function(e) {
			pg.codeCheck(e);
		})*/;
	pg.nodes.code.html(inCode);
	
	//Display site pages to add to page group
	var sp = 'sitePages';
	if (pg.hasNode(sp)) {
		//Get HTML for site pages list
		var site_pages = jQuery(PageGroup.getTemplate('sitePages'));
		//Setup links as drag targets
		var rePID = /\bpage-item-(\d+?)\b/i;
		var pId = '';
		var el;
		site_pages.find('li').each(function(i) {
			console.info('Element: %o', this);
			//Process only valid page items
			if (rePID.test(this.className)) {
				//Get Page ID
				pId = rePID.exec(this.className);
				if (pId != null && pId.length && pId.length > 1) {
					console.warn('Page Item Found');
					pId = pId[1];
					//Set as Page Item
					jQuery(this).children('a').addClass(pg.classes.page + ' pid_' + pId).click(function() { return false; });
				}
			}
		});
		
		//Insert list into site pages node
		siteNode = jQuery(pg.getNode(sp));
		siteNode.html(site_pages);
		//Make list items draggable
		siteNode.find(pg.getClass('page')).draggable({
			connectToSortable: pg.getNode('pages'),
			helper:		'clone',
			opacity:	0.6
		});
	}
}

/**
 * Closes Edit mode
 */
PageGroup.prototype.editFinalize = function() {
	console.group('Edit Finalize');
	//Get all property inputs
	var pg = this,
		prefix = 'prop-',
		prop,
		reProp = /.*\bprop-(\S+?)\b.*/i,
		match,
		el;
	console.group('Finding Property Values for Group');
	jQuery(this.nodes.group).find(this.getClass('propInput')).each(function(i) {
		console.warn('Property: %o', this);
		prop = 0;
		//Determine property input belongs to
		match = reProp.exec(this.className);
		if (match && match.length >= 2 && match[1].length > 0) 
			prop = match[1];
		if (prop && prop in pg) {
			el = jQuery(this);
			console.log('Valid Property Found: %s \nValue: %o', prop, el.val());
			pg[prop] = el.val();
			el.replaceWith(el.val());
		}
	});
	//Clear Site Page list
	pg.getNode('sitePages').empty();
	console.groupEnd();
	console.groupEnd();
}

/**
 * Resets page group data to its default state
 * FUTURE: Exits Edit mode if currently in edit mode
 * @param object pg Page Group instance object to reset
 */
PageGroup.prototype.reset = function(pg) {
	console.group('Reset Group');
	console.log(pg);
	if (pg.isEmpty())
		pg.getNode().remove();
	else
		pg.restoreState();
	console.groupEnd();
}

PageGroup.prototype.isEmpty = function(pg) {
	if (typeof pg == 'undefined') {
		if (this instanceof PageGroup)
			pg = this;
		else
			return false;
	}
	if (pg.title.length < 1)
		return true;
	return false;
}

/**
 * Remove a page group (from page and server)
 * @param object pg PageGroup instance object
 */
PageGroup.prototype.remove = function(pg) {
	console.group('Remove Group');
	if (typeof pg == 'undefined') {
		if (this instanceof PageGroup) 
			pg = this;
		else 
			return false;
	}
	//Prepare data
	var data = {
				'action':		'pg_remove',
				'id':			pg.id,
				'cookie':		encodeURIComponent(document.cookie),
				'_ajax_nonce':	pg.nonces.remove
				};
	//Send data
	console.log('Removing from server');
	if (confirm('Are you sure you want to delete this page group?')) {
		jQuery.post(Cnr.urlAdmin, data, function(ret) {
			if (typeof ret == 'object' && 'success' in ret) {
				console.dir(ret);
				console.warn('Return Value: ' + ret.success);
				if (ret.success) {
					clear();
				}
			}
			else if (ret == -1) {
				console.warn('Action Failed: %s', ret);
			}
		}, 'json');
	}
	var clear = function() {
		console.log('Removing from client');
		pg.getNode().addClass('removing', 1000).fadeOut();
	}
	console.groupEnd();
}

/**
 * Validates group code for uniqueness
 * (Not currently implemented -- awaiting client-side validation)
 * @param object event jQuery Event object
 */
PageGroup.prototype.codeCheck = function(event) {
	console.log('Code Changed');
	var target = jQuery(event.target);
	var savedVal = target.val();
	var checkDelay = 1500;
	var pg = this;
	//TODO: Cancel current/pending server checks
	var doCheck = function(e) {
		console.log('Previous Value: %s \nCurrent Value: %s', savedVal, target.val());
		if (target.val() == savedVal) {
			console.warn('Checking code on server');
			//Check server
			var data = {
					'action':		'pg_check_code',
					'id':			pg.id,
					'cookie':		encodeURIComponent(document.cookie),
					'code':			target.val(),
					};
			//Send data
			jQuery.post(Cnr.urlAdmin, data, function(ret) {
				if ('val' in ret) {
					console.warn('Return Value: %s', ret.val);
				}
			},
			'json'); 
		}
	}
	setTimeout(doCheck, checkDelay, event);
	console.log('Set Value: %s', this.codeVal);
}

/* Static PageGroup Functions */

/**
 * Sets template variable on object prototype (accessible by all instances)
 * @param mixed temp Template(s) to set for PageGroup objects 
 */
PageGroup.setTemplate = function(temp) {
	if (typeof temp != 'undefined')
		PageGroup.prototype.template = temp;
}

/**
 * Create empty node in Groups list to create new Page Group
 */
PageGroup.create = function() {
	//Get template
	var temp = PageGroup.getTemplate('group');
	console.log('Template: \n %s', temp);
	//Load into group container
	var gNode = jQuery(temp);
	//Create Page group for node
	PageGroup.container.append(gNode);
	var pg = new PageGroup({'node': gNode});
	console.warn('Display Site Pages and enter Edit mode');
	pg.edit();
}

PageGroup.initGroups = function() {
	PageGroup.container = jQuery(".page-groups_wrap");
	//Add event handlers
	
	//New Group link(s)
	jQuery('.page-group_new').click(function(e) {
		PageGroup.create();
		return false;
	});
}

PageGroup.container;

PageGroup.getTemplate = function(temp) {
	console.info('Get Template: %s', temp);
	var ret = '';
	console.dir(PageGroup.templates);
	if (typeof temp == 'undefined')
		return ret;
	console.log('Continuing to retrieve template');
	var proc = 'template_' + temp;
	var count = 0;
	if ((!(temp in PageGroup.templates) || PageGroup.templates[temp] == null) && !PageGroup.hasProcess(proc)) {
		//Retrieve specified template from server
		console.warn('Retrieving template from server');
		PageGroup.setProcess(proc);
		var data = {
			'action': 'pg_get_template',
			'template': temp
		}
		jQuery.ajax({
					'async':	false,
					'url':		Cnr.urlAdmin,
					'dataType':	'html',
					'data':		data,
					'timeout':	10000,
					'success':	function(ret, status) {
						PageGroup.templates[temp] = ret;
						PageGroup.clearProcess(proc);
					}
					});
	}
	return PageGroup.templates[temp];
}

PageGroup.hasProcess = function(proc) {
	var ret = false;
	if (proc in PageGroup.processes && PageGroup.processes[proc])
		ret = true;
	console.warn('Has Process (%s): %s', proc, ret);
	return ret;
}

PageGroup.setProcess = function(proc) {
	var pVal = (arguments.length == 2) ? arguments[1] : true;
	PageGroup.processes[proc] = pVal;
}

PageGroup.clearProcess = function(proc) {
	PageGroup.setProcess(proc, false);
}

PageGroup.processes = {};

PageGroup.templates = {};

/* PageGroup Action */

/**
 * Creates PageGroup Action object
 * @param string name Name of Action
 * @param function callback[optional] Function to handle action
 * @param string type[optional] Action event type
 * 	- Examples
 * 		- modify:	Modifies group
 * 		- clean:	Clears group of all modifications
 */
PageGroup.Action = function(name, callback, type) {
	console.group('PageGroup.Action Constructor')
	console.info('Name: %o \nCallback: %o \nType: %o', name, callback.toSource(), type);
	console.dir(callback);
	//Create object
	this.name = '';
	this.callback = null;
	this.type = '';
	//Process arguments
	
	var paramValid = function(arg, argType) {
		var def = typeof arg;
		if (def != 'undefined') {
			var argDef = typeof argType;
			if (argDef == 'undefined' || (typeof argType != 'undefined' && argType.length > 0 && def == argType))
				return true;
		}
		return false;
	};
	
	//Name
	if (paramValid(name, 'string'))
		this.name = name.trim();
	//Callback
	if (paramValid(callback, 'function'))
		this.callback = callback;
	//type
	if (paramValid(type, 'string'))
		this.type = type.trim();
	console.groupEnd();
	return this;
}

/**
 * Checks if Action has a callback set
 */
PageGroup.Action.prototype.hasCallback = function() {
	if (jQuery.isFunction(this.callback))
		return true;
	return false;
}



/* Site Pages */

function SitePages(node) {
	
	/**
	 * @var object Holds DOM element representing site pages
	 */		
	this.node = jQuery(node);
	
	/**
	 * @var object Stores defaults values for reverting back to last saved state
	 */
	this.defaults = {
					'fields': [],
					'values': {}
					};
	
	this.init();
}

/**
 * Initializes instance
 * Sets instance variables, etc.
 */
SitePages.prototype.init = function() {
	if (this.node.length < 1)
		return false;
	//Assign instance to node
	this.node.get(0)[SitePages.Attribute] = this;
	
	//Setup Actions
	this.setActions();
}

/**
 * Sets up actions for Site Pages instance
 * Examples:
 * - Save
 * - Cancel
 * - Page Delete
 */
SitePages.prototype.setActions = function() {
	//Save
	var sp = this;
	jQuery('.action.save').click(function(e) {
		console.warn('Send data to server');
		sp.save();
	});
}

/**
 * Gets all pages that are direct children of node
 * @param object node DOM Node to get children of
 * @return object jQuery object of retrieved children page elements
 */
SitePages.prototype.getPages = function(node) {
	if (typeof node == 'undefined')
		node = this.node;
	//Get children page elements
	var children = jQuery(node).children(SitePages.Selectors.page.get());
	//If no children are found, check for page parent elements
	if (children.length < 1) {
		children = jQuery(node).children('ul');
		//If page parent elements are found, check first element for children
		if (children.length > 0) {
			children = this.getPages(children.get(0));
		}
	}
	return children;
}

/**
 * Recursively builds a flat array of all pages in site
 * @param object node DOM node to get pages in
 * @return array SitePages.Page objects for each page in Site
 */
SitePages.prototype.buildPagesArray = function(node) {
	var pagesArr = [];
	var nodePages = this.getPages(node);
	var pageTemp;
	var nodeTemp;
	var childrenTemp = [];
	var parent = (typeof node != 'undefined' && SitePages.Page.Attribute in node) ? node[SitePages.Page.Attribute].id : 0;  
	for (var p = 0; p < nodePages.length; p++) {
		childrenTemp = [];
		nodeTemp = nodePages.get(p);
		//Create new page object based on page
		pageTemp = new SitePages.Page(nodeTemp);
		//Set order
		pageTemp.order = p;
		//Set parent
		pageTemp.parent = parent;
		//Add to array
		pagesArr.push(pageTemp);
		//Get children of current page
		childrenTemp = this.buildPagesArray(nodeTemp);
		//Add children pages to master array
		if (childrenTemp.length > 0) {
			pagesArr = pagesArr.concat(childrenTemp);
		}
	}
	//Return master array (for recursion)
	return pagesArr;
}

/**
 * Serializes pages as JSON string
 * @return string Serialized Pages
 */
SitePages.prototype.serialize = function() {
	//Get array of site pages
	var pagesArr = this.buildPagesArray();
	
	//Iterate through pages and build JSON string
	var pagesObj = {};
	var pTemp;
	for (var p = 0; p < pagesArr.length; p++) {
		pTemp = pagesArr[p];
		pagesObj[pTemp.id] = pTemp;
	}
	console.dir(pagesObj);
	return pagesArr;
}

/**
 * Saves Group properties (title, code, pages, etc.) to server
 * @param object pg Page Group instance object to save to server
 */
SitePages.prototype.save = function() {
	console.group('Save Group')
	
	//Prepare data
	var data = {
				'action':		'sp_save',
				'cookie':		encodeURIComponent(document.cookie),
				'pages[]':		this.serialize(),
				'_ajax_nonce':	''
				};
	//Send data
	jQuery.post(Cnr.urlAdmin, data, function(ret) {
		if ('success' in ret) {
			console.dir(ret);
			console.warn('Return Value: ' + ret.success);
			if (ret.success) {
				console.log('Saving Server Data to Page Group');
				//Save current state as default
				if ('id' in ret)
					pg.setId(ret.id);
				if ('title' in ret)
					pg.setTitle(ret.title);
				if ('code' in ret)
					pg.setCode(ret.code);
				if ('nonces' in ret) {
					pg.nonces = ret.nonces;
				}
				pg.setPages(pg.getPages(), 'def');
				pg.saveState();
			}
		}
	},
	'json');
	console.groupEnd();
}

/* Static */

/**
 * Selectors for different elements in Site Pages
 */
SitePages.Selectors = {
						'wrap':		new Selector('site-pages', Selector.Types.Class),
						'page':		new Selector('page_item', Selector.Types.Class),
						'title':	new Selector('page-title', Selector.Types.Class),
						'slug':		new Selector('page-slug', Selector.Types.Class)
						};

SitePages.Patterns = {
					"page_id":		/.*\bpage-item-(\d+)\b.*/i
					}

SitePages.Attribute = "spData";

/* Page */

SitePages.Page = function(node) {
	this.id = 0;
	this.title = '';
	this.slug = '';
	this.order = 0;
	this.parent = 0;
	this.node = node;
	
	if (typeof node == 'undefined')
		return false;
	//Populate properties based on node attributes
	//ID
	if ('className' in node) {
		var match = SitePages.Patterns.page_id.exec(node.className);
		if (match && match.length > 1 && !isNaN(match[1]))
			this.id = match[1];
	}
	
	var getText = function(selector) {
		var res = "";
		//Get first matching element
		var valNode = jQuery(node).find(selector.get() + ':first');
		if (valNode.length > 0)
		{
			res = jQuery(valNode).text();
		}
		return res;
	}
	
	//Name
	this.title = getText(SitePages.Selectors.title);
	//Slug
	this.slug = getText(SitePages.Selectors.slug);
	
	//Add instance object to node
	node[SitePages.Page.Attribute] = this;
}

SitePages.Page.Attribute = 'pageData';