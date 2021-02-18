(function() {

    /**
     * Controls for Averages
     */
    $.fn.marksheetAverages = function(options) {
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
                 * a Due Work/Quiz e.g. [data-visiblity-selector]
                 *
                 * data-visibility-selector, as an attribute, is also used
                 * to select a corresponding ajax feedback label
                 */
                visibilitySelector: 'data-visibility-selector',

                /* Icon replacement
                 * Icon to show when you can expand/collapse
                 */
                icon: {
                    expand: 'icon-down-arrow',
                    collapse: 'icon-up-arrow'
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
            $('[' + config.visibilitySelector + ']').change(function(event) {
                event.preventDefault();

                var self = $(this);

                $.get($(this).val(), function(data) {
                    var indicator = self.attr(config.visibilitySelector);

                    var label = $('[data-identifier="'+indicator+'"]');
                    label.text('Saved').css({opacity:1}).removeClass('hide');
                    label.toastTimeout({duration: 3000});
                });
            });
        }
    }

    /**
     * Controls for Inputs
     */
    $.fn.marksheetInputs = function(options) {
        options = options || {}

        var config = init(options, $(this));

        everything();

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

                /* The url which receives the updated values via ajax
                 */
                url: '/ajax/submission/marks.php'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Everything
         */
        function everything() {
            $(config.element).on('change', 'input', function (e) {
                var self = $(this);

                /* Disable the current input until ajax transaction completes
                 * This prevents update spamming
                 */
                self.prop('disabled', true);

                // set up the data variable for POSTing
                var data = {}
                data[this.name] = self.val();

                /* Label data identifier
                 */
                var identifier = self.attr('name') + '-weight';
                var label = $('[data-identifier="'+identifier+'"]');

                label.toastActivate({
                    text: 'Preparing to save'
                });

                /* Ajax new settings
                 */
                config.element
                    .ajaxForm({
                        type: 'POST',
                        url: config.url,
                        data: data
                    })
                    .on('done.ajaxForm', function(event, data) {
                        var tbody = $(config.element).children('tbody');

                        $.each(data, function (k, v) {
                            var userId = k,
                                gradient = v['avg-int'],
                                letter = v['avg-letter'];

                            var row = tbody.children('tr[data-student=' + userId + ']');

                            row.children('td').last()
                                .attr('class', 'gradient-' + gradient + '-bg').children('a')
                                .attr('class', 'gradient-' + gradient).text(letter);

                        });

                        /* Animate label feedback
                         */
                        label.toastTimeout({
                            text: 'Saved',
                            css: 'success'
                        });
                    })
                    .on('fail.ajaxForm', function() {
                        label.toastTimeout({
                            text: 'Value failed to save',
                            css: 'alert'
                        });
                    })
                    .on('always.ajaxForm', function() {
                        self.prop('disabled', false);
                    });
            });
        }
    }
}(jQuery));
