/**
 * Cheat's font resizer:
 */
(function($) {
    $.fn.cheatsResize = function(options) {
        var defaults = {
            minSize: 1,
            maxSize: 6,
            getSize: function(element) {
                return element.width() / 100;
            }
        };
        var settings = $.extend({}, defaults, options);
        $(this).each(function() {
            var element = $(this);
            var width   = Math.max(settings.minSize, Math.min(settings.getSize(element), settings.maxSize));
            element.css({
                fontSize: String(width)+'em',
                padding: String(width/4)+'em'
            });
        })
    };
})(jQuery);