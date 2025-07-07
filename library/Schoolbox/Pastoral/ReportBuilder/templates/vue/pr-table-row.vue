<script setup lang="ts">
/**
 * Table row component for pastoral reports.
 * Renders a single row with cells based on column types.
 */

import type { PastoralReportTableHeader, ColumnUser, ColumnRecordsAndPoints } from "../js/types/pastoralReportTypes";

type Props = {
    headers: PastoralReportTableHeader[];
    rowData: (ColumnUser | ColumnRecordsAndPoints)[];
    isTotal?: boolean;
};
const props = withDefaults(defineProps<Props>(), {
    isTotal: false,
});

/**
 * Gets the content for a table cell based on the column type and data
 * @param data - the column data (either ColumnUser or ColumnRecordsAndPoints)
 * @param columnType - the type of column being rendered
 * @returns the formatted content string for the cell
 */
function getCellContent(data: ColumnUser | ColumnRecordsAndPoints, columnType: string): string {
    switch (columnType) {
        case "ColumnUser":
            return (data as ColumnUser).fullName;
        case "ColumnRecordsAndPoints":
        case "ColumnTotalRecordsAndPoints": {
            const recordsData = data as ColumnRecordsAndPoints;
            return `${recordsData.records} records, ${recordsData.points} points`;
        }
        default:
            return String(data);
    }
}

/**
 * Checks if a column is a user column type
 * @param columnType - the column type to check
 * @returns true if the column is a user column
 */
function isUserColumn(columnType: string): boolean {
    return columnType === "ColumnUser";
}

/**
 * Checks if a column is a records column type
 * @param columnType - the column type to check
 * @returns true if the column is a records or total records column
 */
function isRecordsColumn(columnType: string): boolean {
    return columnType === "ColumnRecordsAndPoints" || columnType === "ColumnTotalRecordsAndPoints";
}
</script>

<template>
    <tr
        :class="{
            'pr-table-row': true,
            'pr-table-row--total': props.isTotal,
        }"
    >
        <td
            v-for="(data, index) in props.rowData"
            :key="props.headers[index]?.key || index"
            :class="{
                'pr-table-row__cell': true,
                'pr-table-row__cell--user': isUserColumn(props.headers[index]?.columnType || ''),
                'pr-table-row__cell--records': isRecordsColumn(props.headers[index]?.columnType || ''),
                'pr-table-row__cell--total': props.isTotal,
            }"
        >
            <div v-if="isUserColumn(props.headers[index]?.columnType || '')" class="pr-table-row__user">
                <div class="pr-table-row__user-name">
                    {{ (data as ColumnUser).fullName }}
                </div>
            </div>

            <div v-else-if="isRecordsColumn(props.headers[index]?.columnType || '')" class="pr-table-row__records">
                <div class="pr-table-row__records-count">
                    <span class="pr-table-row__records-number">{{ (data as ColumnRecordsAndPoints).records }}</span>
                    <span class="pr-table-row__records-label">records</span>
                </div>
                <div class="pr-table-row__points-count">
                    <span class="pr-table-row__points-number">{{ (data as ColumnRecordsAndPoints).points }}</span>
                    <span class="pr-table-row__points-label">points</span>
                </div>
            </div>

            <div v-else class="pr-table-row__content">
                {{ getCellContent(data, props.headers[index]?.columnType || "") }}
            </div>
        </td>
    </tr>
</template>

<style scoped lang="scss">
td {
    border: 1px solid red;
}
</style>
