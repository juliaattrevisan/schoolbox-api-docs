/**
 * Generate a bargraph for Quiz/Survey responses
 */
(function() {
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
