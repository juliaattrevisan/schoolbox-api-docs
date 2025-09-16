<script setup lang="ts">
/**
 * Table row component for pastoral report tables.
 * Renders a single total row with cells based on column types.
 */

import type { Ref } from "vue";
import { Chart } from "highcharts-vue";
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { TableHeaderType } from "../../js/types/pastoralReportTypes";
import type { TableHeader, RowOptions, RecordsAndPoints, Tab } from "../../js/types/pastoralReportTypes";
import { getChartOptions } from "./config/chartOptions";
import { getMeta } from "../util/pastoralReportUtils";

const props = defineProps<{
    headers: TableHeader[];
    rowData: { [key: string]: RowOptions };
    recordsOrPoints: "records" | "points";
    isTotalSticky: boolean;
}>();

const emit = defineEmits<{
    cellSelect: [header: TableHeader, meta: string[]];
}>();

const selectedTab = window.Vue.inject<Ref<Tab>>("selectedTab");

/**
 * Handles the cell click
 * @param header - the header of the cell that was clicked
 */
function onCellSelected(header: TableHeader): void {
    if (selectedTab === undefined) {
        return;
    }
    emit("cellSelect", header, getMeta(selectedTab.value, header));
}
</script>

<template>
    <tr class="total-row">
        <td
            v-for="(header, index) in props.headers"
            :key="header.key"
            :class="`u-border-right ${index === 0 ? 'u-sticky-left u-sticky-left--row' : ''} ${header.key === 'total' ? 'total-cell' : ''}  ${header.key === 'total' && props.isTotalSticky ? 'u-sticky-left u-sticky-left--row' : ''}`"
        >
            <!-- The first cell is always just 'Total Records/Points' text -->
            <template v-if="header.key === props.headers[0].key">
                <b>{{ props.rowData[header.key] }} {{ capitalizeFirstLetter(props.recordsOrPoints) }}</b>
            </template>
            <!-- We don't need to make the total cell selectable, as it would just be a duplicate result list of the List tab -->
            <template v-else-if="header.key === 'total'">
                <!-- Change data based on current radio button selection -->
                <!-- Only show records if they're above 0 -->
                <template
                    v-if="
                        props.recordsOrPoints === 'records' &&
                        (props.rowData[header.key] as RecordsAndPoints)?.records! > 0
                    "
                >
                    {{ (props.rowData[header.key] as RecordsAndPoints).records }}
                </template>
                <!-- Only show if points exist -->
                <template
                    v-else-if="
                        props.recordsOrPoints === 'points' &&
                        (props.rowData[header.key] as RecordsAndPoints)?.points! !== null
                    "
                >
                    {{ (props.rowData[header.key] as RecordsAndPoints).points }}
                </template>
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
            <template v-else-if="header.type == TableHeaderType.TREND">
                <chart :options="getChartOptions(props.rowData, props.recordsOrPoints)"></chart>
            </template>
            <template v-else>
                {{ props.rowData[header.key] }}
            </template>
        </td>
    </tr>
</template>

<style lang="scss">
/* to make the totals more prominent in comparison to the rows below */
tr.total-row {
    background: var(--sbx-pr-table-total-cell-colour);
    td.u-sticky-left {
        background: var(--sbx-pr-table-total-cell-colour);
        &:hover {
            background-color: var(--sbx-pr-table-row-hover-colour);
        }
    }
    .highcharts-background {
        fill: var(--sbx-pr-table-total-cell-colour);
    }
}

.total-cell {
    left: var(--sbx-pr-table-first-column-width); // will only be used when sticky
}
</style>
