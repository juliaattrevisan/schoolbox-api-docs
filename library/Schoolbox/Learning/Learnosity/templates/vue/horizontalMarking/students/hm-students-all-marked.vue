<script setup lang="ts">
/**
 * Displayed when only showing unmarked students and all attempts have been marked
 */

import { useStudentsStore } from "../store/hmStudentsStore";

const studentsStore = useStudentsStore();

/**
 * To show/hide the entire student container, hiding if the student has been marked
 * and the option to show marked attempts is unchecked
 * @returns {string} - the height of the response container
 */
const containerHeight = window.Vue.computed(() => {
    return studentsStore.allStudentsMarked && !studentsStore.showMarkedAttempts ? "1fr" : "0fr";
});
</script>

<template>
    <div class="all-marked l-grid">
        <div class="all-marked__inner-container">
            <div class="empty-state-flex-centered">
                <i class="icon-submissions"></i>
                <p>All students who have submitted an attempt have already been marked.</p>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.all-marked {
    grid-template-rows: v-bind(containerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}

/* gap and margin handled by parent instead */
.empty-state-flex-centered {
    gap: 0;
    margin: 0;
}
</style>
