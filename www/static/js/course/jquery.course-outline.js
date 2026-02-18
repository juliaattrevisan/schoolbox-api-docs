/**
 * Event listeners for the course instance component.
 */
(function ($) {
    $.fn.setupCourseOutline = function (options) {
        var defaults = {
            readonly: false,
            previewSelector: "[data-plugin=preview]",
        };
        var settings = $.extend({}, defaults, options);
        var component = $(this);

        // Expand/collapse of units
        component.on("click.courseInstance", 'a[data-interaction="toggle-expanded"]', function (event) {
            event.preventDefault();
            var element = $(this);
            var is_collapsed = element.data("interaction-state") == "collapsed";
            var unit_element = element.closest("[data-unit]");
            var task_list = unit_element.find("[data-task-list]");
            if (is_collapsed) {
                unit_element.removeClass("collapsed").addClass("expanded");
                task_list.slideDown("fast");
                element.data("interaction-state", "expanded");
            } else {
                unit_element.removeClass("expanded").addClass("collapsed");
                task_list.slideUp("fast");
                element.data("interaction-state", "collapsed");
            }
        });

        // Task editor - prevent empty inputs
        component.on("change.courseInstance, keyup.courseInstance", 'input[name="title"]', function (event) {
            var title = this.value.replace(/^\s+|\s+$/g, "");
            $(this)
                .closest("form")
                .find(":submit")
                .prop("disabled", title.length === 0);
        });

        // Reloads the task list, setting up drag/drop again too.
        var reloadTaskList = function (data) {
            var unit_list = $(data.html).find("[data-unit]");
            // Multiple task lists exist, inject the task list for each unit in
            // data into the matching task list on the page.
            component.find("[data-unit]").each(function () {
                var element = $(this);
                var unit_id = element.data("unit_id");
                if (unit_id) {
                    var replacement = unit_list.filter(function () {
                        return $(this).data("unit_id") === unit_id;
                    });
                    if (replacement.length) {
                        element.replaceWith(replacement);
                    }
                }
            });
            initTaskList();
        };

        var initTaskList = function () {
            /* Apply Preview to all anchor links in the task-list for
             * this component. This is used when a new task is dynamically
             * added via ajax - this applies the preview plugin when
             * the ajax occurs
             *
             * Pass it in from the course instance plugin instantiation
             */
            component.find(settings.previewSelector).preview();

            if (settings.readonly) {
                return;
            }
            var task_list = component.find("[data-task-list]");
            task_list.sortable({
                placeholder: "sortable-placeholder",
                forcePlaceholderSize: true,
                handle: ".grip",
                axis: "y",
            });
            task_list.sortable({
                placeholder: "sortable-placeholder",
                forcePlaceholderSize: true,
                handle: ".grip",
                axis: "y",
                helper: "clone",
                start: function (event, ui) {
                    // When the course outline component is filtered, sorting is not possible without seeing the hidden items
                    // this reveals those hidden items, and provides a placeholder for where the active sort item came from
                    task_list.find("[data-sortable-hidden]").each(function () {
                        $(this).attr("data-sortable-hidden", false);
                    });
                    ui.item.show();
                    ui.item.attr("data-sortable-active", true);
                    $("[data-sortable-hidden]").slideDown({
                        complete: function () {
                            task_list.sortable("refreshPositions");
                        },
                    });
                },
                stop: function (event, ui) {
                    task_list.find("[data-sortable-hidden]").each(function () {
                        $(this).attr("data-sortable-hidden", true);
                    });
                    ui.item.removeAttr("data-sortable-active");
                    $("[data-sortable-hidden]").slideUp();
                },
            });
            task_list.off("sortstop.courseInstance").on("sortstop.courseInstance", function (event, ui) {
                // Calculate ordering of all items
                var unit = ui.item.closest("[data-unit]");
                var form = unit.find("form.reorder_form");
                var params = form.serializeArray();
                unit.find("[data-assessment]").each(function () {
                    // Switch dark on/off
                    params.push({ name: "id[]", value: $(this).data("assessment") });
                });
                $.post(form.attr("action"), params, reloadTaskList, "json");
            });
        };
        initTaskList();
    };
})(jQuery);
