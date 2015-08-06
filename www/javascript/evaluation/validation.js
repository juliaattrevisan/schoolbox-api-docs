/**
 * Reusable Evaluation validation rules
 */
(function() {

    /**
     * Greater Than Zero
     */
    $.fn.addCustomRules_greaterThanZero = function(config) {
        $.validator.addMethod("greaterThanZero", function(value, element) {
            return this.optional(element) || (parseFloat(value) > 0);
        }, "Amount must be greater than zero");
    }

    /**
     * Empty Inputs
     *
     * You must provide a description for each answer option
     */
    $.fn.addCustomRules_emptyInputs = function(config) {
        $.validator.addMethod('emptyInputs', function(value, element) {
            var fieldsAreFilled = true;

            $.each($(config.answerCollectionList).find('[data-input]'), function(index, element) {
                var description = $.trim($(element).find('[data-answer-description]').val());

                /* Ignore empty answers that weren't removed
                 */
                if (description == '') {
                    fieldsAreFilled = false;
                }
            });

            return fieldsAreFilled;
        }, 'Please fill out all answers');
    }

    /**
     * Something Checked
     *
     * At least one of the answer options must be marked as "correct"
     */
    $.fn.addCustomRules_somethingChecked = function(config) {
        $.validator.addMethod('somethingChecked', function(value, element) {
            var fieldsAreFilled = true;

            $.each($(config.answerCollectionList).find('[data-input]'), function(index, element) {
                var description = $.trim($(element).find('[data-answer-description]').val());

                /* Ignore empty answers that weren't removed
                 */
                if (description == '') {
                    fieldsAreFilled = false;
                }
            });

            return fieldsAreFilled;
        }, 'Tick at least one checkbox');
    }
}(jQuery));