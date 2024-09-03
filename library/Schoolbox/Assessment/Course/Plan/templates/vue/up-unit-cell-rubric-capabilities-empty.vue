<script setup lang="ts">
/**
 * The empty state for a rubric capabilities cell on the unit planner when no capabilities are present
 */
import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
}

const props = defineProps<Props>();
</script>

<template>
    <i class="icon-criteria"></i>
    <p v-if="props.unit.rubricCapabilities._routes.manageUnit || props.unit.rubricCapabilities._routes.manageCourse">
        No rubric {{ locale.rubricGroup.plural() }} have been added to this course or
        {{ locale.unit.singular() }} yet,<br />
        start by adding rubric {{ locale.rubricGroup.plural() }} to the
        <a :href="getReturnUrl(`${props.unit.rubricCapabilities._routes.manageCourse}`)"> course </a>
        or to the
        <a :href="getReturnUrl(`${props.unit.rubricCapabilities._routes.manageUnit}`)">
            {{ locale.unit.singular() }}
        </a>
    </p>
    <p v-else>
        No rubric {{ locale.rubricGroup.plural() }} have been added to this course or {{ locale.unit.singular() }}.
    </p>
</template>

<style scoped></style>
