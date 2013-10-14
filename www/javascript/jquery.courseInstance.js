/**
 * Event listeners for the course instance component.
 */
(function($) {
    $.fn.setupCourseInstance = function(options) {

        var component = $(this);
        var browser   = $('#resource-browser');
        browser.dialog({
            width: 650,
            autoOpen: false,
            title: "Select a homepage",
            modal: true,
            resizable: false
        });

        // Browser options: now based on component
        var component_id    = component.attr('id');
        var browser_options = {
            destination:     '#'+component_id+' input[name="homepage"]',
            base:            options.course_homepage,
            start:           options.course_homepage,
            triggerChange:   true,
            destinationText: '#'+component_id+' span[data-resource_text]'
        }

        // Event for displaying the homepage selector.
        component.delegate(
            'a[data-interaction="homepage_selector"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                browser.load(
                    '/ajax/resourceBrowser',
                    browser_options,
                    function() { browser.dialog('open'); }
                );
            }
        );

        // Event for opening the import page.
        component.delegate(
            'a[data-interaction="import"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                var source = component.find('input[name="homepage"]').val().replace(/.*?(\d+$)/, '$1')
                // What's the last char of the href? Add a / if there isn't one.
                var base_href = $(this).attr('href').replace(/\/*$/, '');
                window.location.href = base_href+'/'+source
            }
        )

        // Expand/collapse of units
        component.delegate(
            'a[data-interaction="toggle-expanded"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                var element = $(this);
                var is_collapsed = element.data('interaction-state') == "collapsed";
                var unit_element = element.closest('.unit');
                var task_list    = unit_element.find('.task-list');
                if (is_collapsed) {
                    unit_element.removeClass('collapsed').addClass('expanded');
                    task_list.slideDown('fast')
                    element.data('interaction-state', 'expanded');
                }
                else {
                    unit_element.removeClass('expanded').addClass('collapsed');
                    task_list.slideUp('fast')
                    element.data('interaction-state', 'collapsed');
                }
            }
        );

        // Delete unit - "are you sure?"
        // (write the expand/collapse stuff into its own plugin)
        component.delegate(
            'a[data-interaction="open_delete_unit_form"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                var element      = $(this);
                var unit_element = element.closest('.unit');
                var container    = unit_element.find('[data-interaction="delete_unit_container"]');
                container.slideToggle('fast');
            }
        );

        // "Yes, I am"
        component.delegate(
            'form[data-form_type="delete_unit"]',
            'submit.courseInstance',
            function(event) {
                event.preventDefault();
                // Submit form via Ajax
                var form = $(this);
                $.post(form.attr('action'), form.serialize(), function(data) {
                    postCallback(form, data);
                }, 'json');
            }
        );

        // "No, I am not"
        component.delegate(
            'form[data-form_type="delete_unit"] [data-interaction="delete_reject"]',
            'submit.courseInstance',
            function(event) {
                event.preventDefault();
                var container = $(this).closest('[data-interaction="delete_unit_container"]');
                container.slideToggle('fast');
            }
        );

        // Create new tasks: show inline form if there is one.
        component.delegate(
            '.add_buttons .btn',
            'click.courseInstance',
            function(event) {
                var create_forms = $(this).closest('.unit').find('.form_container');
                var form_class   = $(this).data('form_class');
                if (form_class) {
                    var create_form = create_forms.filter('.'+form_class);
                    if (create_form.length) {
                        event.preventDefault();
                        create_forms.not(create_form).slideUp('fast');
                        create_form.slideToggle('fast');
                    }
                }
            }
        );

        // Create task form submission
        component.delegate(
            'form[data-form_type="task"]',
            'submit.courseInstance',
            function(event) {
                var form = $(this);
                event.preventDefault();
                // Is this a new form?
                var is_new = !(form.find('input[name="id"]').val());
                $.post(form.attr('action'), form.serialize(), function(data) {
                    // Do validation stuff here eventually, but for now validate.
                    if (is_new) {
                        form.get(0).reset();
                    }
                    postCallback(form, data);
                }, 'json');
            }
        );

        var postCallback = function(form, data) {
            var container = form.closest('.form_container');
            container.slideUp('fast', function() {
//                var new_tasks = $(data.html).find('.information-list');
                component.find('.information-list').replaceWith($(data.html).find('.information-list'));
//                reloadTaskList(data)
            });
        }

        // Reloads the task list, setting up drag/drop again too.
        var reloadTaskList = function(data) {
            var new_tasks = $(data.html).find('.information-list');
            component.find('.information-list').replaceWith(new_tasks);
//            initTaskList();
        }
    }
})(jQuery);
