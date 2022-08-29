/**
 * Event listeners for the course instance component.
 */
(function($) {
    $.fn.setupCourseOutline = function(options) {
        var defaults = {
            course_homepage: '',
            import_base_url: '',
            readonly:        false,
            previewSelector: '[data-plugin=preview]'
        };
        var settings  = $.extend({}, defaults, options);
        var component = $(this);
        var browser   = $('#resource-browser');

        // Browser options: now based on component
        var component_id = component.attr('id');
        var destination  = '#'+component_id+' input[name="homepage"]';

        // Event for opening the import page - tied to change event of input
        // that gets changed by resource selector, instead of a button.
        component.on(
            'change.courseInstance',
            'input[name="homepage"]',
            function() {
                var homepage_id = this.value.replace(/.*?(\d+$)/, '$1');
                // Strip trailing / off of the import base URL
                var base_href   = settings.import_base_url.replace(/\/*$/, '');
                window.location.href = base_href+'/'+homepage_id;
            }
        );

        // Expand/collapse of units
        component.on(
            'click.courseInstance',
            'a[data-interaction="toggle-expanded"]',
            function(event) {
                event.preventDefault();
                var element = $(this);
                var is_collapsed = element.data('interaction-state') == "collapsed";
                var unit_element = element.closest('[data-unit]');
                var task_list    = unit_element.find('[data-task-list]');
                if (is_collapsed) {
                    unit_element.removeClass('collapsed').addClass('expanded');
                    task_list.slideDown('fast');
                    element.data('interaction-state', 'expanded');
                }
                else {
                    unit_element.removeClass('expanded').addClass('collapsed');
                    task_list.slideUp('fast');
                    element.data('interaction-state', 'collapsed');
                }
            }
        );

        // Task editor - prevent empty inputs
        component.on(
            'change.courseInstance, keyup.courseInstance',
            'input[name="title"]',
            function (event) {
                var title = this.value.replace(/^\s+|\s+$/g, '');
                $(this).closest('form').find(':submit').prop('disabled', title.length === 0);
            }
        );

        // Delete unit - "are you sure?"
        component.on(
            'click.courseInstance',
            'a[data-action="delete-unit"]',
            function(event) {
                event.preventDefault();

                var element = $(this);

                element.confirmDialog({
                    header: 'Are you sure you wish to delete this unit?',
                    text: '<span class=\"alert alert-box\"><b>WARNING</b> All activities, student responses, feedback and marks will also be deleted.</span>',
                    format: 'html',
                    confirmLabel: 'Yes, permanently delete',
                    cancelLabel: 'No, keep it',
                    confirmEvent: function(element) {
                        var toaster = component.find('[data-alert]');
                        toaster.toastActivate({text: 'Deleting unit...'});

                        /* The list element containing this row
                         */
                        $.ajax(element.attr('href'), {
                                headers: {
                                    'X-HTTP-Method-Override': 'DELETE'
                                },
                                dataType: 'json',
                                type: 'POST'
                            })
                            .done(function () {
                                // Delete this unit from the list
                                element.closest('[data-unit]').remove();

                                toaster.toastTimeout({
                                    text: 'Unit deleted',
                                    css: 'pop success'
                                });
                            })
                            .fail(function () {
                                toaster.toastTimeout({
                                    text: 'Could not delete this unit',
                                    css: 'pop alert'
                                });
                            });
                    }
                });
            }
        );

        // Delete task - are you sure?
        component.on('click.courseInstance', '[data-action=delete]', function(event) {
                event.preventDefault();
                var $element = $(this);
                var $assessmentRow = $element.closest('[data-unit-assessment]');

                $element.confirmDialog({
                    header: 'Are you sure you wish to delete this activity?',
                    text: '<span class=\"alert alert-box\"><b>WARNING</b> All student responses, feedback and marks will also be deleted.</span>',
                    format: 'html',
                    confirmLabel: 'Yes, permanently delete',
                    cancelLabel: 'No, keep it',
                    confirmEvent: function() {
                        var toaster = component.find('[data-alert]');
                        toaster.toastActivate({text: 'Deleting activity...'});

                        $.post($element.attr('href'))
                        .done(function() {

                            toaster.toastTimeout({
                                text: 'Activity deleted.',
                                css: 'pop success'
                            });

                            $assessmentRow.fadeOut({
                                done: $assessmentRow.remove
                            });
                        })
                        .fail(function () {
                            toaster.toastTimeout({
                                text: 'Could not delete this activity.',
                                css: 'pop alert'
                            });
                        });
                    }
                });
            }
        );


        // Save to course actions
        $(document).on(
            'click.courseInstance',
            '[data-related-component="'+component_id+'"][data-action=save-to-course-cancel]',
            function (event) {
                event.preventDefault();
                let $element = $(this),
                    $modal   = $element.closest('[data-reveal]');
                $modal.foundation('reveal', 'close');
            }
        );
        $(document).on(
            'click.courseInstance',
            '[data-related-component="'+component_id+'"][data-action=save-to-course]',
            function (event) {
                event.preventDefault();

                let $element   = $(this),
                    $modal     = $element.closest('[data-reveal]'),
                    $toaster   = component.find('[data-alert]'),
                    ajaxParams = {
                        url:    $element.attr('href'),
                        method: $element.attr('data-method'),
                    };

                $modal.foundation('reveal', 'close');
                $toaster.toastActivate({text: 'Writing assessment to course...'});
                $.ajax(ajaxParams)
                    .done(function(data) {
                        $toaster.toastTimeout({
                            text: 'Assessment written to course.',
                            css: 'pop success'
                        });

                        // After this item is updated even once:
                        // * method becomes PUT
                        // * title is updated in modal
                        // * show the spec exists modal variant
                        // ** show the spec not deleted variant within
                        // ** hide the spec deleted variant within
                        // * hide the spec missing modal variant
                        $element.attr('data-method', 'PUT');
                        $modal.find('[data-spec-assessment-title]').text(data.title);
                        $modal.find('[data-display-if-spec-assessment-exists]').removeClass('hide');
                        $modal.find('[data-display-if-spec-assessment-not-exists]').addClass('hide');
                        $modal.find('[data-display-if-spec-assessment-deleted]').addClass('hide');
                        $modal.find('[data-display-if-spec-assessment-not-deleted]').removeClass('hide');
                    })
                    .fail(function () {
                        $toaster.toastTimeout({
                            text: 'Could not write assessment to course.',
                            css: 'pop alert'
                        });
                    });
            }
        );



        // Student updating progress or clicking task name
        component.on(
            'click.courseInstance',
            'a[data-interaction="update_progress"]',
            function(event) {
                event.preventDefault();

                var taskRow = $(this).closest('[data-unit-assessment]');
                var taskId  = taskRow.data('unit_task_id');

                $.ajax({type: 'post', url: this.href, dataType: 'json' })
                    .done(function(data) {
                        switch (data.type) {
                            case 'redirect':
                                window.location.href = data.fragment;
                                break;
                            case 'none':
                            default:
                                // Maybe just reload the component?
                                var component = $(data.fragment);
                                taskRow.empty().append(
                                    component.find('[data-unit-assessment="'+taskId+'"]').children()
                                );
                                // Reapply preview
                                taskRow.find(settings.previewSelector).preview();
                                break;
                        }
                    });
            }
        );

        // Create/edit task form submission
        // Bind to the document and filter on component ID manually,
        // because the form is no longer a child of the component
        $(document).on(
            'submit.courseInstance',
            'form[data-form_type="task"][data-component="'+component_id+'"]',
            function(event) {
                var form = $(this);
                event.preventDefault();

                var label = form.find('[data-create-task-indicator]');

                label.toastActivate();

                // Is this a new form?
                var is_new = !(form.find('input[name="id"]').val());

                $(this).find("input[type=submit]").attr('disabled', true);

                $.post(form.attr('action'), form.serialize(), function(data) {
                    label.toastTimeout({
                        text: 'Saved',
                        css: 'success'
                    });

                    // Do validation stuff here eventually.
                    if (is_new) {
                        form.get(0).reset();
                    }

                    $(document).foundation('reveal', 'close');
                    postCallback(form, data);
                }, 'json');
            }
        );

        /**
         * Callback that gets called when the list of tasks changes - it reloads
         * the contents of the component.
         *
         * @param form The form that was submitted
         * @param data Object returned by JSON call. The "html" key contains
         *             the contents of this component.
         */
        var postCallback = function(form, data) {
            //var container = form.closest('[data-form-container]');
            //container.addClass('hide');
            reloadTaskList(data);
        };

        // Reloads the task list, setting up drag/drop again too.
        var reloadTaskList = function(data) {
            var unit_list = $(data.html).find('[data-unit]');
            // Multiple task lists exist, inject the task list for each unit in
            // data into the matching task list on the page.
            component.find('[data-unit]').each(function() {
                var element = $(this);
                var unit_id = element.data('unit_id');
                if (unit_id) {
                    var replacement = unit_list.filter(function() {
                        return $(this).data('unit_id') === unit_id;
                    });
                    if (replacement.length) {
                        element.replaceWith(replacement)
                    }
                }
            });
            initTaskList();
        };

        var initTaskList = function() {

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
            var task_list = component.find('[data-task-list]');
            task_list.sortable({
                placeholder:          "sortable-placeholder",
                forcePlaceholderSize: true,
                handle:               '.grip',
                axis:                 'y'
            });
            task_list.unbind('sortstop.courseInstance').bind('sortstop.courseInstance', function(event, ui) {
                // Calculate ordering of all items
                var unit = ui.item.closest('[data-unit]');
                var form = unit.find('form.reorder_form');
                var params = form.serializeArray();
                unit.find('[data-unit-assessment]').each(function() {
                    // Switch dark on/off
                    params.push({name: 'id[]', value: $(this).data('unit-assessment')});
                });
                $.post(form.attr('action'), params, reloadTaskList, 'json');
            })
        };
        initTaskList();
    }
})(jQuery);
