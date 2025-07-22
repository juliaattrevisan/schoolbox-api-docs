<script setup lang="ts">
/**
 * Table row component for pastoral report tables.
 * Renders a single total row with cells based on column types.
 */

import { TableHeaderType } from "../js/types/pastoralReportTypes";
import type { TableHeader, RowOptions, RecordsAndPoints } from "../js/types/pastoralReportTypes";

type Props = {
    headers: TableHeader[];
    rowData: { [key: string]: RowOptions };
    recordsOrPoints: string;
};
const props = defineProps<Props>();
</script>

<template>
    <tr>
        <td
            v-for="header in props.headers"
            :key="header.key"
            :class="`u-border-right ${header.key === 'user' ? 'u-sticky-left u-sticky-left--row' : ''}`"
        >
            <!-- The first cell is always just 'Total' text -->
            <template v-if="header.key === props.headers[0].key">
                <b>{{ props.rowData[header.key] }}</b>
            </template>
            <template v-else-if="header.type == TableHeaderType.RECORDS_AND_POINTS">
                <!-- Change data based on current radio button selection -->
                <Transition name="fade" mode="out-in">
                    <span :key="`${props.recordsOrPoints}-${header.key}`">
                        <template v-if="props.recordsOrPoints === 'Records'">
                            {{ (props.rowData[header.key] as RecordsAndPoints).records }}
                        </template>
                        <template v-else>
                            {{ (props.rowData[header.key] as RecordsAndPoints).points }}
                        </template>
                    </span>
                </Transition>
            </template>
            <template v-else>
                {{ props.rowData[header.key] }}
            </template>
        </td>
    </tr>
</template>

<style scoped lang="scss">
td {
    background: lightgray;
}
</style>
