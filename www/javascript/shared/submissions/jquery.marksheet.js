(function() {
    /**
     * Label animator
     *
     * @param label
     */
    $.fn.labelTimeout = function(label) {
        label.animate({opacity: 0}, 1500, function() {
            label.addClass('hide');
        });
    }

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

                var self = $(this);

                $.get($(this).val(), function(data) {
                    var indicator = self.attr('data-visibility-selector');

                    var label = $('[data-identifier="'+indicator+'"]');
                    label.css({opacity: 1}).removeClass('hide');

                    setTimeout(function() {
                        $.fn.labelTimeout(label)
                    }, 3000);
                });
            });
        }
    }

    /**
     * Controls for Inputs
     */
    $.fn.marksheet.inputs = function(options) {
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
                this.disabled = 'disabled';

                // set up the data variable for POSTing
                var data = {}
                data[this.name] = self.val();

                /* Ajax new settings
                 */
                $.ajax({
                    type: 'POST',
                    url: config.url,
                    data: data,
                    // callbacks
                    success: function (data) {
                        self.addClass('ajax-response win');
                        self.prop('disabled', false);
                        self.attr('1title', '');

                        var tbody = $(config.element).children('tbody');

                        $.each(data, function (k, v) {
                            var userId = k,
                                gradient = v['avg-int'],
                                letter = v['avg-letter'];

                            var row = tbody.children('tr[data-id=' + userId + ']');

                            row.children('td').last()
                                .attr('class', 'gradient-' + gradient + '-bg').children('a')
                                .attr('class', 'gradient-' + gradient).text(letter);

                        });

                        setTimeout(function () {
                            self.removeClass('ajax-response win')
                        }, 500);
                    },
                    error: function (xhr) {
                        self.addClass('ajax-response fail');
                        self.attr('title', 'Value failed to save.')
                        self.prop('disabled', false);
                        setTimeout(function () {
                            self.removeClass('ajax-response fail')
                        }, 2000);
                    }
                });
            });
        }
    }
}(jQuery));