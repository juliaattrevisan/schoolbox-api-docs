<script setup lang="ts">
/**
 * The staff directory table cell that displays the staff profile image
 */

import type { ColumnsData } from "./staffDirectory";

interface Props {
    data: ColumnsData; // the row of data
}
const props = defineProps<Props>();
</script>

<template>
    <template v-if="props.data.profile_url && props.data.portrait_url">
        <a :href="props.data.profile_url" title="View profile">
            <img :src="props.data.portrait_url" :alt="`Portrait photo of ${props.data.StaffPreferredName}`" />
        </a>
    </template>
    <template v-else-if="props.data.portrait_url">
        <img :src="props.data.portrait_url" :alt="`Portrait photo of ${props.data.StaffPreferredName}`" />
    </template>
</template>

<style lang="scss">
// .image-td is declared via columnsClasses in sd-table
.VueTables__table .image-td {
    max-width: 3rem; // prevent the image column from being too wide
}
</style>
