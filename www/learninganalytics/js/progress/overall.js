(function() {

    /**
     * Render overall progress graph
     *
     * @param options
     */
    $.fn.overallProgressRender = function(options) {
        var options = options || {
            element: '',
            title: 'Overall Progress',
            mostAdvancedStudent: 0,
            classCompletions: 0
        };

        /* The plot unfolds
         */
        $(options.element).highcharts({
            /* lol use an inverted column chart instead of bar
             * chart to simulate an overlaid bar chart.
             */
            chart: {
                type: 'column',
                inverted: true,
                height: 160
            },
            title: {
                text: options.title
            },
            xAxis: {
                labels: {
                    enabled: false
                },
                categories: ['Most Advanced Student', 'Entire Class'],

                /* Hide useless ticketty-boo lines (you need all
                 * of these values, seemingly)
                 */
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                minorTickLength: 0,
                tickLength: 0
            },
            yAxis: {
                min: 0,
                max: 100,
                title: {
                    text: 'Percentage Complete %'
                }
            },

            /* This is used to ensure that Entire Class bar always
             * appears over Most Advanced Student bar when the
             * Most Advanced Student has an equal value to the
             * Entire Class value
             */
            legend: {
                reversed: true
            },
            plotOptions: {
                column: {
                    grouping: false
                }
            },
            series: [
                {
                    name: 'Most Advanced Student',
                    data: [parseFloat(options.mostAdvancedStudent.toFixed(1))]
                },
                {
                    name: 'Class Completions',
                    data: [parseFloat(options.classCompletions.toFixed(1))]
                }
            ],
            tooltip: {
                formatter: function() {
                    return this.series.name + ': ' + this.y + '%';
                }
            }
        });
    }
}(jQuery));