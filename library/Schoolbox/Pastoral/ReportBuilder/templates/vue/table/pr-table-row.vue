<script setup lang="ts">
/**
 * Table row component for pastoral reports tables.
 * Renders a single row with cells based on column types.
 */

import type { User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { TableHeaderType } from "../../js/types/pastoralReportTypes";
import type { TableHeader, RowOptions, RecordsAndPoints } from "../../js/types/pastoralReportTypes";

const props = defineProps<{
    headers: TableHeader[];
    rowData: { [key: string]: RowOptions };
    recordsOrPoints: "records" | "points";
}>();

const emit = defineEmits<{
    cellSelect: [header: TableHeader, rowId: number];
}>();

/**
 * Returns the row id, currently if there's a 'user' key in rowData,
 * it'll return the user's id, otherwise it defaults to 0.
 * @returns - The id of the row, or 0 if not defined.
 */
const rowId = (): number => {
    // check if there's a 'user' key in rowData and return its id if it exists
    const user = props.rowData.user as User;
    return user?.id || 0; // Default to 0 if user is not defined
};

/**
 * Handles the cell click
 * @param header - the header of the cell that was clicked
 */
function onCellSelected(header: TableHeader): void {
    emit("cellSelect", header, rowId());
}

/**
 * To cast the row data to a User type
 * @param data - the row data to check
 * @returns true if the data is a User, false otherwise
 */
function isUser(data: RowOptions): data is User {
    return (data as User).firstName !== undefined && (data as User).lastName !== undefined;
}
</script>

<template>
    <tr>
        <td
            v-for="header in props.headers"
            :key="header.key"
            :class="`u-border-right ${header.key === 'user' ? 'u-sticky-left u-sticky-left--row' : ''} ${header.key === 'total' ? 'total-cell' : ''}`"
        >
            <template v-if="header.type == TableHeaderType.USER && isUser(props.rowData[header.key])">
                <div class="c-card">
                    <div class="l-flex-row l-gap-m">
                        <user-photo :user="props.rowData[header.key] as User" render="small" rounded-corners />
                        <div class="l-flex-col l-flex--justify-center f-no-margins">
                            <template v-if="isUser(props.rowData[header.key])">
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
                            </template>
                        </div>
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
            <template v-else>
                {{ props.rowData[header.key] }}
            </template>
        </td>
    </tr>
</template>

<style scoped lang="scss">
.total-cell {
    background: var(--sbx-pr-table-total-cell-colour);
}
</style>
