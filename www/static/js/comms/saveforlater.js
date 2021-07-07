(function() {

    var pluginName = 'saveForLater';

    /**
     * Save For Later
     *
     * @param {dom}    element
     * @param {Object} options
     */
    function SaveForLater(element, options) {
        options = options || {};

        var config = init(options, element);

        initSaveForLaterActions();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                onChangeSuccess: function (isSaved, articleId) {}
            };

            var derived = {
                element: $(element)
            };

            return $.extend(true, defaults, derived, options);
        }


        /**
         * Set up event listeners
         */
        function initSaveForLaterActions() {
            $(config.element).on('click', '[data-save-for-later] [data-action]', function(event) {
                event.preventDefault();

                var $element = $(this),
                    $wrapper = $element.closest('[data-save-for-later]'),
                    $toast   = $('#toast'),
                    action   = $element.attr('data-action');

                if ($element.data('inflight')) {
                    // don't allow more than one concurrent POST
                    return;
                }

                $toast.toastActivate({
                    text: $element.attr('data-toast-processing')
                });

                // set the blocking flag
                $element.data('inflight', true);

                $.ajax({
                    type: 'POST',
                    url: $element.attr('href')
                }).done(function () {
                    $toast.toastTimeout({
                        text: $element.attr('data-toast-complete'),
                        css: 'success'
                    });
                    $element.addClass('hide');
                    var otherAction = action === 'add' ? 'remove' : 'add';
                    $wrapper.find('[data-action=' + otherAction + ']').removeClass('hide');
                    config.onChangeSuccess(action === 'add', $wrapper.attr('data-save-for-later'));
                }).fail(function () {
                    var textSuffix = action === 'add' ? 'save article for later' : 'remove article from saved list';
                    $toast.toastTimeout({
                        text: 'Error: Unable to ' + textSuffix,
                        css: 'alert'
                    });
                }).always(function () {
                    $element.data('inflight', false);
                });
            });
        }
    }

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new SaveForLater(this, options));
            }
        });
    }
}(jQuery));
