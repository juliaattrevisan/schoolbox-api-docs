(function() {

    /**
     * Save For Later
     *
     * @param options
     */
    $.fn.saveForLater = function(options) {
        options = options || {};

        var config = init(options, $(this));

        initSaveForLater();
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

                /* The container which has stars in it
                 * Title is the description of the next state which will be entered because this is a toggle
                 */
                element: element,

                /* Transitions
                 */
                saved: {
                    toast: {
                        processing: 'Saving...',
                        complete: 'Saved'
                    },
                    icon: 'icon-favourite',
                    title: 'Remove article from saved items'
                },
                unsaved: {
                    url: '/',
                    toast: {
                        processing: 'Removing...',
                        complete: 'Removed'
                    },
                    icon: 'icon-favourite-hollow',
                    title: 'Save article'
                }
            };

            return $.extend(defaults, options);
        }

        /**
         * Set up initial state of the button that activates Save For Later
         */
        function initSaveForLater() {
            var currentState;

            $.each($(config.element).find('a[data-saveforlater]'), function(index, element) {
                if ($(element).attr('data-saveforlater') === '1') {
                    currentState = config.saved;
                }
                else {
                    currentState = config.unsaved;
                }

                $(element)
                    .attr('title', currentState.title)
                    .addClass(currentState.icon);
            });
        }

        /**
         * Set up event listeners
         */
        function initSaveForLaterActions() {
            $(config.element).on('click', 'a[data-saveforlater]', function(event) {
                event.preventDefault();

                var $element = $(this);
                var $toast = $('#toast');

                /* The state of everything after the action takes place
                 */
                var newState;

                if ($element.attr('data-saveforlater') === '1') {
                    newState = config.unsaved;
                } else {
                    newState = config.saved;
                }

                $toast.toastActivate({
                    text: newState.toast.processing
                });

                $.ajax({
                    type: 'POST',
                    url: $element.attr('href'),
                    dataType: 'json'
                }).success(function (response) {
                    $toast.toastTimeout({
                        text: newState.toast.complete,
                        css: 'success'
                    });

                    // Update data attribute
                    $element.attr('data-saveforlater', response.currentValue);

                    // Update icon by removing all classes and adding the new one
                    $element.removeClass().addClass(newState.icon);

                    // Update title
                    $element.attr('title', newState.title);
                }).error(function (response) {
                    var temp = $.parseJSON(response.responseText);

                    $toast.toastTimeout({
                        text: 'Error: ' + temp.message,
                        css: 'alert'
                    });
                });

            });
        }
    }
}(jQuery));