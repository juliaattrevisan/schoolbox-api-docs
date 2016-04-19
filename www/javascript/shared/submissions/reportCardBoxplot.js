
(function() {

    /**
     * Render boxplot graphs
     *
     * @param options
     */
    $.fn.reportCardBoxplotRender = function(options) {
        var options = options || {
                element: '',
                title: '',
                orientation: 'vertical',
                allAssessments: [],
                allData: [],
                additionalSeries: [],

                /* Format of outlier data
                 */
                pointFormatter: function() {
                    return '';
                }
            };

        /* The typical boxplots
         */
        var currentSeries = [
            {
                name: 'Analysis',
                data: options.allData.allGraphData,
                tooltip: {
                    headerFormat: ''
                },
                enableMouseTracking: false
            }
        ]

        /* Include overlaid graphs
         */
        var allSeries = currentSeries.concat(options.additionalSeries);

        /* The plot unfolds
         */
        $(options.element).highcharts({

            /* can use inverted:true to make it horizontal'
             */
            chart: {
                type: 'boxplot',
                inverted: options.orientation == 'vertical' ? false : true
            },

            title: {
                text: options.title
            },

            legend: {
                enabled: false
            },

            xAxis: {
                categories: options.allAssessments
            },

            yAxis: options.yAxis || {
                min: 0,
                max: 100,
                labels: {
                    enabled: false
                },
                title: {
                    text: ''
                }
            },

            tooltip: {
                useHTML: true
            },

            credits: {
                enabled: false
            },

            series: allSeries
        });
    }

    /**
     * Calculate five-point summary and outliers for all series
     * of data, in both structured and array foramts
     *
     * @returns {{allGraphData: Array, allOutliers: Array}}
     */
    $.fn.reportCardBoxplotCalculateAllSeriesData = function(input) {
        var output = {
            input: input,
            allGraphData: [],
            allOutliers: []
        }

        for (var i = 0; i < input.length; i++) {
            var currentSeriesData = $.fn.reportCardBoxplotCalculateSeriesData({
                input: input[i]
            });

            /* Outliers and boxplots rendered separately
             */
            output.allGraphData.push(currentSeriesData.fivePointSummary.array);

            for (var j = 0; j < currentSeriesData.outliers.length; j++) {
                output.allOutliers.push([i, currentSeriesData.outliers[j].value]);
            }
        }

        return output;
    }

    /**
     * Calculate five-point summary and outliers for one series
     * of data, in both structured and array formats
     *
     * @param options
     * @returns {{fivePointSummary: {structured: null, array: Array}, iqr: null, outliers: Array}}
     */
    $.fn.reportCardBoxplotCalculateSeriesData = function(options) {

        /* Store the config in this instance so private methods
         * can access it
         */
        var config = init(options);

        /* Output data structure
         */
        var output = {

            /* An object literal containing another object literal and
             * an array, both of which correspond to the five-point
             * summary (min, q1, q2, q3 and max values)
             */
            fivePointSummary: {
                structured: null,
                array: []
            },

            /* A float, the interquartile range
             */
            iqr: null,

            /* An array of key/value literals
             * e.g. {98, 'John Smith, Year 9'}
             */
            outliers: []
        }

        /* Populate with data
         */
        return populateOutput(output);

        /**
         * Init
         */
        function init(options) {

            /* Default values
             */
            var defaults = {

                /* An array of object literals
                 */
                input: []
            }

            /* Derived values
             */
            var derived = {

                /* An array of values derived from input
                 */
                values: []
            }

            /* This sorts as strings by default so use an
             * anonymous function to do it properly
             *
             * http://www.w3schools.com/jsref/jsref_sort.asp
             */
            options.input.sort(function(a, b) {
                return a.value - b.value
            });

            /* Remove null values before computing
             */
            for (var i = 0; i < options.input.length; i++) {
                if (options.input[i].value != undefined) {
                    derived.values.push(options.input[i].value);
                }
            }

            /* Merge everything
             */
            var merged = $.extend(defaults, derived, options);

            return merged;
        }
        /**
         * http://www.purplemath.com/modules/boxwhisk.htm
         */
        function findMedian(values) {
            var median = null;

            /* Figure out medians in different ways depending
             * on even or odd number of elements
             */
            var halfway = Math.floor(values.length/2);

            var even = !(values.length % 2);

            /* Careful of these offsets
             */
            if (even) {
                median = (values[halfway-1] + values[halfway])/2.0;
            }
            else {
                median = values[halfway];
            }

            if (median != null) {
                median = parseFloat(median.toFixed(1));
            }

            return median;
        }

        /**
         * Find the values used to calculate first quartile
         */
        function setupFirstQuartile(values) {
            return values.slice(0, Math.floor(values.length/2));
        }

        /**
         * Find the values used to calculate third quartile
         *
         * Even number of items: split down the middle
         * Odd number of items: ignore the median value
         */
        function setupThirdQuartile(values) {
            var even = !(values.length % 2);

            if (even) {
                return values.slice(Math.floor(values.length/2), values.length);
            }

            return values.slice(Math.ceil(values.length/2), values.length);
        }

        /**
         * Find minimum value in an array
         */
        function findMinimum(values) {
            var minimum = null;

            if (values.length) {
                minimum = values[0];

                for (var i = 0; i < values.length; i++) {
                    if (values[i] < minimum) {
                        minimum = values[i];
                    }
                }
            }

            return minimum;
        }

        /**
         * Find maximum value in an array
         */
        function findMaximum(values) {
            var maximum = null;

            if (values.length) {
                maximum = values[0];

                for (var i = 0; i < values.length; i++) {
                    if (values[i] > maximum) {
                        maximum = values[i];
                    }
                }
            }

            return maximum;
        }

        /**
         * Generate an object containing all the box plot values
         * required to manufacture a graph
         */
        function generateValues() {
            var data = {
                minimum: findMinimum(config.values),
                quartile1: findMedian(setupFirstQuartile(config.values)),
                quartile2: findMedian(config.values),
                quartile3: findMedian(setupThirdQuartile(config.values)),
                maximum: findMaximum(config.values)
            };

            return data;
        }

        /**
         * Generate IQR value
         *
         * fivePointSummary is a structured object, the output
         * from generateValues()
         */
        function generateIQR(fivePointSummary) {
            return fivePointSummary.quartile3 - fivePointSummary.quartile1;
        }


        /**
         * Populate output data structure with computed values
         * and any outlier stacks
         */
        function populateOutput(output) {

            /* Calculate five point summary
             */
            var fivePointSummary = generateValues();

            /* Calculate interquartile range
             */
            var iqr = generateIQR(fivePointSummary);

            /* Assign values
             */
            output.fivePointSummary.structured = fivePointSummary;
            output.iqr = iqr;


            /* Convert into an array format
             */
            output.fivePointSummary.array = [
                output.fivePointSummary.structured.minimum,
                output.fivePointSummary.structured.quartile1,
                output.fivePointSummary.structured.quartile2,
                output.fivePointSummary.structured.quartile3,
                output.fivePointSummary.structured.maximum
            ];

            return output;
        }
    }
}(jQuery));