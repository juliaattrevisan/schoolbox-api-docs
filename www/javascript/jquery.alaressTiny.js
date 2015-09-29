(function($) {

	/**
	 * Unified TinyMCE interface
	 *
	 * @param options
	 */
	$.fn.alaressTiny = function(options) {
		options = options || {};

		var config = init(options);

		tiny(config);

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options) {

			/* Standard options across all tinyMCE instances
			 */
			var standard = {
				theme: "advanced",
				skin: "cirkuit",
				plugins: "pdw,table,tabfocus,media,inlinepopups,advimage,assetmanager,directionality,paste,spellchecker,noneditable",
				browser_spellcheck: true,
				content_css: "/css/foundation/chrome.css",
				accessibility_warnings: false,
				extended_valid_elements: "a[class|name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|style],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]",
				theme_advanced_blockformats: "h3,h4,p,address,pre",
				tabfocus_elements: ":prev,:next",
				convert_urls: false,

				/* To prevent stripping of embed tags, presumably
				 */
				media_strict: false
			}

			/* Default tinyMCE init choices which can be overridden
			 */
			var defaults = {

				/* What to apply Tiny upon
				 */
				mode: 'textareas',

				/* Used only with mode "specific_textareas"
				 */
				editor_selector: '',

				/* Used only with mode "exact"
				 */
				elements: '',

				/* Custom alaress file browser plugin
				 */
				file_browser_callback: 'tinyMCE.activeEditor.plugins.assetmanager.fileBrowser',
				file_browser_category_id: 0,
				file_uploader_callback: 'tinyMCE.activeEditor.plugins.assetmanager.fileUploader',

				/* Available butonye
				 */
				theme_advanced_buttons1: "bold,italic,underline,separator,bullist,numlist,separator,link,unlink,image,media,separator,undo,redo,separator,pdw_toggle",
				theme_advanced_buttons2: "forecolor,formatselect,fontsizeselect,separator,justifyleft,justifycenter,justifyright,z,spellchecker",
				theme_advanced_buttons3: "outdent,indent,sub,sup,separator,hr,charmap,separator,seperator,ltr,rtl,selectall,pastetext,pasteword,separator,removeformat,cleanup,code",
				theme_advanced_buttons4: "tablecontrols",
				pdw_toggle_on: 1,
				pdw_toggle_toolbars: "2, 3, 4",

				/* Formatting stuff
				 */
				force_br_newlines: false,
				force_p_newlines: true,
				removeformat_selector: '',

				/* Toolbar
				 */
				theme_advanced_toolbar_location: 'top',
				theme_advanced_toolbar_align: 'left',
				theme_advanced_statusbar_location: 'bottom'
			}

			/* Computed/derived/optional values
			 */
			var derived = {

				/* Width
				 * Specify in options
				 *
				 * width: '100%'
				 */

				/* Setup callback
				 * Specify in options
				 *
				 * setup: function() ...
				 */

				/* Disable toolbars
				 * Specify in options as an array of ints
				 *
				 * disable_toolbars = [2,3,4]
				 */

				/* Protect elements from tiny conversion
				 * Specify in options
				 *
				 * editor_deselector: 'myClass'
				 */
			}

			/* Combined init literal
			 */
			var merged = $.extend(standard, defaults, derived, options);
			merged.file_browser_category_id = parseInt(merged.file_browser_category_id);

			return merged;
		}

		/**
		 * Disturb the sleeping dragon
		 */
		function tiny(config) {
			tinyMCE.init(config);
		}
	}
})(jQuery);

