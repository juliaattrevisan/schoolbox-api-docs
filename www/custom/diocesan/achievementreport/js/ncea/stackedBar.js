(function() {

    /**
     * Manufactures a NCEA generic stacked barchart
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2019, Schoolbox Pty Ltd
     * @link        https://schoolbox.com.au/
     */
    $.fn.ncea_stackedBar = function(options) {
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

                /* Colour stack for the bars
                 */
                colors: [],

                /* Y Axis title
                 *
                 * Note that in horizontal barcharts x and y are
                 * used conversely in highcharts
                 */
                yAxisTitle: '',

                /* Bar Names
                 */
                categories: [],

                /* Bar segments
                 */
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
                colors: config.colors,
                title: {
                    text: config.title
                },
                xAxis: {
                    categories: config.categories
                },
                yAxis: {
                    min: 0,
                    reversedStacks: false,
                    title: {
                        text: config.yAxisTitle
                    }
                },
                legend: {
                    reversed: false
                },
                plotOptions: {
                    series: {
                        stacking: 'percent',
                        dataLabels: {
                            enabled: true,
                            align: 'center',
                            inside: true,
                            formatter: function() {
                                var str = '';

                                if (this.y > 0) {
                                    str = this.y;
                                }
                                
                                return str;
                            }
                        }
                    }
                },
                series: config.series,
                credits: {
                    enabled: false
                }
            });
        }
    }
}(jQuery));
