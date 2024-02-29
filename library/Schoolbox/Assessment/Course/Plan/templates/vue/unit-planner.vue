<script setup lang="ts">
import Toast from "@schoolboxVue/components/toast.vue";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Unit } from "./unitPlannerTypes";
import ContentContainer from "./content-container.vue";
import RubricCapabilities from "./rubric-capabilities.vue";
import CurriculumCodesList from "./curriculum-codes-list.vue";
import TextboxContent from "./textbox-content.vue";
import ActivitiesList from "./activities-list.vue";

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
const windowHeight = window.Vue.ref(0);

/* The height of the scrollable area (table), such that no vertical scrollbar is shown for the whole page.
 * This is to ensure the horizontal scrollbar of the unit planner table is always visible.
 */
const containerHeight = window.Vue.computed(() => {
    if (!tabsEl.value) {
        return 0; // Not loaded yet
    }

    const tabsRect = tabsEl.value.getBoundingClientRect();
    const footerRect = document.getElementById("footer")?.getBoundingClientRect();

    const mainContainerEl = document.getElementById("container");

    const mainContainerPaddingBtm = mainContainerEl
        ? parseInt(window.getComputedStyle(mainContainerEl).paddingBottom, 10)
        : 0;

    return windowHeight.value - tabsRect.bottom - (footerRect?.height || 0) - mainContainerPaddingBtm - 1;
});

window.Vue.onMounted(async () => {
    store.init(props.units, props.routeFolderSequence, toastEl);

    // Whether to allow the user to resequence the units
    if (props.routeFolderSequence) {
        await window.Vue.nextTick();

        const sortableTarget = $("[data-sortable]");
        sortableTarget.sortable({
            handle: ".grip",
            axis: "y",
            update: () => {
                // Adjust the sequence of the units
                const unitIds: Unit["id"][] = sortableTarget
                    .sortable("toArray", { attribute: "data-unit-id" })
                    .map(Number);
                store.resequenceUnits(unitIds);
            },
        });
    }

    // Track window height to adjust the height of the scrollable area
    window.addEventListener("resize", () => {
        windowHeight.value = window.innerHeight;
    });

    // Wait for DOM render, then update height again, in case we haven't fully loaded yet
    if (document.getElementById("footer")) {
        windowHeight.value = window.innerHeight;
    } else {
        window.addEventListener("DOMContentLoaded", () => {
            windowHeight.value = window.innerHeight;
        });
    }
});

window.Vue.onUnmounted(() => {
    // Clean up event listeners
    window.removeEventListener("resize", () => {
        windowHeight.value = window.innerHeight;
    });
});
</script>

<template>
    <dl ref="tabsEl" class="tabs">
        <dd v-for="tab in tabs" :key="tab.id" :class="{ active: selectedTab === tab.id }">
            <a @click="selectedTab = tab.id">{{ tab.label }}</a>
        </dd>
    </dl>
    <div ref="unitPlannerContainer" class="scrollable" :style="{ height: containerHeight + 'px' }">
        <table class="unit-planner" :class="{ sortable: props.routeFolderSequence }">
            <thead>
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
                <tr
                    v-for="unit in store.orderedUnits"
                    :key="unit.id"
                    :data-unit-id="unit.id"
                    :class="{ collapsed: !store.expandedUnits.includes(unit.id) }"
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
                        <activities-list :activities="unit.activities" :unit-id="unit.id"></activities-list>
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
                z-index: 2;
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
        z-index: 100;
    }

    > tbody {
        > tr {
            height: 300px;

            > td:not(:first-child) {
                padding: 0px;
            }

            &.ui-sortable-helper {
                display: table; /* Keep widths consistent */
                border: 1px solid var(--body-background);
            }

            &.ui-sortable-placeholder {
                visibility: visible !important;

                > td {
                    background-color: #dddddd;
                    border-left: none;
                    border-right: none;
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
</style>
