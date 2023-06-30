<script setup lang="ts">
/**
 * The staff directory table row that displays the users profile image
 */

interface Props {
    data: object; // the row of data
}

const props = defineProps<Props>();
</script>

<template>
    <p>{{ props.data.portrait_url }}</p>
</template>

<style scoped></style>
