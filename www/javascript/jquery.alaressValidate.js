(function($) {
    "use strict";

    $.alaressValidate = {};

    /**
     * Default error placement method.
     *
     * First check for button block radios, in which case insert into an .error-placeholder div
     * Then check for standard checkbox or radio, in which case insert error before the set of checkboxes/radios
     * In all other situations place the error after the element.
     *
     * @param error
     * @param element
     */
    $.alaressValidate.defaultErrorPlacement = function(error, element) {
        if (element.is('[data-radio-block]')) {
            element.parents('fieldset').find('div.error-placeholder').html(error);
        } else if (element.closest('.input-group').length) {
            error.insertAfter(element.closest('.input-group'));
        } else if (element.is('.selectized')) {
            error.insertAfter(element.closest('div, fieldset').find('.selectize-control'));
        } else if (element.is('[type="checkbox"], [type="radio"]')) {
            var parentSearch;

            // If element has a .checklist or .radiolist parent - use that to search for the first input
            if (element.closest('.checklist, .radiolist').length) {
                parentSearch = '.checklist, .radiolist';
            } else {
                // Otherwise use the closest div or fieldset parent
                parentSearch = 'div, fieldset';
            }

            // This used to use the :first-of-type pseudoclass selector (see https://api.jquery.com/first-of-type-selector/)
            // However for some reason it would not select anything if the closest parent had
            // multiple input types e.g. an input[type=text] alongside an input[type=checkbox].
            // get(0) does the same thing using jquery instead of css selectors, and doesn't break.
            error.insertBefore(element.closest(parentSearch).find('input[type=checkbox], input[type=radio]').get(0));

        } else {
            error.insertAfter(element);
        }
    };


    /**
     * Explode on the slash to determine numerator/denominator
     * Unfortunately this replicates backend logic
     * Both parts must be numeric
     * Numerator must be at least 0 otherwise it's an illegal attribution
     */
    jQuery.validator.addMethod("withinRange", function(value, element) {
        var parts = $(element).val().split('/');

        /* Trim perhaps-adjacent spaces to test for numeric representation
         * This rule might also be used for percentage, so remove that symbol too
         */
        parts[0] = $.trim(parts[0].replace('%', ''));
        parts[1] = $.trim(parts[1]);

        if ($.isNumeric(parts[0])) {
            var numerator = parseInt(parts[0]);
            var denominator = $(element).attr('data-validation-limit');

            return numerator >= 0 && numerator <= denominator;
        }

        /* Otherwise it could be one of the submission states like Absent or Not Submitted
         * so test that it's one of the available customisable options
         */
        return $(element).parents('.combobox').find('.combobox_selector li:contains("' + $(element).val() + '")').length > 0;
    });


    /**
     * Unfortunately this replicates backend logic
     * Letter Grades, though similar to other types, are distinct due to <option> structure in a select
     * It's not just some random unordered list
     */
    jQuery.validator.addMethod("withinLetterGradeRange", function(value, element) {
        return $(element).find('option:contains("' + $(element).val() + '")').length > 0;
    });


    /**
     * Unified interface for form validation
     *
     * @param options
     */
    $.fn.alaressValidate = function(options) {
        var defaults = {
            // Default to not ignoring validation on any fields, even if hidden
            ignore: false,
            ignoreTitle: true,
            errorPlacement: $.alaressValidate.defaultErrorPlacement,
            submitHandler: function(form) {
                // Find the submit button for this form and disable it,
                // to avoid accidental multiple requests
                $(form).find('input[type=submit]').prop("disabled", true).val('Working...');
                $(form).find('button[type=submit]').prop("disabled", true).text('Working...');

                // ..and submit the form
                form.submit();
            }
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    };
})(jQuery);
