$(function () {
    var schoolboxTheme = {
        colors: [
            /* First series color: also default colour of
             * borders and stuff
             */
            '#bcd7ea',

            /* Second series color
             */
            '#0066b0'
        ],
        plotOptions: {

            /* Stacked Progress Bars
             *
             * You can't use colors array, because colorsByPoint needs
             * to be set to true, and the data in overallProgress and
             * nitProgress are two separate series. They'll end up with
             * the same color. Instead, use the default stack of series
             * colours at the top
             */
            column: {
                states: {
                    hover: {
                        brightness: 0.1
                    }
                }
            },

            /* Boxplots
             */
            boxplot: {
                color: '#0066b0',
                fillColor: '#bcd7ea'
            },

            /* Effort
             */
            scatter: {
                color: 'rgba(0,102,176, .5)'
            }
        },

        credits: {
            enabled: false
        }
    };

    Highcharts.setOptions(schoolboxTheme);
});