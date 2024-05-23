<script setup lang="ts">
import type { Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit;
    column: Column;
}

const props = defineProps<Props>();
</script>

<template>
    <i class="icon-criteria"></i>
    <p>
        No Rubric Capabilities have been added to this unit yet,
        <a :href="`/rubric/manager/${props.unit.folderId}`">start by adding rubric capabilities</a>
    </p>
</template>

<style scoped></style>
