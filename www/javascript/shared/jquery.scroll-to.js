/**
 * ScrollTo plugin
 *
 * @see http://lions-mark.com/jquery/scrollTo/
 */
(function ($) {
    "use strict";
    $.fn.scrollTo = function (target, options, callback) {
        if (typeof options == 'function' && arguments.length == 2) {
            callback = options;
            options = target;
        }
        var settings = $.extend({
            scrollX: false,
            scrollY: true,
            scrollTarget: target,
            offsetTop: 50,
            offsetLeft: 50,
            duration: 500,
            easing: 'swing'
        }, options);
        return this.each(function () {
            var scrollPane = $(this);
            var scrollTarget = (typeof settings.scrollTarget == "number") ? settings.scrollTarget : $(settings.scrollTarget);

            var properties = {};
            if (settings.scrollX) {
                properties.scrollLeft = (typeof scrollTarget == "number")
                    ? scrollTarget
                    : scrollTarget.offset().left + scrollPane.scrollLeft() - parseInt(settings.offsetLeft);
            }
            if (settings.scrollY) {
                properties.scrollTop = (typeof scrollTarget == "number")
                    ? scrollTarget
                    : scrollTarget.offset().top + scrollPane.scrollTop() - parseInt(settings.offsetTop);
            }
            scrollPane.animate(properties, parseInt(settings.duration), settings.easing, function () {
                if (typeof callback == 'function') {
                    callback.call(this);
                }
            });
        });
    };
})(jQuery);
