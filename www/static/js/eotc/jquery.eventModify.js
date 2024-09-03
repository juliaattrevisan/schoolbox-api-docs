(function ($) {
    /**
     * Constructor
     *
     * @param options
     */
    $.fn.eventModify = function (options) {
        /* Optional parameter
         */
        options = options || {};

        /* Store the config in this instance so private methods
         * can access it
         */
        var config = init(options, this);

        /* Form validator
         */
        setupFormActions();
        setupEventValidation();

        /* Show/hide repeat event options (after validation)
         */
        setupRepeat();

        setupDeleteActions(config.deleteButton);

        /* https://coderwall.com/p/zyrr2g/reset-jquery-form-validation-jquery-validate
         * but why...?
         */
        $(config.element).on("change", "select", function (event) {
            $(config.element).validate().resetForm();
        });

        /* https://coderwall.com/p/zyrr2g/reset-jquery-form-validation-jquery-validate
         * but why...?
         */
        $(config.element).on("change", "input", function (event) {
            $(config.element).validate().resetForm();
        });

        /**
         * Initialise various configured params and determine a bunch
         * of ids and stuff that we'll probably use elsewhere
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,
                functionLabel: "",
                deleteButton: $(),
            };

            var derived = {};

            return $.extend(defaults, derived, options);
        }

        /**
         * Set up repeating events options
         */
        function setupRepeat() {
            // TODO move this to hideWatch plugin (once we apply rules conditionally)
            var showRepeats = function () {
                var repeatInput = config.element.find("input[name=repeat_until]");
                if ($(this).val() !== "") {
                    $("#repeatUntil").show();
                    $("[data-repeat-until]").removeClass("hide");
                    if (repeatInput.length) {
                        repeatInput.rules("add", "required");
                    }
                } else {
                    $("#repeatUntil, [data-repeat-until]").hide();
                    $("[data-repeat-until]").addClass("hide");
                    if (repeatInput.length) {
                        repeatInput.rules("remove", "required");
                    }
                }
            };
            var repeatSelector = config.element.find("select[name=repeat]");
            repeatSelector.change(showRepeats);
            showRepeats.apply(repeatSelector);
        }

        /**
         * Apply button actions
         *
         * Now, all #b{action} change the form's action.
         */
        function setupFormActions() {
            config.element.find(':submit[id^="b"]').on("click", function () {
                config.element.find("#action").val(this.id.substr(1));
            });
        }

        /**
         * Applies delete actions to the delete button.
         *
         * * single event: show confirm dialog (which will use foundation reveals)
         * * multi event: ask if applying to this event or all in series
         *
         * @param deleteButton
         */
        function setupDeleteActions(deleteButton) {
            var multi = $("#repeat-reminder, [data-repeat-reminder]").length != 0;
            if (multi) {
                deleteButton.on("click", function (event) {
                    event.preventDefault();
                    var dialog = $('[data-dialog="bulk-delete"]');
                    dialog.foundation("reveal", "open");
                    // Do stuff with buttons
                    setupBulkDialogEvents(
                        dialog,
                        function () {
                            // Add self to href
                            location.href = deleteButton.attr("href") + "&efficacy=self";
                        },
                        function () {
                            // Add series to href
                            location.href = deleteButton.attr("href") + "&efficacy=series";
                        },
                    );
                });
            } else {
                deleteButton.on("click", function (event) {
                    event.preventDefault();
                    deleteButton.confirmDialog({
                        text: "Are you sure you want to delete this event?",
                        confirmLabel: "Delete",
                        confirmEvent: $.confirmDialog.followHref,
                    });
                });
            }
        }

        /**
         * Resets events on bulk modify or delete dialog.
         *
         * @param dialog
         * @param selfCallback   Call this when updating or deleting this event only
         * @param seriesCallback Call this when updating or deleting entire series
         */
        function setupBulkDialogEvents(dialog, selfCallback, seriesCallback) {
            // Rebind events on open/close/cancel
            dialog
                .find('[data-action="self"]')
                .off("click")
                .on("click", function (e) {
                    e.preventDefault();
                    dialog.foundation("reveal", "close");
                    selfCallback();
                });
            dialog
                .find('[data-action="series"]')
                .off("click")
                .on("click", function (e) {
                    e.preventDefault();
                    dialog.foundation("reveal", "close");
                    seriesCallback();
                });
            dialog
                .find('[data-action="cancel"]')
                .off("click")
                .on("click", function (e) {
                    e.preventDefault();
                    dialog.foundation("reveal", "close");
                });
        }

        /**
         * Generate rules for validator
         *
         * @returns {object}
         */
        function setupEventValidationRules() {
            /* A campus was chosen
             */
            var validateCampusSelected = function () {
                return $('input[name="campus[]"]').length;
            };

            /* Disabled need for files to be uploaded, regardless of the setting
             */
            var validateRamsFile = function () {
                return false;
            };

            var validateTeacherInCharge = function () {
                var ticValue = $("input[name=teacherInCharge]").val();

                return ticValue == "[]" || ticValue == "";
            };

            return {
                "campus[]": {
                    required: {
                        depends: validateCampusSelected,
                    },
                    minlength: {
                        param: 1,
                        depends: validateCampusSelected,
                    },
                },
                ramsFile: {
                    required: {
                        depends: validateRamsFile,
                    },
                    minlength: {
                        param: 1,
                    },
                },
                teacherInCharge: {
                    required: {
                        depends: validateTeacherInCharge,
                    },
                    minlength: {
                        param: 1,
                    },
                },
                startDate: {
                    required: true,
                    datepickerDate: true,
                },
                endDate: {
                    required: true,
                    datepickerDate: true,
                    datepickerDateNotBefore: $("#startDate"),
                },
            };
        }

        /**
         * Validate form data
         *
         * Augment validation rules with callback options
         */
        function setupEventValidation() {
            $(config.element).alaressValidate({
                rules: setupEventValidationRules(),
                messages: {
                    endDate: {
                        datepickerDateNotBefore: "Start date and time must occur before end date and time",
                    },
                },
                submitHandler: function (form) {
                    // Find the submit button for this form and disable it,
                    // to avoid accidental multiple requests
                    $("#eotc-modify-submit").prop("disabled", true);

                    var multi = $("#repeat-reminder, [data-repeat-reminder]").length != 0;
                    if (multi) {
                        // series - show a dialog
                        var dialog = $('[data-dialog="bulk-modify"]');
                        dialog.foundation("reveal", "open");
                        setupBulkDialogEvents(
                            dialog,
                            function () {
                                $("#efficacy").val("self");

                                form.submit();
                            },
                            function () {
                                $("#efficacy").val("series");

                                form.submit();
                            },
                        );
                    } else {
                        // single event - just submit
                        form.submit();
                    }
                },
            });
        }
    };
})(jQuery);
