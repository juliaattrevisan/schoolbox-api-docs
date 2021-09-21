(function() {

    /**
     * Manufactures a Progress Grade Summary barchart
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2019, Schoolbox Pty Ltd
     * @link        https://schoolbox.com.au/
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

                /* Colour stack for the bars
                 */
                colors: [],

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
                colors: config.colors,
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
                },
                credits: {
                    enabled: false
                }
            });
        }
    }
}(jQuery));
