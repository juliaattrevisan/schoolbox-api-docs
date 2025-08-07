/**
 * Return the preset chart options for cells in the Pastoral Report Table that use highcharts.
 * Refer to https://api.highcharts.com/highcharts/ for more details.
 */

import type { Point } from "highcharts";

export const trendOptions = {
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
            type: "line" as const,
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
