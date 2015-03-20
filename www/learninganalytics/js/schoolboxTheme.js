$(function () {
    var schoolboxTheme = {
        colors: [
            /* First series color: also default colour of
             * borders and stuff
             */
            '#ddebf4',

            /* Second series color
             */
            '#0066b0'
        ],
        plotBorderColor: '#00ff00',
        plotOptions: {
            boxplot: {
                color: '#0066b0',
                fillColor: '#ddebf4'
            }
        }
    };

    Highcharts.setOptions(schoolboxTheme);
});