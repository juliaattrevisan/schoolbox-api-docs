<script setup lang="ts">
/**
 * Table row component for pastoral reports.
 * Renders a single total row with cells based on column types.
 */

import type { TableHeader, RowOptions, RecordsAndPoints } from "../js/types/pastoralReportTypes";

type Props = {
    headers: TableHeader[];
    rowData: RowOptions[];
    recordsOrPoints: string;
};
const props = defineProps<Props>();
</script>

<template>
    <tr>
        <td v-for="(data, index) in props.rowData" :key="props.headers[index]?.key || index">
            <!-- The first cell is always just 'Total' text -->
            <template v-if="index === 0">
                <b>{{ data }}</b>
            </template>
            <template v-else-if="props.headers[index].columnType == 'RecordsAndPoints'">
                <!-- Change data based on current radio button selection -->
                <template v-if="props.recordsOrPoints === 'Records'">
                    {{ (data as RecordsAndPoints).records }}
                </template>
                <template v-else>
                    {{ (data as RecordsAndPoints).points }}
                </template>
            </template>
            <template v-else>
                {{ data }}
            </template>
        </td>
    </tr>
</template>

<style scoped lang="scss">
td {
    background: lightgray;
    border: 1px solid red;
}
</style>
