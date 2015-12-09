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
            ignore: false
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    }
})(jQuery);
