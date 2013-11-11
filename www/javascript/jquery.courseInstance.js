/**
 * Event listeners for the course instance component.
 */
(function($) {
    $.fn.setupCourseInstance = function(options) {
        var defaults = {
            course_homepage: '',
            session_name:    '',
            session_id:      '',
            import_base_url: ''
        };
        var settings  = $.extend({}, defaults, options);
        var component = $(this);

        var browser   = $('#resource-browser');
        browser.dialog({
            width: 650,
            autoOpen: false,
            title: "Select a homepage",
            modal: true,
            resizable: false
        });

        var dialog = component.find('.file_uploader');
        dialog.ajaxUploadDialog({
            session: {
                name: settings.session_name,
                id:   settings.session_id
            }
        });

        // Browser options: now based on component
        var component_id    = component.attr('id');
        var browser_options = {
            destination:     '#'+component_id+' input[name="homepage"]',
            base:            settings.course_homepage,
            start:           settings.course_homepage,
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

        // Event for opening the import page - tied to change event of input
        // that gets changed by resource selector, instead of a button.
        component.delegate(
            'input[name="homepage"]',
            'change.courseInstance',
            function() {
                var homepage_id = this.value.replace(/.*?(\d+$)/, '$1')
                // Strip trailing / off of the import base URL
                var base_href   = settings.import_base_url.replace(/\/*$/, '');
                window.location.href = base_href+'/'+homepage_id;
            }
        );

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
                    //postCallback(form, data);
                    //container.slideToggle('fast');
                    var unit = form.closest('.unit');
                    unit.slideUp(
                        'fast', function() {
                            unit.remove();
                        }
                    );
                }, 'json');
            }
        );

        // "No, I am not"
        component.delegate(
            'form[data-form_type="delete_unit"] [data-interaction="delete_reject"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                var container = $(this).closest('[data-interaction="delete_unit_container"]');
                container.slideToggle('fast');
            }
        );

        // Delete task - are you sure?
        component.delegate(
            'a[data-interaction="open_delete_task_form"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                var element      = $(this);
                var unit_element = element.closest('.unit-task');
                var container    = unit_element.find('[data-interaction="delete_task_container"]');
                container.slideToggle('fast');
            }
        );
        // Yes
        component.delegate(
            'form[data-form_type="delete_task"]',
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
        // No
        component.delegate(
            'form[data-form_type="delete_task"] [data-interaction="delete_reject"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                var container = $(this).closest('[data-interaction="delete_task_container"]');
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

        // show/hide the edit task dialog (if it exists?)
        component.delegate(
            'a[data-interaction="open_edit_task_form"]',
            'click.courseInstance',
            function(event) {
                var element      = $(this);
                var unit_element = element.closest('.unit-task');
                var container    = unit_element.find('[data-interaction="edit_task_container"]');
                if (container.length) {
                    event.preventDefault();
                    container.slideToggle('fast');
                }
            }
        );

        // Create/edit task form submission
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

        // Create/edit task upload file
        component.delegate(
            'form a[data-function="upload"]',
            'click.courseInstance',
            function(event) {
                event.preventDefault();
                // Set the form that this dialog was triggered from.
                var source_form = $(this).closest('form');
                dialog.ajaxUploadDialog('open', function(output) {
                    var result = JSON.parse(output);
                    var link   = '/storage/fetch.php?hash='+result.hash+'&save=1';
                    source_form.find(':input[name="link"]').val(link);
                });
            }
        );

        // Set up the same resource browser as is used in the social stream.
        var socialstream_browser = component.find('.resource_browser');
        if (socialstream_browser.length && $.fn.socialstreamBrowser) {
            component.delegate('form a[data-function="browse"]', 'click.courseTaskList', {}, function(event) {
                var form = $(this).closest('form');
                event.preventDefault();
                socialstream_browser.socialstreamBrowser({
                    submitCallback: function(url) {
                        form.find('input[name="link"]').val(url);
                    }
                });
            });
        }

        /**
         * Callback that gets called when the list of tasks changes - it reloads
         * the contents of the component.
         *
         * @param form The form that was submitted
         * @param data Object returned by JSON call. The "html" key contains
         *             the contents of this component.
         */
        var postCallback = function(form, data) {
            var container = form.closest('.form_container');
            container.slideUp('fast', function() {
                component.find('.box > .information-list').replaceWith(
                    $(data.html).find('.box > .information-list')
                );
            });
        }
    }
})(jQuery);
