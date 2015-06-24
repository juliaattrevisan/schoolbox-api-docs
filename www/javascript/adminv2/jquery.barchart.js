(function() {

    /**
     * Manufactures a bar graph
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2011, Alaress Pty Ltd
     * @link        http://www.alaress.com.au/
     * @version     SVN: $Id$
     */
    $.fn.barchart = function(options) {
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

                /* Chart canvas height
                 */
                height: 150,

                /* Graph title
                 */
                title: '',

                /* Colour stack for the bars
                 */
                colors: [],

                /* y-axis names
                 */
                categories: [],

                /* Numeric data
                 */
                series: [],

                /* Tooltip enabled?
                 */
                tooltip: {
                    enabled: true
                },

                /* Custom callback formatter
                 */
                formatter: function() {
                    return '';
                }
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
                    height: config.height,
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
                    max: 100,
                    reversedStacks: false,
                    title: {
                        text: ''
                    }
                },
                tooltip: config.tooltip,
                legend: {
                    enabled: false
                },
                plotOptions: {
                    series: {
                        dataLabels: {
                            enabled: true,
                            align: 'center',
                            inside: true,
                            formatter: config.formatter
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