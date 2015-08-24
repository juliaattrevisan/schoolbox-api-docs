/**
 * Generic hide watcher setup, based on data attributes
 *
 * Usage (checkbox based):
 * <div class="hide" data-hide-watch="switcher">...</div>
 * <input type="checkbox" id="switcher" />
 *
 * Usage (select value based):
 * <div class="hide" data-hide-watch="switcher" data-hide-watch-value="1">...</div>
 * <div class="hide" data-hide-watch="switcher" data-hide-watch-value="2">...</div>
 * <select id="switcher">
 *     <option value="1">First</option>
 *     <option value="2">Second</option>
 * </select>
 *
 * Usage (select value based w/ option attribute):
 * <div class="hide" data-hide-watch="switcher" data-hide-watch-value="first" data-hide-watch-option-attribute="label">...</div>
 * <div class="hide" data-hide-watch="switcher" data-hide-watch-value="second" data-hide-watch-option-attribute="label">...</div>
 * <select id="switcher">
 *     <option value="1" label="first">First</option>
 *     <option value="2" label="second">Second</option>
 * </select>
 *
 * Options:
 * * hide-watch:                  a selector that selects an element to watch
 * * hide-watch-value:            select value based only - if the watched element is this value,
 *                                show this element, otherwise hide
 * * hide-watch-option-attribute: select value based only - check this attribute instead of the value
 */
(function($){
    $(function(){
        $('[data-hide-watch]').each(function() {
            var element = $(this);
            var watch   = $(element.data('hide-watch'));
            if (watch.length) {
                var toggleCallback = function() { return true; };
                if (watch.is(':checkbox')) {
                    toggleCallback = function() {
                        return !watch.prop('checked');
                    };
                } else {
                    // Watch on a value, or attribute of the selected option
                    var watchValue = element.data('hide-watch-value');
                    toggleCallback = function() {
                        return watch.val() != watchValue;
                    };
                    if (watch.is('select')) {
                        var watchAttribute = element.data('hide-watch-option-attribute');
                        if (watchAttribute) {
                            toggleCallback = function() {
                                return watch.find('option:selected').attr(watchAttribute)
                                    != watchValue;
                            };
                        }
                    }
                }
                watch.on('change.hideWatch', function() {
                    element.toggleClass('hide', toggleCallback());
                });
                // Apply now
                element.toggleClass('hide', toggleCallback())
            }
        });
    })
})(jQuery);
