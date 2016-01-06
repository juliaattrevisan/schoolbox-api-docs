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

(function() {
    /**
     * Populate dialog fields with image data
     *
     * @param fields
     * @param url
     * @param dialog
     * @param reset
     */
    $.fn.schoolboxResource_Image_Render = function(fields, url, dialog, reset) {

        /* Special case for video url
         */
        if (fields.type.getValue() == 'video') {
            url = '/cms/videoThumb.php?type=sbfs&size=poster&id=' + fields.id.getValue();
        }
        if (fields.type.getValue() == 'audio') {
            url = '/images/core/component/audio-thumbnail-processing.gif';
        }

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
            fields.preview.html(preview);

            /* Don't change width or height if they were already set
             *
             * This occurs when setup() runs, when editing an existing image
             * Or when an image was set to Fullwidth (it won't have a width/height style attribute)
             */
            if (fields.width.getValue() == '' || fields.height.getValue() == '' || reset) {
                fields.width.setValue(width);
                fields.height.setValue(height);
            }

            /* Reset aspect ratio value
             */
            $.fn.schoolboxResource_Image_AspectRatioReset(fields);
        }
    };

    /**
     * Reset the aspect ratio for an image
     *
     * Used when selecting a new image with constrain aspect ratio already activated
     * because that remembers the aspect ratio from the previously-selected image
     *
     * @param fields
     */
    $.fn.schoolboxResource_Image_AspectRatioReset = function(fields) {
        fields.aspectRatioValue.setValue(fields.width.getValue()/fields.height.getValue());
    };

    /**
     * Inject image into editor panel with modifiers applied
     *
     * @param editorInstance
     * @param fields
     * @param url
     * @returns {*}
     */
    $.fn.schoolboxResource_Image_Inject = function(editorInstance, fields, url) {
        var dataVars = {};

        /* Special case for video data
         */
        if (fields.type.getValue() == 'video') {
            url = '/cms/videoThumb.php?type=sbfs&size=poster&id=' + fields.id.getValue();

            dataVars = {
                'data-schoolboxresource-type': 'video',
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': '/cms/sendVideo.php?type=sbfs&id=' + fields.id.getValue()
            };
        }
        /* Special case for audio data
         */
        if (fields.type.getValue() == 'audio') {
            url = '/images/core/component/audio-thumbnail-processing.gif';

            dataVars = {
                'data-schoolboxresource-type': 'audio',
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': '/cms/sendVideo.php?type=sbfs&id=' + fields.id.getValue()
            };
        }
        /* Images render with provided information
         */
        else if (fields.type.getValue() == 'image') {
            dataVars = {
                'data-schoolboxresource-type': 'image',
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': url
            };
        }

        /* Render image
         */
        var styles = [];
        var classes = [];

        var element = editorInstance.document.createElement('img');
        element.setAttribute('src', url);
        element.setAttributes(dataVars);

        /* Determine width - if fullwidth checked, use 100%
         * otherwise use specified values. Note that width:100%
         * is not allowed, so use the usual class instead
         *
         * Include both styles and clases to display this; because
         * of htmlpurifier and ckeditor iframe respectively
         */
        if (fields.fullwidth.getValue()) {
            classes.push('full-width');
            styles.push('width:100%');
        }
        else {
            styles.push('width:' + fields.width.getValue() + 'px');
            styles.push('height:' + fields.height.getValue() + 'px');
        }

        /* Alignment
         * Floats left and right;
         * centered uses 0 auto;
         * none is no floating, just display inline
         */
        if (fields.alignment != undefined) {
            switch (fields.alignment.getValue()) {
                case 'center':
                    styles.push('margin:0 auto');
                    styles.push('display:block');
                    break;

                case 'left':
                case 'right':
                    styles.push('float:' + fields.alignment.getValue());
                    break;

                default:
                    break;
            }
        }

        /* Border
         */
        if (fields.border.getValue() != '') {
            styles.push('border:1px solid #808080;');
        }

        /* Vertical Spacing (applies padding-top and padding-bottom)
         */
        if (fields.verticalPadding.getValue() != '') {
            styles.push('padding-top:' + fields.verticalPadding.getValue() + 'px');
            styles.push('padding-bottom:' + fields.verticalPadding.getValue() + 'px');
        }

        /* Horizontal Spacing (applies padding-left and padding-right)
         */
        if (fields.horizontalPadding.getValue() != '') {
            styles.push('padding-left:' + fields.horizontalPadding.getValue() + 'px');
            styles.push('padding-right:' + fields.horizontalPadding.getValue() + 'px');
        }

        /* Combine and inject
         */
        element.setAttribute('class', classes.join(' '));
        element.setAttribute('style', styles.join(';'));

        return element;
    };

    /**
     * A macro to all the fields in the Dialog
     * (because it's such a longwinded way to retrieve them)
     *
     * @param dialog
     * @returns {{url: *, width: *, height: *, aspectRatio: *, aspectRatioValue: *, fullwidth: *, preview: (*|Mixed|jQuery|HTMLElement)}}
     */
    $.fn.schoolboxResource_Image_DialogFields = function(dialog) {
        return {
            id: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-id'),
            type: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-type'),
            url: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-url'),
            preview: $('#schoolboxresource-image-preview'),
            width: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-width'),
            height: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-height'),
            aspectRatio: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-aspectratio'),
            aspectRatioValue: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-aspectratio-value'),
            fullwidth: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-fullwidth'),
            alignment: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-alignment'),
            border: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-border'),
            verticalPadding: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-verticalpadding'),
            horizontalPadding: dialog.getContentElement('schoolboxresource-image-tab', 'schoolboxresource-image-horizontalpadding')
        };
    };
}(jQuery));


(function() {

    /**
     * Extract a particular css property's value from a style string
     *
     * @param property
     * @param style
     */
    $.fn.schoolboxResource_ExtractStyle = function(property, style) {
        if (style != null) {
            var allProperties = style.split(';');

            for (var i = 0; i < allProperties.length; i++) {
                var current = allProperties[i].split(':');

                /* Replace any spaces in the property name before checking
                 * as sometimes the style attribute is formatted
                 */
                if (current[0].replace(' ', '') == property) {
                    return current[1];
                }
            }
        }

        return '';
    };

    /**
     * Extract resource id from a url
     *
     * @param url
     * @returns {*}
     */
    $.fn.schoolboxResource_ExtractResourceId = function(url) {
        var bits = url.match(/id=(\d+)/);

        if ((bits != null) && bits.length) {
            return bits[1];
        }

        return '';
    }
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
                extraAllowedContent: 'img(full-width)[data-schoolboxresource-type,data-schoolboxresource-thumbnail,data-schoolboxresource-source]{width,height,border,margin,display,padding-top,padding-bottom,padding-left,padding-right,padding,float}',

                /* Remove annoying dialog that always appears
                 */
                dialog_noConfirmCancel: true,

                /* Remove default plugins; add Schoolbox Custom Plugins
                 */
                removePlugins: 'image,image2',
                extraPlugins: 'schoolboxresourceimage',

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

/* Proxy Replacements
 */
$.fn.schoolboxResource_ProxyReplacment();
