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

                /* Use color transitions?
                 * Must be one of ['default', 'stepped', 'continuous', 'reverseContinuous']
                 */
                useColorTransitions: 'stepped',

                /* Custom callback formatter
                 */
                formatter: function() {
                    return '';
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            /* Use color transitions
             */
            merged.series[0].data = useColorTransition(merged);

            return merged;
        }

        /**
         * Use color transitions
         */
        function useColorTransition(merged) {
            var newData;
            var method = merged.useColorTransitions.toLowerCase();

            switch(method) {
                case 'stepped':
                case 'continuous':
                case 'reversecontinuous':
                    newData = applyColorTransitions(merged.series[0].data, method);
                    break;

                default:
                    newData = applyColorTransitions(merged.series[0].data, 'default');
                    break;
            }

            return newData;
        }

        /**
         * Apply color transitions
         *
         * @param data
         * @param method
         * @returns {Array}
         */
        function applyColorTransitions(data, method) {
            var allTemp = [];

            for (var i = 0; i < data.length; i++) {
                allTemp.push({
                    color: $.fn.serverUsage[method](data[i]),
                    y: data[i]
                });
            }

            return allTemp;
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

    /**
     * Default bar color
     *
     * @param percent
     * @returns {string}
     */
    $.fn.serverUsage.default = function(percent) {
        return '#0066b0';
    }

    /**
     * Stepped transition
     *
     * Blue until 90%, then red
     *
     * @param percent
     * @returns {string}
     */
    $.fn.serverUsage.stepped = function(percent) {
        if (percent < 90) {
            return '#0066b0';
        }

        return '#ff5757';
    }

    /**
     * Continuous transition
     *
     * Green is 100%
     * Red is 0%
     *
     * @param percent
     */
    $.fn.serverUsage.continuous = function(percent) {
        var max = 230;

        var r = percent<50 ? max : Math.floor(max-(percent*2-100)*max/100);
        var g = percent>50 ? max : Math.floor((percent*2)*max/100);

        return '#' + $.fn.serverUsage.hexColorComponent(r) + $.fn.serverUsage.hexColorComponent(g) + '00';
    }

    /**
     * Continuous transition
     *
     * Green is 0%
     * Red is 100%
     *
     * @param percent
     */
    $.fn.serverUsage.reversecontinuous = function(percent) {
        var max = 230;

        var r = percent>50 ? max : Math.floor((percent*2)*max/100);
        var g = percent<50 ? max : Math.floor(max-(percent*2-100)*max/100);

        return '#' + $.fn.serverUsage.hexColorComponent(r) + $.fn.serverUsage.hexColorComponent(g) + '00';
    }

    /**
     * With prefixed 0
     *
     * @param percentage
     * @returns {*}
     */
    $.fn.serverUsage.hexColorComponent = function(percentage) {
        var hexValue = percentage.toString(16);

        if (hexValue.length < 2) {
            return '0' + hexValue;
        }

        return hexValue;
    }
}(jQuery));