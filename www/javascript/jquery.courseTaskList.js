/**
 * Event listeners for the course task list.
 */
(function($) {
    $.fn.setupCourseTaskList = function(options) {
        var defaults = {
            session_name: '',
            session_id:   '',
            units:        [],
            previewSelector: '[data-plugin=preview]'
        };
        var settings  = $.extend({}, defaults, options);
        var component = $(this);
        var fileDialog = component.find('.file_uploader');
        fileDialog.ajaxUploadDialog({
            session: {
                name: settings.session_name,
                id:   settings.session_id
            }
        });

        var copyTaskDialog = component.find('.dialog.copy_new_task');
        copyTaskDialog.dialog({
            autoOpen: false,
            modal: true
        });

        /**
         * Submit save task callback.
         * @param form The form element to save (wrapped in a jQuery object)
         * @param copy Is this new task copied across to all classes which use
         *             this unit?
         */
        var submitSaveTaskForm = function(form, copy) {
            var formData = form.serializeArray()
            // Booleans in data get passed as string 'true' or 'false', wtf?
            formData.push({name: 'copy', value: (copy ? 1 : 0)})
            $.post(form.attr('action'), formData, function(data) {
                if (form.hasClass("create_task_form")) {
                    form.get(0).reset();
                }
                postCallback(form, data);
            }, 'json');
        }

        // Post-AJAX form event
        var postCallback = function(form, data) {
            var container = form.closest('.edit_task_form_container, .create_task_form_container, .delete_task_form_container');
            container.slideUp('fast', function() {
                // reload all tasks too.
                reloadTaskList(data);

                form.find('.post-attachment.attach').find('p.actions a.delete').each(function() {
                    var event = new CustomEvent('click');
                    this.dispatchEvent(event);
                });
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
				placeholder: "sortable-placeholder",
				forcePlaceholderSize: true
            });
            task_list.bind('sortstop', function(event, ui) {
                // Calculate ordering of all items
                var form   = component.find('form.reorder_form');
                var params = form.serializeArray();
                task_list.find('.task').each(function(index) {
                    var taskId = $(this).data('task_id');
                    if (taskId) {
                        // Switch dark on/off
                        $(this).toggleClass('dark', (index % 2 === 1));
                        params.push({name: 'id[]', value: $(this).data('task_id')});
                    }
                });
                $.post(form.attr('action'), params, reloadTaskList, 'json');
            })

            /* Apply Preview to all anchor links in the task-list for
             * this component. This is used when a new task is dynamically
             * added via ajax - this applies the preview plugin when
             * the ajax occurs
             *
             * Pass it in from the course taskList plugin instantiation
             */
            console.log(settings.previewSelector);
            component.find(settings.previewSelector).preview();
        };
        initTaskList();

		/* Link button which shows the Link input field
		 * Hide the input
		 * Hide the label
		 */
		$('#link-new_task').hide();
		$('form a[data-function="link"]').closest('dd').prev('dt').hide();

		component.find('form a[data-function="link"]').bind('click.courseTaskList', function(event) {
			event.preventDefault();

			/* Toggle the input
			 * Toggle the label
			 */
			$('#link-new_task').show();
			$(this).closest('dd').prev('dt').show();
		});

        // Upload button for link items
        component.find('form a[data-function="upload"]').bind('click.courseTaskList', function(event) {
            event.preventDefault();

			/* Toggle the input
			 * Toggle the label
			 */
			$('#link-new_task').show();
			$(this).closest('dd').prev('dt').show();
			
            // Set the form that this dialog was triggered from.
            var source_form = $(this).closest('form');
            fileDialog.ajaxUploadDialog('open', function(output) {
                var result = JSON.parse(output);
                var link   = '/storage/fetch.php?hash='+result.hash+'&save=1';
                source_form.find(':input[name="link"]').val(link);
            });
        });

        // Set up the same resource browser as is used in the social stream.
        var socialstream_browser = component.find('.resource_browser');
        if (socialstream_browser.length && $.fn.socialstreamBrowser) {
            component.on('click.courseTaskList', 'form a[data-function="browse"]', {}, function(event) {

				/* Toggle the input
				 * Toggle the label
				 */
				$('#link-new_task').hide();
				$(this).closest('dd').prev('dt').hide();

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
            'input[name="title"]',
            function (event) {
                var title = this.value.replace(/^\s+|\s+$/g, '');
                $(this).closest('form').find(':submit').prop('disabled', title.length === 0);
            }
        );

        // AJAX form submissions
        // Only show the dialog if there's a class using this unit.
        component.on('submit.courseTaskList', 'form.edit_task_form, form.create_task_form', {}, function(event) {
            var form = $(this);
            event.preventDefault();
            if (form.hasClass("create_task_form") && settings.units.length > 0) {
                copyTaskDialog
                    .dialog(
                        'option', 'buttons',
                        [{
                            text: "Yes",
                            click: function() {
                                form.trigger('submitCopy.courseTaskList');
                                copyTaskDialog.dialog('close');
                            }
                        }, {
                            text: "No",
                            click: function() {
                                form.trigger('submitNoCopy.courseTaskList');
                                copyTaskDialog.dialog('close');
                            }
                        }]
                    )
                    .dialog('open');
            }
            else {
                form.trigger('submitNoCopy.courseTaskList');
            }
        });

        // Modify task: if there's a modify form inline, show/hide it.
        // (Delegate because extra elements can be added via AJAX)
        component.on('click.courseTaskList', '.actions a.modify', {}, function(event) {
            // Show the form instead
            var edit_form = $(this).closest('.task').find('.edit_task_form_container');
            if (edit_form.length) {
                event.preventDefault();
                edit_form.slideToggle('fast');
            }
        });

        // Delete task: "are you sure?"
        component.on('click.courseTaskList', 'a[data-interaction="open_delete_task_form"], .delete_task_form_container a.delete-reject', {}, function(event) {
            event.preventDefault();
            var delete_form = $(this).closest('.task').find('.delete_task_form_container');
            delete_form.slideToggle('fast');
        });

        // "why, yes I am"
        component.on('submit.courseTaskList', '.delete_form', {}, function(event) {
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

        // Event - save new item, no copy
        component.on('submitNoCopy.courseTaskList', 'form.edit_task_form, form.create_task_form', {}, function(event) {
            submitSaveTaskForm($(this), false)
        })

        // Event - save new item, copy it across
        component.on('submitCopy.courseTaskList', 'form.edit_task_form, form.create_task_form', {}, function(event) {
            submitSaveTaskForm($(this), true)
        })

    }
})(jQuery);
