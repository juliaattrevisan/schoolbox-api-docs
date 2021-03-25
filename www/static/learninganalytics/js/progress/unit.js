(function() {

    /**
     * Render unit progress graph
     *
     * @param options
     */
    $.fn.unitProgressRender = function(options) {
        var options = options || {
            element: '',
            title: 'Unit Progress',
            clusterName: 'Class', // or 'Cohort'

            /* All unit names
             */
            units: [],

            /* A series of values corresponding to % of available tasks
             * completed by one student who has done the most
             */
            mostAdvancedStudents: [],

            /* A series of values corresponding to % of tasks completed
             * by all students
             */
            classCompletions: []
        };

        /* Data formatting
         */
        var formattedMostAdvancedStudents = [];

        for (i = 0; i < options.mostAdvancedStudents.length; i++) {
            formattedMostAdvancedStudents[i] = parseFloat(options.mostAdvancedStudents[i].toFixed(1))
        }

        options.mostAdvancedStudents = formattedMostAdvancedStudents;

        var formattedClassCompletions = [];

        for (i = 0; i < options.classCompletions.length; i++) {
            formattedClassCompletions[i] = parseFloat(options.classCompletions[i].toFixed(1))
        }

        options.classCompletions = formattedClassCompletions;

        /* The plot unfolds
         */
        $(options.element).highcharts({
            /* lol use an inverted column chart instead of bar
             * chart to simulate an overlaid bar chart.
             */
            chart: {
                type: 'column',
                inverted: true
            },
            title: {
                text: options.title
            },
            xAxis: {
                categories: options.units
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
                    data: options.mostAdvancedStudents
                },
                {
                    name: options.clusterName + ' Completion',
                    data: options.classCompletions
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
