<script setup lang="ts">
/**
 * Display a basic cell value, with optional link support.
 */

import type { BasicType } from "@schoolboxVue/components/cell/cellTypes/BasicType";

const props = defineProps<{
    value: BasicType;
}>();
</script>

<template>
    <div class="u-flex-col u-pad-s u-gap-m">
        <a v-if="props.value._links?.view" :href="props.value._links.view">
            {{ props.value.title }}
        </a>
        <span v-else>
            {{ props.value.title }}
        </span>
    </div>
</template>

<style scoped></style>
