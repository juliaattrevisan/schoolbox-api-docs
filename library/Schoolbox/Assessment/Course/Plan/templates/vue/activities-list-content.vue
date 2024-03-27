<script setup lang="ts">
import type { Ref } from "vue";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import { useActivitiesDragStore } from "./store/activitiesDragStore";
import { Unit } from "./unitPlannerTypes";
import { Column } from "./unitPlannerConfig";
import ActivitiesListItem from "./activities-list-item.vue";

interface Props {
    unit: Unit;
    column: Column;
    isSortablePlaceholder: boolean; // Whether this has been created from a unit sortable placeholder
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();
// We need the drag store to be unique per unit+column to isolate the activities
const activitiesDragStoreId = `${props.unit.id}-${props.column.id}${props.isSortablePlaceholder ? "-placeholder" : ""}`;
const activitiesDragStore = useActivitiesDragStore(activitiesDragStoreId);

const activitiesContainerEl = window.Vue.ref<HTMLElement | null>(null);

window.Vue.onMounted(() => {
    activitiesDragStore().init(
        props.unit.activities._links.reorder,
        activitiesContainerEl as Ref<HTMLElement>,
        props.unit.activities.items,
        store.toastEl!,
        props.isSortablePlaceholder,
    );
});
</script>

<template>
    <ul ref="activitiesContainerEl" class="information-list sortable-card ui-sortable">
        <activities-list-item
            v-for="activity in activitiesDragStore().orderedActivities"
            :key="activity.id"
            :activities-drag-store-id="activitiesDragStoreId"
            :unit="props.unit"
            :column="props.column"
            :activity="activity"
        />
    </ul>
</template>

<style scoped></style>
