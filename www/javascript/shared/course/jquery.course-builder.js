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
            toastSelector: ''
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
                assessmentList.find('[data-assessment]').each(function(idx, elem){
                    sequence.push($(elem).attr('data-assessment'));
                });
                var data = {
                    id: sequence
                };
                $.post(assessmentList.attr('data-action'), data);
            })
        };
        initTaskList();

        var $toastFeedback = $(settings.toastSelector);

        // Delete task: "are you sure?"
        unitSpec.on('click.courseAssessmentList', '[data-action=delete-assessment]', function(event) {
            event.preventDefault();
            var $element = $(this);
            var $assessmentRow = $element.closest('[data-assessment]');
            $element.confirmDialog({
                text: 'Are you sure you wish to delete this activity? You will be unable to import this activity to classes.',
                confirmEvent: function () {

                    $toastFeedback.toastActivate({text: 'Deleting activity...'});

                    $.ajax({
                        method: "POST",
                        url: $element.attr('href'),
                        success: function () {
                            $toastFeedback.toastTimeout({text: 'Activity deleted.', css: 'success'});
                            $assessmentRow.fadeOut({
                                done: function () {
                                    // If no Assessments remaining, display empty-state
                                    var $unit = $assessmentRow.closest('[data-unit-spec]');

                                    $assessmentRow.remove();

                                    if (0 == $unit.find('[data-assessment]').length) {
                                        $unit.find('[data-empty-state]').removeClass('hide');
                                    }
                                }
                            });
                        },
                        error: function(){
                            $toastFeedback.toastTimeout({text: 'Deletion failed!', css: 'alert'});
                        }
                    });
                }
            })
        });
    }
})(jQuery);
