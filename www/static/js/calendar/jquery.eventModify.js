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
        setupEventValidation();

        /* Show/hide repeat event options (after validation)
         */
        setupRepeat();

        setupDeleteActions(config.deleteButton);

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

            var merged = $.extend(defaults, derived, options);

            return merged;
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
                            $.post(deleteButton.attr("href"), {
                                id: $("input[name=id]").val(),
                                efficacy: "self",
                            }).done(function (response) {
                                window.location.href = response.callbackUrl;
                            });
                        },
                        function () {
                            // Add series to href
                            $.post(deleteButton.attr("href"), {
                                id: $("input[name=id]").val(),
                                efficacy: "series",
                            }).done(function (response) {
                                window.location.href = response.callbackUrl;
                            });
                        },
                    );
                });
            } else {
                deleteButton.on("click", function (event) {
                    event.preventDefault();
                    deleteButton.confirmDialog({
                        text: "Are you sure you want to delete this event?",
                        confirmLabel: "Delete",
                        confirmEvent: function () {
                            $.post(deleteButton.attr("href"), {
                                id: $("input[name=id]").val(),
                            }).done(function (response) {
                                window.location.href = response.callbackUrl;
                            });
                        },
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
         * Validate form data
         */
        function setupEventValidation() {
            // Campus selection validation - only required if eventType === Campus
            var validateCampusSelected = function () {
                if ($('select[name="level"]').length) {
                    return $('select[name="level"]').val() == 3;
                }

                return false;
            };

            /* Check if a Group is selected when using the Group
             * level option in the dropdown
             */
            var validateFolderSelected = function () {
                if ($('select[name="level"]').length) {
                    return parseInt($("select[name=level]").val()) == 2;
                }

                return false;
            };

            /* check if repeating the event
             * to confirm the repeat till date is correctly formatted
             * the easiest way, check if repeating is set to `single event=''`
             */
            var isRepeating = function () {
                if ($('select[name="repeat"]').length) {
                    return "" !== $("select[name=repeat]").val();
                }
                return false;
            };

            $(config.element).alaressValidate({
                rules: {
                    "campuses[]": {
                        required: {
                            depends: validateCampusSelected,
                        },
                        minlength: {
                            param: 1,
                            depends: validateCampusSelected,
                        },
                    },
                    "roles[]": {
                        required: {
                            depends: validateCampusSelected,
                        },
                        minlength: {
                            param: 1,
                            depends: validateCampusSelected,
                        },
                    },
                    "folders[]": {
                        required: {
                            depends: validateFolderSelected,
                        },
                        minlength: {
                            param: 1,
                            depends: validateFolderSelected,
                        },
                    },
                    startDate: {
                        required: true,
                        datepickerDate: true,
                    },
                    endDate: {
                        required: true,
                        datepickerDate: true,
                    },
                    repeat_until: {
                        datepickerDate: {
                            param: true,
                            depends: isRepeating,
                        },
                    },
                    publishDate: {
                        datepickerDate: true,
                    },
                },

                /**
                 * Executes when form is valid
                 *
                 * Note: if you have a non-visible input field with the "required" attribute assigned
                 * (such as an input element inside a div with display:none) the submitHandler doesn't
                 * fire at all, and silently does nothing. Remove the "required" attribute in accordance
                 * with the element's visibility, in those circumstances; or just make it optional
                 *
                 * @param form
                 */
                submitHandler: function (form) {
                    // Find the submit button for this form and disable it,
                    // to avoid accidental multiple requests
                    $("#calendar-modify-submit").prop("disabled", true);

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
