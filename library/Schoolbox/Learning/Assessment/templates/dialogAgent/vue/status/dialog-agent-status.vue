<script setup lang="ts">
import DasFilters from "./das-filters.vue";
</script>

<template>
    <div class="row">
        <!-- Metadata about the activity -->
        <div class="small-12 large-4 columns">
            <slot name="meta"></slot>
        </div>

        <!-- Status filters -->
        <div class="small-12 large-8 columns text-right">
            <das-filters />
        </div>
    </div>
</template>

<style scoped></style>
