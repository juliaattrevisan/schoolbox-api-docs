<script setup lang="ts">
/**
 * Displayed when only showing unmarked students and all attempts have been marked
 */

import { useStudentsStore } from "../store/hmStudentsStore";

const studentsStore = useStudentsStore();
const containerHeight = window.Vue.ref("0fr");

/**
 * To show/hide the all marked container, showing if all the students have already been marked
 * and the option to show marked attempts is unchecked
 */
window.Vue.watch(
    () => studentsStore.showReviewedAttempts,
    () => {
        containerHeight.value =
            studentsStore.allStudentsReviewed && !studentsStore.showReviewedAttempts ? "1fr" : "0fr";
    },
);
</script>

<template>
    <div class="all-marked l-grid">
        <div class="all-marked__inner-container">
            <div class="empty-state-flex-centered">
                <i class="icon-quiz"></i>
                <p>All students who have submitted an attempt have already been reviewed.</p>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.all-marked {
    grid-template-rows: v-bind(containerHeight);
    transition: grid-template-rows 0.3s;
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
