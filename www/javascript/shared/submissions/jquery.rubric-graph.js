(function() {

    /**
     * Plot a spider graph
     *
     * @param options
     */
    $.fn.rubricGraph = function(options) {
        options = options || {}

        var config = init(options, $(this));

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

                /* Leg labels
                 */
                categories: [],

                /* Series data
                 */
                data: [],

                /* Maximum y value
                 */
                ymax: 0
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
                    polar: true,
                    type: 'line'
                },

                title: {
                    text: ''
                },

                credits: {
                    enabled: false
                },

                legend: {
                    enabled: false
                },
                
                xAxis: {
                    categories: config.categories,
                    tickmarkPlacement: 'on',
                    lineWidth: 0
                },

                yAxis: {
                    gridLineInterpolation: 'polygon',
                    lineWidth: 0,
                    min: 0,
                    max: config.ymax
                },

                series: [{
                    name: 'Value',
                    data: config.data,
                    pointPlacement: 'on'
                }]
            });
        }
    }
}(jQuery));