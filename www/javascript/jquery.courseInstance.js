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
        var browser_options = {
            destination:     '#component{COMPONENT_INSTANCE_ID} input[name="homepage"]',
            base:            '{COURSE_HOMEPAGE}',
            start:           '{COURSE_HOMEPAGE}',
            triggerChange:   true,
            destinationText: '#component{COMPONENT_INSTANCE_ID} span[data-resource_text]'
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
                var source = $j('#component{COMPONENT_INSTANCE_ID} input[name="homepage"]').val();
                source = source.replace(/.*?(\d+$)/, '$1')
                window.location.href = '/course/import/{ID}/{COMPONENT_INSTANCE_ID}/'+source
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

        // Create new tasks: show inline form if there is one.
        component.delegate(
            '.add_buttons .btn',
            'click.courseInstance',
            function(event) {
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
            }
        );
    }
})(jQuery);
