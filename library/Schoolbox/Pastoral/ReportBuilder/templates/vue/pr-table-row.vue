<script setup lang="ts">
/**
 * Table row component for pastoral reports tables.
 * Renders a single row with cells based on column types.
 */

import type { User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { TableHeaderType } from "../js/types/pastoralReportTypes";
import type { TableHeader, RowOptions, RecordsAndPoints } from "../js/types/pastoralReportTypes";

type Props = {
    headers: TableHeader[];
    rowData: RowOptions[];
    recordsOrPoints: string;
};
const props = defineProps<Props>();

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
            v-for="(data, index) in props.rowData"
            :key="props.headers[index]?.key || index"
            :class="`u-border-right ${props.headers[index].key === 'total' ? 'total-cell' : ''}`"
        >
            <template v-if="props.headers[index].type == TableHeaderType.USER && isUser(data)">
                <div class="c-card">
                    <div class="l-flex-row l-gap-m">
                        <user-photo :user="data" render="small" rounded-corners />
                        <div class="l-flex-col l-flex--justify-center f-no-margins">
                            <a v-if="data._links?.profile" :href="data._links.profile" title="View Profile">
                                {{ data.lastName }}, {{ data.preferredName || data.firstName }}
                            </a>
                            <template v-else>
                                {{ data.lastName }}, {{ data.preferredName || data.firstName }}
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <template v-else-if="props.headers[index].type == TableHeaderType.RECORDS_AND_POINTS">
                <!-- Change data based on current radio button selection -->
                <Transition name="fade" mode="out-in">
                    <span :key="`${props.recordsOrPoints}-${index}`">
                        <template v-if="props.recordsOrPoints === 'Records'">
                            {{ (data as RecordsAndPoints).records > 0 ? (data as RecordsAndPoints).records : "" }}
                        </template>
                        <template v-else>
                            {{ (data as RecordsAndPoints).records > 0 ? (data as RecordsAndPoints).points : "" }}
                        </template>
                    </span>
                </Transition>
            </template>
            <template v-else>
                {{ data }}
            </template>
        </td>
    </tr>
</template>

<style scoped lang="scss">
.total-cell {
    background: lightgray;
}
</style>
