/**
 * Event listeners for the course task list.
 */
(function($) {
    $(function() {

        $.fn.setupCourseTaskList = function(options) {
            $.extend(options, {
                session_name: '',
                session_id:   ''
            })
            var component = $(this);
            var dialog    = component.find('.file_uploader');
            dialog.dialog({
                minWidth: 400,
                modal:    true,
                autoOpen: false
            });
            dialog.find('form').each(function() {
                var form        = $(this);
                var file_upload = form.find(':input[name="upload"]');

                if (file_upload.length) {
                    file_upload.uploadify({
                        'uploader':'/javascript/uploadify/uploadify.swf',
                        'script':'/storage/asyncUpload.php?'+options.session_name+'='+options.session_id,
                        'cancelImg':'/javascript/uploadify/cancel.png',
                        'fileDataName':'upload',
                        'multi':false,
                        'auto':false,
                        'descriptionField':false,
                        'removeCompleted':true,
                        onComplete: function(event, code, file, output) {
                            var result = JSON.parse(output);
                            var link   = '/storage/fetch.php?hash='+result.hash+'&save=1';
                            dialog.data('source_form').find(':input[name="link"]').val(link);
                            dialog.dialog('close');
                        }
                    });
                    // Form submit event - instead of triggering
                    form.submit(function(event) {
                        event.preventDefault();
                        file_upload.uploadifyUpload();
                    });
                }
            });

            // Upload button for link items
            component.find('form[data-form_type="link"] a[data-function="upload"]').bind('click.courseTaskList', function(event) {
                event.preventDefault();
                // Set the form that this dialog was triggered from.
                dialog.data('source_form', $(this).closest('form'));
                dialog.dialog('open');
            });

            // AJAX form submissions
            component.delegate('form.edit_task_form, form.create_task_form', 'submit.courseTaskList', {}, function(event) {
                var form = $(this);
                event.preventDefault();
                // Is this a new form?
                var is_new = form.hasClass("create_task_form");
                $.post(form.attr('action'), form.serialize(), function(data) {
                    // Do validation stuff here
                    if (is_new) {
                        form.get(0).reset();
                    }
                    var container = form.closest('.edit_task_form_container, .create_task_form_container');
                    container.slideUp('fast');
                    // Probably want to reload all tasks too.
                    // Jam the list of tasks into data somewhere
                });
            });

            // Modify task: if there's a modify form inline, show/hide it.
            // (Delegate because extra elements can be added via AJAX)
            component.delegate('.interact a.modify', 'click.courseTaskList', {}, function(event) {
                // Show the form instead
                var edit_form = $(this).closest('.task').find('.edit_task_form_container');
                if (edit_form.length) {
                    edit_form.slideToggle('fast');
                    event.preventDefault();
                }
            });

            // Delete task: "are you sure?"
            component.delegate('.interact a.delete, .delete_task_form_container a.delete-reject', 'click.courseTaskList', {}, function() {
                var delete_form = $(this).closest('.task').find('.delete_task_form_container');
                delete_form.slideToggle('fast');
                event.preventDefault();
            });

            // Create new tasks: show inline form if there is one.
            component.find('.add_buttons .btn').bind('click.courseTaskList', function() {
                var create_forms = component.find('.create_task_form_container');
                var form_class   = $(this).data('form_class');
                if (form_class) {
                    var create_form = create_forms.filter('.'+form_class);
                    if (create_form.length) {
                        create_forms.not(create_form).slideUp('fast');
                        create_form.slideToggle('fast');
                        event.preventDefault();
                    }
                }
            });
        }
    });
})(jQuery);
