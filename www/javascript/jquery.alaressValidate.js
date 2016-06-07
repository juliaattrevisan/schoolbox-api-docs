(function($) {
    "use strict";

    $.alaressValidate = {};

    /**
     * Default error placement method.
     *
     * If a checkbox, insert error before the checkbox, otherwise place after.
     * This prevents interference with checkbox/radio button stylage.
     *
     * @param error
     * @param element
     */
    $.alaressValidate.defaultErrorPlacement = function(error, element) {
        if (element.is('[type="checkbox"], [type="radio"]')) {
            error.insertBefore(element);
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
            ignore: false,
            ignoreTitle: true,
            errorPlacement: $.alaressValidate.defaultErrorPlacement
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    }
})(jQuery);
