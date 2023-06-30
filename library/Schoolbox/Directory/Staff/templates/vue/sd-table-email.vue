<script setup lang="ts">
/**
 * The staff directory table cell that displays the staff email
 */

interface Props {
    data: object; // the row of data
}

const props = defineProps<Props>();
</script>

<template>
    <template v-if="props.data.StaffOccupEmail">
        <a :href="`mailto:${props.data.StaffOccupEmail}`" :title="`Email ${props.data.StaffPreferredName}`">
            {{ props.data.StaffOccupEmail }}
        </a>
    </template>
</template>
