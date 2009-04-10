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
	 * @var object DOM Nodes used in Group
	 * group:	Entire group
	 * title:	Group Title
	 * code:	Group Code
	 * count:	Group Page count
	 * pages:	Pages list
	 * actions:	Group actions (save, reset, etc.)
	 */
	this.nodes = {
				'group':	'',
				'title':	'',
				'code':		'',
				'count':	'',
				'pages':	'',
				'actions':	''
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
					'actions':		'actions',
					'action':		'action',
					'pageDelete':	'page-delete'
					};
	
	/**
	 * @var object actions Actions for different events
	 */
	this.actions = {
					'save':		new PageGroup.Action('save', '', 'clean'),
					'edit':		new PageGroup.Action('edit', '', 'modify'),
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
					'modify':	function(e) {
									console.warn('Modify event triggered: %o', e);
									console.warn(e.pg);
									jQuery(e.pg.nodes.actions).show(300);
								},
					'clean':	function(e) {
									console.warn('Clean event triggered: %o', e);
									console.log(e.pg);
									jQuery(e.pg.nodes.actions).hide(300);
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
	this.saveState();
	this.makeSortable();
	this.setActions();
}

PageGroup.prototype.addDefault = function (propName) {
	if (typeof propName == 'undefined' || arguments.length < 1 || !propName)
		return false;
	for (var x = 0; x < arguments.length; x++) {
		propName = arguments[x];
		if (this.defaults.fields.indexOf(propName) == -1)
			this.defaults.fields.push(propName);
	}
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
	pagesType = this.getPagesType(pagesType);
	if (pagesArr instanceof Array)
		this.pages[pagesType] = pagesArr;
	this.formatPages(pagesType);
	console.dir(this.pages);
	console.groupEnd();
}

PageGroup.prototype.getPagesType = function(pagesType) {
	if (typeof pagesType == 'undefined' || !(pagesType in this.pages))
		pagesType = 'current';
	return pagesType;
}

/**
 * Formats values in pages property to integers
 * @param string [pagesType] which pages to format (def or current [default])
 */
PageGroup.prototype.formatPages = function(pagesType) {
	console.group('Formatting Pages Array');
	console.info('Type: %o \nData: %o', pagesType, this.pages[pagesType]);
	pagesType = this.getPagesType(pagesType);
	for (x = 0; x < this.pages[pagesType].length; x++) {
		this.pages[pagesType][x] = parseInt(this.pages[pagesType][x]);
	}
	console.groupEnd();
}

/**
 * Gets DOM nodes of Pages in Group
 * @return object jQuery object of Pages in Group
 * 
 */
PageGroup.prototype.getPagesNodes = function() {
	return this.nodes.group.find(this.getClass('pages') + ' ' + this.getClass('page'));
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
	this.pages.current = pagesArr;
	console.groupEnd();
	return this.pages.current;
}

/**
 * Compares current pages in group to default pages in group
 */
PageGroup.prototype.pagesChanged = function() {
	console.group('Comparing Pages array');
	this.getPages();
	console.dir(this.pages);
	console.groupEnd();
	return !this.pages.current.compare(this.pages.def);
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
					console.dir(subNode);
				}
			}
			//Unset node variable for next iteration
			subNode = null;
		}
		console.groupEnd();
	}
	console.dir(this.nodes);
	//Add group object to DOM node
	this.nodes.group.get(0)[this.connections.group] = this;
	console.groupEnd();
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
PageGroup.prototype.getNode = function() {
	if (!this.nodes.group)
		this.setNode();
	return this.nodes.group;
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
			}
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
	//Add Page Group instance to event
	event.pg = this;
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
	this.nodes.group.find(this.getClass('pages')).sortable({
		opacity: 0.6,
		placeholder: 'ph',
		start: function(event, ui) {
			ui.item.addClass('sorting');
		},
		stop: function(event, ui) {
			console.log('Stopped Sorting\nEvent: %o \nUI: %o', event, ui);
			ui.item.removeClass('sorting');
			pg.addPageDelete(ui.item);
			if (pg.pagesChanged())
				pg.triggerEvent(pg.events.modify, event);	
		}
	});
	
	//Click Events
	this.nodes.group.find('a').click(function() {return false;});
	console.groupEnd();
}

/**
 * Saves Group properties (title, code, pages, etc.) to server
 * @param object pg Page Group instance object to save to server
 */
PageGroup.prototype.save = function(pg) {
	console.group('Save Group')
	console.log(pg);
	//get pages
	//pg = new PageGroup(pg);
	pg.getPages();
	if (pg.pagesChanged()) {
		//Pages have been changed, save changes
		console.warn('Pages have been changed, save changes');
		//Prepare data
		var data = {
					'action':		'pg_save',
					'id':			pg.id,
					'cookie':		encodeURIComponent(document.cookie),
					'pages[]':		pg.pages.current,
					'_ajax_nonce':	pg.nonces.save
					};
		//Send data
		jQuery.post('http://cns.wp/wp-admin/admin-ajax.php', data, function(ret) {
			if ('msg' in ret) {
				console.warn('Return Value: ' + ret.msg);
				//Save current state as default
				pg.setPages(pg.getPages(), 'def');
				pg.saveState();
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
}

/**
 * Resets page group data to its default state
 * FUTURE: Exits Edit mode if currently in edit mode
 * @param object pg Page Group instance object to reset
 */
PageGroup.prototype.reset = function(pg) {
	console.group('Reset Group');
	console.log(pg);
	pg.restoreState();
	console.groupEnd();
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
	//Draggable list (Site Pages)
	jQuery('.list-pages ul li').draggable({
		connectToSortable: '.group-pages',
		helper:		'clone',
		opacity:	0.6
	});
	
	//Click Events
	jQuery('.list-pages a').click(function() {return false;});	
});