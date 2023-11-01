<script setup lang="ts">
/**
 * Displayed when only showing unmarked students and all attempts have been marked
 */
</script>

<template>
    <div class="empty-state-flex-centered">
        <i class="icon-submissions"></i>
        <p>All students who have submitted an attempt have already been marked.</p>
    </div>
</template>

<style scoped>
/* gap and margin handled by parent instead */
.empty-state-flex-centered {
    gap: 0;
    margin: 0;
}
</style>
