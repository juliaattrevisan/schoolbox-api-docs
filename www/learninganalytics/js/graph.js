(function() {

    /**
     * Calculate five-point summary and outliers for all series
     * of data, in both structured and array foramts
     *
     * @returns {{allGraphData: Array, allOutliers: Array}}
     */
    $.fn.calculateAllSeriesData = function(input) {
        var output = {
            allGraphData: [],
            allOutliers: []
        }

        console.log(input);

        for (var i = 0; i < input.length; i++) {
            var currentSeriesData = $.fn.calculateSeriesData({
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
    $.fn.calculateSeriesData = function(options) {

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
        populateOutput();

        return output;

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

            for (var i = 0; i < options.input.length; i++) {
                derived.values.push(options.input[i].value);
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }
        /**
         * http://www.purplemath.com/modules/boxwhisk.htm
         */
        function findMedian(values) {

            /* Figure out medians in different ways depending
             * on even or odd number of elements
             */
            var halfway = Math.floor(values.length/2);

            var even = !(values.length % 2);

            /* Careful of these offsets
             */
            if (even) {
                return (values[halfway-1] + values[halfway])/2.0;
            }

            return values[halfway];
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
            var min = values[0];

            for (var i = 0; i < values.length; i++) {
                if (values[i] < min) {
                    min = values[i];
                }
            }

            return min;
        }

        /**
         * Find maximum value in an array
         */
        function findMaximum(values) {
            var max = values[0];

            for (var i = 0; i < values.length; i++) {
                if (values[i] > max) {
                    max = values[i];
                }
            }

            return max;
        }

        /**
         * Generate an object containing all the box plot values
         * required to manufacture a graph
         */
        function generateValues() {
            return {
                minimum: findMinimum(config.values),
                quartile1: findMedian(setupFirstQuartile(config.values)),
                quartile2: findMedian(config.values),
                quartile3: findMedian(setupThirdQuartile(config.values)),
                maximum: findMaximum(config.values)
            }
        }

        /**
         * Generate IQR value
         */
        function generateIQR() {
            return output.fivePointSummary.structured.quartile3 - output.fivePointSummary.structured.quartile1;
        }

        /**
         * Generate a list of outliers
         *
         * Decide where the whiskers will be
         * Use a Tukey box plot
         */
        function generateOutliers() {
            var allOutliers = [];
            var i;

            /* Lower whisker
             * Use the first value that is above the lowerThreshold
             */
            var lowerThreshold = output.fivePointSummary.structured.quartile1 - (1.5 * output.iqr);

            for (i = (config.values.length-1); i >= 0; i--) {
                if (config.values[i] > lowerThreshold) {
                    output.fivePointSummary.structured.minimum = config.values[i];
                }
            }

            /* Upper whisker
             * Use the first value that is below the upperThreshold
             */
            var upperThreshold = output.fivePointSummary.structured.quartile3 + (1.5 * output.iqr);

            for (i = 0; i < config.values.length; i++) {
                if (config.values[i] < upperThreshold) {
                    output.fivePointSummary.structured.maximum = config.values[i];
                }
            }

            /* Outlier stack
             * Stack with information about the outlier,
             * not just its value
             */
            for (i = 0; i < config.values.length; i++) {
                if ((config.values[i] < lowerThreshold) || (config.values[i] > upperThreshold)) {
                    allOutliers.push(config.input[i]);
                }
            }

            return allOutliers;
        }

        /**
         * Populate output data structure with computed values
         * and any outlier stacks
         */
        function populateOutput() {

            /* Calculate five point summary
             */
            output.fivePointSummary.structured = generateValues();

            /* Calculate interquartile range
             */
            output.iqr = generateIQR();

            /* Decide outliers
             */
            output.outliers = generateOutliers();

            /* Convert into an array format
             */
            output.fivePointSummary.array = [
                output.fivePointSummary.structured.minimum,
                output.fivePointSummary.structured.quartile1,
                output.fivePointSummary.structured.quartile2,
                output.fivePointSummary.structured.quartile3,
                output.fivePointSummary.structured.maximum
            ];
        }
    }
}(jQuery));