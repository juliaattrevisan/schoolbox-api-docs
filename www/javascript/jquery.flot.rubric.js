function setupRubricChart(data, legs, ticks) {
    var spidata = {
        points: [
            {
                data: data,
                spider: {
                    show: true,
                    lineWidth: 4
                }
            }
        ],
        legs: legs,
        ticks: ticks
    };

    var options = {
        series: {
            spider: {
                active: true,
                hightlight: {
                    mode: 'area',
                    opacity: 0.9
                },
                legs: {
                    data: spidata.legs,
                    font: '11px Arial',
                    legStartAngle: 270,
                    legScaleMin: 0,
                    legScaleMax: 1
                },
                connection: {
                    width: 2
                },
                pointSize: 3,
                scaleMode: 'others',
                legMin: 0,
                legMax: spidata.ticks,
                spiderSize: 0.9,
                lineWidth: 1,
                lineStyle: 'rgba(0,0,0,0.1)'
            }
        },
        grid: {
            hoverable: false,
            clickable: false,
            tickColor: 'rgba(0,0,0,0.15)',
            ticks: spidata.ticks,
            mode: 'radar'
        }
    };

    return { spidata: spidata, options: options };
}

