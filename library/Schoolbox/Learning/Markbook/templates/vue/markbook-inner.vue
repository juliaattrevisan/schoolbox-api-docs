<script setup lang="ts">
import { useMarkbookStore } from "./store/markbookStore";
import MbClassAverageHeader from "./mb-class-average-header.vue";
import MbClassimplRows from "./mb-classimpl-rows.vue";

interface Props {
    type: "course" | "class";
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const resizeObserver = window.Vue.ref<ResizeObserver | null>(null);
const tableContainerEl = window.Vue.ref<HTMLElement | null>(null);
const markbookHeightOffset = window.Vue.ref(window.innerHeight);

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
</script>

<template>
    <div v-if="mbStore.data" :class="{ 'actions-small-1': mbStore.data._routes.exportReport !== null }">
        <div class="legend">
            <div class="indicators">
                <p class="meta"><span class="legend-indicator indicator-none"></span>Unmarked</p>
                <p class="meta"><span class="legend-indicator indicator-pending"></span>Unpublished draft</p>
                <p class="meta"><span class="legend-indicator indicator-resubmit"></span>Resubmit</p>
                <p class="meta"><span class="legend-indicator indicator-published"></span>Mark Published</p>
            </div>
        </div>

        <div class="checklist">
            <input v-model="mbStore.showDisplayMarks" type="checkbox" />
            <label
                for="show-display-marks"
                data-test="markbook-show-display-marks"
                @click="mbStore.showDisplayMarks = !mbStore.showDisplayMarks"
            >
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
            <input v-model="mbStore.showDrafts" type="checkbox" />
            <label for="show-drafts" @click="mbStore.showDrafts = !mbStore.showDrafts">Show Drafts</label>
        </div>
        <template v-if="mbStore.data._routes.exportReport">
            <nav>
                <sbx-pop-out content-id="header-options"></sbx-pop-out>
            </nav>
            <ul id="header-options" class="f-dropdown" data-dropdown-content>
                <li class="separator">Report</li>
                <li>
                    <a :href="mbStore.data._routes.exportReport" class="icon-download"> Download Spreadsheet </a>
                </li>
            </ul>
        </template>
    </div>

    <div v-if="mbStore.isDegraded">
        <div class="small-12 column list-item">
            <ul class="meta">
                <li class="icon-alert">
                    This markbook is showing a large number of students; for performance reasons, student portraits and
                    marking of tasks have been disabled.
                </li>
            </ul>
        </div>
    </div>

    <div
        v-if="mbStore.data"
        ref="tableContainerEl"
        class="diagonal-headings-table-container small-12 island"
        :style="{ '--markbook-height-offset': `${markbookHeightOffset}px` }"
    >
        <table class="diagonal-headings-table marksheet">
            <thead>
                <tr class="rotate-headers">
                    <th class="fixed-left"><div></div></th>
                    <th class="fixed-left">
                        <div><span>Student Weighted Average</span></div>
                    </th>
                    <th v-if="mbStore.data.rank !== null">
                        <div>
                            <span>{{ props.type === "class" ? "Class" : "Course" }} Ranking</span>
                        </div>
                    </th>
                    <th
                        v-for="activity in mbStore.orderedActivities"
                        :key="activity.id"
                        :data-test="`markbook-assessment-${activity.id}`"
                    >
                        <div>
                            <!-- A teacher clicking an assessment from the markbook is most likely interested in seeing more granular results -->
                            <span>
                                <a
                                    v-if="activity._links.summary !== null"
                                    :href="activity._links.summary"
                                    :title="activity.title"
                                >
                                    {{ activity.title }}
                                </a>
                                <span v-else :title="activity.title">{{ activity.title }}</span>
                                <br />
                                <span class="meta">{{ activity.typeName }}</span>
                            </span>
                        </div>
                    </th>
                </tr>

                <template v-if="props.type === 'class'">
                    <!-- NB; This won't look correct if there are multiple classImpls.
                      -- Currently that will never happen since this is restricted to the class markbook -->
                    <mb-class-average-header
                        v-for="(classImpl, classImplId) in mbStore.data.classImpls"
                        :key="classImplId"
                        :class-impl="classImpl"
                    />
                </template>

                <tr v-if="props.type === 'course'" class="course-headers">
                    <th class="fixed-left transparent"></th>
                    <th class="fixed-left white"></th>
                    <th v-if="mbStore.data.rank !== null" class="white"></th>
                    <th
                        v-for="group in mbStore.activityGroups"
                        :key="group.unitSpec?.id"
                        class="unit-title"
                        :colspan="group.activities.length"
                    >
                        {{ group.unitSpec?.title ?? "" }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="props.type === 'course'">
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
