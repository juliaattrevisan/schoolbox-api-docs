<script lang="ts" setup>
/** Learning Moments Cohort Report */
import { useElementSize } from "@vueuse/core";
import { useCohortReportStore } from "./store/cohortReportStore";
import CrFilters from "./cr-filters.vue";
import CrResults from "./cr-results.vue";
import CrEntryModal from "./cr-entry-modal.vue";

const SINGLE_COLUMN_BREAKPOINT = 800;

interface Props {
    endpointGetReport: string;
    endpointPutBulkReject: string;
    endpointPutBulkPublish: string;
    endpointGetEvidenceTags: string;
    endpointDeleteBulkEvidence: string;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

window.Vue.onMounted(async () => {
    await crStore.init(
        props.endpointGetReport,
        props.endpointPutBulkReject,
        props.endpointPutBulkPublish,
        props.endpointDeleteBulkEvidence,
    );
});

const cohortReportEl = window.Vue.useTemplateRef<HTMLDivElement>("cohortReportRef");
const { width: containerWidth } = useElementSize(cohortReportEl);
const isSingleColumn = window.Vue.computed(() => containerWidth.value < SINGLE_COLUMN_BREAKPOINT);
</script>

<template>
    <div class="row columns">
        <div class="small-12">
            <h1>Cohort Learning Moments</h1>
        </div>
    </div>

    <div class="row columns">
        <div
            ref="cohortReportRef"
            class="cohort-report small-12 l-grid l-gap-xl"
            :class="{ 'cohort-report--single-column': isSingleColumn }"
        >
            <cr-filters
                class="cohort-report--filters"
                :is-single-column="isSingleColumn"
                :endpoint-get-evidence-tags="props.endpointGetEvidenceTags"
            />
            <cr-results class="cohort-report--results" />
        </div>
    </div>

    <cr-entry-modal />
</template>

<style lang="scss" scoped>
.cohort-report {
    grid-template-areas: "filters results";
    grid-template-columns: 400px 1fr;
    grid-template-rows: 1fr auto;
    &--filters {
        grid-area: filters;
    }
    &--results {
        grid-area: results;
    }
    // is applied when the page is in single column mode
    &--single-column {
        gap: var(--spacing-m);
        grid-template-areas:
            "filters"
            "results";
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }
}
</style>
