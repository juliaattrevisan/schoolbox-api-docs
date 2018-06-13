(function() {

    /**
     * The Draft plugin detects differences in the elements listed in config.inputs
     * and config.textareas and asynchronously saves when needed
     *
     * This also quietly updates the url and form action so if you refresh the page
     * without hitting the submit button, you won't reload what the screen originally
     * did which might actually be a Create screen.
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

                /* Toaster
                 */
                toaster: $('#saveDraft'),

                /* Save every 10 seconds by default
                 */
                interval: 10,

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
         * Initiate an autosave - activate toast and save it
         */
        function initAutosave() {
            autosaveInterval = setInterval(function() {
                if (isDifferent()) {
                    config.toaster.toastActivate({
                        text: 'Autosaving'
                    });

                    save();
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

            /* Disable inputs
             */
            config.element.find('input[type=submit]').prop('disabled', true);

            /* Transact
             */
            $.ajax({
                type: 'POST',
                url: config.element.attr('action'),
                data: formContents,

                complete: function () {

                    /* Re-init the autosave interval
                     */
                    initAutosave();

                    /* Re-enable submit
                     */
                    config.element.find('input[type=submit]').prop('disabled', false);
                },
                success: function (responseText) {

                    /* Recycle the id ><
                     */
                    config.element.attr('action', responseText.formAction);

                    /* hehe
                     */
                    window.history.pushState({}, null, responseText.formAction);

                    config.toaster.toastTimeout({
                        text: 'Draft Saved',
                        css: 'success'
                    });
                },
                error: function (response) {
                    var temp = $.parseJSON(response.responseText);

                    config.toaster.toastTimeout({
                        text: 'Error: ' + temp.message,
                        css: 'alert'
                    });
                },
                dataType: 'json'
            });
        }
    }
}(jQuery));