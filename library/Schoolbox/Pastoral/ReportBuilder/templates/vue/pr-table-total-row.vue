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
    recordsOrPoints: "records" | "points";
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
                <b>{{ props.rowData[header.key] }} {{ props.recordsOrPoints }}</b>
            </template>
            <template v-else-if="header.type == TableHeaderType.RECORDS_AND_POINTS">
                <!-- Change data based on current radio button selection -->
                <Transition name="fade" mode="out-in">
                    <span :key="`${props.recordsOrPoints}-${header.key}`">
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
    background: var(--sbx-pr-table-total-cell-colour);
}
</style>
