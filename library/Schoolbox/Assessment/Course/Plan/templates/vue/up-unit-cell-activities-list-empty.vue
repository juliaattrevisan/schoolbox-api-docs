<script setup lang="ts">
/**
 * The empty state for a activities cell on the unit planner
 * Shows when:
 * - The course builder is not created yet
 * - The course builder is created, but has no content
 * - The course builder is created, but has the wrong name
 */
import type { Unit } from "./unitPlannerTypes";
import ActivitiesListEmpty from "./activities-list/activities-list-empty.vue";

interface Props {
    unit: Unit;
}
const props = defineProps<Props>();

const extraActivitiesComponents = props.unit.activities.components.filter((component) => !component.inTemplate).length; // Number of extra components in the template
</script>

<template>
    <template v-if="extraActivitiesComponents">
        <i class="icon-due-work"></i>
        <p>This component is incorrectly named.</p>
        <p>Update it <a :href="props.unit.homepage"> here </a></p>
    </template>
    <activities-list-empty v-else />
</template>

<style scoped></style>
