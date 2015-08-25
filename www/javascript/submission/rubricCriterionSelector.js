/**
 * jQuery events for the submission rubric criterion selector
 */
(function($) {
    "use strict";
    $.fn.submissionRubricCriterionSelector = function() {
        // Setup
        var element = $(this);
        var form    = element.closest('form');

        /**
         * Do assessment-specific descriptors get shown?
         */
        var isRubricAssessmentSpecificDescriptors = function() {
            return form.find('[data-relation="rubric-use-assessment-specific"]').prop('checked');
        };

        /**
         * Switch descriptors on/off
         *
         * @param action  "show" or "hide"
         * @param element The element to switch
         * @param timeout Currently unused
         */
        var toggleDescriptors = function(action, element, timeout) {
            $(element).find('[data-role="criterion-descriptor"]').toggleClass('hide', (action == 'hide'));
        };

        /**
         * Switch descriptors on/off
         */
        var applyAllDescriptors = function() {
            if (!isRubricAssessmentSpecificDescriptors()) {
                toggleDescriptors('hide', element.find('[data-role="criterion"]'), 100);
            } else {
                element.find('[data-role="criterion"]').each(function () {
                    var checked = $(this).find('[data-role="criterion-selector"]').prop('checked');
                    toggleDescriptors(checked ? 'show' : 'hide', $(this), 100)
                });
            }
        };
        applyAllDescriptors();


        // Events

        /**
         * Change result set type:
         * * if assess-specific descriptors are off, hide individual descriptors
         * * if on, show individual descriptors for selected criteria
         */
        form.on('change', 'input[name="resultset"]', applyAllDescriptors);

        /**
         * Switch on/off a criterion:
         * show its individual descriptors (if assess-specific descriptors are on)
         */
        element.on('change', '[data-role="criterion-selector"]', applyAllDescriptors);

        /**
         * Change event on the rubric descriptor counter:
         * set the number of descriptors to the same as the number of levels selected
         */
        form.on('change', '[data-relation="rubric-descriptor-count"]', function () {
            var count = this.value;

            element.find('[data-role="criterion-descriptors"]').each(function () {
                var i,
                    inputCount = $(this).find('[data-role="criterion-descriptor"]').length,
                    diff       = count - inputCount;

                if (diff < 0) {
                    // Remove trailing descriptors
                    for (i = 0; i < 0 - diff; ++i) {
                        $(this).find('[data-role="criterion-descriptor"]').last().detach();
                    }
                }
                else if (diff > 0) {
                    // Add descriptors to end
                    for (i = 0; i < diff; i++) {
                        var last    = $(this).find('[data-role="criterion-descriptor"]').last();
                        var newItem = last.clone(true);

                        // clear the descriptor value
                        newItem.find('[data-role="criterion-description"]').val('');
                        newItem.find('[data-role="criterion-value"]').val(
                            parseInt(newItem.find('[data-role="criterion-value"]').val()) + 1
                        );
                        newItem.insertAfter(last);
                    }
                }
            });
        });

        var critSelector       = '[data-role="criterion"]',
            inputSelector      = '[data-role="criterion-description"], [data-role="criterion-value"]',
            descriptorSelector = '[data-role="criterion-descriptor"]',
            editingClass       = 'editing',
            placeholderText    = 'Enter descriptor';

        /**
         * Focus on an descriptor input - add the editing class
         * (which performs some CSS magick to resize the descriptor)
         */
        element.on('focus', inputSelector, function () {
            var input = $(this);
            input
                .closest(critSelector)
                .addClass(editingClass);
            input
                .closest(descriptorSelector)
                .addClass(editingClass)
                .find(inputSelector)
                .prop('readonly', false)
                .attr('placeholder', placeholderText);
        });

        /**
         * Un-focus on an descriptor input - undo resize magick
         */
        element.on('blur', inputSelector, function () {
            element.find(critSelector + ', ' + descriptorSelector)
                .removeClass(editingClass)
                .find(inputSelector)
                .prop('readonly', true)
                .removeAttr('placeholder');
        });
    };
}(jQuery));
