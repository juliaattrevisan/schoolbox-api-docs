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
        setupRedraw();

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
                ymax: 0,

                /* All the rubric inputs which can cause graph to update
                 * are contained in this element
                 */
                inputs: ''
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
                    type: 'area'
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
                    allowDecimals: false,
                    tickInterval: 1,
                    min: 0,
                    max: config.ymax
                },

                series: [{
                    name: 'Value',
                    data: config.data,
                    pointPlacement: 'on',
                    fillOpacity: 0.2
                }]
            });
        }

        /**
         * Set up redraw activators
         */
        function setupRedraw() {
            $(config.inputs).find('input').change(function(event) {
                var data = [];

                $.each($(config.inputs).find('input:checked'), function(index, element) {
                    data.push(parseFloat($(element).val()));
                });

                redraw(0, data);
            });

            /**
             * Execute redraw
             *
             * @param data
             */
            function redraw(series, data) {
                var chart = $(config.element).highcharts();
                chart.series[series].setData(data,true);
                chart.redraw();
            }
        }
    }
}(jQuery));