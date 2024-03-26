<script setup lang="ts">
import { useActivitiesDragStore } from "./store/activitiesDragStore";
import { Unit } from "./unitPlannerTypes";
import { Column } from "./unitPlannerConfig";
import ActivitiesListItem from "./activities-list-item.vue";

interface Props {
    unit: Unit;
    column: Column;
}

const props = defineProps<Props>();

// We need the drag store to be unique per unit+column to isolate the activities
const activitiesDragStoreId = `${props.unit.id}-${props.column.id}`;
const activitiesDragStore = useActivitiesDragStore(activitiesDragStoreId);

window.Vue.onMounted(() => {
    activitiesDragStore().init(props.unit.activities._links.reorder, props.unit.activities.items);
});
</script>

<template>
    <ul class="information-list sortable-card ui-sortable">
        <activities-list-item
            v-for="activity in activitiesDragStore().orderedActivities"
            :key="activity.id"
            :activities-drag-store-id="activitiesDragStoreId"
            :unit="props.unit"
            :activity="activity"
        />
    </ul>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
