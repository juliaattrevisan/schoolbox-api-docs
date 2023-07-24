<script setup lang="ts">
/**
 * Display a single search result
 */

import { Result } from "../../../js/types/Result";

interface Props {
    result: Result; // the result to displays
}
const props = defineProps<Props>();
</script>

<template>
    <div class="result">
        <h2>{{ props.result.title }}</h2>
        <p class="meta">{{ result.description }}</p>
        <template v-if="props.result._subResults">
            <div v-for="subResult in props.result._subResults.data" :key="subResult.id" class="sub-result">
                <h3>{{ subResult.title }}</h3>
                <p class="meta">{{ subResult.description }}</p>
            </div>
        </template>
    </div>
</template>

<style scoped>
.result {
    background: white;
    padding: 1rem;
}
</style>
