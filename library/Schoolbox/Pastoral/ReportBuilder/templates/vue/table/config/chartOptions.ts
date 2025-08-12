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
        width: 200,
    },
    colors: ["#000"],
    credits: {
        enabled: false,
    },
    legend: {
        enabled: false,
    },
    title: {
        text: undefined,
    },
    tooltip: {
        formatter(this: Point): string {
            return `The value for <b>${this.key}</b> is <b>${this.y}</b>`;
        },
        valueSuffix: "Points",
    },
    series: [
        {
            data: [],
            name: "Points",
            type: "area" as const,
        },
    ],
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
 * @returns - The chart options for the trend.
 */
export function getChartOptions(rowData: { [key: string]: RowOptions }): Options {
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
                ...trendOptions.series[0],
                data: trend.line,
            },
        ],
        xAxis: {
            ...trendOptions.xAxis,
            categories: trend.tooltips,
        },
    };
}
