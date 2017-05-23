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
        } else if (element.is('[type="checkbox"], [type="radio"]')) {
            error.insertBefore(element);
        } else if (element.closest('.input-group').length) {
            error.insertAfter(element.closest('.input-group'))
        } else {
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
            ignore: "[data-av-ignore]",
            ignoreTitle: true,
            errorPlacement: $.alaressValidate.defaultErrorPlacement
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    }
})(jQuery);