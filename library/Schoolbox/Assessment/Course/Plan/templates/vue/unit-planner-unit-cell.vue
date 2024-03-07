<script setup lang="ts">
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { Column, ColumnType } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";
import ContentContainer from "./content-container.vue";
import RubricCapabilities from "./rubric-capabilities.vue";
import CurriculumCodesList from "./curriculum-codes-list.vue";
import TextboxContent from "./textbox-content.vue";
import ActivitiesListItem from "./activities-list-item.vue";

interface Props {
    unit: Unit;
    column: Column;
    routeFolderSequence: string | null;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();
</script>

<template>
    <div
        :data-column-type="column.type"
        class="unit-cell"
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
</template>

<style scoped lang="scss">
.unit-cell {
    position: relative;
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
</style>
