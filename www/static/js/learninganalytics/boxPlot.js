(function () {
    /**
     * Render boxplot graphs
     *
     * @param options
     */
    $.fn.boxPlotRender = function (options) {
        var options = options || {
            element: "",
            title: "",
            orientation: "vertical",
            allAssessments: [],
            allData: [],
        };

        /* The plot unfolds
         */
        $(options.element).highcharts({
            /* can use inverted:true to make it horizontal'
             */
            chart: {
                type: "boxplot",
                inverted: options.orientation == "vertical" ? false : true,
            },

            title: {
                text: options.title,
            },

            legend: {
                enabled: false,
            },

            xAxis: {
                categories: options.allAssessments,
            },

            yAxis: {
                min: 0,
                max: 100,
                title: {
                    text: "Mark",
                },
            },

            tooltip: {
                useHTML: true,
            },

            series: [
                {
                    name: "Analysis",
                    data: options.allData.allGraphData,
                    tooltip: {
                        headerFormat: "",
                    },
                },
                {
                    name: "Outlier",
                    color: Highcharts.getOptions().colors[0],
                    type: "scatter",
                    data: options.allData.allOutliers,
                    marker: {
                        fillColor: "white",
                        lineWidth: 1,
                        lineColor: Highcharts.getOptions().colors[0],
                    },
                    tooltip: {
                        /**
                         * Sigh, find all the values in the series which match the
                         * outlier value and concat it all into one giant tooltip
                         *
                         * so goofy
                         *
                         * @returns {string}
                         */
                        pointFormatter: function () {
                            /* Basic template for each outlier
                             */
                            var template =
                                "<tr>" +
                                "<td>" +
                                '<span style="display:block;width:32px;height:32px;background-image:url(/portrait.php?id={UID}&size=square32);"></span>' +
                                "</td>" +
                                "<td>" +
                                "<em>{NAME}</em><br />{VALUE}" +
                                "</td>" +
                                "</tr>";

                            /* Concatenate everything
                             */
                            var allTooltipContent = "";

                            for (var i = 0; i < options.allData.input[this.x].length; i++) {
                                if (options.allData.input[this.x][i].value == this.y) {
                                    var tooltipContent = template;

                                    tooltipContent = tooltipContent.replace(
                                        /{EXTERNAL_ID}/,
                                        options.allData.input[this.x][i].externalId,
                                    );
                                    tooltipContent = tooltipContent.replace(
                                        /{UID}/,
                                        options.allData.input[this.x][i].uid,
                                    );
                                    tooltipContent = tooltipContent.replace(
                                        /{NAME}/,
                                        options.allData.input[this.x][i].info,
                                    );
                                    tooltipContent = tooltipContent.replace(/{VALUE}/, this.y);

                                    allTooltipContent += tooltipContent;
                                }
                            }

                            return "<table><tbody>" + allTooltipContent + "</tbody></table>";
                        },
                    },
                },
            ],
        });
    };

    /**
     * Calculate five-point summary and outliers for all series
     * of data, in both structured and array foramts
     *
     * @returns {{allGraphData: Array, allOutliers: Array}}
     */
    $.fn.boxPlotCalculateAllSeriesData = function (input) {
        var output = {
            input: input,
            allGraphData: [],
            allOutliers: [],
        };

        for (var i = 0; i < input.length; i++) {
            var currentSeriesData = $.fn.boxPlotCalculateSeriesData({
                input: input[i],
            });

            /* Outliers and boxplots rendered separately
             */
            output.allGraphData.push(currentSeriesData.fivePointSummary.array);

            for (var j = 0; j < currentSeriesData.outliers.length; j++) {
                output.allOutliers.push([i, currentSeriesData.outliers[j].value]);
            }
        }

        return output;
    };

    /**
     * Calculate five-point summary and outliers for one series
     * of data, in both structured and array formats
     *
     * @param options
     * @returns {{fivePointSummary: {structured: null, array: Array}, iqr: null, outliers: Array}}
     */
    $.fn.boxPlotCalculateSeriesData = function (options) {
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
                array: [],
            },

            /* A float, the interquartile range
             */
            iqr: null,

            /* An array of key/value literals
             * e.g. {98, 'John Smith, Year 9'}
             */
            outliers: [],
        };

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
                input: [],
            };

            /* Derived values
             */
            var derived = {
                /* An array of values derived from input
                 */
                values: [],
            };

            /* This sorts as strings by default so use an
             * anonymous function to do it properly
             *
             * http://www.w3schools.com/jsref/jsref_sort.asp
             */
            options.input.sort(function (a, b) {
                return a.value - b.value;
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
            var halfway = Math.floor(values.length / 2);

            var even = !(values.length % 2);

            /* Careful of these offsets
             */
            if (even) {
                median = (values[halfway - 1] + values[halfway]) / 2.0;
            } else {
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
            return values.slice(0, Math.floor(values.length / 2));
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
                return values.slice(Math.floor(values.length / 2), values.length);
            }

            return values.slice(Math.ceil(values.length / 2), values.length);
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
                maximum: findMaximum(config.values),
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
         * Generate a list of outliers
         *
         * Decide where the whiskers will be
         * Use a Tukey box plot
         */
        function generateOutliers(fivePointSummary, iqr) {
            var allOutliers = [];
            var minimum = fivePointSummary.minimum;
            var maximum = fivePointSummary.maximum;
            var i;

            /* Lower whisker
             * Use the first value that is above the lowerThreshold
             */
            var lowerThreshold = fivePointSummary.quartile1 - 1.5 * iqr;

            for (i = config.values.length - 1; i >= 0; i--) {
                if (config.values[i] > lowerThreshold) {
                    minimum = config.values[i];
                }
            }

            if (minimum != null) {
                minimum = parseFloat(minimum.toFixed(1));
            }

            /* Upper whisker
             * Use the first value that is below the upperThreshold
             */
            var upperThreshold = fivePointSummary.quartile3 + 1.5 * iqr;

            for (i = 0; i < config.values.length; i++) {
                if (config.values[i] < upperThreshold) {
                    maximum = config.values[i];
                }
            }

            if (maximum != null) {
                maximum = parseFloat(maximum.toFixed(1));
            }

            /* Outlier stack
             * Stack with information about the outlier,
             * not just its value
             */
            for (i = 0; i < config.values.length; i++) {
                if (config.values[i] < lowerThreshold || config.values[i] > upperThreshold) {
                    allOutliers.push(config.input[i]);
                }
            }

            var data = {
                outliers: allOutliers,
                minimum: minimum,
                maximum: maximum,
            };

            return data;
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

            /* Decide outliers
             * Amend min/max values where required due to outlier derivation
             */
            var outliers = generateOutliers(fivePointSummary, iqr);
            output.outliers = outliers.outliers;
            output.fivePointSummary.structured.minimum = outliers.minimum;
            output.fivePointSummary.structured.maximum = outliers.maximum;

            /* Convert into an array format
             */
            output.fivePointSummary.array = [
                output.fivePointSummary.structured.minimum,
                output.fivePointSummary.structured.quartile1,
                output.fivePointSummary.structured.quartile2,
                output.fivePointSummary.structured.quartile3,
                output.fivePointSummary.structured.maximum,
            ];

            return output;
        }
    };
})(jQuery);
