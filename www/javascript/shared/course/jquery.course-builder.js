/**
 * Event listeners for the course task list.
 */
(function($) {
    $.fn.setupCourseBuilder = function(options) {
        var defaults = {
            units:        [],
            previewSelector: '[data-plugin=preview]'
        };
        var settings  = $.extend({}, defaults, options);
        var component = $(this);

        var copyTaskDialog = component.find('[data-interaction="copy_task_dialog"]');

        /**
         * Submit save task callback.
         * @param form The form element to save (wrapped in a jQuery object)
         * @param copy Is this new task copied across to all classes which use
         *             this unit?
         */
        var submitSaveTaskForm = function(form, copy) {
            var formData = form.serializeArray();
            // Booleans in data get passed as string 'true' or 'false', wtf?
            formData.push({name: 'copy', value: (copy ? 1 : 0)});
            $.post(form.attr('action'), formData, function(data) {
                // Clear the create task form
                if (form.data("interaction") === 'create_task') {
                    form.get(0).reset();
                }
                postCallback(form, data);
            }, 'json');
        };

        // Post-AJAX form event
        var postCallback = function(form, data) {
            var container = form.closest('[data-form-container]');
            container.addClass('hide');

            // reload all tasks too.
            reloadTaskList(data);
        };

        // Reloads the task list, setting up drag/drop again too.
        var reloadTaskList = function(data) {
            var new_tasks = $(data.html).find('[data-task_list]');
            component.find('[data-task_list]').replaceWith(new_tasks);
            initTaskList();
        };

        // Initialises the task list, setting up drag/drop as well.
        var initTaskList = function() {
            /* Apply Preview to all anchor links in the task-list for
             * this component. This is used when a new task is dynamically
             * added via ajax - this applies the preview plugin when
             * the ajax occurs
             *
             * Pass it in from the course taskList plugin instantiation
             */
            component.find(settings.previewSelector).preview();
            var task_list = component.find('[data-task_list]');
            task_list.sortable({
                placeholder:          "sortable-placeholder",
                forcePlaceholderSize: true,
                handle:               '.grip',
                axis:                 'y'
            });
            task_list.bind('sortstop', function(event, ui) {
                // Calculate ordering of all items
                var form   = component.find('form[data-interaction="reorder-tasks"]');
                var params = form.serializeArray();
                task_list.find('[data-task_id]').each(function(index) {
                    var taskId = $(this).data('task_id');
                    if (taskId) {
                        // Switch dark on/off
                        //$(this).toggleClass('dark', (index % 2 === 1));
                        params.push({name: 'id[]', value: taskId});
                    }
                });
                $.post(form.attr('action'), params, reloadTaskList, 'json');
            })
        };
        initTaskList();

        // Set up the same resource browser as is used in the social stream.
        var socialstream_browser = component.find('.resource_browser');
        if (socialstream_browser.length && $.fn.socialstreamBrowser) {
            component.on('click.courseTaskList', 'form a[data-function="browse"]', {}, function(event) {
                var form = $(this).closest('form');
                event.preventDefault();
                socialstream_browser.socialstreamBrowser({
                    submitCallback: function(url) {
                        form.find('input[name="link"]').val(url);
                    }
                });
            });
        }

        // Task editor - prevent empty inputs
        component.on(
            'change.courseTaskList, keyup.courseTaskList',
            '[data-new-task-container] input[name="title"]',
            function () {
                var title = this.value.replace(/^\s+|\s+$/g, '');
                $(this).closest('form').find(':submit').prop('disabled', title.length === 0);
            }
        );

        // AJAX form submissions
        // Only show the dialog if there's a class using this unit.
        component.on(
            'submit.courseTaskList',
            'form[data-interaction="create_task"], form[data-interaction="edit_task"]',
            {},
            function(event) {
                var form = $(this);
                event.preventDefault();
                //submitSaveTaskForm($(this), false);
                if (form.data("interaction") == 'create_task' && settings.units.length > 0) {
                    // Rewire copy task events
                    copyTaskDialog.off('click.courseTaskList', '[data-action="copy_task"]');
                    copyTaskDialog.on(
                        'click.courseTaskList',
                        '[data-action="copy_task"]',
                        function(e) {
                            e.preventDefault();
                            copyTaskDialog.foundation('reveal', 'close');
                            form.trigger('submitCopy.courseTaskList');
                        }
                    );
                    copyTaskDialog.off('click.courseTaskList', '[data-action="no_copy_task"]');
                    copyTaskDialog.on(
                        'click.courseTaskList',
                        '[data-action="no_copy_task"]',
                        function(e) {
                            e.preventDefault();
                            copyTaskDialog.foundation('reveal', 'close');
                            form.trigger('submitNoCopy.courseTaskList');
                        }
                    );
                    copyTaskDialog.off('click.courseTaskList', '[data-action="cancel"]');
                    copyTaskDialog.on(
                        'click.courseTaskList',
                        '[data-action="cancel"]',
                        function(e) {
                            e.preventDefault();
                            copyTaskDialog.foundation('reveal', 'close');
                        }
                    );
                    // Open the foundation modal reveal, which asks the question
                    copyTaskDialog.foundation('reveal', 'open');
                } else {
                    form.trigger('submitNoCopy.courseTaskList');
                }
            });

        // Modify task: if there's a modify form inline, show/hide it.
        // (Delegate because extra elements can be added via AJAX)
        component.on(
            'click.courseTaskList',
            'a[data-interaction="open_edit_task_form"]',
            {},
            function(event) {
                // Show the form instead
                var edit_form = $(this).closest('[data-task_id]').find('[data-interaction="edit_task_container"]');
                if (edit_form.length) {
                    event.preventDefault();
                    edit_form.toggleClass('hide');
                    component.find('[data-form-container]').not(edit_form).addClass('hide');
                }
            }
        );

        // Delete task: "are you sure?"
        component.on(
            'click.courseTaskList',
            'a[data-interaction="open_delete_task_form"], a[data-interaction="delete_reject"]',
            {},
            function(event) {
                event.preventDefault();
                var delete_form = $(this).closest('[data-task_id]').find('[data-interaction="delete_task_container"]');
                delete_form.toggleClass('hide');
                component.find('[data-form-container]').not(delete_form).addClass('hide');
            }
        );

        // "why, yes I am"
        component.on(
            'submit.courseTaskList',
            'form[data-interaction="delete_task"]',
            {},
            function(event) {
                event.preventDefault();
                var form = $(this);
                $.post(
                    form.attr('action'),
                    form.serialize(),
                    function(data) {
                        postCallback(form, data);
                    },
                    'json'
                );
            }
        );

        // Create new tasks: show inline form if there is one.
        component.on(
            'click.courseTaskList',
            'a[data-interaction="create_task"]',
                function(event) {
                var create_form = component.find('[data-interaction="create_task_container"]');
                if (create_form.length) {
                    event.preventDefault();
                    create_form.toggleClass('hide');
                    component.find('[data-form-container]').not(create_form).addClass('hide');
                }
            }
        );

        // Event - save new item, no copy
        component.on(
            'submitNoCopy.courseTaskList',
            'form[data-interaction="create_task"], form[data-interaction="edit_task"]',
            {},
            function(event) {
                submitSaveTaskForm($(this), false)
            }
        );

        // Event - save new item, copy it across
        component.on(
            'submitCopy.courseTaskList',
            'form[data-interaction="create_task"], form[data-interaction="edit_task"]',
            {},
            function(event) {
                submitSaveTaskForm($(this), true)
            }
        );

    }
})(jQuery);
