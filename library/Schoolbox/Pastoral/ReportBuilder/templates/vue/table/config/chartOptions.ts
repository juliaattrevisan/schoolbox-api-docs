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
        height: 44,
        spacing: [10, 0, 10, 0],
        width: 200,
    },
    credits: {
        enabled: false,
    },
    legend: {
        enabled: false,
    },
    plotOptions: {
        area: {
            marker: {
                radius: 2,
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
        endOnTick: false, // so the plotline is shown with at least one value
        labels: {
            enabled: false, // Hide tick text/labels
        },
        plotLines: [
            // Force the axis to always include 0
            {
                color: "var(--sbx-color-text-primary, #000)",
                width: 2,
                value: 0,
            },
        ],
        softMax: 1, // Ensure positive values have some space
        softMin: -1, // Ensure negative values have some space
        startOnTick: false, // so the plotline is shown with at least one value
        title: {
            text: undefined, // to hide the text
        },
    },
};

/**
 * Chart options for the trend chart in the table row.
 * @param rowData - The data for the row, which includes the trend information.
 * @param recordsOrPoints - The type of data to display (records or points).
 * @returns - The chart options for the trend.
 */
export function getChartOptions(rowData: Record<string, RowOptions>, recordsOrPoints: "records" | "points"): Options {
    const trend = rowData.trend as Trend;
    if (trend === undefined) {
        return trendOptions;
    }
    return {
        ...trendOptions,
        series: [
            {
                color:
                    recordsOrPoints === "points"
                        ? "var(--sbx-color-success, #4fa74f)"
                        : "var(--sbx-color-accent, var(--accent-foreground))",
                data: trend.line,
                name: recordsOrPoints,
                negativeColor:
                    recordsOrPoints === "points"
                        ? "var(--sbx-color-alert, #fa4a47)"
                        : "var(--sbx-color-accent, var(--accent-foreground))",
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
                const pluralSuffix = this.y === 1 || this.y === -1 ? withoutSuffix : `${withoutSuffix}s`;
                return `<b>${this.key}:</b> ${this.y} ${pluralSuffix}`;
            },
            hideDelay: 0,
            outside: true,
            valueSuffix: recordsOrPoints,
        },
        xAxis: {
            ...trendOptions.xAxis,
            categories: trend.tooltips,
        },
    };
}
