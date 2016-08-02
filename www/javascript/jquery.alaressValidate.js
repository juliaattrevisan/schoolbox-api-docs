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


    /**
     * Custom method to ensure a date is on or after minDate
     * Requires date in format dd/mm/yyyy
     * Usage:
     *     rules: {
     *        elemName: {
     *          minDate: "21/05/2015"
     *        }
     *     }
     */
    $.validator.addMethod("minDate", function(value, element, params) {
        var day      = parseInt(value.substr(0,2), 10);
        var month    = parseInt(value.substr(3,2), 10);
        var year     = parseInt(value.substr(6,4), 10);

        var minDay   = parseInt(params.substr(0,2), 10);
        var minMonth = parseInt(params.substr(3,2), 10);
        var minYear  = parseInt(params.substr(6,4), 10);

        var isAfterMin = year != minYear ? year > minYear : (month != minMonth ? month > minMonth : day >= minDay);

        return this.optional(element) || isAfterMin;
    }, "Enter a later Date");

    /**
     * Custom method to ensure a date is on or before maxDate
     * Requires date in format dd/mm/yyyy
     * Usage:
     *     rules: {
     *        elemName: {
     *          maxDate: "21/05/2015"
     *        }
     *     }
     */
    $.validator.addMethod("maxDate", function(value, element, params) {
        var day      = parseInt(value.substr(0,2), 10);
        var month    = parseInt(value.substr(3,2), 10);
        var year     = parseInt(value.substr(6,4), 10);

        var maxDay   = parseInt(params.substr(0,2), 10);
        var maxMonth = parseInt(params.substr(3,2), 10);
        var maxYear  = parseInt(params.substr(6,4), 10);

        var isBeforeMax = year != maxYear ? year < maxYear : (month != maxMonth ? month < maxMonth : day <= maxDay);

        return this.optional(element) || isBeforeMax;
    }, "Enter an earlier Date");


})(jQuery);
