<script setup lang="ts">
/**
 * A list of capabilities and indicators for a rubric
 */

import type { Rubric } from "../js/types/curriculumCoverageTypes";

interface Props {
    rubric: Rubric;
}
const props = defineProps<Props>();
</script>

<template>
    <div v-for="capability in props.rubric.capabilities" :key="capability.name">
        <p class="f-no-margins">
            <b>{{ capability.name }}</b>
        </p>
        <ul class="l-mar--0-bottom">
            <li v-for="indicator in capability.indicators" :key="indicator.name">
                {{ indicator.name }}
            </li>
        </ul>
    </div>
</template>

<style scoped></style>
