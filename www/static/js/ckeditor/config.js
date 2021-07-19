/**
 * @license Copyright (c) 2003-2021, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For complete reference see:
	// https://ckeditor.com/docs/ckeditor4/latest/api/CKEDITOR_config.html

	// The toolbar groups arrangement, optimized for two toolbar rows.
	config.toolbarGroups = [
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		{ name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
		{ name: 'links' },
		{ name: 'insert' },
		{ name: 'forms' },
		{ name: 'tools' },
		{ name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] },
		{ name: 'others' },
		'/',
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'styles' },
		{ name: 'colors' },
		{ name: 'about' }
	];

	// Remove some buttons provided by the standard plugins, which are
	// not needed in the Standard(s) toolbar.
	config.removeButtons = 'Underline,Subscript,Superscript';

	// Set the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';

	// Simplify the dialog windows.
	config.removeDialogTabs = 'image:advanced;link:advanced';

	// Dialog background colour
	config.dialog_backgroundCoverColor = "#000000";
};

// Inject nanospell

let nanospell = {
	ckeditor: function (selector, settings) {
		if (typeof(window.CKEDITOR) == "undefined") {
			setTimeout(function () {
				window.nanospell.ckeditor(selector, settings)
			}, 300)
		}
		if (!selector) {
			selector = 'all'
		}
		if (!settings) {
			settings = {}
		}

		// Plugin URL: relative to the page's location, not the script's?
		let plugin_name = 'nanospell',
		    plugin_url = '../../ajax/nanospell/nanospell.ckeditor/plugin.js';

		if (typeof(CKEDITOR) != 'undefined') {
			ckInstances = {};

			for (let i in CKEDITOR.instances) {
				if (selector.toLowerCase() === 'all' || CKEDITOR.instances[i].element.$.id === selector) {
					ckInstances[i] = true;
				}
			}
			CKEDITOR.plugins.addExternal(plugin_name, plugin_url, '');
			CKEDITOR.plugins.load(plugin_name, function (plugins) {
				for (let i in ckInstances) {
					let editor = CKEDITOR.instances[i];
					editor.config.nanospell = settings;
					editor.config.removePlugins += ',wsc,scayt';
					plugins[plugin_name].init(editor)
				}
			});
		}
	}
};

nanospell.ckeditor('all', {
	dictionary: "en_au,en",
	ignore_block_caps: true,
	ignore_non_alpha: true,
	server: "/ajax/nanospell/server/ajax/php/tinyspell.php",
});
