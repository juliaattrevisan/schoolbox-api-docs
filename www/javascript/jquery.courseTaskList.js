/**
 * Event listeners for the course task list.
 */
(function($) {
    $(function() {

        // Slap listeners on the form elements
        var course_component = $('.course_task_listing');

        // Modify task: if there's a modify form inline, show/hide it.
        course_component.delegate('.interact a.modify', 'click', {}, function(event) {
            // Show the form instead
            var edit_form = $(this).closest('.task').find('.edit_task_form_container');
            if (edit_form.length) {
                edit_form.slideToggle('fast');
                event.preventDefault();
            }
        });

        // Delete task: "are you sure?"
        course_component.delegate('.interact a.delete, .delete_task_form_container a.delete-reject', 'click', {}, function() {
            var delete_form = $(this).closest('.task').find('.delete_task_form_container');
            delete_form.slideToggle('fast');
            event.preventDefault();
        });

        // Create new tasks: show inline form if there is one.
        course_component.delegate('.add_buttons .btn', 'click', {}, function() {
            var create_forms = course_component.closest('[id^="component"]').find('.create_task_form_container');
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

    });
})(jQuery);
