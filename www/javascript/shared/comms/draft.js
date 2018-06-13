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

        /* Set initial state
         */
        isDifferent();

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

                /* Textareas to check
                 */
                textareas: null
            };

            return $.extend(defaults, options);
        }

        /**
         * Find out if any textareas changed
         *
         * @returns {boolean}
         */
        function isDifferent() {
            var contentChanged = false;

            Object.keys(config.textareas).forEach(function(key) {
                var current = CKEDITOR.instances[key].getData();

                /* Update registry of known values
                 */
                if (config.textareas[key] !== current) {
                    contentChanged = true;

                    config.textareas[key] = current;
                }
            });

            return contentChanged;
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
         * Actually save the data
         */
        function save() {
            var formContents = $(config.element).serializeArray();

            Object.keys(config.textareas).forEach(function(key) {
                formContents.push({name: key, value: CKEDITOR.instances[key].getData()});
            });

            console.log(formContents);
        }
    }
}(jQuery));