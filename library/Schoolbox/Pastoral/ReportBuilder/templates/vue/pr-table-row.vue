<script setup lang="ts">
/**
 * Table row component for pastoral reports tables.
 * Renders a single row with cells based on column types.
 */

import type { User } from "@schoolboxLibrary/types/User";
import { TableHeaderType } from "../js/types/pastoralReportTypes";
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
            <template v-if="props.headers[index].type == TableHeaderType.USER">
                {{ (data as User).fullName }}
            </template>
            <template v-else-if="props.headers[index].type == TableHeaderType.RECORDS_AND_POINTS">
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
    border: 1px solid red;
}
</style>
