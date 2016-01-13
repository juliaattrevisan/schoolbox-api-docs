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
                source: '/resources/attachment'
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

            singletonModal = $('<div data-reveal data-schoolboxresource-reveal class="reveal-modal file-picker" />')
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
            $('[data-schoolboxresource-reveal]').css({zIndex: 20000});

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
                    {name: 'schoolbox', groups: ['schoolboxresource', 'schoolboxiframe']}
                ],

                /* Allow images to be styled using style attribute (they can use source if clever)
                 */
                extraAllowedContent: 'img(full-width)[data-schoolboxresource-width,data-schoolboxresource-height,data-schoolboxresource-filesystem,data-schoolboxresource-type,data-schoolboxresource-thumbnail,data-schoolboxresource-source]{width,height,border,margin,margin-top,margin-bottom,margin-left,margin-right,display,float};iframe(full-width)[src,allowfullscreen]{width,height,border,margin,margin-top,margin-bottom,margin-left,margin-right,display,float}',

                /* Remove annoying dialog that always appears
                 */
                dialog_noConfirmCancel: true,

                /* Remove default plugins; add Schoolbox Custom Plugins
                 */
                removePlugins: 'image,image2',
                extraPlugins: 'schoolboxresource,schoolboxiframe',

                /* Other settings
                 */
                toolbarCanCollapse: true,
                removeButtons: 'CodeSnippet,HorizontalRule,Sourcedialog,About,Indent,Outdent',
                magicline_color: '#0000FF',
                format_tags: 'p;h3;h4;pre;address',

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
        }
	}
})(jQuery);