<script setup lang="ts">
/**
 * Shows a particular nodes usage in a pop out window
 */

import { useTemplateRef } from "vue";
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import { usePopOutStore } from "./store/ccPopOutStore";
import { useFiltersStore } from "./store/ccFiltersStore";
import { ReportOptionValues } from "../js/types/curriculumCoverageTypes";

const popOutElement = useTemplateRef<HTMLDivElement>("popOutElement");
const popOutStore = usePopOutStore();
const filtersStore = useFiltersStore();
const button = window.Vue.ref<HTMLButtonElement | null>(null);

/**
 * When the pop out is closed, clear out the selected button to allow
 * for possible reselection of the same button
 */
function onPopOutClosed(): void {
    popOutStore.button = null;
}

/**
 * When the button is selected, open the pop out
 */
const { onButtonSelected } = usePopOut(
    button,
    popOutElement,
    "",
    "right-start",
    undefined,
    undefined,
    undefined,
    onPopOutClosed,
    false,
);

/**
 * Open the pop out modal, waits for a tick to ensure the element is available
 * since we just changed it.
 */
async function openPopOut(): Promise<void> {
    await window.Vue.nextTick();
    await onButtonSelected();
}

/**
 * Watch the button, triggering the pop out when it changes
 */
window.Vue.watch(
    () => popOutStore.button,
    (newButton) => {
        if (newButton === null) {
            return;
        }
        button.value = newButton;
        void openPopOut();
    },
);
</script>

<template>
    <div ref="popOutElement" class="usage" style="display: none">
        <div v-if="popOutStore.hasLoadError" class="alert-box alert f-no-margins">
            There was an error during usage retrieval. Please
            <a href="" title="refresh the page">refresh the page</a>
            to try again and contact your support if the problem persists
        </div>
        <div v-else-if="popOutStore.isLoading && !popOutStore.hasLoadError" class="usage__loader">
            <div class="loader"></div>
        </div>
        <div v-else class="l-flex-col l-gap-s">
            <div
                v-if="filtersStore.selectedReportOptions.includes(ReportOptionValues.UNIT) && popOutStore.usage?.onUnit"
                class="usage__section"
            >
                <h5 class="section-title">Unit</h5>
                <div class="l-flex l-gap-s">
                    <sbx-icon name="unit" />
                    <p class="f-no-margins">Assigned to Unit</p>
                </div>
            </div>
            <div
                v-if="
                    filtersStore.selectedReportOptions.includes(ReportOptionValues.LEARNING_ACTIVITY) &&
                    popOutStore.usage?.activity &&
                    popOutStore.usage.activity.length > 0
                "
                class="usage__section"
            >
                <h5 class="section-title">Learning Activities</h5>
                <template v-for="activity in popOutStore.usage.activity" :key="activity._links.view">
                    <h6 class="f-no-margins">
                        <a :href="activity._links.view" :title="`View ${activity.name}`" class="l-flex l-gap-s">
                            <sbx-icon :name="activity.icon" />
                            {{ activity.name }}
                        </a>
                    </h6>
                </template>
            </div>
            <div
                v-if="
                    filtersStore.selectedReportOptions.includes(ReportOptionValues.LEARNING_ACTIVITY_LESSON_PLAN) &&
                    popOutStore.usage?.lessonPlan &&
                    popOutStore.usage.lessonPlan.length > 0
                "
                class="usage__section"
            >
                <h5 class="section-title">Lesson Plans</h5>
                <template v-for="lessonPlan in popOutStore.usage.lessonPlan" :key="lessonPlan._links.view">
                    <h6 class="f-no-margins">
                        <a :href="lessonPlan._links.view" :title="`View ${lessonPlan.name}`" class="l-flex l-gap-s">
                            <sbx-icon :name="lessonPlan.icon" />
                            {{ lessonPlan.name }}
                        </a>
                    </h6>
                </template>
            </div>
            <div
                v-if="
                    filtersStore.selectedReportOptions.includes(ReportOptionValues.RUBRIC) && popOutStore.usage?.rubric
                "
                class="usage__section"
            >
                <h5 class="section-title">Rubric</h5>
                <template
                    v-if="popOutStore.usage.rubric.course && popOutStore.usage.rubric.course.capabilities.length > 0"
                >
                    <h6 class="section-sub-title">
                        <a :href="popOutStore.usage.rubric.course._links.view">Course Rubrics</a>
                    </h6>
                    <div v-for="capability in popOutStore.usage.rubric.course.capabilities" :key="capability.name">
                        <p class="f-no-margins">
                            <b>{{ capability.name }}</b>
                        </p>
                        <ul class="l-mar--0-bottom">
                            <li v-for="indicator in capability.indicators" :key="indicator.name">
                                {{ indicator.name }}
                            </li>
                        </ul>
                    </div>
                </template>
                <template v-if="popOutStore.usage.rubric.unit && popOutStore.usage.rubric.unit.capabilities.length > 0">
                    <h6 class="section-sub-title">
                        <a :href="popOutStore.usage.rubric.unit._links.view">Unit Rubrics</a>
                    </h6>
                    <div v-for="capability in popOutStore.usage.rubric.unit.capabilities" :key="capability.name">
                        <p class="f-no-margins">
                            <b>{{ capability.name }}</b>
                        </p>
                        <ul class="l-mar--0-bottom">
                            <li v-for="indicator in capability.indicators" :key="indicator.name">
                                {{ indicator.name }}
                            </li>
                        </ul>
                    </div>
                </template>
            </div>
            <div
                v-if="
                    filtersStore.selectedReportOptions.includes(ReportOptionValues.IN_USE_RUBRIC) &&
                    popOutStore.usage?.inUseRubric &&
                    popOutStore.usage.inUseRubric.length > 0
                "
                class="usage__section"
            >
                <h5 class="section-title">In-use Rubric</h5>
                <template v-for="activity in popOutStore.usage.inUseRubric" :key="activity._links.view">
                    <h6 class="section-sub-title">
                        <a :href="activity._links.view" :title="`View ${activity.name}`" class="l-flex l-gap-s">
                            <sbx-icon :name="activity.icon" />
                            {{ activity.name }}
                        </a>
                    </h6>
                    <div v-for="capability in activity.rubric.capabilities" :key="capability.name">
                        <p class="f-no-margins">
                            <b>{{ capability.name }}</b>
                        </p>
                        <ul class="l-mar--0-bottom">
                            <li v-for="indicator in capability.indicators" :key="indicator.name">
                                {{ indicator.name }}
                            </li>
                        </ul>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.usage {
    background-color: #fff;
    border-radius: 0.25rem;
    box-shadow: 0px 0px 6px rgba(0, 0, 0, 0.2);
    max-height: 50rem;
    max-width: 24rem;
    min-width: 16rem;
    overflow-y: auto;
    padding: var(--spacing-m);
    position: absolute;
    z-index: var(--sbx-depth-pop-over);
    &__section {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-s);
    }
}

.section-title,
.section-sub-title {
    padding-block-end: var(--spacing-xs);
    margin: 0;
}

.section-title {
    border-bottom: 2px solid var(--body-background);
}

.section-sub-title {
    border-bottom: 1px solid var(--body-background);
}
</style>
