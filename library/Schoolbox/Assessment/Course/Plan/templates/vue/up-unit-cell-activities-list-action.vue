<script setup lang="ts">
/**
 * To display the actions available for the course builder / activities component,
 */

import ActivitiesListAction from "./activities-list/activities-list-action.vue";
import type { CourseBuilderComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";
import { useUnitPlannerActivityListStore } from "./store/unitPlannerActivityListStore";

interface Props {
    unit: Unit; // the unit linked to the activities cell
    column: Column; // the column linked to the activities cell
    activitiesListStoreId: string; // the id of the activities list store
}
const props = defineProps<Props>();

const unitPlannerActivityListStore = useUnitPlannerActivityListStore();
const component = window.Vue.computed<CourseBuilderComponent | null>(() => {
    return unitPlannerActivityListStore.getComponent(props.unit.activities.components, props.column);
});
const isCreating = window.Vue.ref(false);

/**
 * To display the appropriate text for the create button
 * @returns {string} The text to display on the create button
 */
const createText = window.Vue.computed(() => {
    return isCreating.value ? "Creating..." : "Create Component";
});

/**
 * Number of extra components in the unit
 *
 * you CAN NOT have multiple course builder components in the same unit, so we need to check if there are any other components in the unit
 * this may change in the future
 */
const extraActivitiesComponents = window.Vue.computed(() => {
    return props.unit.activities.components.filter((comp) => !comp.inTemplate).length;
});

/**
 * To create a new course builder component, adding it to the relevant units homepage
 */
async function onCreateSelected(): Promise<void> {
    if (isCreating.value) {
        return;
    }
    isCreating.value = true;
    await unitPlannerActivityListStore.createComponent(props.unit, props.column);
    await window.Vue.nextTick();
    isCreating.value = false;
}
</script>

<template>
    <template v-if="props.unit.activities._routes.create">
        <div v-if="component === null && extraActivitiesComponents === 0" class="component-action">
            <section>
                <button class="button" @click.prevent="onCreateSelected">{{ createText }}</button>
            </section>
        </div>
        <div v-else-if="extraActivitiesComponents > 0" class="component-action">
            <section></section>
        </div>
        <activities-list-action v-else :activities-list-store-id="`${props.activitiesListStoreId}`" />
    </template>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all; /* to allow the button to sit above the overlay */
}
</style>
