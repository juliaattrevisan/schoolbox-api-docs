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
    $.fn.schoolboxResource = function(options) {
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
                source: '/resources/attachment',

                /* Allowed Filesystems for choosing resources
                 */
                allowedFilesystems: false
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
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

            if (config.allowedFilesystems) {
                qString += '&fs=' + config.allowedFilesystems;
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
         * Resize picker dimensions dynamically
         */
        function resizePicker() {
            var pickerWidth = $(window).width() * 0.67;

            $('.reveal-modal.file-picker.open').css({
                width: pickerWidth + 'px',
                left: Math.floor($(window).width()/2 - pickerWidth/2) + 'px'
            });
        }

        /**
         * Manufacture a modal
         *
         * @param data
         */
        function build(data) {
            var body = $('body');

            if (singletonModal) {
                // you've got a modal; don't try to make another one
                return;
            }
            else if($('#attachment-browser').length) {
                singletonModal = $('#attachment-browser').parent();
            }
            else {
                singletonModal = $('<div data-reveal class="reveal-modal file-picker" id="file-picker" />')
                    .data('content', data);

                body.append(singletonModal);
            }

            singletonModal
                .html(singletonModal.data('content'))
                .foundation('reveal', 'open')
                .off('closed.fndtn.reveal.attach');

            /* If you're opening the modal in cke_maximised,
             * you have to wrangle styles around so it doesn't
             * appear in a stupid layout
             *
             * For some reason cke fights foundation and kills responsiveness
             * so dynamically assign a pixel width upon initiation and
             * also aupon resization
             */
            if ($('.cke_maximized').length) {
                $(window).resize(function() {
                    resizePicker();
                });

                $(document).on('opened.fndtn.reveal', '.file-picker', function() {
                    resizePicker();
                });
            }

            /* Hide cke overlay and adjust a z-index so the
             * Schoolbox Resource Browser appears on top of the
             * ckeditor dialog (enormous sigh)
             */
            $('.cke_dialog_background_cover').addClass('hide');

            /* ckeditor dialog appears at z-index 10010 so our
             * Resource Browser needs to be more
             */
            $('[data-reveal]').css({zIndex: 20000});

            // reinit
            $(document)
                //.foundation('reveal', 'reflow')
                .on('closed.fndtn.reveal', '[data-reveal]', function () {
                    if (singletonModal) {
                        singletonModal.detach();
                        singletonModal = null;
                    }
                    if(undefined !== config.previousModal) {
                        config.previousModal.removeClass('hide');
                    }

                    // Show the cke dialog overlay again
                    $('.cke_dialog_background_cover').removeClass('hide');
                });

            singletonModal.on('click', '.cancel[role=button]', function (e) {
                e.preventDefault();
                window.top.postMessage(JSON.stringify({ data: [] }), location.origin);
                singletonModal.foundation('reveal', 'close');
            });
        }
    };
}(jQuery));

(function($) {

	/**
	 * Unified Editor interface
	 *
	 * @param options
	 */
	$.fn.alaressEditor = function(options) {
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
                    /* Custom schoolbox-specific plugin stuff can go here
                     * This is a hole into which you may put once-off abilities
                     * for the ckeditor (such as Make Blank, in Quiz/Fillgap)
                     * This is typically not used for anything else for now
                     */
                    {name: 'schoolbox', groups: ['schoolboxlink', 'schoolboxanchor', 'schoolboxresource', 'schoolboxiframe']},
                    {name: 'insert', groups: ['insert']},
                    '/',
                    {name: 'styles', groups: ['styles']},
                    {name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align', 'bidi', 'paragraph']},
                    {name: 'others', groups: ['others']},
                    '/',
                    {name: 'colors', groups: ['colors']},
                    {name: 'clipboard', groups: ['clipboard', 'undo']},
                    {name: 'document', groups: ['mode', 'document', 'doctools']},
                    {name: 'editing', groups: ['find', 'selection', 'spellchecker', 'editing']},
                    {name: 'tools', groups: ['tools']}
                ],

                /* Quicktable config for best table defaults
                 */
                qtBorder: '0', // Border of inserted table
                qtWidth: '100%', // Width of inserted table
                qtCellPadding: '0', // Cell padding table
                qtCellSpacing: '0', // Cell spacing table

                /* Maximum height of editing panel is half the viewport height
                 * due to navigation panel, other elements on the page, etc.
                 */
                autoGrow_maxHeight: 0.5 * $(window).height(),

                /* Allow images to be styled using style attribute (they can use source if clever)
                 */
                extraAllowedContent: 'img(full-width)[data-schoolboxresource-fullwidth,data-schoolboxresource-width,data-schoolboxresource-height,data-schoolboxresource-filesystem,data-schoolboxresource-type,data-schoolboxresource-thumbnail,data-schoolboxresource-source]{width,height,border,margin,margin-top,margin-bottom,margin-left,margin-right,display,float};' +
                    'iframe(full-width)[src,allowfullscreen,width,height]{width,height,margin,margin-top,margin-bottom,margin-left,margin-right,display,float}; '+
                    'div(*);' +
                    'object[id,name,width,height];' +
                    'param[name,value];' +
                    'embed[src,type,allowscriptaccess,allowfullscreen,wmode,width,height];' +
                    'a[id,href,target,data-portal];' +
                    'map[!name];' +
                    'area[!shape,!coords,!href,target,!alt];' +
                    'img[usemap];',

                /* Remove annoying dialog that always appears
                 */
                dialog_noConfirmCancel: true,

                /* Remove default plugins; add Schoolbox Custom Plugins
                 */
                removePlugins: 'image,image2,uploadimage',
                extraPlugins: 'schoolboxresource,schoolboxiframe,schoolboxlink,schoolboxanchor,dragresize',

                /* Other settings
                 */
                toolbarCanCollapse: true,
                removeButtons: 'CodeSnippet,HorizontalRule,Sourcedialog,About,Indent,Outdent,Embed,Link,Anchor',
                magicline_color: '#0066b0',
                format_tags: 'p;h2;h3;h4;pre',
                contentsCss: '/css/'+siteLocation+'/editor.css',

                /* set to use our iframely API key for oEmbed calls
                 */
                embed_provider: '//iframe.ly/api/oembed?url={url}&callback={callback}&api_key=94aa8fc28ac1089a2dfb28',

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
            };

            return $.extend(defaults, derived, options);
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

            CKEDITOR.instances[identifier].on('key', function(event) {

                /* Disable Nanospell if keycode 229 is pressed (sign of IME use)
                 * (Nanospell breaks IME use)
                 */
                if (event.data.keyCode === 229) {

                    CKEDITOR.instances[identifier].stopNanospell();

                    /* If nanospell has been disabled - we can remove this from running on every keypress
                     * NOTE: if any further code is added to this listener this will need to change
                     */
                    event.removeListener();
                }
            });
        }
	};

    // Remove unsupported table options on table properties dialog open
    CKEDITOR.on('dialogDefinition', function(ev) {
        var dialogName = ev.data.name;
        var dialogDefinition = ev.data.definition;

        if (dialogName === 'table' || dialogName === 'tableProperties') {
            var contents = dialogDefinition.getContents('info');

            contents.remove('txtCellSpace'); // Cell spacing table attribute is overridden by css, and deprecated in HTML5
            contents.remove('txtCellPad'); // Cell padding table attribute is overridden by css, and deprecated in HTML5
            contents.remove('txtCaption'); // table caption is deprecated HTML5
            contents.remove('txtSummary'); // table summary is deprecated in HTML5
        }
    });

})(jQuery);
