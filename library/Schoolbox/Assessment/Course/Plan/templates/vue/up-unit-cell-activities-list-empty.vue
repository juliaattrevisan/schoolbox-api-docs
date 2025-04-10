<script setup lang="ts">
/**
 * The empty state for a textbox cell on the unit planner
 * Shows when:
 * - The textbox is not created yet
 * - The textbox is created, but has no content
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
  <!--
    3 options exist here;
    * component exist, but is labelled incorrectly, we can only have 1 on the page, so err
    * no component exist, allow creating component
    * component exist, but are empty
    -->
  <template v-if="extraActivitiesComponents">
    <i class="icon-due-work"></i>
    <p>Incorrectly named component</p>
    <p>Update it <a :href="props.unit.homepage">
      here
    </a> </p>
  </template>
  <activities-list-empty v-else />

</template>

<style scoped></style>
