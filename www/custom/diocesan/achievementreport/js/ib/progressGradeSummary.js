(function() {

    /**
     * Manufactures a Progress Grade Summary barchart
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2011, Alaress Pty Ltd
     * @link        http://www.alaress.com.au/
     * @version     SVN: $Id$
     */
    $.fn.ib_progressGradeSummary = function(options) {
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
                    text: ''
                },
                xAxis: {
                    categories: config.categories
                },
                yAxis: {
                    min: 0,
                        max: 100,
                        reversedStacks: false,
                        title: {
                        text: 'Percentage'
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
                tooltip: {

                    /**
                     * Sigh, find all the values in the series which match the
                     * outlier value and concat it all into one giant tooltip
                     *
                     * so goofy
                     *
                     * @returns {string}
                     */
                    formatter: function() {
                        var template = '<strong>{NAME}</strong> ({VALUE}%)';

                        var tooltipContent = template;
                        tooltipContent = tooltipContent.replace(/{NAME}/, this.series.name);
                        tooltipContent = tooltipContent.replace(/{VALUE}/, this.y.toFixed(1));

                        return tooltipContent;
                    }
                }
            });
        }
    }
}(jQuery));