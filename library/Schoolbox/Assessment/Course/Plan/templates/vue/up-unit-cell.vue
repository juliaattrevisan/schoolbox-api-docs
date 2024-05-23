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
import { Column, ColumnType } from "./unitPlannerConfig";
import type { Unit } from "./unitPlannerTypes";
import { useUnitPlannerTextboxStore } from "./store/unitPlannerTextboxStore";
import { useActivitiesListDragStore } from "./store/activitiesListDragStore";
import RubricCapabilities from "./rubric-capabilities.vue";
import UpUnitCellRubricCapabilitiesAction from "./up-unit-cell-rubricCapabilities-action.vue";
import UpUnitCellRubricCapabilitiesEmpty from "./up-unit-cell-rubricCapabilities-empty.vue";
import UpUnitCellTextboxContent from "./up-unit-cell-textbox-content.vue";
import UpUnitCellTextboxAction from "./up-unit-cell-textbox-action.vue";
import UpUnitCellTextboxEmpty from "./up-unit-cell-textbox-empty.vue";
import ActivitiesListContent from "./activities-list-content.vue";
import ActivitiesListAction from "./activities-list-action.vue";
import ActivitiesListEmpty from "./activities-list-empty.vue";
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

const activitiesListDragStore = useActivitiesListDragStore(`${props.unit.id}-${props.column.id}`);
const textboxStore = useUnitPlannerTextboxStore();

const storeUnit = window.Vue.computed(() => {
    return store.units.find((unit) => unit.id === props.unit.id);
});

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionHeight = window.Vue.ref(0);

const showViewMoreGradient = window.Vue.computed(() => {
    if (!store.unitRowDisplayHeights[props.unit.id]) return false;
    return contentHeight.value + componentActionHeight.value > store.unitRowDisplayHeights[props.unit.id];
});
const showViewMoreInner = window.Vue.computed(() => {
    if (store.dragCurrentUnitNextTick === props.unit) return false;
    return showViewMoreGradient.value;
});

const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

const contentIsFocused = window.Vue.computed(() => {
    return (store.focusedUnitCells[props.unit.id] ?? []).includes(props.column.id);
});

const contentIsEmpty = window.Vue.computed(() => {
    if (!storeUnit.value) return false;

    // If this cell is focused, treat it as not empty
    if (contentIsFocused.value) return false;

    if (props.column.type === ColumnType.OVERVIEW) {
        // If there is unsaved content, the cell is not empty
        if (textboxStore.unsavedTextboxes[props.unit.id]?.[props.column.id] !== undefined) {
            return false;
        }

        // Otherwise, the cell is empty if there is no content in the body yet
        return !storeUnit.value.textboxes[props.column.label]?.body;
    }
    if (props.column.type === ColumnType.ACTIVITIES) {
        return activitiesListDragStore().orderedActivities.length === 0;
    }
    if (props.column.type === ColumnType.RUBRIC_CAPABILITIES) {
        return !storeUnit.value.rubricCapabilities.length;
    }
    if (props.column.type === ColumnType.CURRICULUM_OVERVIEW) {
        return !storeUnit.value.curriculum.selectedNodes.length;
    }
    return false;
});

/**
 * Update the content height in the store when the content height changes
 */
function updateContentHeight() {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
    componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;

    // We don't want the placeholder to override the actual known content heights
    if (!props.isSortablePlaceholder) {
        store.setUnitContentHeight(props.unit, props.column, contentHeight.value + componentActionHeight.value);
    }
}

// Provide access to exposed focusEditor from the UpUnitCellTextboxContent component
// Only relevant when props.column.type === ColumnType.OVERVIEW
const textboxContentComponent = window.Vue.ref<(typeof UpUnitCellTextboxContent & { focusEditor: () => void }) | null>(
    null,
);

/**
 * When the content is clicked
 * @param {MouseEvent} event - the click event
 */
function onContentClicked(event: MouseEvent) {
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
function initResizeObserver() {
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
</script>

<template>
    <div
        :data-column-type="props.column.type"
        class="unit-cell"
        :class="{
            'fixed-left': props.column.fixedLeft,
            'sortable-dropzone': !props.isSortablePlaceholder && store.dragCurrentUnit === props.unit,
            'content-focused': contentIsFocused,
            'overview-has-created-textbox': (storeUnit?.textboxes[props.column.label]?.id || 0) >= 0,
        }"
        :style="{ height: `${store.unitRowDisplayHeights[props.unit.id]}px` }"
        @click="onContentClicked"
    >
        <div ref="contentEl">
            <template v-if="props.column.type === ColumnType.UNIT">
                <div
                    v-if="dragStore.routeFolderSequence"
                    ref="draggableEl"
                    class="sortable-holder"
                    data-test="unit-row-drag-handle"
                    :class="{ isDragging: store.dragCurrentUnit === props.unit }"
                ></div>

                <div>
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

            <activities-list-content
                v-else-if="props.column.type === ColumnType.ACTIVITIES"
                :activities-list-store-id="`${props.unit.id}-${props.column.id}`"
                :activities="props.unit.activities.items"
                :activities-list-routes="props.unit.activities._routes"
                :prevent-store-init="props.isSortablePlaceholder"
                @drag-start="store.updateFocusedUnitCell(props.unit.id, props.column, true)"
                @drag-stop="store.updateFocusedUnitCell(props.unit.id, props.column, false)"
            />

            <rubric-capabilities
                v-else-if="props.column.type === ColumnType.RUBRIC_CAPABILITIES"
                :rubric-capabilities="props.unit.rubricCapabilities"
            />

            <up-unit-cell-curriculum-content
                v-else-if="props.column.type === ColumnType.CURRICULUM_OVERVIEW"
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
            <div v-else class="content-overlay empty-state">
                <up-unit-cell-textbox-empty
                    v-if="props.column.type === ColumnType.OVERVIEW"
                    :unit="props.unit"
                    :column="props.column"
                    @modify-clicked="textboxContentComponent?.focusEditor"
                />
                <activities-list-empty v-else-if="props.column.type === ColumnType.ACTIVITIES" />
                <up-unit-cell-rubricCapabilities-empty
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
                    :unit="props.unit"
                    :column="props.column"
                    @modify-clicked="textboxContentComponent?.focusEditor"
                />
                <activities-list-action
                    v-else-if="props.column.type === ColumnType.ACTIVITIES"
                    :activities-list-store-id="`${props.unit.id}-${props.column.id}`"
                />
                <up-unit-cell-rubricCapabilities-action
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
@import "www/static/css/core/base/_variables.scss";

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
    z-index: var(--depth-default);

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
    z-index: calc(var(--depth-default) + 1); // Always appear above the content

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
</style>
