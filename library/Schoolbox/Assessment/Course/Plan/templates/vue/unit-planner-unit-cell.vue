<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerDragStore } from "./store/unitPlannerDragStore";
import { Column, ColumnType } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";
import RubricCapabilities from "./rubric-capabilities.vue";
import CurriculumCodesList from "./curriculum-codes-list.vue";
import TextboxContent from "./textbox-content.vue";
import TextboxAction from "./textbox-action.vue";
import ActivitiesListItem from "./activities-list-item.vue";

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

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

const componentActionEl = window.Vue.ref<HTMLElement | null>(null);
const componentActionHeight = window.Vue.ref(0);

const contentMaxHeight = window.Vue.computed(() => {
    // unitContentHeights is a map of unit IDs to column IDs to heights
    if (!store.unitContentHeights[props.unit.id]) return 0;
    return Math.max(...Object.values(store.unitContentHeights[props.unit.id]).map((height) => height || 0));
});

const cellMaxHeight = window.Vue.computed(() => {
    // Ensure the height of the component initially renders as if it wasn't being dragged to ensure
    // the height transition is able to occur.
    if (dragStore.dragCurrentUnitNextTick === props.unit) return dragStore.dragUnitHeight;

    // If the unit is expanded, allow it to grow to fit its content
    if (store.expandedUnits.includes(props.unit.id)) return contentMaxHeight.value;

    // Otherwise, limit the height to the unexpanded height
    return store.unexpandedUnitHeight;
});

const showViewMoreGradient = window.Vue.computed(() => {
    return contentHeight.value + componentActionHeight.value > cellMaxHeight.value;
});
const showViewMoreInner = window.Vue.computed(() => {
    if (dragStore.dragCurrentUnitNextTick === props.unit) return false;
    if (store.expandedUnits.includes(props.unit.id)) return false;
    return showViewMoreGradient.value;
});

const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

const contentIsFocused = window.Vue.computed(() => {
    return (store.focusedUnitCells[props.unit.id] ?? []).includes(props.column.id);
});

/**
 * Update the content height in the store when the content height changes
 */
function updateContentHeight() {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
    componentActionHeight.value = componentActionEl.value?.offsetHeight || 0;
    store.setUnitContentHeight(props.unit, props.column, contentHeight.value + componentActionHeight.value);
}

// Provide access to exposed focusEditor from the TextboxContent component
// Only relevant when props.column.type === ColumnType.OVERVIEW
const textboxContentComponent = window.Vue.ref<(typeof TextboxContent & { focusEditor: () => void }) | null>(null);

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

    // If there's a textbox content component, focus the editor
    if (textboxContentComponent.value) {
        textboxContentComponent.value.focusEditor();
    }
}

window.Vue.onMounted(() => {
    // Wait a tick to ensure the content has been rendered before measuring its height
    setTimeout(updateContentHeight, 0);

    // Whether to allow the user to resequence the units
    if (dragStore.routeFolderSequence) {
        if (draggableEl.value) {
            draggableInstance = Draggable.create(draggableEl.value, {
                type: "y",
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
            'sortable-dropzone': !props.isSortablePlaceholder && dragStore.dragCurrentUnit === props.unit,
            'sortable-placeholder': props.isSortablePlaceholder,
            'content-focused': contentIsFocused,
            'overview-has-created-textbox': (storeUnit?.textboxes[props.column.label]?.id || 0) >= 0,
        }"
        :style="{ height: `${cellMaxHeight}px` }"
        @click="onContentClicked"
    >
        <div ref="contentEl">
            <template v-if="props.column.type === ColumnType.UNIT">
                <div
                    v-if="dragStore.routeFolderSequence"
                    ref="draggableEl"
                    class="sortable-holder"
                    :class="{ isDragging: dragStore.dragCurrentUnit === props.unit }"
                ></div>

                <div>
                    <i v-if="dragStore.routeFolderSequence" class="icon-grip ui-sortable-handle grip" />
                    <div>
                        <a :href="props.unit.homepage">
                            <strong>{{ props.unit.title }}</strong>
                        </a>
                        <p v-if="props.unit.extraTextboxes > 0" class="meta">
                            {{ props.unit.extraTextboxes }} extra textbox{{
                                props.unit.extraTextboxes === 1 ? "" : "es"
                            }}
                        </p>
                    </div>
                </div>
            </template>

            <template v-else-if="props.column.type === ColumnType.OVERVIEW">
                <textbox-content
                    ref="textboxContentComponent"
                    :unit="props.unit"
                    :column="props.column"
                    @update-content-height="updateContentHeight"
                ></textbox-content>
            </template>

            <template v-else-if="props.column.type === ColumnType.ACTIVITIES">
                <ul class="information-list">
                    <activities-list-item
                        v-for="activity in props.unit.activities.items"
                        ref="activityListItem"
                        :key="activity.id"
                        :unit="unit"
                        :activity="activity"
                    />
                </ul>
            </template>

            <template v-else-if="props.column.type === ColumnType.RUBRIC_CAPABILITIES">
                <rubric-capabilities :rubric-capabilities="props.unit.rubricCapabilities"></rubric-capabilities>
            </template>

            <template v-else-if="props.column.type === ColumnType.CURRICULUM_OVERVIEW">
                <curriculum-codes-list :curriculum-codes="props.unit.curriculumCodes"></curriculum-codes-list>
                <curriculum-details-modal />
            </template>
        </div>

        <div class="content-container-fixed">
            <div class="content-overlay">
                <div class="show-more-container" :data-visible="showViewMoreGradient">
                    <button
                        :data-visible="showViewMoreInner"
                        :tabindex="showViewMoreInner ? 0 : -1"
                        @click.prevent="store.toggleViewMore(props.unit.id)"
                    >
                        Show More
                    </button>
                </div>
            </div>

            <div ref="componentActionEl">
                <template v-if="props.column.type === ColumnType.OVERVIEW">
                    <textbox-action
                        :unit="props.unit"
                        :column="props.column"
                        @modify-clicked="textboxContentComponent?.focusEditor"
                    />
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
@import "www/static/css/core/base/_variables.scss";

.unit-cell {
    transition: height 0.5s ease;

    position: relative;
    overflow: clip; // Use clip to avoid focus scrolling the cell content
    text-overflow: ellipsis;

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
    width: 25px;
    position: absolute;
    z-index: 100;

    &:not(.isDragging) {
        transform: none !important;
    }
}

.content-container-fixed {
    bottom: 0;
    left: 0;
    position: absolute;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: end;
    pointer-events: none;

    .content-overlay {
        display: flex;
        width: 100%;
        flex-grow: 1;
        flex-direction: column;
        justify-content: end;
    }

    .show-more-container {
        display: flex;
        justify-content: end;
        background: linear-gradient(0deg, #fff 0%, #fff0 100%);
        transition: opacity 0.3s;
        opacity: 0;

        &[data-visible="true"] {
            opacity: 1;
        }

        button {
            margin: 5px;
            pointer-events: none;
            transition: opacity 0.3s;
            opacity: 0;

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
        text-wrap: wrap;

        // Avoid the content being pushed around by the draggable holder
        position: absolute;
        top: 5px;
        left: 5px;
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
