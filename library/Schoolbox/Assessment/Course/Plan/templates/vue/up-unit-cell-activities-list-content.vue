<script setup lang="ts">
/**
 * A wrapper for the activities list component to integrate it into the unit planner
 */
import ActivitiesListContent from "./activities-list/activities-list-content.vue";
import { useActivitiesListStore } from "./activities-list/store/activitiesListStore";
import { useActivitiesListDragStore } from "./activities-list/store/activitiesListDragStore";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import type { CourseBuilderComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";
import { useUnitPlannerActivityListStore } from "./store/unitPlannerActivityListStore";

interface Props {
    unit: Unit; // the unit linked to the curriculum cell
    column: Column; // the column linked to the curriculum cell
    preventStoreInit: boolean; // Whether to skip store initialization, e.g. if we're using an existing store
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const activitiesListStoreId = `${props.unit.id}-${props.column.id}`;

const activitiesListStore = useActivitiesListStore(activitiesListStoreId);
const unitPlannerActivityListStore = useUnitPlannerActivityListStore();
const activitiesListDragStore = useActivitiesListDragStore(activitiesListStoreId);

const component = window.Vue.computed<CourseBuilderComponent | null>(() => {
    return unitPlannerActivityListStore.getComponent(props.unit.activities.components, props.column);
});

window.Vue.onMounted(() => {
    if (!props.preventStoreInit) {
        activitiesListStore().init(props.unit.activities.items, props.unit.activities._routes);
        // Add the drag start and end callbacks to the drag store
        activitiesListDragStore().setCallbackOnDragStart(() => {
            store.updateFocusedUnitCell(props.unit.id, props.column, true);
        });
        activitiesListDragStore().setCallbackOnDragEnd(() => {
            store.updateFocusedUnitCell(props.unit.id, props.column, false);
        });
    }
});

/**
 * Is this cell empty?
 * Used by the parent component to determine whether to show the empty state
 */
const isEmpty = window.Vue.computed(() => {
    return activitiesListDragStore().orderedActivities.length === 0 || component.value === null;
});

// Allow the parent component to identify if the cell is empty
defineExpose({ isEmpty });
</script>

<template>
    <activities-list-content
        v-if="!isEmpty"
        :activities-list-store-id="activitiesListStoreId"
        :activities="props.unit.activities.items"
        :activities-list-routes="props.unit.activities._routes"
        :prevent-store-init="props.preventStoreInit"
    />
</template>

<style scoped></style>
