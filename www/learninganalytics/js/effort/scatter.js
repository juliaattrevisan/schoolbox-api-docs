(function() {

    /**
     * Render scatter graphs
     *
     * @param options
     */
    $.fn.scatterRender = function(options) {
        var options = options || {
            element: '',
            title: '',
            orientation: 'vertical',
            allData: []
        };

        /* The plot unfolds
         */
        $(options.element).highcharts({

            /* can use inverted:true to make it horizontal'
             */
            chart: {
                type: 'scatter',
                zoomType: 'xy'
            },

            title: {
                text: options.title
            },

            legend: {
                enabled: false
            },

            xAxis: {
                min: 0,
                title: {
                    enabled: true,
                    text: 'Homepage Visits'
                },
                startOnTick: true,
                endOnTick: true,
                showLastLabel: true
            },

            yAxis: {
                min: 0,
                max: 100,
                title: {
                    text: 'Grade Average'
                }
            },

            tooltip: {
                useHTML: true
            },

            plotOptions: {
                scatter: {
                    marker: {
                        radius: 5,
                        states: {
                            hover: {
                                enabled: true,
                                lineColor: 'rgb(100,100,100)'
                            }
                        }
                    },
                    states: {
                        hover: {
                            marker: {
                                enabled: false
                            }
                        }
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
                        pointFormatter: function() {

                            /* Basic template for each outlier
                             */
                            var template =
                                '<tr>' +
                                    '<td>' +
                                        '<span style="display:block;width:32px;height:32px;background-image:url(/portrait.php?userId={UID}&size=square32);"></span>' +
                                    '</td>' +
                                    '<td>' +
                                        '<em>{NAME}</em><br />{VALUE}' +
                                    '</td>' +
                                '</tr>';

                            /* Concatenate everything
                             */
                            var allTooltipContent = '';

                            for (var i = 0; i < options.allData.length; i++) {
                                if ((options.allData[i].value[0] == this.x) && (options.allData[i].value[1] == this.y)) {
                                    var tooltipContent = template;

                                    tooltipContent = tooltipContent.replace(/{UID}/, options.allData[i].uid);
                                    tooltipContent = tooltipContent.replace(/{NAME}/, options.allData[i].info);
                                    tooltipContent = tooltipContent.replace(/{VALUE}/, this.y.toFixed(1) + '%');

                                    allTooltipContent += tooltipContent;
                                }
                            }

                            return '<table><tbody>' + allTooltipContent + '</tbody></table>';
                        }
                    }
                }
            },

            series: [
                {
                    name: 'Grade Average vs Hits',
                    data: $.fn.scatterCalculateSeriesData(options.allData),
                    tooltip: {
                        headerFormat: ''
                    }
                }
            ]
        });
    }

    /**
     * Some preprocessing for highcharts
     *
     * @param input
     * @returns {Array}
     */
    $.fn.scatterCalculateSeriesData = function(input) {
        var output = [];

        for (i = 0; i < input.length; i++) {
            output.push(input[i].value);
        }

        return output;
    }
}(jQuery));