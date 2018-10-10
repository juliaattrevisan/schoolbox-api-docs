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

        /* A registry of current values that change over time while content is created
         * Used to determine when to initiate actual save transaction
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

                // Toast element
                toaster: $('#saveDraft'),

                // The grace period for after a user makes a change and when the draft save trigger (in seconds)
                interval: 3,

                // Array of selectors which will have .val() checked for changes
                inputs: [],

                // CKEditors to listen for changes on
                textareas: [],

                // Attachzones to listen for changes on
                attachzones: [],

                /* Activation element
                 * The thing that gets disabled while saving
                 */
                activator: element.find('button[type=submit]'),

                /**
                 * Called on successful save.
                 * @param {bool} wasAutoSave
                 */
                onSaveSuccess: $.noop,

                // The infamous stallSubmit
                staller: null
            };

            return $.extend(defaults, options);
        }

        /**
         * Remember what the initial values were at the start
         */
        function initRegistry() {
            var i;

            for (i = 0; i < config.inputs.length; i++) {
                registry[config.inputs[i]] = $(config.inputs[i]).val();
            }

            for (i = 0; i < config.textareas.length; i++) {
                registry[config.textareas[i]] = CKEDITOR.instances[config.textareas[i]].getData();
            }

            /* Files that were in the attachzones initially
             */
            for (i = 0; i < config.attachzones.length; i++) {
                registry[config.attachzones[i]] = [];

                $.each($(config.attachzones[i]), function(index, element) {
                    if ($(element).val() !== '') {
                        registry[config.attachzones[i]].push($(element).val());
                    }
                });
            }
        }

        /**
         * Initiate an autosave - activate toast and save it
         */
        function initAutosave() {
            var draftSave;
            var deferred;

            $(document).on('change keyup', config.inputs, function () { // TODO: listen for changes to textareas and attachzones also
                if (isDifferent()) {
                    // kill any other timeout pending, and resolve the deferred
                    clearTimeout(draftSave);
                    if (deferred !== undefined) {
                        deferred.resolve();
                    }

                    // (re)set the deferred
                    deferred = $.Deferred();

                    // Set a timeout for the draft to save
                    draftSave = setTimeout(function() {
                        config.toaster.toastActivate({
                            text: 'Saving...'
                        });

                        save(deferred);
                    }, config.interval * 1000);

                    // install the deferred as stalling form submit
                    if (config.staller) {
                        config.staller.stallSubmit(deferred);
                    }
                }
            });
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
            var i, j;

            /* Check inputs
             */
            for (i = 0; i < config.inputs.length; i++) {
                key = config.inputs[i];
                current = $(key).val();

                if (
                    (_.isArray(registry[key]) && !_.isEqual(current.sort(), registry[key].sort())) ||
                    (!_.isArray(registry[key]) && registry[key] !== current)
                ) {
                    contentChanged = true;
                    registry[key] = current;
                }
            }

            /* Check textareas
             */
            for (i = 0; i < config.textareas.length; i++) {
                key = config.textareas[i];

                current = CKEDITOR.instances[key].getData();

                if (registry[key] !== current) {
                    contentChanged = true;
                    registry[key] = current;
                }
            }

            /* Check attachzones
             */
            for (i = 0; i < config.attachzones.length; i++) {
                key = config.attachzones[i];

                current = config.element.find('input[name="'+config.attachzones[i]+'[]"]');

                var currentFiles = [];

                /* Check whether there's a different file in the attachzone than the ones we knew
                 */
                for (j = 0; j < current.length; j++) {
                    currentFiles.push($(current[j]).val());

                    if (registry[key].indexOf($(current[j]).val()) === -1) {
                        contentChanged = true;
                    }
                }

                /* If a file has been removed from the list, contentChanged might still be false
                 * because the current list of files is a subset of what was initially there
                 * Now check the length to ensure that a) no new files were added and b) no existing
                 * files were removed
                 */
                if (currentFiles.length !== registry[key].length) {
                    contentChanged = true;
                }

                /* Remember the current state of things
                 */
                registry[key] = currentFiles;
            }

            return contentChanged;
        }

        /**
         * Actually save the data
         */
        function save(deferred) {
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

            /* Transact
             */
            $.ajax({
                type: config.element.attr('method'),
                url: config.element.attr('action'),
                data: formContents,
                dataType: 'json'
            }).success(function (responseText) {
                config.toaster.toastTimeout({
                    text: 'Saved',
                    css: 'success'
                });

                config.onSaveSuccess(responseText);

                deferred.resolve();
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
