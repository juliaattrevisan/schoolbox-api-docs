(function() {

    /**
     * Manufactures a bar graph
     *
     * @author      Steve Chan <steve.chan@alaress.com.au>
     * @copyright   Copyright 2011, Alaress Pty Ltd
     * @link        http://www.alaress.com.au/
     * @version     SVN: $Id$
     */
    $.fn.serverUsage = function(options) {
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

            /* Work out colour transitions
             */
            var allTemp = [];

            for (var i = 0; i < merged.series[0].data.length; i++) {
                var temp = {
                    color: getSteppedHexColor(merged.series[0].data[i]),
                    y: merged.series[0].data[i]
                }

                allTemp.push(temp);

            }

            merged.series[0].data = allTemp;

            return merged;
        }

        /**
         * Stepped transition
         *
         * Blue until 90%, then red
         *
         * @param percent
         * @returns {string}
         */
        function getSteppedHexColor(percent) {
            if (percent < 90) {
                return '#0066b0';
            }

            return '#ff5757';
        }

        /**
         * Colour transition
         *
         * Green is 100%
         * Red is 0%
         *
         * @param percent
         */
        function getGradientHexColor(percent) {
            var max = 230;

            var r = percent<50 ? max : Math.floor(max-(percent*2-100)*max/100);
            var g = percent>50 ? max : Math.floor((percent*2)*max/100);

            return '#' + hexColorComponent(r) + hexColorComponent(g) + '00';
        }

        /**
         * Colour transition
         *
         * Green is 0%
         * Red is 100%
         *
         * @param percent
         */
        function getReversedGradientHexColor(percent) {
            var max = 230;

            var r = percent>50 ? max : Math.floor((percent*2)*max/100);
            var g = percent<50 ? max : Math.floor(max-(percent*2-100)*max/100);

            return '#' + hexColorComponent(r) + hexColorComponent(g) + '00';
        }

        /**
         * With prefixed 0
         *
         * @param percentage
         * @returns {*}
         */
        function hexColorComponent(percentage) {
            var hexValue = percentage.toString(16);

            if (hexValue.length < 2) {
                return '0' + hexValue;
            }

            return hexValue;
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
                            color: '#ffffff',
                            style: {
                                textShadow: ''
                            },
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