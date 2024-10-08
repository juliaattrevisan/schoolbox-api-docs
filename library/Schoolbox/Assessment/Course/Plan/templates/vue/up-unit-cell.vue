<script setup lang="ts">
/**
 * A cell in the unit planner grid
 * Contains the content for a specific unit and column based on the column type
 * If draggableEl is present (e.g. if the column type is UNIT), the draggable instance is initialised
 * to allow for resequencing units in the unit planner
 * The content height of the cell is measured and reflected in the store to allow for units to expand and collapse
 */
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerDragStore } from "./store/unitPlannerDragStore";
import type { Column} from "./unitPlannerConfig";
import { ColumnType } from "./unitPlannerConfig";
import type { Unit } from "./unitPlannerTypes";

// Import all the content/action/empty components for the different column types
import UpUnitCellTextboxContent from "./up-unit-cell-textbox-content.vue";
import UpUnitCellTextboxAction from "./up-unit-cell-textbox-action.vue";
import UpUnitCellTextboxEmpty from "./up-unit-cell-textbox-empty.vue";
import UpUnitCellActivitiesListContent from "./up-unit-cell-activities-list-content.vue";
import ActivitiesListAction from "./activities-list/activities-list-action.vue";
import ActivitiesListEmpty from "./activities-list/activities-list-empty.vue";
import UpUnitCellRubricCapabilitiesContent from "./up-unit-cell-rubric-capabilities-content.vue";
import UpUnitCellRubricCapabilitiesAction from "./up-unit-cell-rubric-capabilities-action.vue";
import UpUnitCellRubricCapabilitiesEmpty from "./up-unit-cell-rubric-capabilities-empty.vue";
import UpUnitCellCurriculumContent from "./up-unit-cell-curriculum-content.vue";
import UpUnitCellCurriculumAction from "./up-unit-cell-curriculum-action.vue";
import UpUnitCellCurriculumEmpty from "./up-unit-cell-curriculum-empty.vue";

gsap.registerPlugin(Draggable);

interface Props {
    unit: Unit;
    column: Column;
    isSortablePlaceholder: boolean;
}
const props = defineProps<Props>();

const store = useUnitPlannerStore();
const dragStore = useUnitPlannerDragStore();

const storeUnit = window.Vue.computed(() => {
    return store.units.find((unit) => unit.id === props.unit.id);
});

/**
 * The main container around this cell's content component
 * Used to measure the height of the content
 */
const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

/**
 * The main container around this cell's action component
 * Used to measure the height of the component action
 */
const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionHeight = window.Vue.ref(0);

/**
 * The component references for each type of content/action/empty component
 * Used to allow interaction with exposed methods / communication between content/action/empty components
 *
 * Only one type of content component will be present at a time, based on the column type
 */
const textboxContentComponent = window.Vue.ref<InstanceType<typeof UpUnitCellTextboxContent> | null>(null);
const textboxActionComponent = window.Vue.ref<InstanceType<typeof UpUnitCellTextboxAction> | null>(null);

const activitiesListContentComponent = window.Vue.ref<InstanceType<typeof UpUnitCellActivitiesListContent> | null>(
    null,
);
const rubricCapabilitiesContentComponent = window.Vue.ref<InstanceType<
    typeof UpUnitCellRubricCapabilitiesContent
> | null>(null);

const curriculumContentComponent = window.Vue.ref<InstanceType<typeof UpUnitCellCurriculumContent> | null>(null);

/**
 * The active content component of the cell
 */
const currentContentComponent = window.Vue.computed(() => {
    if (props.column.type === ColumnType.OVERVIEW) return textboxContentComponent.value;
    if (props.column.type === ColumnType.ACTIVITIES) return activitiesListContentComponent.value;
    if (props.column.type === ColumnType.RUBRIC_CAPABILITIES) return rubricCapabilitiesContentComponent.value;
    if (props.column.type === ColumnType.CURRICULUM_OVERVIEW) return curriculumContentComponent.value;
    return null;
});

/**
 * Whether the content of the cell is focused
 */
const contentIsFocused = window.Vue.computed(() => {
    return (store.focusedUnitCells[props.unit.id] ?? []).includes(props.column.id);
});

