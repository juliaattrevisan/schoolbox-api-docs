<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import type { Nested } from "@js/util/queryUtils";
import { SearchParam } from "@js/util/sbx/apiUtils";
import { getKeyValuePairs } from "@js/util/queryUtils";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import CursorPagination from "@schoolboxVue/components/pagination/cursor-pagination.vue";
import PastoralRecordsModal from "@schoolboxLibrary/Pastoral/vue/pastoral-records-modal.vue";
import type { Routes, Config, SeverityColors } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type {
    MDPHeader,
    Header,
    AdjustmentType,
    AdjustmentText,
    Payload,
    Profile,
} from "../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./grid/mdp-grid.vue";
import MdpGridCellStudent from "./grid/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/mdp-grid-cell-adjustment.vue";
import MdpGridCellText from "./grid/mdp-grid-cell-text.vue";
import { ON_FILTER_CHANGE } from "../js/MDPEvents";
import { useLoadData } from "./composable/useLoadData";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    route: string;
    pastoralConfig: Config;
    pastoralRoutes: Routes;
    severityColors: SeverityColors;
}>();

window.Vue.provide("pastoralConfig", props.pastoralConfig);
window.Vue.provide("pastoralRoutes", props.pastoralRoutes);
window.Vue.provide("severityColors", props.severityColors);

// #region grid data

const header = window.Vue.ref<Header[]>([
    { id: 0, title: "Student", width: 250, isFixed: true, isSortable: true, key: "student", type: HeaderType.USER },
    ...props.header
        .filter((h) => h.is_enabled)
        .map((h) => ({
            id: h.id,
            key: h.name,
            type:
                h.name === "adjustment_category" || h.name === "adjustment_level"
                    ? HeaderType.ADJUSTMENT
                    : HeaderType.TEXT,
            title: h.terminology,
            width: 400,
            isFixed: false,
            isSortable: false,
        })),
    {
        id: Math.max(0, ...props.header.map((h) => h.id)) + 1,
        key: "updated_at",
        type: HeaderType.DATE,
        title: "Last updated",
        width: 200,
        isFixed: false,
        isSortable: true,
    },
]);

// #endregion grid data

// #region grid loading / filtering /  pagination

const url = window.Vue.ref<string>("");
const LIMIT = 20; // the current set limit for the number of records to fetch at once
const filters = window.Vue.ref<Nested>({});
const { isNewLoading, isMoreLoading, payload, isEmpty, hasRows, loadError } = useLoadData(url);

/**
 * Adds filter search parameters to the given URLSearchParams object.
 * @param $searchParams - The URLSearchParams object to modify.
 * @param $filters - The filters to apply.
 */
function addFilterSearchParams($searchParams: URLSearchParams, $filters: Nested): void {
    const keyValuePairs = getKeyValuePairs({ filter_selection: $filters });
    const map = new Map(Object.entries(keyValuePairs));
    map.forEach((value, key) => {
        $searchParams.append(key, value);
    });
}

/**
 * Listen for filter changes and reload data accordingly
 */
window.Vue.onMounted(() => {
    document.addEventListener(ON_FILTER_CHANGE, (event) => {
        filters.value = event.detail.selectedFilters;
        const newUrl = new URL(props.route, window.location.origin);
        const searchParams = new URLSearchParams();
        addFilterSearchParams(searchParams, filters.value);
        searchParams.set("limit", LIMIT.toString());
        newUrl.search = searchParams.toString();
        url.value = newUrl.toString();
    });
});

/**
 * Handle previous/next page button clicks
 * @param cursor - the cursor to use for the previous/next page
 */
function onPaginate(cursor: string): void {
    const newUrl = new URL(props.route, window.location.origin);
    const searchParams = new URLSearchParams();
    addFilterSearchParams(searchParams, filters.value);
    searchParams.set("limit", LIMIT.toString());
    searchParams.set(SearchParam.CURSOR as string, cursor);
    newUrl.search = searchParams.toString();
    url.value = newUrl.toString();
}

// #endregion grid loading / filtering /  pagination

// #region linked records selected

const linkedRecordsUrl = window.Vue.ref<string>("");
const linkedRecordsTitle = window.Vue.ref<string>("");
const linkedRecordsMeta = window.Vue.ref<string>("");

/**
 * When linked records is selected for a profile
 * @param profile - the profile to view linked records for
 */
function onLinkedRecordsSelected(profile: Profile): void {
    if (profile._routes?.linkedRecords) {
        linkedRecordsTitle.value = `${profile.student.fullName} - Linked Records`;
        linkedRecordsUrl.value = profile._routes.linkedRecords;
    }
}

// #endregion linked records selected
</script>

<template>
    <div v-if="isNewLoading" class="c-island l-pad-m">
        <div class="loader"></div>
    </div>
    <div v-else-if="loadError" class="c-island l-pad-m">
        <data-load-error data-title="record" :further-info="loadError" />
    </div>
    <empty-state v-else-if="isEmpty" icon="icon-learning-support">
        <p>
            No {{ locale.differentiationProfile.translate(true, true) }} exist. They will appear here once records have
            been linked.
        </p>
    </empty-state>
    <template v-else-if="hasRows && payload">
        <mdp-grid :header="header" :rows="payload.data.profiles" :is-loading="isMoreLoading">
            <template #User="{ row, header: columnHeader }">
                <mdp-grid-cell-student
                    :user="row[columnHeader.key] as User"
                    :profile="row as Profile"
                    @linked-records-select="onLinkedRecordsSelected"
                />
            </template>
            <template #Adjustment="{ row, header: columnHeader }">
                <mdp-grid-cell-adjustment
                    :adjustment="row[columnHeader.key] as AdjustmentType[]"
                    :options="columnHeader.key === 'adjustment_category' ? adjustmentCategories : adjustmentLevels"
                />
            </template>
            <template #Text="{ row, header: columnHeader }">
                <mdp-grid-cell-text :text="row[columnHeader.key] as AdjustmentText" />
            </template>
            <template #Date="{ row, header: columnHeader }">
                <div class="date-cell">
                    <!-- Custom date display -->
                    {{ row[columnHeader.key] ? new Date(row[columnHeader.key] as string).toLocaleDateString() : "" }}
                </div>
            </template>
        </mdp-grid>
        <loading-table v-if="isMoreLoading" :rows="5" row-height="3rem" />
        <cursor-pagination
            :url="url"
            :metadata="(payload as Payload).metadata"
            :limit="LIMIT"
            :is-loading="isNewLoading || isMoreLoading"
            @previous="onPaginate"
            @next="onPaginate"
        />
    </template>
    <pastoral-records-modal v-model:url="linkedRecordsUrl" :title="linkedRecordsTitle" :meta="linkedRecordsMeta" />
</template>

<style scoped></style>

<style>
/* A few tweaks to adjust the filter panel to sit properly within the layout */
#filter_panel {
    padding: 0;
}

#filter_panel section {
    margin-bottom: 0;
}
</style>
