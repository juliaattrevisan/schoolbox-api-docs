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
    // TODO for some reason, this is not being called for the checkboxes
    $.alaressValidate.defaultErrorPlacement = function(error, element) {
        console.log("Currently checking error placement for: ");
        console.log(element);

        if (element.is('[data-radio-block]')) {
            console.log("Element is data-radio-block - searching for nearest fieldset with error-placeholder");
            element.parents('fieldset').find('div.error-placeholder').html(error);
        } else if (element.is('[type="checkbox"], [type="radio"]')) {
            console.log("Element is type checkbox or radio...")
            var parentSearch;

            // If element has a .checklist or .radiolist parent - use that to search for the first input
            if (element.closest('.checklist, .radiolist').length) {
                console.log("Finding closest checklist or radiolist parent");
                parentSearch = '.checklist, .radiolist';
            } else {
                console.log("Searching for closest div or fieldset parent");
                // Otherwise use the closest div or fieldset parent
                parentSearch = 'div, fieldset';
            }

            error.insertBefore(element.closest(parentSearch).find('input[type=checkbox]:first-of-type, input[type=radio]:first-of-type'));

        } else if (element.closest('.input-group').length) {
            console.log("Inserting after closest .input-group");
            error.insertAfter(element.closest('.input-group'));
        } else {
            console.log("Inserting error message after element");
            error.insertAfter(element);
        }
    };

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
            errorPlacement: $.alaressValidate.defaultErrorPlacement
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    };
})(jQuery);