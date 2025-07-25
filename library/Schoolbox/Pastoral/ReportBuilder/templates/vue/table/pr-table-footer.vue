<script setup lang="ts">
/**
 * To load further rows when required
 */

import type { Metadata } from "@js/util/sbx/apiUtils";

const props = defineProps<{
    metadata: Metadata;
}>();
</script>

<template>
    <div class="pr-table-footer l-flex-row l-flex--align-center l-flex--justify-end l-gap-m l-pad-m">
        <p class="f-no-margins">
            <b>1-25 of {{ props.metadata.count }}</b>
        </p>
        <nav class="l-flex-row l-gap-s">
            <button type="button">&lt;</button>
            <button type="button">&gt;</button>
        </nav>
    </div>
</template>

<style scoped>
.pr-table-footer {
    background-color: white;
    left: 0;
    position: sticky;
}
</style>
