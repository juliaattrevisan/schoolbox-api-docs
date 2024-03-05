<script setup lang="ts">
import Toast from "@schoolboxVue/components/toast.vue";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Unit } from "./unitPlannerTypes";
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

const selectedTab = window.Vue.ref("all");
const tabs = window.Vue.ref([
    { id: "all", label: "All" },
    { id: "overview", label: "Overview" },
    { id: "activities", label: "Learning Activities" },
    { id: "rubric-capabilities", label: "Rubric Capabilities" },
    { id: "curriculum-overview", label: "Curriculum Overview" },
]);

// Get a list of every textbox that could be shown in a unit
// in case a unit doesn't have a textbox for a particular key
// Ensures that the table will always show all possible textboxes
type TextboxKey = keyof Unit["textboxes"];
const allTextboxKeys = window.Vue.computed(() => {
    const keysSet = new Set<TextboxKey>();
    props.units.forEach((unit) => {
        Object.keys(unit.textboxes).forEach((key) => {
            keysSet.add(key);
        });
    });
    return [...keysSet];
});

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
        <dd v-for="tab in tabs" :key="tab.id" :class="{ active: selectedTab === tab.id }">
            <a @click="selectedTab = tab.id">{{ tab.label }}</a>
        </dd>
    </dl>
    <div ref="unitPlannerContainerEl" class="scrollable" :style="{ height: containerHeight + 'px' }">
        <table class="unit-planner" :class="{ sortable: props.routeFolderSequence }">
            <thead ref="tableHeaderEl">
                <tr>
                    <th>Unit</th>
                    <template v-if="selectedTab === 'all' || selectedTab === 'overview'">
                        <th v-for="textboxKey in allTextboxKeys" :key="textboxKey">
                            {{ textboxKey }}
                        </th>
                    </template>
                    <th v-if="selectedTab === 'all' || selectedTab === 'activities'">Learning Activities</th>
                    <th v-if="selectedTab === 'all' || selectedTab === 'rubric-capabilities'">Rubric Capabilities</th>
                    <th v-if="selectedTab === 'all' || selectedTab === 'curriculum-overview'">Curriculum Overview</th>
                </tr>
            </thead>
            <tbody v-bind="{ 'data-sortable': props.routeFolderSequence ? '' : undefined }">
                <tr id="unitplanner-dropdown-container">
                    <!-- Dropdowns that need to escape a content container's overflow:hidden target this with [data-dropdown-move-to]
                        The specific placement of this element ensures that the dropdowns are still contained within the table -->
                </tr>
                <tr
                    v-for="unit in store.orderedUnits"
                    :key="unit.id"
                    :data-unit-id="unit.id"
                    :style="{
                        height: store.expandedUnits.includes(unit.id) ? 'auto' : store.unexpandedUnitHeight + 'px',
                    }"
                >
                    <td>
                        <div class="unit-wrapper">
                            <i v-if="props.routeFolderSequence" class="icon-grip ui-sortable-handle grip" />
                            <div>
                                <a :href="unit.homepage">
                                    <strong>{{ unit.title }}</strong>
                                </a>
                                <p v-if="unit.extraTextboxes > 0" class="meta">
                                    {{ unit.extraTextboxes }} extra textbox{{ unit.extraTextboxes === 1 ? "" : "es" }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <template v-if="selectedTab === 'all' || selectedTab === 'overview'">
                        <td v-for="(textbox, textboxKey) in unit.textboxes" :key="textboxKey">
                            <content-container :unit-id="unit.id">
                                <textbox-content v-if="textbox !== null" :textbox="textbox"></textbox-content>
                            </content-container>
                        </td>
                    </template>
                    <td v-if="selectedTab === 'all' || selectedTab === 'activities'">
                        <content-container :unit-id="unit.id">
                            <ul class="information-list">
                                <activities-list-item
                                    v-for="activity in unit.activities"
                                    ref="activityListItem"
                                    :key="activity.id"
                                    :activity="activity"
                                />
                            </ul>
                        </content-container>
                    </td>
                    <td v-if="selectedTab === 'all' || selectedTab === 'rubric-capabilities'">
                        <content-container :unit-id="unit.id">
                            <rubric-capabilities :rubric-capabilities="unit.rubricCapabilities"></rubric-capabilities>
                        </content-container>
                    </td>
                    <td
                        v-if="selectedTab === 'all' || selectedTab === 'curriculum-overview'"
                        class="curriculum-codes-container"
                    >
                        <content-container :unit-id="unit.id">
                            <curriculum-codes-list :curriculum-codes="unit.curriculumCodes"></curriculum-codes-list>
                            <curriculum-details-modal />
                        </content-container>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <toast ref="toastEl"></toast>
</template>

<style scoped lang="scss">
/* Use :deep to ensure elements created by sortable are styled correctly.
 *
 * > is used to ensure the styles are only applied to the direct children of the unit-planner
 * component. Otherwise, they may apply to other tables nested within content containers too.
 */
.unit-planner:deep() {
    position: relative; // Ensure sortable placeholders are positioned correctly and cannot escape the table

    > thead > tr,
    > tbody > tr {
        /* Fix the unit title column to the left */
        > th,
        > td {
            background-color: white;

            &:first-child {
                border-right: 1px solid var(--body-background);
                min-width: 200px;
                left: 0;
                position: sticky;
                width: 200px;
                z-index: 2; // Behind the dropdowns and table header, but on top of the content
            }

            &:not(:first-child) {
                border-left: 1px solid var(--body-background);
                min-width: 400px;
                width: 400px;
            }
        }
    }

    // Pin the header to the top of the table when scrolling
    > thead {
        position: sticky;
        top: 0;
        z-index: 4; // On top of both the left column and the dropdowns
    }

    > tbody {
        overflow: hidden;
        position: relative;
        > tr {
            transition: height 0.5s ease;

            > td:not(:first-child) {
                padding: 0px;
            }

            &.ui-sortable-helper {
                display: table; /* Keep widths consistent */
                border-top: 1px solid var(--body-background);
                border-bottom: 1px solid var(--body-background);
                width: 100% !important;

                height: v-bind('store.unexpandedUnitHeight + "px"') !important;

                > td > div > .content-container {
                    height: v-bind('store.unexpandedUnitHeight + "px"') !important;
                }
            }

            &.ui-sortable-placeholder {
                height: v-bind('store.unexpandedUnitHeight + "px"');

                > td {
                    background-color: #dddddd;
                    border-left: none;
                    border-right: none;
                }
            }

            // A unit that is currentl being sorted
            &[style*="display: none"] {
                display: table-row !important;

                > td {
                    background-color: #fafafa;

                    > div {
                        opacity: 0.2;
                    }
                }
            }
        }
    }

    .curriculum-codes-container {
        max-width: 500px;
    }

    .unit-wrapper {
        display: flex;
    }
}

#unitplanner-dropdown-container {
    height: 0;
    position: sticky;
    z-index: 3; // On top of left column, but behind the header
}
</style>
