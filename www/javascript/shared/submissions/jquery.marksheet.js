(function() {

    /**
     * Marksheet
     */
    $.fn.marksheet = function() {
    }

    /**
     * Controls for Averages
     */
    $.fn.marksheet.averages = function(options) {
        options = options || {}

        var config = init(options, $(this));

        hideTableRows();
        toggleTableRows();
        updateVisibility();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* When you click this element, rows are toggled
                 */
                averagesRowsSelector: '[data-toggle-averages]',

                /* These rows are shown when averagesRowsSelector is clicked
                 */
                averagesRows: '[data-sub-table-row]',

                /* Element containing the url for updating visibility of
                 * a Due Work/Quiz
                 */
                visibilitySelector: '[data-visibility-selector]',

                /* Icon replacement
                 * Icon to show when you can expand/collapse
                 */
                icon: {
                    expand: 'icon-add',
                    collapse: 'icon-collapse'
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Hide all Averages rows
         */
        function hideTableRows() {
            $(config.averagesRows).hide();
        }

        /**
         * Toggle averages rows and change icon
         */
        function toggleTableRows() {
            //
            $(config.averagesRowsSelector).click(function(event){
                event.preventDefault();

                if ($(config.averagesRows).is(':visible')) {
                    $(this).addClass(config.icon.expand).removeClass(config.icon.collapse);

                    $('[data-sub-table-row]').hide();
                }
                else {
                    $(this).removeClass(config.icon.expand).addClass(config.icon.collapse);

                    $('[data-sub-table-row]').show();
                }
            });
        }

        /**
         * Change visibility value for a particular Due Work/Quiz
         * Ajaxes the new value silently
         */
        function updateVisibility() {
            $(config.visibilitySelector).change(function(event) {
                event.preventDefault();

                var url = $(this).val();

                $.get(url);
            });
        }
    }
}(jQuery));