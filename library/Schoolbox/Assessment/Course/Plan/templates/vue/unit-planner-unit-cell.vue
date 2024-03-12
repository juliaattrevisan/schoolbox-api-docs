<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Column, ColumnType } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";
import ContentContainer from "./content-container.vue";
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

const draggableEl = window.Vue.ref<HTMLElement>();
let draggableInstance: ReturnType<typeof Draggable.create> | null = null;

window.Vue.onMounted(() => {
    // Whether to allow the user to resequence the units
    if (store.routeFolderSequence) {
        if (draggableEl.value) {
            draggableInstance = Draggable.create(draggableEl.value, {
                type: "y",
                autoScroll: 1,
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
        :style="{
            '--UnitMaxHeight': store.expandedUnits.includes(props.unit.id) ? 'auto' : store.unexpandedUnitHeight + 'px',
        }"
    >
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
                        {{ props.unit.extraTextboxes }} extra textbox{{ props.unit.extraTextboxes === 1 ? "" : "es" }}
                    </p>
                </div>
            </div>
        </template>

        <content-container v-else-if="props.column.type === ColumnType.OVERVIEW" :unit-id="props.unit.id">
            <textbox-content
                v-if="props.unit.textboxes[props.column.label] !== null"
                :textbox="props.unit.textboxes[props.column.label]!"
            ></textbox-content>
        </content-container>

        <content-container v-else-if="props.column.type === ColumnType.ACTIVITIES" :unit-id="props.unit.id">
            <ul class="information-list">
                <activities-list-item
                    v-for="activity in props.unit.activities"
                    ref="activityListItem"
                    :key="activity.id"
                    :activity="activity"
                />
            </ul>
        </content-container>

        <content-container v-else-if="props.column.type === ColumnType.RUBRIC_CAPABILITIES" :unit-id="props.unit.id">
            <rubric-capabilities :rubric-capabilities="props.unit.rubricCapabilities"></rubric-capabilities>
        </content-container>

        <content-container v-else-if="props.column.type === ColumnType.CURRICULUM_OVERVIEW" :unit-id="props.unit.id">
            <curriculum-codes-list :curriculum-codes="props.unit.curriculumCodes"></curriculum-codes-list>
            <curriculum-details-modal />
        </content-container>
    </div>
</template>

<style scoped lang="scss">
.unit-cell {
    height: var(--UnitMaxHeight);
    transition: height 0.5s ease;

    position: relative;
    overflow: hidden;
    text-overflow: ellipsis;

    &.unit-content-padded {
        padding: 5px;
    }

    &[data-column-type="unit"] > div:not(.sortable-holder) {
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
</style>
