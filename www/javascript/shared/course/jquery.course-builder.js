/**
 * Event listeners for the course task list.
 */
(function($) {
    /**
     * Execute this plugin against a UnitSpec's DOM element
     *
     * @param options
     * @var int[] options['unitsImplementationsIds']     IDs of UnitImpls imported from this UnitSpec
     * @todo, this should really be executed once against a Course, and use delegation to select a Unit
     */
    $.fn.setupCourseBuilder = function(options) {
        var defaults = {
            unitsImplementationsIds: []
        };
        var settings       = $.extend({}, defaults, options);
        var unitSpec       = $(this);
        var unitSpecId     = unitSpec.attr('data-unit-spec');

        // Initialises the task list, setting up drag/drop as well.
        var initTaskList = function() {

            var assessmentList = unitSpec.find('[data-assessment-list]');
            assessmentList.sortable({
                placeholder:          "sortable-placeholder",
                forcePlaceholderSize: true,
                handle:               '.grip',
                axis:                 'y'
            });
            assessmentList.bind('sortstop', function(event, ui) {
                var sequence = [];
                console.log(event);
                console.log(ui);
                assessmentList.find('[data-assessment]').each(function(idx, elem){
                    sequence.push($(elem).attr('data-assessment'));
                });
                var data = {
                    sequence:   sequence
                };
                $.post(assessmentList.attr('data-action'), data);
            })
        };
        initTaskList();



        // AJAX form submissions
        // Only show the dialog if there's a class using this unit.
        $(document).on(
            'submit.courseTaskList',
            'form[data-form_type="task"][data-component="'+unitSpecId+'"]',
            function(event) {
                var form = $(this);
                event.preventDefault();
                // Show the dialog for asking whether to copy this task to schools
                // (This only functions for new tasks)
                var isNew = !(form.find('input[name="id"]').val());
                if (isNew && settings.unitsImplementationsIds.length > 0) {
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

        // Delete task: "are you sure?"
        unitSpec.on('click.courseAssessmentList', '[data-action=delete-assessment]', function(event) {
            event.preventDefault();
            var $element = $(this);
            var $assessmentRow = $element.closest('[data-assessment]');
            $element.confirmDialog({
                text: 'Are you sure you wish to delete this activity? You will be unable to import this activity to classes.',
                confirmEvent: function () {
                    $.post($element.attr('href'), {}, function () {
                        $assessmentRow.fadeOut({
                            done: function(){
                                // If no Assessments remaining, display empty-state
                                var $unit = $assessmentRow.closest('[data-unit-spec]');

                                $assessmentRow.remove();

                                if(0 == $unit.find('[data-assessment]').length){
                                    $unit.find('[data-empty-state]').removeClass('hide');
                                }
                            }
                        });
                    });
                }
            })
        });

        // Event - save new item, no copy
        $(document).on(
            'submitNoCopy.courseTaskList',
            'form[data-form_type="task"][data-component="'+unitSpecId+'"]',
            function(event) {
                submitSaveTaskForm($(this), false)
            }
        );

        // Event - save new item, copy it across
        $(document).on(
            'submitCopy.courseTaskList',
            'form[data-form_type="task"][data-component="'+unitSpecId+'"]',
            function(event) {
                submitSaveTaskForm($(this), true)
            }
        );

    }
})(jQuery);