/**
 * Whether the content of the cell is empty (and not focused)
 * Based on the exposed isEmpty from the current content component
 */
const contentIsEmpty = window.Vue.computed(() => {
    if (!storeUnit.value) return false;

    // If this cell is focused, treat it as not empty
    if (contentIsFocused.value) return false;

    // Otherwise, use the exposed isEmpty from the current content component
    // (Will show a type warning if the component doesn't have an isEmpty method exposed)
    return currentContentComponent.value?.isEmpty || false;
});

/**
 * Update the content height in the store when the content height changes
 */
function updateContentHeight(): void {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
    componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;

    // We don't want the placeholder to override the actual known content heights
    if (!props.isSortablePlaceholder) {
        store.setUnitContentHeight(props.unit, props.column, contentHeight.value + componentActionHeight.value);
    }
}

/**
 * Whether to show the view more gradient and button
 */
const showViewMoreGradient = window.Vue.computed(() => {
    if (!store.unitRowDisplayHeights[props.unit.id]) return false;
    return contentHeight.value + componentActionHeight.value > store.unitRowDisplayHeights[props.unit.id];
});

/**
 * Whether to show the view more button
 */
const showViewMoreInner = window.Vue.computed(() => {
    if (store.dragCurrentUnitNextTick === props.unit) return false;
    return showViewMoreGradient.value;
});

/**
 * The current draggable element, relevant if the column type is UNIT
 */
const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

/**
 * When the content is clicked
 * @param event - the click event
 */
function onContentClicked(event: MouseEvent): void {
    if (contentEl.value === null || !event.target) return;

    // If the target is not the contentEl, a child of the contentEl, or its parent (the cell),
    // then the click wasn't on the content, and instead on the view more button or component action.
    if (
        !contentEl.value.contains(event.target as Node) &&
        event.target !== contentEl.value &&
        event.target !== contentEl.value.parentElement
    ) {
        return;
    }

    // if the cell is already focused, there's no need to re-focus it
    if (contentIsFocused.value) {
        return;
    }

    // If there's a textbox content component, focus the editor
    if (textboxContentComponent.value) {
        textboxContentComponent.value.focusEditor();
    }
}

const extraCurriculumComponents = props.unit.curriculum.components.filter((component) => !component.inTemplate).length; // Number of extra components in the template

/**
 * To detect when the dimensions of the cell changes,
 * like when a different tab is selected
 */
function initResizeObserver(): void {
    const resizeObserver = new ResizeObserver(() => updateContentHeight());
    resizeObserver.observe(contentEl.value as HTMLElement);
}

window.Vue.onMounted(() => {
    initResizeObserver();

    // Wait a tick to ensure the content has been rendered before measuring its height
    setTimeout(updateContentHeight, 0);

    // Whether to allow the user to resequence the units
    if (dragStore.routeFolderSequence) {
        if (draggableEl.value) {
            draggableInstance = Draggable.create(draggableEl.value, {
                type: "y",
                cursor: "move",
                autoScroll: 1, // Scroll speed
                inertia: true,
                allowContextMenu: true,
                onPress: () => {
                    if (!draggableInstance) return;
                    dragStore.onDragStart(draggableInstance[0], props.unit);
                },
                onDrag: () => {
                    if (!draggableInstance) return;
                    dragStore.onDragMove(draggableInstance[0]);
                },
                onRelease: () => {
                    if (!draggableInstance) return;
                    dragStore.onDragEnd(draggableInstance[0]);
                },
            });
        }
    }
});

window.Vue.onUnmounted(() => {
    if (draggableInstance) {
        draggableInstance[0].kill();
    }
});

/**
 * Define 'isFirstUnit' to check if the current unit is the first in the table.
 * Used to avoid adding a blank page at the beginning of the document in print media
 */
const isFirstUnit = window.Vue.computed(() => {
    return store.units[0].id === props.unit.id;
});
</script>

