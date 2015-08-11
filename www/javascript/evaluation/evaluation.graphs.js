(function() {

    /**
     * Generate a bargraph for Quiz/Survey responses
     */
    $.fn.evaluationBarchart = function(options) {
        options = options || {};

        var config = init(options, $(this));

        var xAxis = {};
        if (config.categories) {
            xAxis = {
                categories: config.categories,
                type: 'category'
            };
        } else {
            xAxis = {
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
            }
        }

        /* The plot unfolds
         */
        $(config.element).highcharts({
            chart: {
                type: 'column'
            },
            title: {
                text: ''
            },
            xAxis: xAxis,
            yAxis: {
                min: 0,
                allowDecimals: false,
                title: {
                    text: ''
                }
            },
            plotOptions: {
                column: {
                    grouping: false,
                    stacking: config.stacking
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
                element: element,
                stacking: false,
                categories: []
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }
    }

    /**
     * Generate a pie for Quiz/Survey responses
     */
    $.fn.evaluationPiechart = function(options) {
        options = options || {};

        var config = init(options, $(this));

        /* The plot unfolds
         */
        $(config.element).highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false
            },
            title: {
                text: config.title
            },
            tooltip: {
                pointFormat: '<strong>{point.percentage:.1f}%</strong>'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        format: '<strong>{point.name}</strong>: {point.percentage:.1f} %'
                    }
                }
            },
            series: [{
                type: 'pie',
                data: config.series
            }],
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
