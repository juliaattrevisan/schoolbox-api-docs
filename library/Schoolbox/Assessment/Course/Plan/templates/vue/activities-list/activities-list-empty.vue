<script setup lang="ts">
/**
 * The empty state for an activities list when there are no activities present
 */
import locale from "@js/terminology/rosettaStone";
</script>

<template>
    <i class="icon-due-work"></i>
    <p>No {{ locale.activity.singular() }} has been added to this unit yet</p>
</template>

<style scoped></style>
