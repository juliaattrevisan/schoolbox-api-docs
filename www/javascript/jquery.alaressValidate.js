(function($) {
    "use strict";
    /**
     * Unified interface for form validation
     *
     * @param options
     */
    $.fn.alaressValidate = function(options) {
        var defaults = {
            // Default to not ignoring validation on any fields, even if hidden
            ignore: false,
            // Error placement: if a checkbox, insert the error after the now required label.
            // If it's there.
            errorPlacement: function(error, element) {
                if (element.is('[type="checkbox"], [type="radio"]')) {
                    var nextElement = element.next();
                    if (nextElement.is('label')) {
                        error.insertAfter(nextElement);
                    } else {
                        error.insertBefore(element);
                    }
                } else {
                    error.insertAfter(element);
                }
            }
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    }
})(jQuery);
