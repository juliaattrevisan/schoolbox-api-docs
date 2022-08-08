(function($) {

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
                'iframe(full-width)[id,src,allowfullscreen,width,height,allow,frameBorder,webkitallowfullscreen,mozallowfullscreen]{width,height,margin,margin-top,margin-bottom,margin-left,margin-right,display,float,border}; '+
                'div(*);' +
                'object[id,name,width,height];' +
                'param[name,value];' +
                'embed[src,type,allowscriptaccess,allowfullscreen,wmode,width,height];' +
                'a[id,href,target,data-portal,title];' +
                'map[!name];' +
                'area[!shape,!coords,!href,target,!alt];' +
                'img[usemap];',

            /* Remove annoying dialog that always appears
             */
            dialog_noConfirmCancel: true,

            /* Remove default plugins; add Schoolbox Custom Plugins
             */
            removePlugins: 'image,image2,uploadimage',
            extraPlugins: 'schoolboxresource,schoolboxiframe,schoolboxlink,schoolboxanchor,dragresize,schoolboxuploadimage',

            /* Other settings
             */
            toolbarCanCollapse: true,
            removeButtons: 'CodeSnippet,HorizontalRule,Sourcedialog,About,Indent,Outdent,Embed',
            magicline_color: '#0066b0',
            format_tags: 'p;h2;h3;h4;pre',

            // Reluctantly use the CDN prefix installed in the window object.
            // The alternative is to inject the CDN prefix into every place
            // which instantiates an editor.
            contentsCss: [
                '/css/core/skin.css.php',
                window.cdnPrefix + '/css/core/editor.css',
            ],

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
            },

            // File paste upload
            uploadUrl: '/storage/asyncUpload.php',
            fileUploadResponse: function (event) {
                let response          = JSON.parse(event.data.fileLoader.xhr.responseText);
                event.data.message    = response.message;
                event.data.uploaded   = 1;
                event.data.url        = response.meta.file._links.image;
                event.data.filesystem = response.hash.split(':')[0];
                event.stop();
            },
        };

        var derived = {
        };

        return $.extend(defaults, derived, options);
    }


    /**
     * Retrieve a configuration for a CKEditor instance.
     *
     * @param {Object|null} options Any overrides of the default configuration
     * @returns {Object}
     */
    $.alaressEditorConfig = function (options) {
        options = options || {};
        return init(options);
    };


    /**
     * Retrieve a standard configuration for comment CKEditor instances.
     *
     * @param { Object } overrideConfig any overrides of the default configuration
     *
     * @returns { Object }
     */
    $.alaressEditorConfigForComments = function (overrideConfig = {}) {
        const config = {
            removePlugins: 'uploadimage,link,schoolboxanchor,elementspath,wordcount,autolink,autoembed,schoolboxiframe',
            removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,EqnEditor,Embed',
            toolbarGroups: [
                { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                { name: 'paragraph', groups: ['list', 'blocks', 'bidi'] },
                { name: 'insert', groups: ['insert'] },
                { name: 'schoolbox', groups: ['schoolboxlink'] },
            ],
            height: '4rem',
            resize_enabled: true,
            toolbarCanCollapse: false,
            ...overrideConfig
        }
        return init(config);
    };


    /**
     * Retrieve a configuration for a CKEditor instance, including user tokens
     * autocomplete configuration.
     *
     * @param {Object|null} [options] Any overrides of the default configuration
     * @returns {Object}
     */
    $.alaressEditorConfigWithUserTokens = function (options) {
        options = options || {};
        options.mentions = options.mentions || [];
        options.mentions.push({
            feed: [
                'firstname',
                'givenname',
                'preferredname',
                'surname',
                'fullname',
                'house',
                'year',
            ],
            marker: '[',
            minChars: 0,
            itemTemplate: '<li data-id="{id}">{name}]</li>',
            outputTemplate: '{name}] ',
        });
        return init(options);
    };

    /**
     * Retrieve a configuration for a CKEditor instance, including user tokens
     * autocomplete configuration. specific to the email signature ckeditor in admin
     *
     * @param {Object|null} [options] Any overrides of the default configuration
     * @returns {Object}
     */
    $.alaressEditorConfigWithUserEmailTokens = function (options) {
        options = options || {};
        options.mentions = options.mentions || [];
        options.mentions.push({
            feed: [
                'title',
                'firstname',
                'givenname',
                'preferredname',
                'surname',//surname as that is the language we are using in admin user modify
                'fullname',
                'email',
                'position',
            ],
            marker: '[',
            minChars: 0,
            itemTemplate: '<li data-id="{id}">{name}]</li>',
            outputTemplate: '{name}] ',
        });
        return init(options);
    };


    /**
     * Unified Editor interface
     *
     * @param options
     */
    $.fn.alaressEditor = function(options) {
        var config = $.alaressEditorConfig(options);

        start();

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

            // Apply fileUploadResponse manually if it's there:
            // somehow, it's not applied when passed as a config option
            if (config.fileUploadResponse) {
                CKEDITOR.instances[identifier].on('fileUploadResponse', config.fileUploadResponse);
            }
        }

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

    };

})(jQuery);

//the maths equation plugin is currently overwriting the global URL variable, ensure this doesnt happen
//https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/defineProperty#custom_setters_and_getters
(function() {
	var actualURL = window.URL;
	Object.defineProperty(window, 'URL', {
		set: function(newValue) {
			()=>{} //just prevent anything from happening, because nothing should //https://stackoverflow.com/questions/21634886/what-is-the-javascript-convention-for-no-operation
		},
		get: function() { return actualURL; }
	});
})();
