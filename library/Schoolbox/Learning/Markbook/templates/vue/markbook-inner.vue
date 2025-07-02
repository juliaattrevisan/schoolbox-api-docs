<script setup lang="ts">
import { useMarkbookStore } from "./store/markbookStore";
import MbTableHead from "./mb-table-head.vue";
import MbClassimplRows from "./mb-classimpl-rows.vue";

interface Props {
    type: "course" | "class";
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
const tableContainerEl = window.Vue.useTemplateRef<HTMLElement>("tableContainerRef");
const markbookHeightOffset = window.Vue.ref(window.innerHeight);

window.Vue.provide("markbookTableContainerEl", tableContainerEl);

/**
 * Updates the height of the scrollable area, such that the user is able to fit the
 * visible area of the markbook within the viewport and see the relevant scrollbars
 */
function updateContainerHeight(): void {
    markbookHeightOffset.value = tableContainerEl.value
        ? Math.round(tableContainerEl.value.getBoundingClientRect().top)
        : 0;
}

window.Vue.onMounted(() => {
    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateContainerHeight);
    resizeObserver.value.observe(document.querySelector("#container")!);
    resizeObserver.value.observe(document.querySelector("#markbook-outer-container")!);

    // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
    updateContainerHeight();
    window.addEventListener("DOMContentLoaded", updateContainerHeight, { once: true });
});

window.Vue.onUnmounted(() => {
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }
});

/**
 * The route for exporting the markbook as a CSV
 * Also appends a query parameter to show drafts or show display marks, if applicable
 */
const PARAM_SHOW_DRAFTS = "showDrafts";
const PARAM_SHOW_DISPLAY_MARKS = "showDisplayMarks";
const exportReportRoute = window.Vue.computed(() => {
    if (mbStore.data === null || mbStore.data._routes.exportReport === null) {
        return null;
    }

    const url = new URL(mbStore.data._routes.exportReport, window.location.origin);

    if (mbStore.showDrafts) {
        url.searchParams.set(PARAM_SHOW_DRAFTS, "1");
    } else {
        url.searchParams.delete(PARAM_SHOW_DRAFTS);
    }

    if (mbStore.showDisplayMarks) {
        url.searchParams.set(PARAM_SHOW_DISPLAY_MARKS, "1");
    } else {
        url.searchParams.delete(PARAM_SHOW_DISPLAY_MARKS);
    }

    return url.toString();
});
</script>

<template>
    <div v-if="mbStore.data" :class="{ 'actions-small-1': exportReportRoute !== null }">
        <div class="legend">
            <div class="indicators">
                <p class="meta"><span class="legend-indicator indicator-none"></span>Unmarked</p>
                <p class="meta"><span class="legend-indicator indicator-pending"></span>Unpublished draft</p>
                <p class="meta"><span class="legend-indicator indicator-resubmit"></span>Resubmit</p>
                <p class="meta"><span class="legend-indicator indicator-published"></span>Mark Published</p>
            </div>
        </div>

        <div class="checklist">
            <input id="show-display-marks" v-model="mbStore.showDisplayMarks" type="checkbox" />
            <label for="show-display-marks" data-test="markbook-show-display-marks">
                Show Display Marks
                <span
                    class="hint has-tip tip-top"
                    data-tooltip
                    title="Shows marks in the same format that students see"
                >
                    ?
                </span>
            </label>
        </div>
        <div class="checklist">
            <input id="show-drafts" v-model="mbStore.showDrafts" type="checkbox" />
            <label for="show-drafts">Show Drafts</label>
        </div>
        <template v-if="exportReportRoute">
            <nav>
                <sbx-pop-out content-id="header-options"></sbx-pop-out>
            </nav>
            <ul id="header-options" class="f-dropdown" data-dropdown-content>
                <li class="separator">Report</li>
                <li>
                    <a :href="exportReportRoute" class="icon-download"> Download Spreadsheet </a>
                </li>
            </ul>
        </template>
    </div>

    <div v-if="mbStore.isDegraded">
        <div class="small-12 column list-item">
            <ul class="meta">
                <li class="icon-alert">
                    This markbook is showing a large number of students; for performance reasons, student portraits have
                    been disabled.
                </li>
            </ul>
        </div>
    </div>

    <div
        v-if="mbStore.data"
        ref="tableContainerRef"
        class="diagonal-headings-table-container small-12 island"
        :style="{ '--markbook-height-offset': `${markbookHeightOffset}px` }"
    >
        <table class="diagonal-headings-table marksheet">
            <mb-table-head :type="props.type" />
            <tbody>
                <tr v-if="props.type === 'course'" :class="{ 'markbook-faded': mbStore.feedbackSelectedCell }">
                    <th class="course-row class-row-heading fixed-left">
                        Course Average
                        <span data-tooltip class="hint has-tip tip-top" title="Average student cohort mark">?</span>
                    </th>
                    <th class="course-row fixed-left"></th>
                    <th v-if="mbStore.data.rank !== null"></th>
                    <template v-for="assessment in mbStore.orderedActivities" :key="assessment.id">
                        <td v-if="assessment.stats !== null" class="course-row">
                            <span v-if="mbStore.showDrafts && assessment.stats.draftMean !== null" class="meta">
                                {{ assessment.stats.draftMean ?? "–" }}
                            </span>
                            <span v-else>
                                {{ assessment.stats.mean ?? "–" }}
                            </span>
                        </td>
                        <td v-else class="course-row na"></td>
                    </template>
                </tr>

                <mb-classimpl-rows
                    v-for="(_, classImplId) in mbStore.data.classImpls"
                    :key="classImplId"
                    :type="props.type"
                    :class-impl-id="classImplId"
                />
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.diagonal-headings-table-container {
    max-height: calc(100vh - var(--markbook-height-offset));
}
</style>
