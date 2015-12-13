(function($) {

	/**
	 * Unified TinyMCE interface
	 *
	 * @param options
	 */
	$.fn.alaressTiny = function(options) {
		options = options || {};

		var config = init(options);

		tiny(options);

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options) {


            var config =  {
                toolbarGroups: [
                    {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                    {name: 'links', groups: ['links']},
                    {name: 'insert', groups: ['insert']},
                    {name: 'editing', groups: ['find', 'selection', 'spellchecker', 'editing']},
                    {name: 'tools', groups: ['tools']},
                    {name: 'document', groups: ['mode', 'document', 'doctools']},
                    '/',
                    {name: 'forms', groups: ['forms']},
                    {name: 'styles', groups: ['styles']},
                    {name: 'colors', groups: ['colors']},
                    {name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi', 'paragraph']},
                    {name: 'others', groups: ['others']},
                    '/',
                    {name: 'clipboard', groups: ['clipboard', 'undo']},
                    {name: 'about', groups: ['about']}
                ],
                toolbarCanCollapse: true,
                removeButtons: 'CodeSnippet,HorizontalRule,Sourcedialog,About,Indent,Outdent',

                magicline_color: '#0000FF',

                format_tags: 'p;h3;h4;pre;address',

                filebrowserBrowseUrl: '/resources/attachment',
                filebrowserUploadUrl: '/storage/asyncUpload.php'

            };



			/* Standard options across all tinyMCE instances
			 */
			/*
			// Site location can be a global
			var siteLocation = options.site_location || window.siteLocation || 'core';
			var standard = {
				theme: "advanced",
				skin: "cirkuit",
				plugins: "pdw,table,tabfocus,media,inlinepopups,advimage,assetmanager,directionality,paste,spellchecker,noneditable",
				browser_spellcheck: true,
				content_css: "/css/"+siteLocation+"/chrome-foundation.css",
				accessibility_warnings: false,
				extended_valid_elements: "a[class|name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|style],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]",
				theme_advanced_blockformats: "h3,h4,p,address,pre",
				tabfocus_elements: ":prev,:next",
				convert_urls: false,

				// To prevent stripping of embed tags, presumably

				media_strict: false
			}*/

			/* Default tinyMCE init choices which can be overridden
			 */
            /*
			var defaults = {

				// What to apply Tiny upon

				mode: 'textareas',

				// Used only with mode "specific_textareas"

				editor_selector: '',

				// Used only with mode "exact"

				elements: '',

				// Custom alaress file browser plugin

				file_browser_callback: 'tinyMCE.activeEditor.plugins.assetmanager.fileBrowser',
				file_browser_category_id: 0,
				file_uploader_callback: 'tinyMCE.activeEditor.plugins.assetmanager.fileUploader',

				// Available butonye

				theme_advanced_buttons1: "bold,italic,underline,separator,bullist,numlist,separator,link,unlink,image,media,separator,undo,redo,separator,pdw_toggle",
				theme_advanced_buttons2: "forecolor,formatselect,fontsizeselect,separator,justifyleft,justifycenter,justifyright,z,spellchecker",
				theme_advanced_buttons3: "outdent,indent,sub,sup,separator,hr,charmap,separator,seperator,ltr,rtl,selectall,pastetext,pasteword,separator,removeformat,cleanup,code",
				theme_advanced_buttons4: "tablecontrols",
				pdw_toggle_on: 1,
				pdw_toggle_toolbars: "2, 3, 4",

				// Formatting stuff

				force_br_newlines: false,
				force_p_newlines: true,
				removeformat_selector: '',

				// Toolbar

				theme_advanced_toolbar_location: 'top',
				theme_advanced_toolbar_align: 'left',
				theme_advanced_statusbar_location: 'bottom'
			}
            */

			/* Combined init literal
			 */
			//var merged = $.extend(standard, defaults, derived, options);
			//merged.file_browser_category_id = parseInt(merged.file_browser_category_id);

			return config;
		}

		/**
		 * Disturb the sleeping dragon
		 */
		function tiny(setting) {

            if(setting.mode == "exact") {
                CKEDITOR.replace(setting.elements,config);
            }
            if(setting.mode == "specific_textareas") {
                CKEDITOR.replace(setting.editor_selector,config);
            }
            else if(setting.mode == 'textareas') {
                $('textarea').each( function() {
                    CKEDITOR.replace(this, config);
                });
            }
		}
	}
})(jQuery);

