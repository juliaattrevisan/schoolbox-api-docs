
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
        var highchartsOptions = {
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

            yAxis: {
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
        };
        $(options.element).highcharts($.extend(true, {}, highchartsOptions, options.highcharts));
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
                derived.values.push(options.input[i].value);
            }

            /* Merge everything
             */
            var merged = $.extend(defaults, derived, options);

            return merged;
        }


        /**
         * Generate an object containing all the box plot values
         * required to manufacture a graph
         *
         * config.values is already a precomputed Five Point Summary
         */
        function generateValues() {
            return {
                minimum: config.values[0],
                quartile1: config.values[1],
                quartile2: config.values[2],
                quartile3: config.values[3],
                maximum: config.values[4]
            };
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
