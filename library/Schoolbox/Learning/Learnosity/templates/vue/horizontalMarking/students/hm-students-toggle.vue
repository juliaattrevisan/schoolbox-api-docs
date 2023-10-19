<script setup lang="ts">
/**
 * Show/hide all students toggle
 */

import { useStudentsStore } from "../store/hmStudentsStore";

const studentsStore = useStudentsStore();

const buttonText = window.Vue.computed(() => {
    return studentsStore.isExpanded ? "Collapse all" : "Expand all";
});

/**
 * To move to previous item
 */
function onSelected() {
    studentsStore.toggleAllStudents();
}
</script>

<template>
    <button
        :class="`button ${studentsStore.isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'}`"
        @click.prevent="onSelected"
    >
        {{ buttonText }}
    </button>
</template>

<style scoped></style>
