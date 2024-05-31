<script setup lang="ts">
/**
 * The empty state for an activities list when there are no activities present
 */
const activityLocale = window.sbProps.l10n.get("activity");
</script>

<template>
    <i class="icon-course"></i>
    <p>No {{ activityLocale.singular() }} have been added to this unit yet</p>
</template>

<style scoped></style>
