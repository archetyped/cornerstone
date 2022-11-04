/**
 * Structure Administration
 * @package Cornerstone
 * @subpackage Structure
 */

(function($) {

if ( CNR && CNR.structure && CNR.structure.extend ) CNR.structure.extend('admin', {
	parent: CNR.structure,
	options: {},

	manage_option: function() {
		//Continue processing if permalink option has been defined
		if ( this.options && this.options.label && this.options.example && this.parent.options.permalink_structure ) {
			const opts = this.options;
			opts.parent = this.parent.options;
			var o = this.options;
			var po = this.parent.options;
			// Options handling (WP 6.1+)
			const cfg = {
				selectors: {
					match: '.structure-selection',
					template: '.structure-selection > .row',
					input: 'input[type="radio"]',
					label: 'label',
					example: 'p > code',
				},
				elements: {
					template: null,
					base: null,
					input: null,
					label: null,
					example: null,
					custom: document.getElementById( 'permalink_structure' ),
				},
				values: {
					id_base: 'cnr-structured',
				},
				legacy: {
					selectors: {
						template: '.permalink-structure tr',
						input: 'input[type="radio"]',
						label: 'label',
						example: 'td > code',
					},
				},
			};
			// Set up values
			cfg.values.input_id = 'permalink-input-' + cfg.values.id_base;
			cfg.values.example_id = 'permalink-' + cfg.values.id_base;
			// Determine if legacy configuration should be used.
			if ( !document.querySelector(cfg.selectors.match) ) {
				let prop;
				for ( prop in cfg.legacy ) {
					cfg[ prop ] = cfg.legacy[ prop ];
				}
			}
			// Reference elements object.
			const els = cfg.elements;
			// Retrieve single structure option to use as template for custom option.
			els.template = document.querySelector( cfg.selectors.template );
			// Stop if option not found.
			if ( !els.template ) {
				return false;
			}
			// Clone option element.
			els.base = els.template.cloneNode( true );
			// Input element.
			els.input = els.base.querySelector( cfg.selectors.input );
			if ( !els.input ) {
				return false;
			}
			els.input.setAttribute( 'id', cfg.values.input_id );
			els.input.setAttribute( 'value', opts.parent.permalink_structure );
			if ( els.custom ) {
				if ( els.custom.value === opts.parent.permalink_structure ) {
					els.input.setAttribute( 'checked', 'checked' );
				} else {
					els.input.removeAttribute( 'checked' );
				}
				// Event handler (option selected).
				els.input.addEventListener('change', function() {
					if ( els.custom.value === this.value ) {
						return false;
					}
					els.custom.value = this.value;
				});
			}
			// Label element.
			els.label = els.base.querySelector( cfg.selectors.label );
			if ( els.label ) {
				// Attributes.
				els.label.setAttribute( 'for', els.input.getAttribute( 'id' ) );
				// Text.
				// Fallback: Set text for label element directly.
				let labelNode = els.label;
				// Update text node within label element.
				if ( els.label.childNodes.length > 1 ) {
					// Get first text node.
					let childNode = Array.from( els.label.childNodes ).find( cnode => cnode.nodeType === Node.TEXT_NODE );
					if ( childNode ) {
						labelNode = childNode;
					}
				}
				labelNode.textContent = labelNode.textContent.replace(/^(\s*)(\S.+?)(\s*)$/i, `\$1${opts.label}\$3`);
			}
			// Example permalink element.
			els.example = els.base.querySelector( cfg.selectors.example );
			if ( els.example ) {
				els.example.setAttribute( 'id', cfg.values.example_id );
				els.example.textContent = opts.example;
			}
			// Insert option into list.
			els.template.parentNode.insertBefore( els.base, els.template );
		}
	}
});

if ( CNR.structure.admin.manage_option )
	$(document).ready(function() {CNR.structure.admin.manage_option();});

}) (jQuery);
