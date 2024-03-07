<script setup lang="ts">
import Toast from "@schoolboxVue/components/toast.vue";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Unit } from "./unitPlannerTypes";
import { ColumnType, createTabs } from "./unitPlannerConfig";
import ContentContainer from "./content-container.vue";
import RubricCapabilities from "./rubric-capabilities.vue";
import CurriculumCodesList from "./curriculum-codes-list.vue";
import TextboxContent from "./textbox-content.vue";
import ActivitiesListItem from "./activities-list-item.vue";

interface Props {
    units: Unit[];
    routeFolderSequence: string | null;
}

const props = defineProps<Props>();
const toastEl = window.Vue.ref<typeof Toast>();
const store = useUnitPlannerStore();

// Get a list of every textbox that could be shown in a unit
// in case a unit doesn't have a textbox for a particular key
// Ensures that the table will always show all possible textboxes
const allTextboxKeys: string[] = (() => {
    const keysSet = new Set<keyof Unit["textboxes"]>();
    props.units.forEach((unit) => {
        Object.keys(unit.textboxes).forEach((key) => {
            keysSet.add(key);
        });
    });
    return Array.from(keysSet);
})();

const tabs = createTabs(allTextboxKeys);

const selectedTab = window.Vue.ref<(typeof tabs)[0]>(tabs[0]);

const tabsEl = window.Vue.ref<HTMLElement>();
const tableHeaderEl = window.Vue.ref<HTMLElement>();
const unitPlannerContainerEl = window.Vue.ref<HTMLElement>();
const resizeObserver = window.Vue.ref<ResizeObserver>();

// Use a ref instead of a computed property so that we can force the height to be recomputed
const containerHeight = window.Vue.ref(window.innerHeight);

/**
 * Updates the height of the scrollable area (table), such that no vertical scrollbar is shown for the whole page.
 * This is to ensure the horizontal scrollbar of the unit planner table is always visible.
 * @returns {void}
 */
