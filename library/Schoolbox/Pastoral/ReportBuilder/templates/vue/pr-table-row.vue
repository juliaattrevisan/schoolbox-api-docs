<script setup lang="ts">
/**
 * Table row component for pastoral reports.
 * Renders a single row with cells based on column types.
 */

import type { User } from "@schoolboxLibrary/types/User";
import type { PastoralReportTableHeader, RowOptions, RecordsAndPoints } from "../js/types/pastoralReportTypes";

type Props = {
    headers: PastoralReportTableHeader[];
    rowData: RowOptions[];
};
const props = defineProps<Props>();
</script>

<template>
    <tr>
        <td v-for="(data, index) in props.rowData" :key="props.headers[index]?.key || index">
            <template v-if="props.headers[index].columnType == 'User'">
                {{ (data as User).fullName }}
            </template>
            <template v-else-if="props.headers[index].columnType == 'RecordsAndPoints'">
                {{ (data as RecordsAndPoints).records }}
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
