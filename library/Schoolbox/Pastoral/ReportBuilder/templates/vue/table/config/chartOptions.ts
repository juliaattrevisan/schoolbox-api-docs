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
        className: "highcharts-trend-chart",
        height: 50,
        width: 200,
    },
    colors: ["#000"],
    credits: {
        enabled: false,
    },
    legend: {
        enabled: false,
    },
    series: [
        {
            data: [],
            name: "Points",
            type: "area" as const,
        },
    ],
    title: {
        text: undefined,
    },
    tooltip: {
        formatter(this: Point): string {
            return `<b>${this.key}:</b> ${this.y} records`;
        },
        valueSuffix: "Points",
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
    // run through the line and make any 0 values null
    // trend.line = trend.line.map((value: number | null) => (value === 0 ? null : value));
    if (trend === undefined) {
        return trendOptions;
    }
    return {
        ...trendOptions,
        series: [
            {
                data: trend.line,
                name: recordsOrPoints,
                type: "area" as const,
            },
        ],
        tooltip: {
            formatter(this: Point): string {
                return `<b>${this.key}:</b> ${this.y} ${recordsOrPoints}`;
            },
            valueSuffix: recordsOrPoints,
        },
        xAxis: {
            ...trendOptions.xAxis,
            categories: trend.tooltips,
        },
    };
}
