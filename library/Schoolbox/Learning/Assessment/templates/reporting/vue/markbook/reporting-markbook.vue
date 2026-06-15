<script setup lang="ts">
/**
 * The reporting markbook / reporting status page
 */

import type { Template } from "@js/editor/types/editorTypes";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";
import CurriculumCodes from "@schoolboxCurriculum/vue/curriculumCodes/curriculum-codes.vue";
import { useMarkbookStore } from "@schoolboxLibrary/Learning/Markbook/templates/vue/store/markbookStore";
import type { Markbook } from "@schoolboxLibrary/Learning/Markbook/templates/vue/types/payload";
import { MarkbookType } from "@schoolboxLibrary/Learning/Markbook/templates/vue/types/types";
import MarkbookInner from "@schoolboxLibrary/Learning/Markbook/templates/vue/markbook-inner.vue";
import MarkbookPopout from "@schoolboxLibrary/Learning/Markbook/templates/vue/markbook-popout.vue";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
// import RmbFilters from "./rmb-filters.vue";

interface Props {
    editorTemplates?: Template[];
    routeGetStatusData?: string | null;
    supportingDataCurriculumNodes: Node[];
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

// TODO: implement filters (out of scope for now according to SA-22474's AC)
// const filters = window.Vue.ref<Record<ReportingFeedbackStatus, boolean>>(
//     fromEntriesOf(Object.values(ReportingFeedbackStatus).map((status) => [status, true])),
// );

const loadError = window.Vue.ref<string | null>(null);

/**
 * Load the reporting markbook data
 */
async function loadData(): Promise<void> {
    mbStore.onLoading();

    if (!props.routeGetStatusData) {
        console.error("Missing route for loading markbook data");
        loadError.value = "";
        return;
    }

    loadError.value = null;
    const response = await apiRequest<Markbook>({
        method: "GET",
        url: props.routeGetStatusData,
    });

    if (!response.success) {
        loadError.value = response.message;
        return;
    }

    mbStore.onMarkbookLoaded(response.data);
}

window.Vue.onMounted(async () => {
    mbStore.init(props.editorTemplates ?? []);
    await loadData();
});
</script>

<template>
    <div class="row">
        <!-- Metadata about the activity -->
        <div class="small-12 large-4 columns">
            <slot name="meta"></slot>
        </div>

        <!-- Status filters -->
        <!-- TODO: implement filters (out of scope for now according to SA-22474's AC) -->
        <!-- <div class="small-12 large-8 columns text-right">
            <rmb-filters v-model:filters="filters" />
        </div> -->
    </div>

    <!-- Search -->
    <div class="row">
        <div class="small-12 columns">
            <input v-model="mbStore.participantSearchFilter" type="search" placeholder="Search for a student" />
        </div>
    </div>

    <p v-if="props.supportingDataCurriculumNodes.length" class="meta l-flex-row l-gap-xs">
        Showing data associated to curriculum codes:
        <curriculum-codes :nodes="props.supportingDataCurriculumNodes" />
    </p>

    <request-error v-if="loadError" :further-info="loadError" />
    <loading-table v-else-if="mbStore.data === null" />
    <markbook-inner v-else :type="MarkbookType.REPORTING" />

    <markbook-popout />
</template>
