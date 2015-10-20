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

        component.on('click.courseInstance', 'a[data-interaction=homepage_selector]', function (event) {
            event.preventDefault();
            browser.socialstreamBrowser({
                title: 'Select a course',
                categoryId: settings.course_homepage,
                isolate: true,
                mimeFilter: false,
                submitCallback: function(url, filename) {
                    $(destination)
                        .val(url.match(/[0-9]+/)[0])
                        .trigger('change.courseInstance');
                }
            });
        });

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
            'a[data-interaction="open_delete_unit_form"]',
            function(event) {
                event.preventDefault();
                var element = $(this);
                element.confirmDialog({
                    text: 'Are you sure you wish to delete this unit? All tasks, linked due work and quizzes within the unit will be deleted.',
                    confirmEvent: function(element) {
                        var unit_element = element.closest('[data-unit]');
                        var form         = unit_element.find('[data-form_type="delete_unit"]');
                        $.post(form.attr('action'), form.serialize(), function(data) {
                            postCallback(form, data);
                        }, 'json');
                    }
                });
            }
        );

        // Delete task - are you sure?
        component.on(
            'click.courseInstance',
            'a[data-interaction="open_delete_task_form"]',
            function(event) {
                event.preventDefault();
                var element = $(this);
                element.confirmDialog({
                    text: 'Are you sure you wish to delete this task? Any linked due work items and quizzes will be deleted, as will all student\'s completion of this task.',
                    confirmEvent: function(element) {
                        var unit_element = element.closest('[data-unit-task]');
                        var form         = unit_element.find('[data-form_type="delete_task"]');
                        $.post(form.attr('action'), form.serialize(), function(data) {
                            postCallback(form, data);
                        }, 'json');
                    }
                });
            }
        );

        // Create new tasks: show inline form if there is one.
        component.on(
            'click.courseInstance',
            '[data-add-button][data-form_class="for-task"]',
            function(event) {
                event.preventDefault();
                var thisCreateForm = $(this).closest('[data-unit]').find('[data-new-task-container]');

                thisCreateForm.toggleClass('hide');
                component.find('[data-form-container]').not(thisCreateForm).addClass('hide');
                ///* Hide all other forms first
                // */
                //var allForms = component.find('[data-form-container]');
                //allForms.removeClass('hide').not(thisCreateForm).slideUp('fast');
                //
                //if (thisCreateForm.hasClass('hide')) {
                //    thisCreateForm.stop().removeClass('hide').slideUp(1).slideDown('fast');
                //}
                //else {
                //    thisCreateForm.stop().slideToggle('fast');
                //}
            }
        );

        // show/hide the edit task dialog (if it exists?)
        component.on(
            'click.courseInstance',
            'a[data-interaction="open_edit_task_form"]',
            function(event) {
                var element      = $(this);
                var unit_element = element.closest('[data-unit-task]');
                var container    = unit_element.find('[data-interaction="edit_task_container"]');

                /* Hide all other forms first
                 */
                //var allForms = component.find('[data-form-container]');
                //allForms.removeClass('hide').not(container).slideUp('fast');

                if (container.length) {
                    event.preventDefault();
                    container.toggleClass('hide');
                    component.find('[data-form-container]').not(container).addClass('hide');

                    //if (container.hasClass('hide')) {
                    //    container.stop().removeClass('hide').slideUp(1).slideDown('fast');
                    //}
                    //else {
                    //    container.stop().slideToggle('fast');
                    //}
                }
            }
        );

        // Create/edit task form submission
        component.on(
            'submit.courseInstance',
            'form[data-form_type="task"]',
            function(event) {
                var form = $(this);
                event.preventDefault();

                var label = form.find('[data-create-task-indicator]');

                label.labelActivate();

                // Is this a new form?
                var is_new = !(form.find('input[name="id"]').val());

                $(this).find("input[type=submit]").attr('disabled', true);
                
                $.post(form.attr('action'), form.serialize(), function(data) {
                    label.labelTimeout({
                        text: 'Saved',
                        css: 'success'
                    });

                    // Do validation stuff here eventually.
                    if (is_new) {
                        form.get(0).reset();
                    }

                    postCallback(form, data);
                }, 'json');
            }
        );

        // Set up the same resource browser as is used in the social stream.
        var socialstream_browser = component.find('.resource_browser');
        if (socialstream_browser.length && $.fn.socialstreamBrowser) {
            component.on('click.courseTaskList','form a[data-function="browse"]' , {}, function(event) {
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
            var container = form.closest('[data-form-container]');
            container.addClass('hide');
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
            task_list.bind('sortstop', function(event, ui) {
                // Calculate ordering of all items
                var form = component.find('form.reorder_form');
                var unit = ui.item.closest('[data-unit]');
                form.find('input[name="unit_id"]').val(unit.data('unit_id'))
                var params = form.serializeArray();
                unit.find('[data-unit-task]').each(function() {
                    // Switch dark on/off
                    params.push({name: 'id[]', value: $(this).data('unit_task_id')});
                });
                $.post(form.attr('action'), params, reloadTaskList, 'json');
            })
        };
        initTaskList();
    }
})(jQuery);
