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

        console.log(options.allData);
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
                        headerFormat: '<b>{series.name}</b><br>',
                        pointFormat: '{point.x} hits, {point.y}%'
                    }
                }
            },

            series: [
                {
                    name: 'Blap',
                    color: 'rgba(223, 83, 83, .5)',
                    data: options.allData,
                    tooltip: {
                        headerFormat: ''
                    }
                }
            ]
        });
    }
}(jQuery));