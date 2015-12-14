(function($) {

	/**
	 * Unified TinyMCE interface
	 *
	 * @param options
	 */
	$.fn.alaressTiny = function(options) {
		options = options || {};

		var config = init(options);

		tiny();

		/**
		 * Init
		 *
		 * @param options
		 * @returns {*}
		 */
		function init(options) {

            /* These are the default CKEDITOR.config values
             * You can find more at http://docs.ckeditor.com/#!/api/CKEDITOR.config
             */
            var defaults =  {
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
                filebrowserUploadUrl: '/storage/asyncUpload.php',

                /* This used to be 'mode', a legacy setting from Tiny.
                 *
                 * Instead of using its broken interface design, I levelled it up
                 * so that you can use jquery selectors here and it will try to
                 * derive the id or name and then apply the ckeditor. It's a bit
                 * different to Tiny, but we use css selectors to start editors
                 *
                 * This also unifies the various scattered instantiations styles
                 * which were previously totally stupid
                 *
                 * The elements must have an id or name for it to work this way
                 */
                targetElements: $('textarea'),

                /* A callback to fire when this plugin inits
                 */
                setup: function(element) {
                },

                /* A callback to fire when content is changed
                 */
                change: function(element) {
                }
            };

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;



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
		}

		/**
		 * Disturb the sleeping dragon
		 */
		function tiny() {
            $.each(config.targetElements, function(index, element) {
                var id = $(element).attr('id');
                var name = $(element).attr('name');

                /* Enormous Note: the name attribute must be unique on the page
                 * ckeditor cannot handle having multiple editors on elements
                 * sharing the same name= attribute
                 *
                 * So that forces you to id everything, mostly, unless there is
                 * only ever one ckeditor on a page (such as modify/create).
                 * Discovered on component-based pages
                 *
                 * Note that in the CKEDITOR.instances literal, ids (when they
                 * exist) take precedence over name
                 */
                if ((id !== undefined) && (id.length)) {
                    config.setup($(element));

                    CKEDITOR.replace(id, config);

                    CKEDITOR.instances[id].on('change', function(event) {
                        config.change($(element));
                    });
                }
                else if ((name !== undefined) && (name.length)) {
                    config.setup($(element));

                    CKEDITOR.replace(name, config);

                    CKEDITOR.instances[name].on('change', function(event) {
                        config.change($(element));
                    });
                }
            });
		}
	}
})(jQuery);

