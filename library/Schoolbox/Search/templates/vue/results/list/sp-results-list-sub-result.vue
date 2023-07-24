<script setup lang="ts">
/**
 * To display a single linked sub results within a result
 */

import { Result } from "../../../js/types/Result";

interface Props {
    subResult: Result; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <div class="sub-result">
        <h3>{{ props.subResult.title }}</h3>
        <p class="meta">{{ props.subResult.description }}</p>
    </div>
</template>

<style scoped>
.sub-result {
    background: #f5f5f5;
    margin-left: 1rem;
    padding: 1rem;
}
</style>
