(function() {

    /**
     * Manufactures a NCEA generic stacked barchart
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2011, Alaress Pty Ltd
     * @link        http://www.alaress.com.au/
     * @version     SVN: $Id$
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
                title: {
                    text: ''
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
                        stacking: 'normal'
                    }
                },
                series: config.series
            });
        }
    }
}(jQuery));