/**
 * Generate a bargraph for Quiz/Survey responses
 */
(function() {
    $.fn.evaluationBarchart = function(options) {
        options = options || {};

        var config = init(options, $(this));

        /* The plot unfolds
         */
        $(config.element).highcharts({
            chart: {
                type: 'column'
            },
            title: {
                text: ''
            },
            xAxis: {
                labels: {
                    enabled: false
                },

                /* Hide useless ticketty-boo lines (you need all
                 * of these values, seemingly)
                 */
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                minorTickLength: 0,
                tickLength: 0
            },
            yAxis: {
                min: 0,
                allowDecimals: false,
                title: {
                    text: ''
                }
            },
            plotOptions: {
                column: {
                    grouping: false
                }
            },
            series: config.series,
            tooltip: {
                formatter: function() {
                    return this.series.name + ': ' + this.y;
                }
            },
            credits: {
                enabled: false
            }
        });

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }
    }
}(jQuery));
