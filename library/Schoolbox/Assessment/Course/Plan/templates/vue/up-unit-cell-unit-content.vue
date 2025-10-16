<script setup lang="ts">
/**
 * The content for a unit cell in the unit planner, displaying the unit title,
 * and allowing for drag-and-drop resequencing if enabled.
 */

import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useUnitPlannerDragStore } from "./store/unitPlannerDragStore";
import type { Unit } from "./unitPlannerTypes";

const props = defineProps<{
    unit: Unit;
}>();

gsap.registerPlugin(Draggable);

const store = useUnitPlannerStore();
const dragStore = useUnitPlannerDragStore();

/**
 * The current draggable element
 */
const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

const extraCurriculumComponents = props.unit.curriculum.components.filter((component) => !component.inTemplate).length; // Number of extra curriculum components in the template

const extraActivitiesComponents = props.unit.activities.components.filter((component) => !component.inTemplate).length; // Number of extra activities components in the template

/**
 * Initialize the draggable instance when the component is mounted
 */
window.Vue.onMounted(() => {
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

/**
 * Kill the draggable instance when the component is unmounted
 */
window.Vue.onUnmounted(() => {
    if (draggableInstance) {
        draggableInstance[0].kill();
    }
});
</script>

<template>
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
                    {{ props.unit.extraTextboxes }} extra textbox{{ props.unit.extraTextboxes === 1 ? "" : "es" }}
                </li>
                <li v-if="extraCurriculumComponents > 0" class="meta">
                    {{ extraCurriculumComponents }} extra curriculum component{{
                        extraCurriculumComponents === 1 ? "" : "s"
                    }}
                </li>
                <li v-if="extraActivitiesComponents > 0" class="meta">
                    Includes {{ extraActivitiesComponents }} additional Course Builder component
                    {{ extraActivitiesComponents === 1 ? "" : "s" }}
                </li>
            </ul>
        </div>
    </div>
</template>

<style lang="scss" scoped>
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

[data-column-type="unit"] {
    padding: 5px;

    > div > div:not(.sortable-holder) {
        display: flex;
        left: 5px;
        padding-right: 0.5rem;
        position: absolute; // Avoid the content being pushed around by the draggable holder
        text-wrap: wrap;
        top: 5px;
    }
}
</style>
