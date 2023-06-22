(function () {
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
    $.fn.draft = function (options) {
        options = options || {};

        var config = init(options, $(this));

        // Watch the content for changes
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
                toaster: $("#saveDraft"),

                // The grace period for after a user makes a change and when the draft save trigger (in seconds)
                interval: 3,

                // Array of selectors which will have .val() checked for changes
                inputs: [],

                // CKEditors to listen for changes on
                textareas: [],

                // Submit buttons to check for the 'data-ckEditorMaxCharCountExceededAtLastCheck' attribute.
                // If the 'data-ckEditorMaxCharCountExceededAtLastCheck' attribute is present on any submit button, this
                // means the maximum character count has been exceeded in one or more of the ckeditor textareas that will
                // be submitted by that submit button (see wordCountSettings.ts).  The content cannot be saved in this
                // situation, because it will not fit into the relevant database field. Therefore, if any of these submit
                // buttons has the attribute autosave will be skipped.
                submitButtons: [],

                // Attachzones to listen for changes on
                attachzones: [],

                // Datepickers to listen for changes on
                // These shouldn't go into 'inputs' because of the strtotime functionality we have on date fields
                datepickers: [],

                /* Activation element
                 * The thing that gets disabled while saving
                 */
                activator: element.find("button[type=submit]"),

                /**
                 * Called on successful save.
                 * @param {bool} wasAutoSave
                 */
                onSaveSuccess: $.noop,

                // The infamous stallSubmit
                staller: null,
            };

            return $.extend(defaults, options);
        }

        /**
         * Init listeners for changes, firing save() when there's changes (with a timeout)
         */
        function initAutosave() {
            var draftTimeout;
            var deferred;

            // Listen for changes on inputs
            config.element.on("change keyup", config.inputs.join(", "), checkForChanges);

            /**
             * Loop through the textareas and listen for changes on them
             * via the CKEditor's model.document.on("change:data") event
             */
            var i;
            for (i = 0; i < config.textareas.length; i++) {
                var key = config.textareas[i];
                if (key in window.sbProps.editors) {
                    window.sbProps.editors[key].model.document.on("change:data", () => {
                        checkForChanges();
                    });
                } else {
                    // If the editor hasn't been created yet, wait for it to be created
                    document.getElementById(key).addEventListener("EDITOR__CREATED", onEditorCreated);
                    function onEditorCreated(event) {
                        event.detail.editor.model.document.on("change:data", () => {
                            checkForChanges();
                        });
                    }
                }
            }

            // Listen for changes to attachzones
            config.element.on(
                "success.attachzone removedfile.attachzone",
                config.attachzones.join(", "),
                checkForChanges
            );

            // Listen for changes to datepicker fields
            config.element.on("datepickerChange", checkForChanges);

            /**
             * The common function triggered on the listeners above:
             * It will reset the draft timeout and deferreds and line up another draft save
             *
             * I've deliberately put this inside the scope of initAutosave in order to directly modify the draftTimeout & deferred vars
             * Alternatively I could've returned the vars in this function and reassigned them above.
             */
            function checkForChanges() {
                for (let submitButton of config.submitButtons) {
                    if (submitButton.hasAttribute('data-ckEditorMaxCharCountExceededAtLastCheck')) {
                        return; // Skip check because the content storage limit has been exceeded.
                    }
                }

                // kill any other timeout pending, and resolve the deferred
                clearTimeout(draftTimeout);
                if (deferred !== undefined) {
                    deferred.resolve();
                }

                // (re)set the deferred
                deferred = $.Deferred();

                // Set a timeout for the draft to save
                draftTimeout = setTimeout(function () {
                    save(deferred);
                }, config.interval * 1000);

                // install the deferred as stalling form submit
                if (config.staller) {
                    config.staller.stallSubmit(deferred);
                }
            }
        }

        /**
         * Save the data via ajax, showing a toast for the user
         *
         * @param {jQuery.Deferred} deferred - a deferred object to resolve on successful save
         */
        function save(deferred) {
            // Init toast
            config.toaster.toastActivate({
                text: "Saving...",
            });

            var formContents = $(config.element).serializeArray();

            /* Special treatment because ck reasons
             */
            for (var i = 0; i < config.textareas.length; i++) {
                var key = config.textareas[i];

                formContents.push({
                    name: key,
                    value: window.sbProps.editors[key].getData(),
                });
            }

            /* Send draft flag
             */
            formContents.push({ name: "draft", value: true });

            /* Transact
             */
            $.ajax({
                type: config.element.attr("method"),
                url: config.element.data("action-draft"),
                data: formContents,
                dataType: "json",
            })
                .done(function (responseText) {
                    config.toaster.toastTimeout({
                        text: "Saved",
                        css: "success",
                    });

                    config.onSaveSuccess(responseText);

                    // Resolve the deferred for stallSubmit
                    deferred.resolve();
                })
                .fail(function (response) {
                    var temp = $.parseJSON(response.responseText);

                    config.toaster.toastTimeout({
                        text: "Error: " + temp.message,
                        css: "alert",
                    });
                });
        }
    };
})(jQuery);
