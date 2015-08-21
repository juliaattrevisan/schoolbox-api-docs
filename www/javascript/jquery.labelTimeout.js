(function() {
    "use strict";
    /**
     * Fades out a label.
     */
    $.fn.labelTimeout = function(options) {
        var defaults = {
            duration: 1000,
            fadeDuration: 1000
        };
        var settings = $.extend({}, defaults, options);
        var label    = this;
        setTimeout(
            function() {
                label.animate({opacity: 0}, settings.fadeDuration, function() {
                    label.addClass('hide');
                });
            },
            settings.duration
        )
    };
}(jQuery));