<template>
    <div
        :data-column-type="props.column.type"
        class="unit-cell"
        :class="{
            'fixed-left': props.column.fixedLeft,
            'sortable-dropzone': !props.isSortablePlaceholder && store.dragCurrentUnit === props.unit,
            'content-focused': contentIsFocused,
            'overview-has-created-textbox': (storeUnit?.textboxes.items[props.column.label]?.textboxId || 0) >= 0,
            'first-unit': isFirstUnit,
        }"
        :style="{ height: `${store.unitRowDisplayHeights[props.unit.id]}px` }"
        @click="onContentClicked"
    >
        <div ref="contentEl">
            <h2 v-if="props.column.type === ColumnType.UNIT" class="print-only unit-title-print">
                <strong>{{ props.unit.title }}</strong>
            </h2>
            <h3 v-else class="print-only label-print">{{ props.column.label }}</h3>
            <template v-if="props.column.type === ColumnType.UNIT">
                <div
                    v-if="dragStore.routeFolderSequence"
                    ref="draggableEl"
                    class="sortable-holder"
                    data-test="unit-row-drag-handle"
                    :class="{ isDragging: store.dragCurrentUnit === props.unit }"
                ></div>
                <div class="no-print">
                    <i v-if="dragStore.routeFolderSequence" class="icon-grip ui-sortable-handle grip" />
                    <div>
                        <a :href="props.unit.homepage" :data-test="`unit-planner-unit-link-${props.unit.id}`">
                            <strong>{{ props.unit.title }}</strong>
                        </a>
                        <ul>
                            <li v-if="props.unit.extraTextboxes > 0" class="meta">
                                {{ props.unit.extraTextboxes }} extra textbox{{
                                    props.unit.extraTextboxes === 1 ? "" : "es"
                                }}
                            </li>
                            <li v-if="extraCurriculumComponents > 0" class="meta">
                                {{ extraCurriculumComponents }} extra curriculum component{{
                                    extraCurriculumComponents === 1 ? "" : "s"
                                }}
                            </li>
                        </ul>
                    </div>
                </div>
            </template>

            <up-unit-cell-textbox-content
                v-else-if="props.column.type === ColumnType.OVERVIEW"
                ref="textboxContentComponent"
                :unit="props.unit"
                :column="props.column"
            />

            <up-unit-cell-activities-list-content
                v-else-if="props.column.type === ColumnType.ACTIVITIES"
                ref="activitiesListContentComponent"
                :unit="props.unit"
                :column="props.column"
                :prevent-store-init="props.isSortablePlaceholder"
            />

            <up-unit-cell-rubric-capabilities-content
                v-else-if="props.column.type === ColumnType.RUBRIC_CAPABILITIES"
                ref="rubricCapabilitiesContentComponent"
                :rubric-capabilities="props.unit.rubricCapabilities.availableRubricCapabilities"
                :used-indicators="props.unit.rubricCapabilities.usedIndicators"
                :used-capabilities="props.unit.rubricCapabilities.usedCapabilities"
                :manage-unit-route="props.unit.rubricCapabilities._routes.manageUnit"
                :manage-course-route="props.unit.rubricCapabilities._routes.manageCourse"
            />

            <up-unit-cell-curriculum-content
                v-else-if="props.column.type === ColumnType.CURRICULUM_OVERVIEW"
                ref="curriculumContentComponent"
                :unit="props.unit"
                :column="props.column"
            />
        </div>

        <div class="content-container-fixed">
            <div v-if="!contentIsEmpty" class="content-overlay">
                <div class="show-more-container" :data-visible="showViewMoreGradient">
                    <button
                        :data-visible="showViewMoreInner"
                        :tabindex="showViewMoreInner ? 0 : -1"
                        @click.prevent="store.toggleViewMore(props.unit.id)"
                    >
                        View More
                    </button>
                </div>
            </div>
            <div v-else class="content-overlay empty-state no-print">
                <up-unit-cell-textbox-empty
                    v-if="props.column.type === ColumnType.OVERVIEW"
                    :unit="props.unit"
                    :column="props.column"
                    @modify-clicked="textboxContentComponent?.focusEditor"
                    @create-clicked="textboxActionComponent?.onCreateClicked"
                />
                <activities-list-empty v-else-if="props.column.type === ColumnType.ACTIVITIES" />
                <up-unit-cell-rubric-capabilities-empty
                    v-else-if="props.column.type === ColumnType.RUBRIC_CAPABILITIES"
                    :unit="props.unit"
                />
                <up-unit-cell-curriculum-empty
                    v-else-if="props.column.type === ColumnType.CURRICULUM_OVERVIEW"
                    :unit="props.unit"
                    :column="props.column"
                />
            </div>

            <div ref="componentActionEl">
                <up-unit-cell-textbox-action
                    v-if="props.column.type === ColumnType.OVERVIEW"
                    ref="textboxActionComponent"
                    :unit="props.unit"
                    :column="props.column"
                    @modify-clicked="textboxContentComponent?.focusEditor"
                />
                <activities-list-action
                    v-else-if="props.column.type === ColumnType.ACTIVITIES"
                    :activities-list-store-id="`${props.unit.id}-${props.column.id}`"
                />
                <up-unit-cell-rubric-capabilities-action
                    v-else-if="props.column.type === ColumnType.RUBRIC_CAPABILITIES"
                    :unit="props.unit"
                />
                <up-unit-cell-curriculum-action
                    v-else-if="props.column.type === ColumnType.CURRICULUM_OVERVIEW"
                    :unit="props.unit"
                    :column="props.column"
                />
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
@import "@css/core/base/_variables.scss";

