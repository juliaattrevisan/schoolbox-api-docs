/**
 * Generic hide watcher setup, based on data attributes.
 *
 * Shows or hides elements with a `data-hide-watch` tag, based on the value of
 * the selector that tag points to.
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
 * * hide-watch-invert:           checkbox based only - by default, content shows when the checkbox
 *                                is checked. If this attribute exists, content shows when unchecked.
 * * hide-watch-value:            select value based only - if the watched element is this value,
 *                                show this element, otherwise hide
 * * hide-watch-option-attribute: select value based only - check this attribute instead of the value
 */
(function($){
    $(function(){
        $('[data-hide-watch]').each(function() {
            var element = $(this);
            var data    = element.data();
            var watch   = $(data.hideWatch);
            if (watch.length) {
                var toggleCallback = function() { return true; };
                if (watch.is(':checkbox')) {
                    toggleCallback = function() {
                        var invert  = 'hideWatchInvert' in data;
                        var checked = watch.prop('checked');
                        return invert ? checked : !checked;
                    };
                } else {
                    // Watch on a value, or attribute of the selected option
                    var watchValue = data.hideWatchValue;
                    toggleCallback = function() {
                        return watch.val() != watchValue;
                    };
                    if (watch.is('select')) {
                        var watchAttribute = data.hideWatchOptionAttribute;
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