function updateContainerHeight(): void {
    if (!tabsEl.value || !tableHeaderEl.value || !unitPlannerContainerEl.value) {
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
    containerHeight.value = Math.max(newHeight, store.unexpandedUnitHeight + tableHeaderEl.value.offsetHeight);
}

window.Vue.onMounted(async () => {
    store.init(props.units, props.routeFolderSequence, toastEl);

    // Whether to allow the user to resequence the units
    if (props.routeFolderSequence) {
        await window.Vue.nextTick();

        const sortableTarget = $("[data-sortable]");
        sortableTarget.sortable({
            handle: ".grip",
            helper: "clone",
            axis: "y",
            placeholder: "ui-sortable-placeholder",
            update: () => {
                // Adjust the sequence of the units
                const unitIds: Unit["id"][] = sortableTarget
                    .sortable("toArray", { attribute: "data-unit-id" })
                    .map(Number);
                store.resequenceUnits(unitIds);
            },
            start: () => {
                store.isDragging = true;

                // After transitioning to new heights, refreshPositions
                sortableTarget.sortable("refreshPositions");
                setTimeout(() => {
                    sortableTarget.sortable("refreshPositions");
                }, 500);
            },
            stop: () => {
                store.isDragging = false;
            },
        });
    }

    // Initialize the ResizeObserver
    resizeObserver.value = new ResizeObserver(updateContainerHeight);
    resizeObserver.value.observe(document.querySelector("#container")!);

    // Set the initial height, and update it again when the DOM is fully loaded to ensure the footer is included
    updateContainerHeight();
    window.addEventListener("DOMContentLoaded", updateContainerHeight, { once: true });
});

window.Vue.onUnmounted(() => {
    // Clean up event listeners
    if (resizeObserver.value) {
        resizeObserver.value.disconnect();
    }
});
</script>

<template>
    <dl ref="tabsEl" class="tabs">
        <dd v-for="tab in tabs" :key="tab.id" :class="{ active: selectedTab === tab }">
            <a @click="selectedTab = tab">{{ tab.label }}</a>
        </dd>
    </dl>
    <div
        ref="unitPlannerContainerEl"
        class="scrollable"
        :style="{ height: containerHeight + 'px', '--UnitPlannerVisibleColumns': selectedTab.columns.length - 1 }"
    >
        <div id="unitplanner-dropdown-container">
            <!-- Dropdowns that need to escape a content container's overflow:hidden target this with [data-dropdown-move-to]
                The specific placement of this element ensures that the dropdowns are still contained within the table -->
        </div>
        <div class="unit-planner">
            <div class="grid-row header-row">
                <div
                    v-for="column in selectedTab.columns"
                    :key="column.id"
                    class="fixed-top"
                    :class="{ 'fixed-left': column.fixedLeft }"
                >
                    {{ column.label }}
                </div>
            </div>

            <div v-for="unit in store.orderedUnits" :key="unit.id" :data-unit-id="unit.id" class="grid-row unit-row">
                <div
                    v-for="column in selectedTab.columns"
                    :key="column.id"
                    :data-column-type="column.type"
                    :class="{ 'unit-content-padded': column.cellPadding, 'fixed-left': column.fixedLeft }"
                    :style="{
                        height: store.expandedUnits.includes(unit.id) ? 'auto' : store.unexpandedUnitHeight + 'px',
                    }"
                >
                    <template v-if="column.type === ColumnType.UNIT">
                        <i v-if="props.routeFolderSequence" class="icon-grip ui-sortable-handle grip" />
                        <div>
                            <a :href="unit.homepage">
                                <strong>{{ unit.title }}</strong>
                            </a>
                            <p v-if="unit.extraTextboxes > 0" class="meta">
                                {{ unit.extraTextboxes }} extra textbox{{ unit.extraTextboxes === 1 ? "" : "es" }}
                            </p>
                        </div>
                    </template>

                    <content-container v-if="column.type === ColumnType.OVERVIEW" :unit-id="unit.id">
                        <textbox-content
                            v-if="unit.textboxes[column.label] !== null"
                            :textbox="unit.textboxes[column.label]!"
                        ></textbox-content>
                    </content-container>

                    <content-container v-if="column.type === ColumnType.ACTIVITIES" :unit-id="unit.id">
                        <ul class="information-list">
                            <activities-list-item
                                v-for="activity in unit.activities"
                                ref="activityListItem"
                                :key="activity.id"
                                :activity="activity"
                            />
                        </ul>
                    </content-container>

                    <content-container v-if="column.type === ColumnType.RUBRIC_CAPABILITIES" :unit-id="unit.id">
                        <rubric-capabilities :rubric-capabilities="unit.rubricCapabilities"></rubric-capabilities>
                    </content-container>

                    <content-container v-if="column.type === ColumnType.CURRICULUM_OVERVIEW" :unit-id="unit.id">
                        <curriculum-codes-list :curriculum-codes="unit.curriculumCodes"></curriculum-codes-list>
                        <curriculum-details-modal />
                    </content-container>
                </div>
            </div>
        </div>
    </div>

    <toast ref="toastEl"></toast>
</template>

<style scoped lang="scss">
.scrollable {
    overflow: auto;
}

.unit-planner {
    display: grid;
    grid-template-columns: 200px repeat(var(--UnitPlannerVisibleColumns), minmax(400px, 1fr));
}

.grid-row {
    display: contents; // Allow the children to be displayed as a grid

    > div {
        background-color: #fff;
        font-size: 0.8125rem;
        border-bottom: 2px solid var(--body-background);

        &:not(:last-child) {
            border-right: 1px solid var(--body-background);
        }

        &:not(:first-child) {
            border-left: 1px solid var(--body-background);
        }
    }

    &.header-row > div {
        color: #000;
        font-weight: 600;
        padding: 0.625rem;
        white-space: nowrap;
    }

    &.unit-row > div {
        overflow: hidden;
        text-overflow: ellipsis;

        &.unit-content-padded {
            padding: 5px;
        }

        &[data-column-type="unit"] {
            display: flex;
            text-wrap: wrap;
        }
    }
}

.fixed-top.fixed-left {
    z-index: 5; // On top of both the left column, the header, and the dropdowns
}

.fixed-left {
    position: sticky;
    left: 0;
    z-index: 2; // Behind the dropdowns and table header, but on top of the content
}
.fixed-top {
    position: sticky;
    top: 0;
    z-index: 4; // On top of both the left column and the dropdowns
}

#unitplanner-dropdown-container {
    height: 0;
    position: sticky;
    z-index: 3; // On top of left column, but behind the header
}
</style>
