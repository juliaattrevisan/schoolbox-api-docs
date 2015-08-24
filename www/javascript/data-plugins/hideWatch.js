/**
 * Generic hide watcher setup, based on data attributes
 */
(function($){
    $(function(){
        $('[data-hide-watch]').each(function() {
            var element = $(this);
            var watch   = $('#'+element.data('hide-watch'));
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
