<script setup lang="ts">
/**
 * Show/hide all students toggle
 */

import { useStore } from "../store/horizontalMarkingStore";
import { useStudentsStore } from "../store/hmStudentsStore";

const store = useStore();
const studentsStore = useStudentsStore();

const buttonText = window.Vue.computed(() => {
    return studentsStore.isExpanded ? "Collapse all" : "Expand all";
});

/**
 * To move to previous item
 */
function onSelected(): void {
    studentsStore.toggleAllStudents();
}
</script>

<template>
    <button
        :disabled="!store.isLoaded"
        :class="`button f-no-margins ${studentsStore.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'}`"
        @click.prevent="onSelected"
    >
        {{ buttonText }}
    </button>
</template>

<style scoped></style>
