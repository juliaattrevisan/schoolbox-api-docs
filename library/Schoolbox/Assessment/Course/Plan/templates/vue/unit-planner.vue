<script setup lang="ts">
/**
 * The main component for the unit planner
 */
import type { Ref } from "vue";
import { useActivitiesListGlobalStore } from "./activities-list/store/activitiesListGlobalStore";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerDragStore } from "./store/unitPlannerDragStore";
import type { Unit, UnitTemplate } from "./unitPlannerTypes";
import { createTabs } from "./unitPlannerConfig";
import UpHeader from "./up-header.vue";
import UpEmptyState from "./up-empty-state.vue";
import UpUnitCell from "./up-unit-cell.vue";
import UpUnitCellTextboxContentPlaceholder from "./up-unit-cell-textbox-content-placeholder.vue";
import UpCurriculum from "./up-curriculum.vue";
import ActivitiesListDeleteModal from "./activities-list/activities-list-delete-modal.vue";

interface Props {
    courseId: number;
    folderId: number;
    courseTitle: string;
    units: Unit[];
    routeFolderSequence: string | null;
    ltiAssessmentsEnabled: boolean;
    template: UnitTemplate; // the course framework template that units are linked to
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();
const activitiesListGlobalStore = useActivitiesListGlobalStore();
const dragStore = useUnitPlannerDragStore();

const tabs = createTabs(props.template);

const selectedTab = window.Vue.ref<(typeof tabs)[0]>(tabs[0]);

const tabsEl = window.Vue.ref<HTMLElement>();
const unitPlannerContainerEl = window.Vue.ref<HTMLElement>();
const headerRowEl = window.Vue.ref<HTMLElement>();
const resizeObserver = window.Vue.ref<ResizeObserver>();

const headerRowHeight = window.Vue.computed(() => {
    if (!headerRowEl.value) return 0;

    const headerRowChild = headerRowEl.value.firstElementChild as HTMLElement;
    return headerRowChild.offsetHeight;
});

// Use a ref instead of a computed property so that we can force the height to be recomputed
const unitPlannerContainerScrollTop = window.Vue.ref(0);
const containerHeight = window.Vue.ref(window.innerHeight);

/**
 * Updates the scroll position of the unit planner container to allow draggables to position themselves correctly
 */
function updateScrollPosition(): void {
    if (!unitPlannerContainerEl.value) return;
    unitPlannerContainerScrollTop.value = unitPlannerContainerEl.value.scrollTop;
}

/**
 * Updates the height of the scrollable area, such that no vertical scrollbar is shown for the whole page.
 * This is to ensure the horizontal scrollbar of the unit planner is always visible.
 */
function updateContainerHeight(): void {
    if (!tabsEl.value || !unitPlannerContainerEl.value) {
        containerHeight.value = window.innerHeight;
        return;
    }

    const tabsBottomY = tabsEl.value.getBoundingClientRect().bottom;
    const footerHeight = document.getElementById("footer")?.getBoundingClientRect().height || 0;

    const mainSBContainerEl = document.getElementById("container");
    const mainSBContainerPaddingBtm = mainSBContainerEl
        ? parseInt(window.getComputedStyle(mainSBContainerEl).paddingBottom, 10)
        : 0;

    const newHeight = window.innerHeight - tabsBottomY - footerHeight - mainSBContainerPaddingBtm - 1;

    // Prevent the container from being too small, this ensures one unit is always visible
    containerHeight.value = Math.max(newHeight, store.unexpandedUnitHeight);

    // Ensure the scroll position is updated to match the new height
    window.Vue.nextTick(updateScrollPosition).catch(() => {
        // noop
    });
}

const unitElements = window.Vue.ref<HTMLElement[]>([]);

// Ensure the container is monitored for scroll events after it is first available
window.Vue.watch(
    () => unitPlannerContainerEl.value,
    (newValue) => {
        if (newValue) {
            newValue.addEventListener("scroll", updateScrollPosition);
            updateScrollPosition();
        }
    },
    { once: true },
);

// Scroll to the bottom of the container when a new unit is added to ensure it's visible
window.Vue.watch(
    () => store.unitCount,
    (newCount, oldCount) => {
        window.Vue.nextTick()
            .then(() => {
                if (newCount > oldCount && unitPlannerContainerEl.value) {
                    unitPlannerContainerEl.value.scrollTop = unitPlannerContainerEl.value.scrollHeight;
                }
            })
            .catch(() => {
                // noop
            });
    },
);

window.Vue.onMounted(() => {
    store.init(props.courseId, props.units, props.template);
    activitiesListGlobalStore.init(props.ltiAssessmentsEnabled);
    dragStore.init(
        props.routeFolderSequence,
        unitPlannerContainerEl as Ref<HTMLElement>, // Override type as it will be defined here.
        headerRowHeight,
        unitPlannerContainerScrollTop,
    );

    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateContainerHeight);
    resizeObserver.value.observe(document.querySelector("#container")!);
    resizeObserver.value.observe(document.querySelector("#unit-planner-container")!);

    // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
    updateContainerHeight();
    window.addEventListener("DOMContentLoaded", updateContainerHeight, { once: true });
});

window.Vue.onUnmounted(() => {
    // Clean up event listeners
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }

    unitPlannerContainerEl.value?.removeEventListener("scroll", updateScrollPosition);
});
</script>

