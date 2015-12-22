(function() {
    var modalCache = {};
    var singletonModal = null;

    /**
     * Spawns a Schoolbox Resource Browser which allows you
     * to choose a file of some sort
     *
     * Shamelessly cannibalised from Dropzone Resource Browser
     *
     * @param options
     */
    $.fn.schoolboxMedia = function(options) {
        options = options || {};

        var config = init(options, $(this));

        cacheModal();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* Comma-separated list of extensions to filter
                 */
                allowedFiles: null,

                /* Url to get node information
                 */
                source: '/resources/attachment'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Augment url with parameters
         *
         * @returns {string}
         */
        function getQueryString() {
            var qString = 'multi=0';

            if (config.allowedFiles) {
                qString += '&mime=' + config.allowedFiles;
            }

            return qString;
        }

        /**
         * Unique identifier for modals
         *
         * @returns {string}
         */
        function getCacheKey() {
            return config.source + '?' + getQueryString();
        }

        /**
         * Respawn a modal from the cache if it already existed for
         * a given url
         */
        function cacheModal() {
            var key = getCacheKey();

            if ('undefined' == typeof modalCache[key]) {
                modalCache[key] = $.get(config.source, getQueryString()).promise();
            }

            modalCache[key].done(function(data) {
                // ensure we retain ResourceAttache context
                build(data);
            });
        }

        /**
         * Manufacture a modal
         *
         * @param data
         */
        function build(data) {
            if (singletonModal) {
                // you've got a modal; don't try to make another one
                return;
            }

            var body = $('body');

            // if the modal already exists, destroy it
//            this.destroy();

            singletonModal = $('<div data-reveal data-schoolboxmedia-reveal class="reveal-modal file-picker" />')
                .data('content', data)
                .data('rtn', this);

            body.append(singletonModal);

            singletonModal
                .html(singletonModal.data('content'))
                .foundation('reveal', 'open')
                .off('closed.fndtn.reveal.attach');

            /* Destroy two overlays and adjust a z-index so the
             * Schoolbox Resource Browser appears on top of the
             * ckeditor dialog (enormous sigh)
             */
            $('.cke_dialog_background_cover').remove();

            /* ckeditor dialog appears at z-index 10010 so our
             * Resource Browser needs to be more
             */
            $('[data-schoolboxmedia-reveal]').css({zIndex: 20000});

            // reinit
            $(document)
                //.foundation('reveal', 'reflow')
                .on('closed.fndtn.reveal', '[data-reveal]', function () {
                    if (singletonModal) {
                        singletonModal.detach();
                        singletonModal = null;
                    }
                });

            singletonModal.on('click', '.cancel[role=button]', function (e) {
                window.top.postMessage(JSON.stringify({ data: [] }), location.origin);
            });
        }
    };

    /**
     * A factory for producing Schoolbox Media Dialog panel contents in ckeditor
     *
     * @param type
     * @param responseText
     * @param dialog
     */
    $.fn.schoolboxMediaFactory = function(type, responseText, dialog) {

        /* Render an image
         */
        if (type == 'image') {
            $.fn.schoolboxMediaRenderImage('/storage/image.php?hash='+responseText.hash, dialog);
        }
        else if (type == 'video') {
        }
        else if (type == 'audio') {
        }
        else {
        }
    };

    /**
     * Populate dialog fields with image data
     *
     * @param url
     * @param dialog
     */
    $.fn.schoolboxMediaRenderImage = function(url, dialog) {
        var fields = $.fn.schoolboxMediaDialogFields(dialog);

        /* Find out width/height ratio
         */
        var temp = new Image();
        temp.src = url;
        temp.onload = applyDimensions;

        /* Apply dimensions upon temp image spawn
         */
        function applyDimensions() {
            var width = this.width;
            var height = this.height;
            var preview = '<img src="'+url+'" />';

            /* Set dialog input contents
             */
            fields.url.setValue(url);
            fields.width.setValue(width);
            fields.height.setValue(height);
            fields.preview.html(preview);

            /* Reset aspect ratio value
             */
            $.fn.schoolboxMediaAspectRatioReset(dialog);
        }
    };

    /**
     * Reset the aspect ratio for an image
     *
     * Used when selecting a new image with constrain aspect ratio already activated
     * because that remembers the aspect ratio from the previously-selected image
     *
     * @param dialog
     */
    $.fn.schoolboxMediaAspectRatioReset = function(dialog) {
        var fields = $.fn.schoolboxMediaDialogFields(dialog);

        fields.aspectRatioValue.setValue(fields.width.getValue()/fields.height.getValue());
    }

    /**
     * A macro to all the fields in the Dialog
     * (because it's such a longwinded way to retrieve them)
     *
     * @param dialog
     * @returns {{url: *, width: *, height: *, aspectRatio: *, aspectRatioValue: *, fullwidth: *, preview: (*|Mixed|jQuery|HTMLElement)}}
     */
    $.fn.schoolboxMediaDialogFields = function(dialog) {
        return {
            url: dialog.getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-url'),
            width: dialog.getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-width'),
            height: dialog.getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-height'),
            aspectRatio: dialog.getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-aspectratio'),
            aspectRatioValue: dialog.getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-aspectratio-value'),
            fullwidth: dialog.getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-fullwidth'),
            preview: $('#schoolboxmedia-image-preview')
        };
    };
}(jQuery));