.unit-cell {
    overflow: clip; // Use clip to avoid focus scrolling the cell content
    position: relative;
    text-overflow: ellipsis;
    transition: height 0.5s ease;

    &.sortable-dropzone {
        background-color: #f8f8f8;
        > div {
            opacity: 0.2;
        }
    }
}

.sortable-holder {
    background-color: transparent;
    height: 25px;
    position: absolute;
    width: 25px;
    z-index: var(--sbx-depth-default);

    &:not(.isDragging) {
        transform: none !important;
    }
}

.content-container-fixed {
    bottom: 0;
    display: flex;
    flex-direction: column;
    height: 100%;
    justify-content: end;
    left: 0;
    pointer-events: none;
    position: absolute;
    width: 100%;
    z-index: calc(var(--sbx-depth-default) + 1); // Always appear above the content

    .content-overlay {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: end;
        margin-bottom: 0;
        width: 100%;

        &.empty-state {
            justify-content: center;
            pointer-events: all;
        }
    }

    .show-more-container {
        background: linear-gradient(0deg, #fff 0%, #fff0 100%);
        display: flex;
        justify-content: end;
        opacity: 0;
        transition: opacity 0.3s;

        &[data-visible="true"] {
            opacity: 1;
        }

        button {
            margin: 5px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;

            &[data-visible="true"] {
                opacity: 1;
                pointer-events: all;
            }
        }
    }
}

/* Overrides for column type specific styles */
[data-column-type="unit"] {
    padding: 5px;

    > div > div:not(.sortable-holder) {
        display: flex;
        left: 5px;
        position: absolute; // Avoid the content being pushed around by the draggable holder
        text-wrap: wrap;
        top: 5px;
    }
}

[data-column-type="overview"] {
    // These styles only apply to textboxes that already
    &.overview-has-created-textbox {
        cursor: text;

        .content-container-fixed {
            cursor: default;
        }

        &.content-focused {
            .content-container-fixed .content-overlay {
                border: 1px solid $form-border-color !important;
            }
        }
    }
}
/* Styles for printing */
@media print {
    .unit-cell {
        height: auto !important; // Expand the unit cell to show all content
        overflow: visible !important; // Ensure content is fully visible
    }
    .meta {
        display: none; // Remove meta data
    }
    a {
        color: #000 !important;
        text-decoration: none !important; // Remove underline from links for print
    }
    .no-print {
        display: none !important; // Hide elements with 'no-print' class
    }
    .label-print {
        border-bottom: 1px solid #eee;
        border-top: 1px solid #eee;
        margin: 0.5rem;
        padding: 0.5rem;
    }
}
/* Print styles for paper size below A4 portrait  */
@media print and (max-width: 210mm) {
    .content-container-fixed {
        display: none !important; // Hide fixed content container for smaller paper sizes
    }

    .unit-title-print {
        border: 2px solid #999;
        margin-left: 0px !important;
        margin-top: 2rem !important;
        padding: 0.5rem;
        page-break-before: always !important; // Ensure page break before each unit title
    }
    .unit-cell.first-unit .unit-title-print {
        page-break-before: avoid !important; // Avoid breaking the page before the first unit
    }
}
</style>