<template>
    <up-unit-cell-textbox-content-placeholder
        v-if="store.units.some((u) => u.textboxes._routes.createTextbox !== null)"
    />
    <up-header :course-title="props.courseTitle" :course-id="store.courseId" :folder-id="props.folderId" />
    <up-empty-state v-if="store.units.length === 0" :course-id="store.courseId" />
    <div v-else class="row">
        <div class="small-12 island">
            <dl ref="tabsEl" class="tabs no-print">
                <dd v-for="tab in tabs" :key="tab.id" :class="{ active: selectedTab.id === tab.id }">
                    <a @click="selectedTab = tab">{{ tab.label }}</a>
                </dd>
            </dl>
            <div
                ref="unitPlannerContainerEl"
                class="scrollable"
                :style="{
                    height: containerHeight + 'px',
                    '--UnitPlannerVisibleColumns': selectedTab.columns.length - 1,
                    '--UnitPlannerTableHeaderHeight': headerRowHeight + 'px',
                }"
            >
                <div id="unitplanner-dropdown-container">
                    <!-- Dropdowns that need to escape a content container's overflow:hidden target this with [data-dropdown-move-to]
                The specific placement of this element ensures that the dropdowns are still contained within the table -->
                </div>
                <div class="unit-planner" v-bind="{ 'data-sortable': props.routeFolderSequence ? '' : undefined }">
                    <div id="unit-planner-sortable-container"></div>
                    <div ref="headerRowEl" class="grid-row header-row">
                        <div
                            v-for="column in selectedTab.columns"
                            :key="column.id"
                            class="fixed-top"
                            :class="{
                                'fixed-left': column.fixedLeft,
                            }"
                        >
                            {{ column.label }}
                        </div>
                    </div>

                    <div
                        v-for="unit in dragStore.isDragging ? dragStore.dragOrderedUnits : store.orderedUnits"
                        :key="unit.id"
                        ref="unitElements"
                        class="grid-row unit-row"
                        :data-test="`unit-row-${unit.id}`"
                        :data-test-unit-id="unit.id"
                    >
                        <up-unit-cell
                            v-for="column in selectedTab.columns"
                            :key="column.id"
                            :unit="unit"
                            :column="column"
                            :is-sortable-placeholder="false"
                        />
                    </div>

                    <div
                        class="unit-planner-sortable-placeholder grid-row"
                        :style="{
                            display: dragStore.isDragging ? 'grid' : 'none',
                            top: `calc(${dragStore.dragPlaceholderY}px + var(--UnitPlannerTableHeaderHeight))`,
                        }"
                    >
                        <template v-if="store.dragCurrentUnit">
                            <up-unit-cell
                                v-for="column in selectedTab.columns"
                                :key="column.id"
                                :unit="store.dragCurrentUnit"
                                :column="column"
                                :is-sortable-placeholder="true"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <activities-list-delete-modal />
    <up-curriculum />
</template>

<style scoped lang="scss">
.scrollable {
    overflow: auto;
}

.unit-planner {
    display: grid;
    grid-template-columns: 200px repeat(var(--UnitPlannerVisibleColumns), minmax(400px, 1fr));
    position: relative;
}

.grid-row {
    display: contents; // Allow the children to be displayed as a grid

    > div {
        background-color: #fff;
        border-color: var(--body-background);
        border-style: solid;
        border-width: 1px 0 1px 0; // Unless overridden below
        font-size: 0.8125rem;
        overflow-wrap: anywhere;
        text-wrap: wrap;

        &:not(:last-child) {
            border-right-width: 1px;
        }

        &:not(:first-child) {
            border-left-width: 1px;
        }
    }

    &.header-row > div {
        border-top-width: 0px;
        color: #000;
        font-weight: 600;
        padding: 0.625rem;
        white-space: nowrap;
    }
}

.fixed-top.fixed-left {
    z-index: calc(var(--sbx-depth-ui-sticky) + 3); // On top of both the left column, the header, and the dropdowns
}

.fixed-left {
    left: 0;
    position: sticky;
    z-index: var(--sbx-depth-ui-sticky); // Behind the dropdowns and table header, but on top of the content
}
.fixed-top {
    position: sticky;
    top: 0;
    z-index: calc(var(--sbx-depth-ui-sticky) + 2); // On top of both the left column and the dropdowns
}

#unitplanner-dropdown-container {
    height: 0;
    position: sticky;
    z-index: calc(var(--sbx-depth-ui-sticky) + 1); // On top of left column, but behind the header
}

#unit-planner-sortable-container {
    height: calc(100% - var(--UnitPlannerTableHeaderHeight));
    left: 0;
    position: absolute;
    top: var(--UnitPlannerTableHeaderHeight);
    width: 100%;
    z-index: calc(var(--sbx-depth-default) - 1);
}

.unit-planner-sortable-placeholder {
    background: white;
    grid-template-columns: 200px repeat(var(--UnitPlannerVisibleColumns), minmax(400px, 1fr));
    position: absolute;
    width: 100%;
    z-index: var(--sbx-depth-pop-over);
}
/* Styles for printing */
@media print {
    .no-print {
        display: none;
    }
    .scrollable {
        height: 100% !important; // Expand scrollable elements to full height in print view
        overflow: visible !important; // Ensure all content is visible
    }
    a {
        color: #000 !important;
        text-decoration: none !important;
    }
    .header-row {
        display: none !important;
    }
}

/* Print styles for paper size below A4 portrait  */
@media print and (max-width: 210mm) {
    .unit-planner {
        grid-template-columns: 1fr !important; // Set a single column layout for unit planner on smaller paper sizes
    }
    .grid-row {
        > div {
            border: none !important; // Remove borders from grid items on smaller paper sizes
        }
    }
}
</style>
