<script setup lang="ts">
/**
 * The staff directory table cell that displays the staff phone number
 */

import type { ColumnsData } from "./staffDirectory";

interface Props {
    data: ColumnsData; // the row of data
}
const props = defineProps<Props>();
</script>

<template>
    <template v-if="props.data.StaffOccupPhone">
        <a :href="`tel:${props.data.StaffOccupPhone}`" :title="`Phone ${props.data.StaffPreferredName}`">
            {{ props.data.StaffOccupPhone }}
        </a>
    </template>
</template>
