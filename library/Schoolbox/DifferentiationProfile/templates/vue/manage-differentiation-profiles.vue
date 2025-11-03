<script setup lang="ts">
/**
 * Manage Differentiation Profiles page
 */

import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Nested } from "@js/util/queryUtils";
import { getSearchString } from "@js/util/queryUtils";
import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type {
    MDPHeader,
    Header,
    AdjustmentType,
    Profile,
    Payload,
    AdjustmentText,
} from "../js/types/manageDifferentiationProfilesTypes";
import { HeaderType } from "../js/types/manageDifferentiationProfilesTypes";
import MdpGrid from "./grid/mdp-grid.vue";
import MdpGridCellStudent from "./grid/mdp-grid-cell-student.vue";
import MdpGridCellAdjustment from "./grid/mdp-grid-cell-adjustment.vue";
import MdpGridCellText from "./grid/mdp-grid-cell-text.vue";
import { ON_FILTER_CHANGE, type FiltersChangeEventDetail } from "../js/MDPEvents";

const props = defineProps<{
    header: MDPHeader[];
    adjustmentCategories: AdjustmentType[];
    adjustmentLevels: AdjustmentType[];
    route: string;
}>();

// #region grid data

const header = window.Vue.ref<Header[]>([
    { id: 0, title: "Student", width: 250, isFixed: true, isSortable: true, key: "student", type: HeaderType.USER },
    ...props.header.map((h) => ({
        id: h.id,
        key: h.name,
        type:
            h.name === "adjustment_category" || h.name === "adjustment_level" ? HeaderType.ADJUSTMENT : HeaderType.TEXT,
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

const rows = window.Vue.ref<Profile[]>([]);

// #endregion grid data

const filters = window.Vue.ref<Nested>({});
const isProcessing = window.Vue.ref(true);
const loadError = window.Vue.ref<string | null>(null); // to store any error that occurs during the create

/**
 * retrieve data for the grid
 */
async function load(): Promise<void> {
    const toast = window.sbProps.toastActivate("Loading...");
    const endpoint = new URL(props.route, window.location.origin);
    const queryData: Nested = {};
    if (Object.keys(filters.value).length > 0) {
        queryData.filter_selection = filters.value;
    }
    endpoint.search = getSearchString(queryData);
    try {
        isProcessing.value = true;
        loadError.value = null;
        const response = await apiRequest<Payload>({
            url: endpoint.toString(),
            method: "GET",
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to load");
        }
        toast.timeout({ text: "Loaded successfully" });
        rows.value = response.data.data.profiles;
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : "There was an unexpected error";
        loadError.value = errorMessage;
        toast.timeout({ text: errorMessage, isSuccess: false });
    } finally {
        isProcessing.value = false;
    }
}

/**
 * Listen for filter changes and reload data accordingly
 */
window.Vue.onMounted(() => {
    document.addEventListener(ON_FILTER_CHANGE, (event: CustomEvent<FiltersChangeEventDetail>) => {
        filters.value = event.detail.selectedFilters;
        void load();
    });
});
</script>

<template>
    <div v-if="isProcessing" class="c-island l-pad-m">
        <div class="loader"></div>
    </div>
    <empty-state v-else-if="rows.length === 0" icon="icon-learning-support">
        <p>
            No {{ locale.differentiationProfile.translate(true, true) }} exist. They will appear here once records have
            been linked.
        </p>
    </empty-state>
    <mdp-grid v-else :header="header" :rows="rows">
        <template #User="{ row, header: columnHeader }">
            <mdp-grid-cell-student :user="row[columnHeader.key] as User" />
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
