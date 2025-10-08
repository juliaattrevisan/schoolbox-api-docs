/**
 * Event listeners for dropdowns used on some assessment-related components
 */
(function ($) {
    $.fn.setupAssessmentComponentTot = function (options) {
        var defaults = {
            emptySelector: false, // When all tasks are deleted, show this element - false to ignore
            contentSelector: "[data-component-content]", // Content to hide when showing the empty selector
        };
        var settings = $.extend({}, defaults, options);
        var component = $(this);

        // Browser options: now based on component
        var component_id = component.attr("id");

        if (component.attr("initialised")) {
            return;
        }
        component.attr("initialised", true);

        // Delete unit - "are you sure?"
        component.on("click.assessmentTotInstance", 'a[data-action="delete-unit"]', function (event) {
            event.preventDefault();

            var element = $(this);

            element.confirmDialog({
                header: "Are you sure you wish to delete this unit?",
                text: '<span class="alert alert-box"><b>WARNING</b> All activities, student responses, feedback and marks will also be deleted.</span>',
                format: "html",
                confirmLabel: "Yes, permanently delete",
                cancelLabel: "No, keep it",
                confirmEvent: function (element) {
                    var toaster = component.find("[data-alert]");
                    toaster.toastActivate({ text: "Deleting unit..." });

                    /* The list element containing this row
                     */
                    $.ajax(element.attr("href"), {
                        dataType: "json",
                        type: "DELETE",
                    })
                        .done(function () {
                            // Delete this unit from the list
                            element.closest("[data-unit]").remove();

                            toaster.toastTimeout({
                                text: "Unit deleted",
                                css: "pop success",
                            });
                        })
                        .fail(function () {
                            toaster.toastTimeout({
                                text: "Could not delete this unit",
                                css: "pop alert",
                            });
                        });
                },
            });
        });

        // Delete task - are you sure?
        component.on("click.assessmentTotInstance", "[data-action=delete]", function (event) {
            event.preventDefault();
            var $element = $(this);
            var $assessmentRow = $element.closest("[data-unit-assessment]");
            var $taskList = $assessmentRow.closest("[data-task-list]");
            const $customisedAssessments = $element.closest("[data-customised-assessments]");
            const customisationCount = $customisedAssessments.find("[data-unit-assessment]").length;

            $element.confirmDialog({
                header: `Are you sure you wish to delete this ${window.sbProps.l10n.dictionary.activity.singular()}?`,
                text: '<span class="alert alert-box"><b>WARNING</b> All student responses, feedback and marks will also be deleted.</span>',
                format: "html",
                confirmLabel: "Yes, permanently delete",
                cancelLabel: "No, keep it",
                confirmEvent: function () {
                    var toaster = component.find("[data-alert]");
                    toaster.toastActivate({
                        text: `Deleting ${window.sbProps.l10n.dictionary.activity.singular()}...`,
                    });

                    $.post($element.attr("href"))
                        .done(function () {
                            toaster.toastTimeout({
                                text: `${window.sbProps.l10n.dictionary.activity.proper()} deleted.`,
                                css: "pop success",
                            });

                            $assessmentRow.fadeOut({
                                done: function () {
                                    $assessmentRow.remove();

                                    // When all activities are deleted, show the empty selector
                                    if (settings.emptySelector) {
                                        if ($taskList.find("[data-unit-assessment]").length === 0) {
                                            component.find(settings.emptySelector).removeAttr("hidden");
                                            component.find(settings.contentSelector).attr("hidden", true);
                                        }
                                    }

                                    /**
                                     * if the activity being removed is a customisation,
                                     * and it's the last customisation within the parent activity
                                     */
                                    if ($customisedAssessments.length !== 0 && customisationCount === 1) {
                                        // remove the expand/collapse as there's nothing left
                                        $customisedAssessments
                                            .parent()
                                            .find("[data-interaction-has-customised]")
                                            .remove();
                                    }
                                },
                            });
                        })
                        .fail(function () {
                            toaster.toastTimeout({
                                text: `Could not delete this ${window.sbProps.l10n.dictionary.activity.singular()}.`,
                                css: "pop alert",
                            });
                        });
                },
            });
        });

        // Save to course actions
        $(document).on(
            "click.assessmentTotInstance",
            '[data-related-component="' + component_id + '"][data-action=save-to-course-cancel]',
            function (event) {
                event.preventDefault();
                let $element = $(this),
                    $modal = $element.closest("[data-reveal]");
                $modal.foundation("reveal", "close");
            },
        );
        $(document).on(
            "click.assessmentTotInstance",
            '[data-related-component="' + component_id + '"][data-action=save-to-course]',
            function (event) {
                event.preventDefault();

                let $element = $(this),
                    $modal = $element.closest("[data-reveal]"),
                    $toaster = component.find("[data-alert]"),
                    ajaxParams = {
                        url: $element.attr("href"),
                        method: $element.attr("data-method"),
                    };
                $modal.foundation("reveal", "close");
                $toaster.toastActivate({ text: "Writing assessment to course..." });
                $.ajax(ajaxParams)
                    .done(function (data) {
                        $toaster.toastTimeout({
                            text: "Assessment written to course.",
                            css: "pop success",
                        });

                        // After this item is updated even once:
                        // * method becomes PUT
                        // * title is updated in modal
                        // * show the spec exists modal variant
                        // ** show the spec not deleted variant within
                        // ** hide the spec deleted variant within
                        // * hide the spec missing modal variant
                        $element.attr("data-method", "PUT");
                        $modal.find("[data-spec-assessment-title]").text(data.title);
                        $modal.find("[data-display-if-spec-assessment-exists]").removeClass("hide");
                        $modal.find("[data-display-if-spec-assessment-not-exists]").addClass("hide");
                        $modal.find("[data-display-if-spec-assessment-deleted]").addClass("hide");
                        $modal.find("[data-display-if-spec-assessment-not-deleted]").removeClass("hide");
                    })
                    .fail(function () {
                        $toaster.toastTimeout({
                            text: "Could not write assessment to course.",
                            css: "pop alert",
                        });
                    });
            },
        );
    };
})(jQuery);