(function($) {

	/**
	 * Unified TinyMCE interface
	 *
	 * @param options
	 */
	$.fn.alaressTiny = function(options) {
		options = options || {};

		var config = init(options);

		start();

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
                    {name: 'styles', groups: ['styles']},
                    {name: 'colors', groups: ['colors']},
                    {name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi', 'paragraph']},
                    {name: 'others', groups: ['others']},
                    '/',
                    {name: 'clipboard', groups: ['clipboard', 'undo']},

                    /* Custom schoolbox-specific plugin stuff can go here
                     * This is a hole into which you may put once-off abilities
                     * for the ckeditor (such as Make Blank, in Quiz/Fillgap)
                     * This is typically not used for anything else for now
                     */
                    {name: 'schoolbox', groups: []}
                ],

                /* Allow images to be styled using style attribute (they can use source if clever)
                 */
                extraAllowedContent: 'img{width,height,border}',

                /* Remove default plugins; add Schoolbox Custom Plugins
                 */
                removePlugins: 'image,image2',
                extraPlugins: 'schoolboxmedia',

                /* Other settings
                 */
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

                /* A callback to execute prior to the ritual spawning of all ck
                 */
                load: function() {
                },

                /* A callback to fire prior to ck spawn
                 */
                setup: function(element) {
                },

                /* A callback to fire immediately after ck spawn
                 */
                start: function(element) {
                },

                /* A callback to fire when content is changed
                 */
                change: function(element) {
                },

                /* A callback to fire when editor blurs
                 */
                blur: function(element) {
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
		function start() {
            config.load();

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
                 * http://stackoverflow.com/questions/5143516/detecting-onchange-events-from-a-ckeditor-using-jquery
                 */
                if ((id !== undefined) && (id.length)) {
                    applyCallbacks(id, element);
                }
                else if ((name !== undefined) && (name.length)) {
                    applyCallbacks(name, element);
                }
            });
		}

        /**
         * Apply setup, ckeditor spawn, and change events
         *
         * @param identifier
         * @param element
         */
        function applyCallbacks(identifier, element) {
            config.setup($(element));

            CKEDITOR.replace(identifier, config);

            config.start($(element));

            CKEDITOR.instances[identifier].on('change', function(event) {
                config.change($(element));
            });

            CKEDITOR.instances[identifier].on('blur', function(event) {
                config.blur($(element));
            });
        }
	}
})(jQuery);

