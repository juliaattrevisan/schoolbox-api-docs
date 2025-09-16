<script setup lang="ts">
/**
 * Table row component for pastoral reports tables.
 * Renders a single row with cells based on column types.
 */

import type { Ref } from "vue";
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
    Tab,
} from "../../js/types/pastoralReportTypes";
import { getChartOptions } from "./config/chartOptions";
import { getRowKeys, getMeta } from "../util/pastoralReportUtils";

const props = defineProps<{
    headers: TableHeader[];
    rowData: { [key: string]: RowOptions };
    recordsOrPoints: "records" | "points";
    isTotalSticky: boolean;
}>();

const emit = defineEmits<{
    cellSelect: [header: TableHeader, rowKeys: RowKeys, meta: string[]];
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
    emit("cellSelect", header, getRowKeys(props.rowData), getMeta(selectedTab.value, header, props.rowData));
}
</script>

<template>
    <tr>
        <td
            v-for="(header, index) in props.headers"
            :key="header.key"
            :class="`u-border-right ${index === 0 ? 'u-sticky-left u-sticky-left--row ' : ''} ${header.key === 'user' ? 'l-pad--0' : ''} ${header.key === 'total' ? 'total-cell' : ''} ${header.key === 'total' && props.isTotalSticky ? 'u-sticky-left u-sticky-left--row' : ''}`"
        >
            <template v-if="header.type == TableHeaderType.USER">
                <div class="l-flex-row l-flex--no-wrap l-gap-m">
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
                <chart :options="getChartOptions(props.rowData, props.recordsOrPoints)"></chart>
            </template>
            <template v-else>
                {{ props.rowData[header.key] }}
            </template>
        </td>
    </tr>
</template>

<style scoped lang="scss">
tr:hover {
    /* to help legibility, keeping the row in focus */
    background-color: var(--sbx-pr-table-row-hover-colour);
    .highcharts-background {
        fill: var(--sbx-pr-table-row-hover-colour);
    }
}

.total-cell {
    background: var(--sbx-pr-table-total-cell-colour);
    left: var(--sbx-pr-table-first-column-width); // will only be used when sticky
}
</style>
