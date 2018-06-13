(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.draft = function(options) {
        options = options || {};

        var config = init(options, $(this));
        var autosaveInterval;

        /* A registry of current values that change over time while content is created
         * Used to determine when to initiate autosave
         */
        var registry = {};

        /* Set initial state
         */
        initRegistry();

        /* Watch the content for changes
         */
        initAutosave();

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

                /* Save every 5 seconds by default
                 */
                interval: 5,

                /* Inputs to check
                 */
                inputs: [],

                /* Textareas to check
                 */
                textareas: []
            };

            return $.extend(defaults, options);
        }

        /**
         * Remember what the initial values were at the start
         */
        function initRegistry() {
            var i;

            for (i = 0; i < config.inputs.length; i++) {
                registry[config.inputs[i]] = $('input[name="'+config.inputs[i]+'"]').val();
            }

            for (i = 0; i < config.textareas.length; i++) {
                registry[config.textareas[i]] = CKEDITOR.instances[config.textareas[i]].getData();
            }
        }

        /**
         * Initiate an autosave
         */
        function initAutosave() {
            autosaveInterval = setInterval(function() {
                if (isDifferent()) {

                    // Disable inputs
                    // clear interval
                    save();
                    // fire toast
                    // restore interval
                }
                else {
                    console.log('no change');
                }
            }, config.interval * 1000);
        }

        /**
         * Find out if any textareas changed
         * Update registry of known values
         *
         * @returns {boolean}
         */
        function isDifferent() {
            var contentChanged = false;
            var key;
            var current;

            /* Check inputs
             */
            for (i = 0; i < config.inputs.length; i++) {
                key = config.inputs[i];

                current = config.element.find('input[name="'+key+'"]').val();

                if (registry[key] !== current) {
                    contentChanged = true;

                    registry[key] = current;
                }
            }

            /* Check textareas
             */
            for (var i = 0; i < config.textareas.length; i++) {
                key = config.textareas[i];

                current = CKEDITOR.instances[key].getData();

                if (registry[key] !== current) {
                    contentChanged = true;

                    registry[key] = current;
                }
            }

            return contentChanged;
        }

        /**
         * Actually save the data
         */
        function save() {
            var formContents = $(config.element).serializeArray();

            /* Special treatment because ck reasons
             */
            for (var i = 0; i < config.textareas.length; i++) {
                var key = config.textareas[i];

                formContents.push({name: key, value: CKEDITOR.instances[key].getData()});
            }

            console.log(formContents);
        }
    }
}(jQuery));