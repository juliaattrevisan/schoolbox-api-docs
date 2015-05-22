(function() {

    /**
     * Manufactures a NAME graph
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2011, Alaress Pty Ltd
     * @link        http://www.alaress.com.au/
     * @version     SVN: $Id$
     */
    $.fn.schoolGrades_nameGraph = function(options) {
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

                /* Year 10, 9, 8...
                 */
                categories: [],

                /* Data for N/A/M/E/AvailableGrades
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
                        text: ''
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
                series: config.series,
                credits: {
                    enabled: false
                }
            });
        }
    }
}(jQuery));