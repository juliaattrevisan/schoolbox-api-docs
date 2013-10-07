/**
 * Event listeners for the course task list.
 */
(function($) {
    $.fn.setupCourseTaskList = function(options) {
        var defaults = {
            session_name: '',
            session_id:   ''
        };
        var settings  = $.extend({}, defaults, options);
        var component = $(this);
        var dialog    = component.find('.file_uploader');
        dialog.ajaxUploadDialog({
            session: {
                name: settings.session_name,
                id:   settings.session_id
            }
        });

        // Post-AJAX form event
        var postCallback = function(form, data) {
            var container = form.closest('.edit_task_form_container, .create_task_form_container, .delete_task_form_container');
            container.slideUp('fast', function() {
                // reload all tasks too.
                reloadTaskList(data);
            });
        }

        // Reloads the task list, setting up drag/drop again too.
        var reloadTaskList = function(data) {
            var new_tasks = $(data.html).find('.task-list');
            component.find('.task-list').replaceWith(new_tasks);
            initTaskList();
        }

        // Initialises the task list, setting up drag/drop as well.
        var initTaskList = function() {
            var task_list = component.find('.task-list');
            task_list.sortable({
                handle: '.icon',
                cursor: 'move'
            });
            task_list.bind('sortstop', function(event, ui) {
                // Calculate ordering of all items
                var form   = component.find('form.reorder_form');
                var params = form.serializeArray();
                task_list.find('.task').each(function(index) {
                    // Switch dark on/off
                    $(this).toggleClass('dark', (index % 2 === 1));
                    params.push({name: 'id[]', value: $(this).data('task_id')});
                });
                $.post(form.attr('action'), params, reloadTaskList, 'json');
            })
        };
        initTaskList();

        // Upload button for link items
        component.find('form a[data-function="upload"]').bind('click.courseTaskList', function(event) {
            event.preventDefault();
            // Set the form that this dialog was triggered from.
            var source_form = $(this).closest('form');
            dialog.ajaxUploadDialog('open', function(output) {
                var result = JSON.parse(output);
                var link   = '/storage/fetch.php?hash='+result.hash+'&save=1';
                source_form.find(':input[name="link"]').val(link);
            });
        });

        // AJAX form submissions
        component.delegate('form.edit_task_form, form.create_task_form', 'submit.courseTaskList', {}, function(event) {
            var form = $(this);
            event.preventDefault();
            // Is this a new form?
            var is_new = form.hasClass("create_task_form");
            $.post(form.attr('action'), form.serialize(), function(data) {
                // Do validation stuff here eventually, but for now validate.
                if (is_new) {
                    form.get(0).reset();
                }
                postCallback(form, data);
            }, 'json');
        });

        // Modify task: if there's a modify form inline, show/hide it.
        // (Delegate because extra elements can be added via AJAX)
        component.delegate('.interact a.modify', 'click.courseTaskList', {}, function(event) {
            // Show the form instead
            var edit_form = $(this).closest('.task').find('.edit_task_form_container');
            if (edit_form.length) {
                event.preventDefault();
                edit_form.slideToggle('fast');
            }
        });

        // Delete task: "are you sure?"
        component.delegate('.interact a.delete, .delete_task_form_container a.delete-reject', 'click.courseTaskList', {}, function(event) {
            event.preventDefault();
            var delete_form = $(this).closest('.task').find('.delete_task_form_container');
            delete_form.slideToggle('fast');
        });

        // "why, yes I am"
        component.delegate('.delete_form', 'submit.courseTaskList', {}, function(event) {
            event.preventDefault();
            var form = $(this);
            $.post(form.attr('action'), form.serialize(), function(data) {
                postCallback(form, data);
            }, 'json');
        });

        // Create new tasks: show inline form if there is one.
        component.find('.add_buttons .btn').bind('click.courseTaskList', function(event) {
            var create_forms = component.find('.create_task_form_container');
            var form_class   = $(this).data('form_class');
            if (form_class) {
                var create_form = create_forms.filter('.'+form_class);
                if (create_form.length) {
                    event.preventDefault();
                    create_forms.not(create_form).slideUp('fast');
                    create_form.slideToggle('fast');
                }
            }
        });
    }
})(jQuery);
