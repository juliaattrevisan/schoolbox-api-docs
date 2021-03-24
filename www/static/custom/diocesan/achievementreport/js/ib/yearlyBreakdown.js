(function() {

    /**
     * Manufactures a IB Yearly Breakdown barchart
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2019, Schoolbox Pty Ltd
     * @link        https://schoolbox.com.au/
     */
    $.fn.ib_yearlyBreakdown = function(options) {
        options = options || {};

        var config = init(options, this);

        graph();

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

                /* Graph title
                 */
                title: '',

                /* Term 2, Term 4
                 */
                categories: [],

                series: []
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Graph it
         */
        function graph() {
            $(config.element).highcharts({
                chart: {
                    type: 'bar'
                },
                title: {
                    text: config.title
                },
                xAxis: {
                    categories: config.categories
                },
                yAxis: {
                    min: 0,
                    max: 7,
                    allowDecimals: false,
                    title: {
                        text: ''
                    }
                },
                legend: {
                    enabled: false
                },
                series: config.series,
                credits: {
                    enabled: false
                }
            });
        }
    }
}(jQuery));
