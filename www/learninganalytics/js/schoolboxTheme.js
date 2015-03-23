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
        plotBorderColor: '#00ff00',
        plotOptions: {
            column: {

                /* You can't use colors array, because colorsByPoint needs
                 * to be set to true, and the data in overallProgress and
                 * nitProgress are two separate series. They'll end up with
                 * the same color. Instead, use the default stack of series
                 * colours at the top
                 */

                states: {
                    hover: {
                        brightness: 0.1
                    }
                }
            },
            boxplot: {
                color: '#0066b0',
                fillColor: '#bcd7ea'
            },
            scatter: {
                color: 'rgba(0,102,176, .5)'
            }
        }
    };

    Highcharts.setOptions(schoolboxTheme);
});