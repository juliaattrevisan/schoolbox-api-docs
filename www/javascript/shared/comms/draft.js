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
         * Used to determine when to initiate actual save transaction
         */
        var registry = {};

        /* Set initial state
         */
        initRegistry();

        /* Set up custom event
         */
        $(config.element).on('draft', function() {
            save();
        });

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

                /* Save every 5 seconds by default
                 */
                interval: 5,

                /* Inputs to check
                 */
                inputs: [],

                /* Textareas to check
                 */
                textareas: [],

                /* Activation element
                 * The thing that gets disabled while saving
                 */
                activator: element.find('input[type=submit]'),

                /* Draft Lozenge
                 */
                newIndicator: $('#status-new'),
                draftIndicator: $('#status-draft')
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

                    $(config.element).trigger('draft');
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

            /* Send draft flag
             */
            formContents.push({name: 'draft', value: true});

            /* Disable inputs
             */
            config.activator.prop('disabled', true);

            /* Clear the autosave interval; it reactivates after drafts transaction occurs
             */
            clearInterval(autosaveInterval);

            /* Transact
             */
            $.ajax({
                type: config.element.attr('method'),
                url: config.element.attr('action'),
                data: formContents,
                dataType: 'json'
            }).done(function () {

                /* Re-init the autosave interval
                 */
                initAutosave();

                /* Re-enable submit
                 */
                config.activator.prop('disabled', false);
            }).success(function (responseText) {

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

                /* Show lozenge
                 */
                config.newIndicator.hide();
                config.draftIndicator.show();
            }).error(function (response) {
                var temp = $.parseJSON(response.responseText);

                config.toaster.toastTimeout({
                    text: 'Error: ' + temp.message,
                    css: 'alert'
                });
            });
        }
    }
}(jQuery));