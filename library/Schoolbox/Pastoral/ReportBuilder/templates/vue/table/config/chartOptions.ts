/**
 * Return the preset chart options for cells in the Pastoral Report Table that use highcharts.
 * Refer to https://api.highcharts.com/highcharts/ for more details.
 */

import type { Point, Options } from "highcharts";
import type { RowOptions, Trend } from "../../../js/types/pastoralReportTypes";

export const trendOptions: Options = {
    accessibility: {
        description: "Trend of points over the year for type/subtype",
    },
    boost: {
        useGPUTranslations: true,
    },
    chart: {
        height: 50,
        spacing: [5, 0, 5, 0],
        width: 200,
    },
    colors: ["#000"],
    credits: {
        enabled: false,
    },
    legend: {
        enabled: false,
    },
    plotOptions: {
        area: {
            marker: {
                enabled: false,
                symbol: "circle",
                radius: 2,
                states: {
                    hover: {
                        enabled: true,
                    },
                },
            },
        },
    },
    series: [
        {
            data: [],
            name: "Points",
            type: "area",
        },
    ],
    title: {
        text: undefined,
    },
    xAxis: {
        categories: [],
        title: {
            text: "Months",
        },
        visible: false,
    },
    yAxis: {
        visible: false,
    },
};

/**
 * Chart options for the trend chart in the table row.
 * @param rowData - The data for the row, which includes the trend information.
 * @param recordsOrPoints - The type of data to display (records or points).
 * @returns - The chart options for the trend.
 */
export function getChartOptions(
    rowData: { [key: string]: RowOptions },
    recordsOrPoints: "records" | "points",
): Options {
    const trend = rowData.trend as Trend;
    if (trend === undefined) {
        return trendOptions;
    }
    return {
        ...trendOptions,
        series: [
            {
                color: recordsOrPoints === "points" ? "#4fa74f" : "#000",
                data: trend.line,
                name: recordsOrPoints,
                negativeColor: recordsOrPoints === "points" ? "#fa4a47" : "#000",
                point: {
                    events: {
                        click(this: Point): void {
                            if (this.y !== 0) {
                                console.warn("open modal?");
                            }
                        },
                    },
                },
                type: "area" as const,
            },
        ],
        tooltip: {
            formatter(this: Point): string | false {
                const withoutSuffix = recordsOrPoints.substring(0, recordsOrPoints.length - 1);
                return `<b>${this.key}:</b> ${this.y} ${this.y === 1 || this.y === -1 ? withoutSuffix : withoutSuffix + "s"}`;
            },
            valueSuffix: recordsOrPoints,
        },
        xAxis: {
            ...trendOptions.xAxis,
            categories: trend.tooltips,
        },
    };
}
