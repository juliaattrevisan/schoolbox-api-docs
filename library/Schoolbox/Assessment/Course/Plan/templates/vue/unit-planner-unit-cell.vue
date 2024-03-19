<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Column, ColumnType } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";
import RubricCapabilities from "./rubric-capabilities.vue";
import CurriculumCodesList from "./curriculum-codes-list.vue";
import TextboxContent from "./textbox-content.vue";
import ActivitiesListItem from "./activities-list-item.vue";

gsap.registerPlugin(Draggable);

interface Props {
    unit: Unit;
    column: Column;
    isSortablePlaceholder: boolean;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

const unitMaxContentHeight = window.Vue.computed(() => {
    // unitContentHeights is a map of unit IDs to column IDs to heights
    if (!store.unitContentHeights[props.unit.id]) return 0;
    return Math.max(...Object.values(store.unitContentHeights[props.unit.id]).map((height) => height || 0));
});

const cellMaxHeight = window.Vue.computed(() => {
    // Ensure the height of the component initially renders as if it wasn't being dragged to ensure
    // the height transition is able to occur.
    if (store.dragCurrentUnitNextTick === props.unit) return store.dragUnitHeight;

    // If the unit is expanded, allow it to grow to fit its content
    if (store.expandedUnits.includes(props.unit.id)) return unitMaxContentHeight.value;

    // Otherwise, limit the height to the unexpanded height
    return store.unexpandedUnitHeight;
});

const showViewMoreGradient = window.Vue.computed(() => {
    return contentHeight.value > cellMaxHeight.value;
});
const showViewMoreInner = window.Vue.computed(() => {
    if (store.dragCurrentUnitNextTick === props.unit) return false;
    if (store.expandedUnits.includes(props.unit.id)) return false;
    return showViewMoreGradient.value;
});

const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

window.Vue.onMounted(() => {
    // Wait a tick to ensure the content has been rendered before measuring its height
    setTimeout(() => {
        contentHeight.value = contentEl.value?.offsetHeight || 0;
        store.setUnitContentHeight(props.unit, props.column, contentHeight.value);
    }, 0);

    // Whether to allow the user to resequence the units
    if (store.routeFolderSequence) {
        if (draggableEl.value) {
            draggableInstance = Draggable.create(draggableEl.value, {
                type: "y",
                autoScroll: 1, // Scroll speed
                inertia: true,
                allowContextMenu: true,
                onPress: () => {
                    if (!draggableInstance) return;
                    store.onDragStart(draggableInstance[0], props.unit);
                },
                onDrag: () => {
                    if (!draggableInstance) return;
                    store.onDragMove(draggableInstance[0]);
                },
                onRelease: () => {
                    if (!draggableInstance) return;
                    store.onDragEnd(draggableInstance[0]);
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
            'unit-content-padded': props.column.cellPadding,
            'fixed-left': props.column.fixedLeft,
            'sortable-dropzone': !props.isSortablePlaceholder && store.dragCurrentUnit === props.unit,
            'sortable-placeholder': props.isSortablePlaceholder,
        }"
        :style="{ height: `${cellMaxHeight}px` }"
    >
        <div ref="contentEl">
            <template v-if="props.column.type === ColumnType.UNIT">
                <div
                    v-if="store.routeFolderSequence"
                    ref="draggableEl"
                    class="sortable-holder"
                    :class="{ isDragging: store.dragCurrentUnit === props.unit }"
                ></div>

                <div>
                    <i v-if="store.routeFolderSequence" class="icon-grip ui-sortable-handle grip" />
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
                    v-if="props.unit.textboxes[props.column.label] !== null"
                    :textbox="props.unit.textboxes[props.column.label]!"
                ></textbox-content>
            </template>

            <template v-else-if="props.column.type === ColumnType.ACTIVITIES">
                <ul class="information-list">
                    <activities-list-item
                        v-for="activity in props.unit.activities"
                        ref="activityListItem"
                        :key="activity.id"
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

        <div class="show-more-container" :data-visible="showViewMoreGradient">
            <button
                :data-visible="showViewMoreInner"
                :tabindex="showViewMoreInner ? 0 : -1"
                @click.prevent="store.toggleExpanded(props.unit.id)"
            >
                Show More
            </button>
        </div>
    </div>
</template>

<style scoped lang="scss">
.unit-cell {
    transition: height 0.5s ease;

    position: relative;
    overflow: clip; // Use clip to avoid focus scrolling the cell content
    text-overflow: ellipsis;

    &.unit-content-padded {
        padding: 5px;
    }

    &[data-column-type="unit"] > div > div:not(.sortable-holder) {
        display: flex;
        text-wrap: wrap;

        // Avoid the content being pushed around by the draggable holder
        position: absolute;
        top: 5px;
        left: 5px;
    }

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

.show-more-container {
    bottom: 0;
    position: absolute;
    width: 100%;
    background: linear-gradient(0deg, #fff 0%, #fff0 100%);
    pointer-events: none;
    transition: opacity 0.3s;
    opacity: 0;

    &[data-visible="true"] {
        opacity: 1;
    }

    button {
        margin: 5px;
        float: right;
        pointer-events: none;
        transition: opacity 0.3s;
        opacity: 0;

        &[data-visible="true"] {
            opacity: 1;
            pointer-events: all;
        }
    }
}
</style>
