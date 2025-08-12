<script setup lang="ts">
/**
 * Table row component for pastoral reports tables.
 * Renders a single row with cells based on column types.
 */

import { Chart } from "highcharts-vue";
import "highcharts/modules/accessibility";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { TableHeaderType } from "../../js/types/pastoralReportTypes";
import type {
    TableHeader,
    RowOptions,
    RecordsAndPoints,
    TypeSubType,
    RowKeys,
    Trend,
} from "../../js/types/pastoralReportTypes";
import { trendOptions } from "./config/chartOptions";

const props = defineProps<{
    headers: TableHeader[];
    rowData: { [key: string]: RowOptions };
    recordsOrPoints: "records" | "points";
}>();

const emit = defineEmits<{
    cellSelect: [header: TableHeader, rowKeys: RowKeys];
}>();

/**
 * Returns the row id, currently if there's a 'user' key in rowData,
 * it'll return the user's id, otherwise it defaults to 0.
 * @returns - The id of the row, or 0 if not defined.
 */
const rowKeys = (): RowKeys => {
    // check if there's a 'user' key in rowData and return its id if it exists
    const user = props.rowData.user as User;
    return {
        student_id: user?.id || 0,
    };
};

/**
 * Handles the cell click
 * @param header - the header of the cell that was clicked
 */
function onCellSelected(header: TableHeader): void {
    emit("cellSelect", header, rowKeys());
}

/**
 * Chart options for the trend chart in the table row.
 */
function getChartOptions() {
    const trend = props.rowData.trend as Trend;
    // run through the line and make any 0 values null
    trend.line = trend.line.map((value: number | null) => (value === 0 ? null : value));
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
</script>

<template>
    <tr>
        <td
            v-for="(header, index) in props.headers"
            :key="header.key"
            :class="`u-border-right ${index === 0 ? 'u-sticky-left u-sticky-left--row ' : ''} ${header.key === 'user' ? 'l-pad--0' : ''} ${header.key === 'total' ? 'total-cell' : ''}`"
        >
            <template v-if="header.type == TableHeaderType.USER">
                <div class="l-flex-row l-gap-m">
                    <user-photo :user="props.rowData[header.key] as User" render="small" />
                    <div class="l-flex-col l-flex--justify-center f-no-margins">
                        <a
                            v-if="(props.rowData[header.key] as User)._links?.profile"
                            :href="(props.rowData[header.key] as User)._links!.profile"
                            title="View Profile"
                        >
                            {{ (props.rowData[header.key] as User).lastName }},
                            {{
                                (props.rowData[header.key] as User).preferredName ||
                                (props.rowData[header.key] as User).firstName
                            }}
                        </a>
                        <template v-else>
                            {{ (props.rowData[header.key] as User).lastName }},
                            {{
                                (props.rowData[header.key] as User).preferredName ||
                                (props.rowData[header.key] as User).firstName
                            }}
                        </template>
                    </div>
                </div>
            </template>
            <template v-else-if="header.type == TableHeaderType.RECORDS_AND_POINTS">
                <!-- Change data based on current radio button selection -->
                <!-- Only show records if they're above 0 -->
                <button
                    v-if="
                        props.recordsOrPoints === 'records' &&
                        (props.rowData[header.key] as RecordsAndPoints)?.records! > 0
                    "
                    class="f-always-show u-td-button"
                    @click.prevent="onCellSelected(header)"
                >
                    {{ (props.rowData[header.key] as RecordsAndPoints).records }}
                </button>
                <!-- Only show if points exist -->
                <button
                    v-else-if="
                        props.recordsOrPoints === 'points' &&
                        (props.rowData[header.key] as RecordsAndPoints)?.points! !== null
                    "
                    class="f-always-show u-td-button"
                    @click.prevent="onCellSelected(header)"
                >
                    {{ (props.rowData[header.key] as RecordsAndPoints).points }}
                </button>
            </template>
            <template v-else-if="header.type == TableHeaderType.TYPE_SUB_TYPE">
                <b>{{ (props.rowData[header.key] as TypeSubType).type }}</b>
                <template v-if="(props.rowData[header.key] as TypeSubType).subtype">
                    <br /><em>{{ (props.rowData[header.key] as TypeSubType).subtype }}</em>
                </template>
            </template>
            <template v-else-if="header.type == TableHeaderType.TREND">
                <chart :options="getChartOptions()"></chart>
            </template>
            <template v-else>
                {{ props.rowData[header.key] }}
            </template>
        </td>
    </tr>
</template>

<style scoped lang="scss">
tr:hover {
    background-color: #f4f3f3; /* to help legibility */
}

.total-cell {
    background: var(--sbx-pr-table-total-cell-colour);
}
</style>
