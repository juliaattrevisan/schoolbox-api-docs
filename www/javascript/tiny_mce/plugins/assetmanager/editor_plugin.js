/**
 * $Id: editor_plugin_src.js 201 2007-02-12 15:56:56Z spocke $
 *
 * @author Moxiecode
 * @copyright Copyright � 2004-2008, Moxiecode Systems AB, All rights reserved.
 */

(function() {
	// Load plugin specific language pack
	tinymce.PluginManager.requireLangPack('assetmanager');

	tinymce.create('tinymce.plugins.AssetManager', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceAssetManager', function() {
				ed.windowManager.open({
					file : url + '/dialog.htm',
					width : 320 + parseInt(ed.getLang('assetmanager.delta_width', 0)),
					height : 120 + parseInt(ed.getLang('assetmanager.delta_height', 0)),
					inline : 1
				}, {
					plugin_url : url, // Plugin absolute URL
					some_custom_arg : 'custom arg' // Custom argument
				});
			});

			this.url = url;

			// Register example button
			ed.addButton('assetmanager', {
				title : 'assetmanager.desc',
				cmd : 'mceAssetManager',
				image : url + '/img/example.gif'
			});

			// Add a node change handler, selects the button in the UI when a image is selected
			ed.onNodeChange.add(function(ed, cm, n) {
				cm.setActive('assetmanager', n.nodeName == 'IMG');
			});
		},

		/**
		 * open a new browser window
		 */
		fileBrowser : function (field_name, url, type, win) {
			var ed = tinyMCE.activeEditor, file;
			if (type.toLowerCase() == 'image')
				file = '/file_browser.php?filter=image';
			else if (type.toLowerCase() == 'media')
                file = '/file_browser.php?filter=video';
         	else
				file = '/file_browser.php?filter=none';

			ed.windowManager.open({
				url : this.url + file + '&categoryID=' + ed.settings['file_browser_category_id'],
				width: 640 + parseInt(ed.getLang('amadeo_draft.delta_width', 0)),
				height : 511 + parseInt(ed.getLang('amadeo_draft.delta_height', 0)),
				movable : true,
				inline : true
			}, {
				window	: win,
				input : field_name
			});
		},

        /**
         * open a new browser window
         */
        eportfolioFileBrowser : function (field_name, url, type, win) {
            var ed = tinyMCE.activeEditor, file;

            if (type.toLowerCase() == 'image')
                file = '/eportfolio_file_browser.php?filter=image';
            else if (type.toLowerCase() == 'media')
                  file = '/eportfolio_file_browser.php?filter=video';
            else
                file = '/eportfolio_file_browser.php?filter=none';

            ed.windowManager.open({
                url : this.url + file + '&userID=' + ed.settings['file_browser_user_id'],
                width: 640 + parseInt(ed.getLang('amadeo_draft.delta_width', 0)),
                height : 511 + parseInt(ed.getLang('amadeo_draft.delta_height', 0)),
                movable : true,
                inline : true
            }, {
                window	: win,
                input : field_name
            });
        },

        fileUploader : function(field_name, url, type, win) {
            var ed = tinyMCE.activeEditor;

            ed.windowManager.open({
                url : this.url + '/file_uploader.php',
                width: 384 + parseInt(ed.getLang('amadeo_draft.delta_width', 0)),
                height : 192 + parseInt(ed.getLang('amadeo_draft.delta_height', 0)),
                movable : true,
                inline : true
            }, {
                window	: win,
                input : field_name
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
				longname : 'Asset Manager',
				author : 'Alaress',
				authorurl : 'http://www.alaress.com.au/',
				infourl : '',
				version : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('assetmanager', tinymce.plugins.AssetManager);
})();